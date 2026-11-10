<?php

namespace App\Services\WordPress;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\Redirect;
use App\Services\Content\SlugGenerator;
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

    /** @var array{categories: int, posts_created: int, posts_updated: int, images: int, embeds_removed: int, slugs_changed: list<string>, skipped: list<string>} */
    public array $report = [
        'categories' => 0,
        'posts_created' => 0,
        'posts_updated' => 0,
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
     * Keep the WordPress slug (same URL, SEO). URL-encoded slugs and slugs
     * already used by a page get a clean slug plus a 301 from the old URL.
     */
    private function slugFor(WpPost $row, string $title, Post $post): string
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
