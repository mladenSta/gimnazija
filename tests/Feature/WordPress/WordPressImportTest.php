<?php

namespace Tests\Feature\WordPress;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Media;
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
}
