<?php

namespace App\Services\WordPress;

use App\Enums\ContentStatus;
use App\Enums\MenuItemType;
use App\Models\AlumniTestimonial;
use App\Models\Category;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Services\Content\MenuTreeSaver;
use App\Services\Content\SlugGenerator;
use App\Support\ReservedSlugs;
use App\Support\Slug;
use Closure;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Imports categories and posts (with their photos) from the old WordPress
 * database. Safe to run again: rows are matched by wp_id and updated.
 */
class WordPressImporter
{
    /** WordPress default category, not imported. */
    private const SKIP_CATEGORY_SLUGS = ['uncategorized', 'nekategorizovano', 'bez-kategorije'];

    /** @var array<int, int> WordPress term_id → categories.id */
    private array $categoryMap = [];

    /** @var array<int, int> WordPress term_id → number of posts (for picking the primary category) */
    private array $categoryCounts = [];

    /** @var array{categories: int, posts_created: int, posts_updated: int, pages_created: int, pages_updated: int, images: int, embeds_removed: int, slugs_changed: list<string>, skipped: list<string>} */
    public array $report = [
        'categories' => 0,
        'posts_created' => 0,
        'posts_updated' => 0,
        'pages_created' => 0,
        'pages_updated' => 0,
        'images' => 0,
        'embeds_removed' => 0,
        'slugs_changed' => [],
        'skipped' => [],
    ];

    public function __construct(
        private ConnectionInterface $wp,
        private ContentConverter $converter,
        private MediaImporter $media,
        private SlugGenerator $slugs,
    ) {}

    public function importCategories(): void
    {
        $terms = $this->wp->table('terms as t')
            ->join('term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', 'category')
            ->orderBy('tt.parent')
            ->get(['t.term_id', 't.name', 't.slug', 'tt.parent', 'tt.description', 'tt.count']);

        foreach ($terms as $term) {
            $this->categoryCounts[(int) $term->term_id] = (int) $term->count;
            $slug = rawurldecode((string) $term->slug);

            if (in_array($slug, self::SKIP_CATEGORY_SLUGS, true)) {
                continue;
            }

            // Match by wp_id, else by slug (a category created by hand before the import).
            $category = Category::query()->where('wp_id', $term->term_id)->first()
                ?? Category::query()->where('slug', $slug)->first()
                ?? new Category;

            $category->fill([
                'name' => html_entity_decode((string) $term->name, ENT_QUOTES | ENT_HTML5),
                'slug' => $category->exists ? $category->slug : $slug,
                'description' => filled($term->description) ? trim(strip_tags((string) $term->description)) : $category->description,
                'parent_id' => $term->parent ? ($this->categoryMap[(int) $term->parent] ?? null) : null,
                'wp_id' => (int) $term->term_id,
            ])->save();

            $this->categoryMap[(int) $term->term_id] = $category->id;
            $this->report['categories']++;
        }
    }

    /**
     * @param  Closure(int $done, int $total): void|null  $progress
     */
    public function importPosts(?int $limit = null, ?Closure $progress = null): void
    {
        $query = $this->wp->table('posts')
            ->where('post_type', 'post')
            ->whereIn('post_status', ['publish', 'future', 'draft', 'pending', 'private'])
            // Published posts first: they keep their real slugs; drafts (often
            // without a slug) get whatever is still free.
            ->orderByRaw("post_status = 'publish' DESC")
            ->orderBy('ID');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $rows = $query->get(['ID', 'post_title', 'post_name', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_modified']);
        $total = $rows->count();

        $terms = $this->wp->table('term_relationships as tr')
            ->join('term_taxonomy as tt', 'tt.term_taxonomy_id', '=', 'tr.term_taxonomy_id')
            ->where('tt.taxonomy', 'category')
            ->whereIn('tr.object_id', $rows->pluck('ID'))
            ->get(['tr.object_id', 'tt.term_id'])
            ->groupBy('object_id');

        $thumbnails = $this->wp->table('postmeta')
            ->where('meta_key', '_thumbnail_id')
            ->whereIn('post_id', $rows->pluck('ID'))
            ->pluck('meta_value', 'post_id');

        foreach ($rows as $i => $raw) {
            $row = WpPost::fromRow($raw);
            $termIds = array_values(($terms[$row->id] ?? collect())->pluck('term_id')->map(fn ($id) => (int) $id)->all());

            DB::transaction(fn () => $this->importPost($row, $termIds, (int) ($thumbnails[$row->id] ?? 0)));

            if ($progress) {
                $progress($i + 1, $total);
            }
        }

        $this->report['images'] = Media::query()->whereNotNull('wp_id')->count();
    }

    /**
     * @param  list<int>  $termIds
     */
    private function importPost(WpPost $row, array $termIds, int $thumbnailId): void
    {
        $post = Post::withTrashed()->where('wp_id', $row->id)->first() ?? new Post;
        $isNew = ! $post->exists;
        $title = trim(html_entity_decode($row->title, ENT_QUOTES | ENT_HTML5)) ?: 'Bez naslova';

        $slug = $this->slugFor($row, $title, $post);

        $converted = $this->converter->convert($row->content);
        $this->report['embeds_removed'] += $converted['removed_embeds'];

        $featured = $thumbnailId ? $this->media->media($thumbnailId) : null;

        $galleryIds = [];
        foreach ($converted['image_ids'] as $attachmentId) {
            if ($attachmentId === $thumbnailId) {
                continue; // already shown as the featured image
            }

            if ($media = $this->media->media($attachmentId)) {
                $galleryIds[] = $media->id;
            }
        }

        $status = in_array($row->status, ['publish', 'future'], true) ? ContentStatus::Published : ContentStatus::Draft;
        $published = Carbon::parse($row->date);
        $modified = Carbon::parse($row->modified);

        $excerpt = trim(html_entity_decode(strip_tags($row->excerpt), ENT_QUOTES | ENT_HTML5));

        $post->timestamps = false;
        $post->forceFill([
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt !== '' ? $excerpt : null,
            'content' => $converted['html'],
            'featured_media_id' => $featured?->id,
            'status' => $status,
            'published_at' => $status === ContentStatus::Published ? $published : null,
            'wp_id' => $row->id,
            'created_at' => $isNew ? $published : $post->created_at,
            'updated_at' => $modified,
        ])->save();

        if ($post->trashed()) {
            $post->restore();
        }

        $post->syncGalleryImages(array_values(array_unique($galleryIds)));
        $post->syncInlineImages([]);
        $post->syncCategories($this->orderedCategoryIds($termIds));

        $this->report[$isNew ? 'posts_created' : 'posts_updated']++;
    }

    /**
     * Published pages linked from the given WordPress menus, in menu order.
     * The front page and pages whose URL the new site already serves
     * (e.g. /vijesti/ is the news archive) are left out.
     *
     * @param  list<string>  $menuSlugs
     * @return list<int>
     */
    public function menuPageIds(array $menuSlugs): array
    {
        $ids = [];
        foreach ($menuSlugs as $menu) {
            foreach ($this->menuItems($menu) as $item) {
                if ($item['type'] === 'post_type' && $item['object'] === 'page') {
                    $ids[] = $item['object_id'];
                }
            }
        }

        $pages = $this->wp->table('posts')
            ->whereIn('ID', array_unique($ids))
            ->where('post_type', 'page')
            ->where('post_status', 'publish')
            ->pluck('post_name', 'ID');

        return array_values(array_unique(array_filter(
            $ids,
            fn (int $id) => isset($pages[$id]) && ! $this->isSkippedPage((string) $pages[$id]),
        )));
    }

    /**
     * @param  list<int>  $ids  WordPress page IDs
     * @return list<Page>
     */
    public function importPages(array $ids): array
    {
        $rows = $this->wp->table('posts')
            ->whereIn('ID', $ids ?: [0])
            ->where('post_type', 'page')
            ->get(['ID', 'post_title', 'post_name', 'post_content', 'post_excerpt', 'post_status', 'post_date', 'post_modified'])
            ->keyBy('ID');

        $thumbnails = $this->wp->table('postmeta')
            ->where('meta_key', '_thumbnail_id')
            ->whereIn('post_id', $ids ?: [0])
            ->pluck('meta_value', 'post_id');

        $pages = [];
        foreach ($ids as $id) {
            if (isset($rows[$id])) {
                $row = WpPost::fromRow($rows[$id]);
                $pages[] = DB::transaction(fn () => $this->importPage($row, (int) ($thumbnails[$id] ?? 0)));
            }
        }

        return $pages;
    }

    private function importPage(WpPost $row, int $thumbnailId): Page
    {
        // Matched by wp_id, else by slug (a page created by hand before the import).
        $page = Page::withTrashed()->where('wp_id', $row->id)->first()
            ?? Page::withTrashed()->where('slug', strtolower($row->name))->first()
            ?? new Page;
        $isNew = ! $page->exists;
        $title = trim(html_entity_decode($row->title, ENT_QUOTES | ENT_HTML5)) ?: 'Bez naslova';

        $converted = $this->converter->convert($row->content);
        $this->report['embeds_removed'] += $converted['removed_embeds'];

        $featured = $thumbnailId ? $this->media->media($thumbnailId) : null;

        $galleryIds = [];
        foreach ($converted['image_ids'] as $attachmentId) {
            if ($attachmentId !== $thumbnailId && ($media = $this->media->media($attachmentId))) {
                $galleryIds[] = $media->id;
            }
        }

        $published = Carbon::parse($row->date);

        $page->timestamps = false;
        $page->forceFill([
            'title' => $title,
            'slug' => $page->exists ? $page->slug : $this->slugFor($row, $title, $page),
            'content' => $converted['html'],
            'featured_media_id' => $featured?->id,
            'status' => ContentStatus::Published,
            'published_at' => $page->published_at ?? $published,
            'wp_id' => $row->id,
            'created_at' => $isNew ? $published : $page->created_at,
            'updated_at' => Carbon::parse($row->modified),
        ])->save();

        if ($page->trashed()) {
            $page->restore();
        }

        $page->syncGalleryImages(array_values(array_unique($galleryIds)));
        $page->syncInlineImages([]);

        $this->report[$isNew ? 'pages_created' : 'pages_updated']++;

        return $page;
    }

    /**
     * Alumni testimonials (theme post type "ac_testimonial"): name, title
     * ("designation"), text and photo. Newest first, like the old home page.
     * Matched by name, so running again updates instead of duplicating;
     * the order and visibility set in the admin are kept.
     *
     * @return list<AlumniTestimonial>
     */
    public function importTestimonials(): array
    {
        $rows = $this->wp->table('posts')
            ->where('post_type', 'ac_testimonial')
            ->where('post_status', 'publish')
            ->orderByDesc('post_date')
            ->get(['ID', 'post_title', 'post_content']);

        $meta = $this->wp->table('postmeta')
            ->whereIn('post_id', $rows->pluck('ID'))
            ->whereIn('meta_key', ['_thumbnail_id', 'ac_testimonial_designation'])
            ->get(['post_id', 'meta_key', 'meta_value'])
            ->groupBy('post_id');

        $text = fn (string $value) => trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5)));

        $imported = [];
        foreach ($rows->values() as $position => $row) {
            $m = ($meta[$row->ID] ?? collect())->pluck('meta_value', 'meta_key');
            $name = $text((string) $row->post_title);
            $quote = $text((string) $row->post_content);

            if ($name === '' || $quote === '') {
                continue;
            }

            $photo = (int) ($m['_thumbnail_id'] ?? 0) ? $this->media->media((int) $m['_thumbnail_id']) : null;
            $profession = $text((string) ($m['ac_testimonial_designation'] ?? ''));

            $testimonial = AlumniTestimonial::query()->where('name', $name)->first() ?? new AlumniTestimonial([
                'sort_order' => $position + 1,
                'is_published' => true,
            ]);

            $testimonial->fill([
                'name' => $name,
                'profession' => $profession !== '' ? mb_substr($profession, 0, 255) : null,
                'quote' => $quote,
                'photo_media_id' => $photo->id ?? $testimonial->photo_media_id,
            ])->save();

            $imported[] = $testimonial;
        }

        return $imported;
    }

    /**
     * Replace the new site's main menu with the given WordPress menu.
     * Pages and categories must be imported first; items pointing to
     * something that wasn't imported are left out.
     */
    public function importMenu(string $menuSlug): int
    {
        $items = $this->menuItems($menuSlug);
        $byParent = [];
        foreach ($items as $item) {
            $byParent[$item['parent']][] = $item;
        }

        $tree = $this->menuLevel($byParent, 0);
        app(MenuTreeSaver::class)->save($tree);

        return MenuItem::query()->count();
    }

    /**
     * @param  array<int, list<array{id: int, parent: int, label: string, title: string, type: string, object: string, object_id: int, url: string}>>  $byParent
     * @return list<array<string, mixed>>
     */
    private function menuLevel(array $byParent, int $parent): array
    {
        $tree = [];
        foreach ($byParent[$parent] ?? [] as $item) {
            $node = match (true) {
                $item['type'] === 'post_type' && $item['object'] === 'page' => $this->pageMenuNode($item['object_id']),
                $item['type'] === 'taxonomy' && $item['object'] === 'category' => ($id = Category::query()->where('wp_id', $item['object_id'])->value('id'))
                    ? ['type' => MenuItemType::Category->value, 'category_id' => $id]
                    : null,
                $item['type'] === 'custom' => ['type' => MenuItemType::Custom->value, 'url' => $this->customUrl($item['url'])],
                default => null,
            };

            if ($node === null) {
                continue;
            }

            // Own label only for headings and renamed items; otherwise the page/category title is used.
            $node['label'] = $item['type'] === 'custom' ? $item['label'] : ($item['title'] !== '' ? $item['title'] : null);
            $node['children'] = $this->menuLevel($byParent, $item['id']);
            $tree[] = $node;
        }

        return $tree;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pageMenuNode(int $wpId): ?array
    {
        $slug = (string) $this->wp->table('posts')->where('ID', $wpId)->value('post_name');

        if ($slug === 'vijesti') {
            return ['type' => MenuItemType::PostsIndex->value];
        }

        $id = Page::query()->where('wp_id', $wpId)->value('id');

        return $id ? ['type' => MenuItemType::Page->value, 'page_id' => $id] : null; // front page, or not imported
    }

    private function customUrl(string $url): ?string
    {
        $url = trim($this->converter->relativeLinks($url));

        return $url === '' || $url === '#' ? null : $url;
    }

    private function isSkippedPage(string $slug): bool
    {
        return in_array($slug, ['home', 'pocetna'], true) || ReservedSlugs::contains($slug);
    }

    /**
     * Items of a WordPress menu, in menu order.
     *
     * @return list<array{id: int, parent: int, label: string, title: string, type: string, object: string, object_id: int, url: string}>
     */
    private function menuItems(string $menuSlug): array
    {
        $taxonomyId = $this->wp->table('terms as t')
            ->join('term_taxonomy as tt', 'tt.term_id', '=', 't.term_id')
            ->where('tt.taxonomy', 'nav_menu')
            ->where('t.slug', $menuSlug)
            ->value('tt.term_taxonomy_id');

        if ($taxonomyId === null) {
            return [];
        }

        $rows = $this->wp->table('posts as p')
            ->join('term_relationships as tr', 'tr.object_id', '=', 'p.ID')
            ->where('tr.term_taxonomy_id', $taxonomyId)
            ->where('p.post_type', 'nav_menu_item')
            ->where('p.post_status', 'publish')
            ->orderBy('p.menu_order')
            ->get(['p.ID', 'p.post_title']);

        $meta = $this->wp->table('postmeta')
            ->whereIn('post_id', $rows->pluck('ID'))
            ->where('meta_key', 'like', '\_menu\_item\_%')
            ->get(['post_id', 'meta_key', 'meta_value'])
            ->groupBy('post_id');

        $items = [];
        foreach ($rows as $row) {
            $m = ($meta[$row->ID] ?? collect())->pluck('meta_value', 'meta_key');
            $type = (string) ($m['_menu_item_type'] ?? '');
            $object = (string) ($m['_menu_item_object'] ?? '');
            $objectId = (int) ($m['_menu_item_object_id'] ?? 0);
            $title = trim(html_entity_decode((string) $row->post_title, ENT_QUOTES | ENT_HTML5));
            $label = $title;

            if ($label === '' && $type === 'post_type') {
                $label = trim(html_entity_decode((string) $this->wp->table('posts')->where('ID', $objectId)->value('post_title'), ENT_QUOTES | ENT_HTML5));
            } elseif ($label === '' && $type === 'taxonomy') {
                $label = (string) $this->wp->table('terms')->where('term_id', $objectId)->value('name');
            }

            $items[] = [
                'id' => (int) $row->ID,
                'parent' => (int) ($m['_menu_item_menu_item_parent'] ?? 0),
                'label' => $label,
                'title' => $title,
                'type' => $type,
                'object' => $object,
                'object_id' => $objectId,
                'url' => (string) ($m['_menu_item_url'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * Keep the WordPress slug (same URL, SEO). URL-encoded slugs and slugs
     * already used by a page get a clean slug plus a 301 from the old URL.
     */
    private function slugFor(WpPost $row, string $title, Post|Page $post): string
    {
        $old = strtolower($row->name);

        if ($post->exists && $post->slug !== '' && ! str_contains($post->slug, '%')) {
            return $post->slug; // re-import: keep whatever slug the post has now
        }

        $clean = $old !== '' && ! str_contains($old, '%') && ! $this->slugs->isTaken($old, $post);

        if ($clean) {
            return $old;
        }

        $base = $old !== '' ? Slug::make(rawurldecode($old)) : Slug::make($title);
        $new = $this->slugs->unique($base !== '' ? $base : $title, $post);

        if ($old !== '' && $row->status === 'publish') {
            // The request path arrives decoded (/dan-broja-π/), so store it decoded too.
            Redirect::query()->updateOrCreate(
                ['from_path' => '/'.rawurldecode($old).'/'],
                ['to_path' => "/{$new}/", 'status_code' => 301],
            );
            $this->report['slugs_changed'][] = rawurldecode($old)." → {$new}";
        }

        return $new;
    }

    /**
     * Most specific category first (fewest posts), so the breadcrumb shows
     * e.g. "Takmičenja" rather than the general "Vijesti".
     *
     * @param  list<int>  $termIds
     * @return list<int>
     */
    private function orderedCategoryIds(array $termIds): array
    {
        $known = array_values(array_filter($termIds, fn (int $id) => isset($this->categoryMap[$id])));
        usort($known, fn (int $a, int $b) => ($this->categoryCounts[$a] ?? 0) <=> ($this->categoryCounts[$b] ?? 0));

        return array_values(array_unique(array_map(fn (int $id) => $this->categoryMap[$id], $known)));
    }
}
