<?php

namespace Tests\Feature\WordPress;

use App\Enums\ContentStatus;
use App\Enums\MenuItemType;
use App\Models\AlumniTestimonial;
use App\Models\Category;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Runs the real importer against a tiny WordPress database
 * (same tables, "wptest_" prefix, in the test database).
 */
class WordPressImportTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = ['posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.wordpress' => [...config('database.connections.mysql'), 'prefix' => 'wptest_'],
            'cms.wordpress.url' => 'https://gimnazija.net',
        ]);
        DB::purge('wordpress');

        $schema = Schema::connection('wordpress');
        foreach (self::TABLES as $table) {
            $schema->dropIfExists($table);
        }
        $schema->create('posts', function (Blueprint $t) {
            $t->unsignedBigInteger('ID')->primary();
            $t->string('post_title')->default('');
            $t->string('post_name')->default('');
            $t->longText('post_content')->nullable();
            $t->text('post_excerpt')->nullable();
            $t->string('post_status')->default('publish');
            $t->string('post_type')->default('post');
            $t->string('post_mime_type')->default('');
            $t->integer('menu_order')->default(0);
            $t->dateTime('post_date')->nullable();
            $t->dateTime('post_modified')->nullable();
        });
        $schema->create('postmeta', function (Blueprint $t) {
            $t->id('meta_id');
            $t->unsignedBigInteger('post_id');
            $t->string('meta_key');
            $t->longText('meta_value')->nullable();
        });
        $schema->create('terms', function (Blueprint $t) {
            $t->unsignedBigInteger('term_id')->primary();
            $t->string('name');
            $t->string('slug');
        });
        $schema->create('term_taxonomy', function (Blueprint $t) {
            $t->unsignedBigInteger('term_taxonomy_id')->primary();
            $t->unsignedBigInteger('term_id');
            $t->string('taxonomy');
            $t->text('description')->nullable();
            $t->unsignedBigInteger('parent')->default(0);
            $t->unsignedBigInteger('count')->default(0);
        });
        $schema->create('term_relationships', function (Blueprint $t) {
            $t->unsignedBigInteger('object_id');
            $t->unsignedBigInteger('term_taxonomy_id');
        });

        Storage::fake('uploads');
    }

    protected function tearDown(): void
    {
        foreach (self::TABLES as $table) {
            Schema::connection('wordpress')->dropIfExists($table);
        }

        parent::tearDown();
    }

    // ---- helpers --------------------------------------------------------------

    private function category(int $id, string $name, string $slug, int $count): void
    {
        DB::connection('wordpress')->table('terms')->insert(['term_id' => $id, 'name' => $name, 'slug' => $slug]);
        DB::connection('wordpress')->table('term_taxonomy')->insert(['term_taxonomy_id' => $id, 'term_id' => $id, 'taxonomy' => 'category', 'count' => $count]);
    }

    private function image(int $id, string $file, bool $onDisk = true): void
    {
        $dir = dirname($file);
        $name = pathinfo($file, PATHINFO_FILENAME);
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        DB::connection('wordpress')->table('posts')->insert([
            'ID' => $id, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg',
            'post_title' => $name, 'post_excerpt' => '',
        ]);
        DB::connection('wordpress')->table('postmeta')->insert([
            ['post_id' => $id, 'meta_key' => '_wp_attached_file', 'meta_value' => $file],
            ['post_id' => $id, 'meta_key' => '_wp_attachment_image_alt', 'meta_value' => "Alt {$id}"],
            ['post_id' => $id, 'meta_key' => '_wp_attachment_metadata', 'meta_value' => serialize([
                'width' => 2000, 'height' => 1500, 'file' => $file,
                'sizes' => [
                    'thumbnail' => ['file' => "{$name}-150x150.{$ext}", 'width' => 150, 'height' => 150],
                    'medium' => ['file' => "{$name}-300x225.{$ext}", 'width' => 300, 'height' => 225],
                    'medium_large' => ['file' => "{$name}-768x576.{$ext}", 'width' => 768, 'height' => 576],
                    'large' => ['file' => "{$name}-1024x768.{$ext}", 'width' => 1024, 'height' => 768],
                    '1536x1536' => ['file' => "{$name}-1536x1152.{$ext}", 'width' => 1536, 'height' => 1152],
                ],
            ])],
        ]);

        if ($onDisk) {
            foreach (['', '-150x150', '-300x225', '-768x576', '-1024x768', '-1536x1152'] as $suffix) {
                Storage::disk('uploads')->put("{$dir}/{$name}{$suffix}.{$ext}", 'x');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $categories
     */
    private function wpPost(int $id, array $attributes, array $categories = [], ?int $thumbnail = null): void
    {
        DB::connection('wordpress')->table('posts')->insert([
            'ID' => $id, 'post_type' => 'post', 'post_status' => 'publish',
            'post_date' => '2025-10-01 09:30:00', 'post_modified' => '2025-10-02 10:00:00',
            'post_title' => "Objava {$id}", 'post_name' => "objava-{$id}", 'post_content' => '<!-- wp:paragraph --><p>Tekst</p><!-- /wp:paragraph -->',
            'post_excerpt' => '',
            ...$attributes,
        ]);

        foreach ($categories as $termId) {
            DB::connection('wordpress')->table('term_relationships')->insert(['object_id' => $id, 'term_taxonomy_id' => $termId]);
        }

        if ($thumbnail) {
            DB::connection('wordpress')->table('postmeta')->insert(['post_id' => $id, 'meta_key' => '_thumbnail_id', 'meta_value' => (string) $thumbnail]);
        }
    }

    private function block(int $imageId, string $file): string
    {
        $src = 'https://gimnazija.net/wp-content/uploads/'.preg_replace('/\.jpg$/', '-1024x768.jpg', $file);

        return "<!-- wp:image {\"id\":{$imageId},\"sizeSlug\":\"large\"} --><figure class=\"wp-block-image size-large\"><img src=\"{$src}\" alt=\"\" class=\"wp-image-{$imageId}\"/></figure><!-- /wp:image -->";
    }

    // ---- tests ----------------------------------------------------------------

    public function test_full_post_import_with_content_images_categories_and_dates(): void
    {
        $this->category(163, 'Vijesti', 'vijesti', 750);
        $this->category(164, 'Takmičenja', 'takmicenja', 180);
        $this->category(1, 'Uncategorized', 'uncategorized', 4);

        $this->image(501, '2025/10/naslovna.jpg');
        $this->image(502, '2025/10/foto-1.jpg');
        $this->image(503, '2025/10/foto-2.jpg');

        $content = '<!-- wp:paragraph --><p>Naši učenici su osvojili <a href="https://gimnazija.net/takmicenja-2024/">nagrade</a>.</p><!-- /wp:paragraph -->'
            .'<!-- wp:embed {"url":"https://youtu.be/x"} --><figure class="wp-block-embed"><div>https://youtu.be/x</div></figure><!-- /wp:embed -->'
            .$this->block(503, '2025/10/foto-2.jpg')
            .$this->block(501, '2025/10/naslovna.jpg')
            .$this->block(502, '2025/10/foto-1.jpg')
            .'<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->'
            .'<!-- wp:file {"id":9} --><div class="wp-block-file"><a href="https://gimnazija.net/wp-content/uploads/2025/10/raspored.pdf">Raspored</a><a href="https://gimnazija.net/wp-content/uploads/2025/10/raspored.pdf" class="wp-block-file__button" download>Preuzmi</a></div><!-- /wp:file -->';

        $this->wpPost(10, ['post_title' => 'Takmičenje &#8211; rezultati', 'post_name' => 'takmicenje-rezultati', 'post_content' => $content], [163, 164, 1], 501);

        $this->artisan('wp:import')->assertSuccessful();

        $post = Post::query()->with(['categories', 'featuredImage', 'galleryImages'])->sole();

        $this->assertSame('Takmičenje – rezultati', $post->title);
        $this->assertSame('takmicenje-rezultati', $post->slug);
        $this->assertSame(ContentStatus::Published, $post->status);
        $this->assertSame('2025-10-01 09:30', $post->published_at?->format('Y-m-d H:i'));
        $this->assertSame(10, $post->wp_id);

        // Text kept, links relative, video and images removed from the text, file link kept once.
        $this->assertSame(
            '<p>Naši učenici su osvojili <a href="/takmicenja-2024/">nagrade</a>.</p><p><a href="/wp-content/uploads/2025/10/raspored.pdf">Raspored</a></p>',
            $post->content,
        );

        // Most specific category first; "Uncategorized" skipped.
        $this->assertSame(['Takmičenja', 'Vijesti'], $post->categories->pluck('name')->all());

        // Featured image, and the other photos (in order) as the gallery — featured not repeated.
        $this->assertSame('2025/10/naslovna.jpg', $post->featuredImage?->path);
        $this->assertSame(['2025/10/foto-2.jpg', '2025/10/foto-1.jpg'], $post->galleryImages->pluck('path')->all());

        // WordPress resized copies become variants (proportional sizes only).
        $media = $post->featuredImage;
        $this->assertSame('2025/10/naslovna-300x225.jpg', $media?->variants['thumb']['path'] ?? null);
        $this->assertSame('2025/10/naslovna-768x576.jpg', $media?->variants['medium']['path'] ?? null);
        $this->assertSame('2025/10/naslovna-1536x1152.jpg', $media?->variants['large']['path'] ?? null);
        $this->assertStringNotContainsString('150x150', json_encode($media?->variants) ?: '');
        $this->assertSame('Alt 501', $media?->alt);
        $this->assertSame(2000, $media?->width);
    }

    public function test_published_posts_keep_their_slug_before_drafts_without_one(): void
    {
        $this->wpPost(5, ['post_status' => 'draft', 'post_name' => '', 'post_title' => 'Školsko takmičenje']);
        $this->wpPost(6, ['post_status' => 'publish', 'post_name' => 'skolsko-takmicenje', 'post_title' => 'Školsko takmičenje']);

        $this->artisan('wp:import')->assertSuccessful();

        $this->assertSame('skolsko-takmicenje', Post::query()->where('wp_id', 6)->value('slug'));
        $this->assertSame('skolsko-takmicenje-2', Post::query()->where('wp_id', 5)->value('slug'));
        $this->assertSame(ContentStatus::Draft, Post::query()->where('wp_id', 5)->first()?->status);
        $this->assertSame(0, Redirect::query()->count());
    }

    public function test_url_encoded_slug_gets_clean_slug_and_redirect_that_works(): void
    {
        $this->wpPost(7, ['post_name' => 'dan-broja-%cf%80', 'post_title' => 'Dan broja π']);

        $this->artisan('wp:import')->assertSuccessful();

        $this->assertSame('dan-broja-p', Post::query()->value('slug'));
        $this->get('/dan-broja-%cf%80/')->assertStatus(301)->assertRedirect('http://localhost/dan-broja-p/');
        $this->get('/dan-broja-p/')->assertOk();
    }

    public function test_existing_category_with_same_slug_is_reused(): void
    {
        $mine = Category::factory()->create(['name' => 'Računarski smjer', 'slug' => 'racunarski-smjer']);
        $this->category(208, 'Računarski smjer', 'racunarski-smjer', 32);

        $this->artisan('wp:import')->assertSuccessful();

        $this->assertSame(1, Category::query()->count());
        $this->assertSame(208, $mine->fresh()?->wp_id);
    }

    public function test_missing_image_files_are_skipped_and_reported(): void
    {
        $this->image(601, '2024/01/postoji.jpg');
        $this->image(602, '2024/01/nema.jpg', onDisk: false);
        $this->wpPost(8, ['post_content' => $this->block(601, '2024/01/postoji.jpg').$this->block(602, '2024/01/nema.jpg')], [], 602);

        $this->artisan('wp:import')
            ->expectsOutputToContain('2024/01/nema.jpg')
            ->assertSuccessful();

        $post = Post::query()->with('galleryImages')->sole();
        $this->assertNull($post->featured_media_id);
        $this->assertSame(['2024/01/postoji.jpg'], $post->galleryImages->pluck('path')->all());
        $this->assertSame(1, Media::query()->count());
    }

    public function test_running_twice_updates_instead_of_duplicating(): void
    {
        $this->category(163, 'Vijesti', 'vijesti', 1);
        $this->image(701, '2025/05/a.jpg');
        $this->wpPost(9, ['post_content' => $this->block(701, '2025/05/a.jpg')], [163], 701);

        $this->artisan('wp:import')->assertSuccessful();
        DB::connection('wordpress')->table('posts')->where('ID', 9)->update(['post_title' => 'Ispravljen naslov']);
        $this->artisan('wp:import')->assertSuccessful();

        $this->assertSame(1, Post::query()->count());
        $this->assertSame('Ispravljen naslov', Post::query()->value('title'));
        $this->assertSame(1, Media::query()->count());
        $this->assertSame(1, Category::query()->count());
    }

    public function test_imported_media_in_use_is_protected(): void
    {
        $this->image(801, '2025/06/b.jpg');
        $this->wpPost(11, [], [], 801);

        $this->artisan('wp:import')->assertSuccessful();

        $this->assertTrue(Media::query()->sole()->isInUse());
    }

    /**
     * @param  array<string, string>  $meta
     */
    private function menuItem(int $id, int $menuTaxonomyId, int $order, string $title, array $meta): void
    {
        DB::connection('wordpress')->table('posts')->insert([
            'ID' => $id, 'post_type' => 'nav_menu_item', 'post_status' => 'publish', 'post_title' => $title,
            'post_name' => (string) $id, 'post_excerpt' => '', 'menu_order' => $order,
        ]);
        DB::connection('wordpress')->table('term_relationships')->insert(['object_id' => $id, 'term_taxonomy_id' => $menuTaxonomyId]);
        foreach ($meta + ['menu_item_parent' => '0'] as $key => $value) {
            DB::connection('wordpress')->table('postmeta')->insert(['post_id' => $id, 'meta_key' => "_menu_item_{$key}", 'meta_value' => $value]);
        }
    }

    public function test_pages_from_the_menu_are_imported_and_the_menu_rebuilt(): void
    {
        $this->category(208, 'Računarski smjer', 'racunarski-smjer', 3);
        $this->image(901, '2020/05/biblioteka.jpg');

        $page = fn (int $id, string $slug, string $title, string $content) => DB::connection('wordpress')->table('posts')->insert([
            'ID' => $id, 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug,
            'post_content' => $content, 'post_excerpt' => '', 'post_date' => '2020-05-01 10:00:00', 'post_modified' => '2020-05-02 10:00:00',
        ]);
        $page(100, 'home', 'Home', '');
        $page(101, 'vijesti', 'Vijesti', '');
        $page(102, 'biblioteka', 'Biblioteka', "[vc_row][vc_column width=\"1/2\"][vc_column_text]DJELATNOST:\n* kulturna\n* obrazovna\n\nNaša škola je obrazovno-<br />vaspitna\nustanova.<!-- /wp:paragraph -->[/vc_column_text][/vc_column][vc_column width=\"1/2\"][vc_single_image image=\"901\" img_size=\"full\"][/vc_column][/vc_row]");
        $page(103, 'dramska-sekcija', 'Dramska sekcija', '[vc_row][vc_column][vc_video link="https://www.youtube.com/watch?v=x"][/vc_column][/vc_row]');
        $page(104, 'kontakt', 'Kontakt', '[vc_row][vc_column][eikra-vc-contact address="Hilandarska 2, 74000 Doboj" email="ss38@skolers.org" phone="053 200 970"][/vc_column][vc_column][contact-form-7 id="7"][/vc_column][/vc_row]');

        DB::connection('wordpress')->table('terms')->insert(['term_id' => 50, 'name' => 'Main Menu', 'slug' => 'main-menu']);
        DB::connection('wordpress')->table('term_taxonomy')->insert(['term_taxonomy_id' => 50, 'term_id' => 50, 'taxonomy' => 'nav_menu']);
        $this->menuItem(1, 50, 1, 'Home', ['type' => 'post_type', 'object' => 'page', 'object_id' => '100']);
        $this->menuItem(2, 50, 2, 'O nama', ['type' => 'custom', 'object' => 'custom', 'object_id' => '2', 'url' => '#']);
        $this->menuItem(3, 50, 3, '', ['type' => 'post_type', 'object' => 'page', 'object_id' => '102', 'menu_item_parent' => '2']);
        $this->menuItem(4, 50, 4, '', ['type' => 'taxonomy', 'object' => 'category', 'object_id' => '208', 'menu_item_parent' => '2']);
        $this->menuItem(5, 50, 5, '', ['type' => 'post_type', 'object' => 'page', 'object_id' => '101']);
        $this->menuItem(6, 50, 6, '', ['type' => 'post_type', 'object' => 'page', 'object_id' => '103']);
        $this->menuItem(7, 50, 7, 'Kontakt', ['type' => 'post_type', 'object' => 'page', 'object_id' => '104']);

        // A test page created by hand with the same link is taken over, not duplicated.
        Page::factory()->create(['slug' => 'biblioteka', 'title' => 'Proba']);
        MenuItem::query()->create(['type' => MenuItemType::Custom, 'url' => '/stari/', 'label' => 'Stari']);

        $this->artisan('wp:import-pages')->assertSuccessful();

        $this->assertSame(['biblioteka', 'dramska-sekcija', 'kontakt'], Page::query()->orderBy('slug')->pluck('slug')->all());

        $biblioteka = Page::query()->with('galleryImages')->where('slug', 'biblioteka')->sole();
        $this->assertSame('Biblioteka', $biblioteka->title);
        $this->assertSame(102, $biblioteka->wp_id);
        $this->assertSame(ContentStatus::Published, $biblioteka->status);
        $this->assertSame(
            '<p>DJELATNOST:</p><ul><li>kulturna</li><li>obrazovna</li></ul><p>Naša škola je obrazovno-vaspitna</p><p>ustanova.</p>',
            preg_replace('/>\s+</', '><', (string) $biblioteka->content),
        );
        $this->assertSame(['2020/05/biblioteka.jpg'], $biblioteka->galleryImages->pluck('path')->all());

        $this->assertNull(Page::query()->where('slug', 'dramska-sekcija')->value('content'));
        $kontakt = (string) Page::query()->where('slug', 'kontakt')->value('content');
        $this->assertStringContainsString('Hilandarska 2, 74000 Doboj<br />Telefon: <a href="tel:053200970">053 200 970</a>', $kontakt);
        $this->assertStringNotContainsString('contact-form', $kontakt);

        // Menu: same structure; "Home" left out (the site has its own), /vijesti/ → news archive.
        $menu = MenuItem::query()->roots()->with(['page', 'category', 'children.page', 'children.category'])->get();
        $this->assertSame(['O nama', 'Vijesti', 'Dramska sekcija', 'Kontakt'], $menu->map->display_label->all());
        $this->assertNull($menu[0]->href);
        $this->assertSame(['/biblioteka/', '/category/racunarski-smjer/'], $menu[0]->children->map->href->all());
        $this->assertSame(MenuItemType::PostsIndex, $menu[1]->type);
        $this->assertSame(['O nama', 'Kontakt'], MenuItem::query()->whereNotNull('label')->orderBy('id')->pluck('label')->all());

        $this->get('/biblioteka/')->assertOk()->assertSee('DJELATNOST');

        // Running again updates in place.
        $this->artisan('wp:import-pages')->assertSuccessful();
        $this->assertSame(3, Page::query()->count());
        $this->assertSame(1, Media::query()->count());
    }

    public function test_testimonials_are_imported_newest_first_with_title_and_photo(): void
    {
        $this->image(950, '2020/05/Vladimir.jpg');
        $insert = function (int $id, string $name, string $date, string $text, string $designation, ?int $photo = null) {
            DB::connection('wordpress')->table('posts')->insert([
                'ID' => $id, 'post_type' => 'ac_testimonial', 'post_status' => 'publish', 'post_title' => $name,
                'post_name' => (string) $id, 'post_content' => $text, 'post_excerpt' => '', 'post_date' => $date,
            ]);
            DB::connection('wordpress')->table('postmeta')->insert(['post_id' => $id, 'meta_key' => 'ac_testimonial_designation', 'meta_value' => $designation]);
            if ($photo) {
                DB::connection('wordpress')->table('postmeta')->insert(['post_id' => $id, 'meta_key' => '_thumbnail_id', 'meta_value' => (string) $photo]);
            }
        };
        $insert(20, 'Nevena Simić', '2020-04-25 10:00:00', "Nijedna druga škola…\n\n<strong>Postanite</strong> ono što želite!", 'Master pravnih nauka');
        $insert(21, 'Vladimir Jovanović', '2020-05-07 13:55:00', 'Gimnazija nije tu da ti ostvari snove &#8211; već da ti pomogne.', 'Senior Android programer, FlixBus', 950);
        DB::connection('wordpress')->table('posts')->insert(['ID' => 22, 'post_type' => 'ac_testimonial', 'post_status' => 'draft', 'post_title' => 'Nacrt', 'post_name' => '22', 'post_content' => 'x', 'post_excerpt' => '']);

        $this->artisan('wp:import-testimonials')->assertSuccessful();

        $all = AlumniTestimonial::query()->published()->with('photo')->get();
        $this->assertSame(['Vladimir Jovanović', 'Nevena Simić'], $all->pluck('name')->all());
        $this->assertSame('Gimnazija nije tu da ti ostvari snove – već da ti pomogne.', $all[0]->quote);
        $this->assertSame('Senior Android programer, FlixBus', $all[0]->profession);
        $this->assertSame('2020/05/Vladimir.jpg', $all[0]->photo?->path);
        $this->assertSame('Nijedna druga škola… Postanite ono što želite!', $all[1]->quote);
        $this->assertNull($all[1]->photo_media_id);

        // Order changed in the admin is kept on a second run; nothing duplicated.
        $all[1]->update(['sort_order' => 0]);
        $this->artisan('wp:import-testimonials')->assertSuccessful();
        $this->assertSame(['Nevena Simić', 'Vladimir Jovanović'], AlumniTestimonial::query()->published()->pluck('name')->all());

        $this->get('/')->assertOk()->assertSee('Senior Android programer, FlixBus');
    }
}
