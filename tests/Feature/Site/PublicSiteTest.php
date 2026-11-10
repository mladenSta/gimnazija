<?php

namespace Tests\Feature\Site;

use App\Models\AlumniTestimonial;
use App\Models\Category;
use App\Models\Event;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Tag;
use App\Models\User;
use App\Support\SiteMenu;
use Database\Seeders\GallerySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GallerySeeder::class);
        SiteMenu::flush();
    }

    // ---- URLs ---------------------------------------------------------------

    public function test_missing_trailing_slash_redirects_with_301(): void
    {
        Page::factory()->create(['slug' => 'kontakt']);

        $this->get('/kontakt')->assertStatus(301)->assertRedirect('http://localhost/kontakt/');
        $this->get('/kontakt?x=1')->assertRedirect('http://localhost/kontakt/?x=1');
        $this->get('/kontakt/')->assertOk();
    }

    public function test_reserved_paths_are_not_treated_as_content(): void
    {
        Page::factory()->create(['slug' => 'test-strana']);

        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/login')->assertOk();
    }

    public function test_unknown_url_uses_stored_redirect_or_404(): void
    {
        Redirect::query()->create(['from_path' => '/stara-vijest/', 'to_path' => '/nova-vijest/']);

        $this->get('/stara-vijest/')->assertStatus(301)->assertRedirect('http://localhost/nova-vijest/');
        $this->get('/ne-postoji/')->assertNotFound()->assertSee('Stranica nije pronađena');
        $this->get('/a/b/c/')->assertNotFound();
    }

    public function test_renamed_post_old_url_redirects_to_new_one(): void
    {
        $post = Post::factory()->create(['slug' => 'stari-naslov']);
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.posts.update', $post), [
            'title' => $post->title, 'slug' => 'novi-naslov', 'status' => 'published',
            'published_at' => $post->published_at?->format('Y-m-d\TH:i'), 'category_ids' => [], 'gallery_ids' => [],
        ]);
        auth()->logout();

        $this->get('/stari-naslov/')->assertStatus(301)->assertRedirect('http://localhost/novi-naslov/');
        $this->get('/novi-naslov/')->assertOk();
    }

    // ---- Posts and pages ----------------------------------------------------

    public function test_published_post_page_with_seo_gallery_and_categories(): void
    {
        $category = Category::factory()->create(['name' => 'Takmičenja', 'slug' => 'takmicenja']);
        $featured = Media::factory()->create(['alt' => 'Ekipa na takmičenju']);
        $post = Post::factory()->create([
            'title' => 'Dan otvorenih vrata',
            'slug' => 'dan-otvorenih-vrata-2',
            'content' => '<p>Pozivamo buduće gimnazijalce.</p>',
            'featured_media_id' => $featured->id,
            'published_at' => '2026-11-06 10:00:00',
            'meta_description' => 'Opis za pretraživače',
        ]);
        $post->syncCategories([$category->id]);
        $post->syncGalleryImages(Media::factory()->count(2)->create()->pluck('id')->all());

        $this->get('/dan-otvorenih-vrata-2/')
            ->assertOk()
            ->assertSee('<h1>Dan otvorenih vrata</h1>', false)
            ->assertSee('06.11.2026.')
            ->assertSee('<p>Pozivamo buduće gimnazijalce.</p>', false)
            ->assertSee('href="/category/takmicenja/"', false)
            ->assertSee('<link rel="canonical" href="http://localhost/dan-otvorenih-vrata-2/">', false)
            ->assertSee('<meta name="description" content="Opis za pretraživače">', false)
            ->assertSee('"@type":"NewsArticle"', false)
            ->assertSee('data-lightbox="content"', false)
            ->assertDontSee('noindex');
    }

    public function test_drafts_and_scheduled_are_hidden_from_guests_but_previewable_by_staff(): void
    {
        Post::factory()->draft()->create(['slug' => 'nacrt']);
        Page::factory()->scheduled()->create(['slug' => 'uskoro']);

        $this->get('/nacrt/')->assertNotFound();
        $this->get('/uskoro/')->assertNotFound();

        $this->actingAs(User::factory()->author()->create());
        $this->get('/nacrt/')->assertOk()->assertSee('Pregled')->assertSee('noindex');
        $this->get('/uskoro/')->assertOk()->assertSee('Pregled');
    }

    public function test_page_shows_title_content_and_gallery(): void
    {
        $page = Page::factory()->create(['title' => 'Istorijat škole', 'slug' => 'istorijat-skole', 'content' => '<h2>Osnivanje</h2>']);
        $page->syncGalleryImages([Media::factory()->create()->id]);

        $this->get('/istorijat-skole/')
            ->assertOk()
            ->assertSee('<h1>Istorijat škole</h1>', false)
            ->assertSee('<h2>Osnivanje</h2>', false)
            ->assertSee('Galerija');
    }

    // ---- Archives & search ----------------------------------------------------

    public function test_news_archive_wordpress_paging(): void
    {
        Post::factory()->count(13)->create();
        Post::factory()->draft()->create(['title' => 'Skriveni nacrt']);

        $this->get('/vijesti/')->assertOk()->assertSee('href="/vijesti/page/2/"', false)->assertDontSee('Skriveni nacrt');
        $this->get('/vijesti/page/2/')->assertOk()->assertSee('<link rel="canonical" href="http://localhost/vijesti/page/2/">', false);
        $this->get('/vijesti/page/1/')->assertStatus(301)->assertRedirect('http://localhost/vijesti/');
        $this->get('/vijesti/page/3/')->assertNotFound();
    }

    public function test_category_archive_lists_only_its_posts(): void
    {
        $category = Category::factory()->create(['name' => 'Računarski smjer', 'slug' => 'racunarski-smjer']);
        Post::factory()->create(['title' => 'Hakaton'])->syncCategories([$category->id]);
        Post::factory()->create(['title' => 'Druga vijest']);

        $this->get('/category/racunarski-smjer/')
            ->assertOk()
            ->assertSee('Računarski smjer')
            ->assertSee('Hakaton')
            ->assertDontSee('Druga vijest');

        $this->get('/category/nema-je/')->assertNotFound();
    }

    public function test_search_finds_posts_and_pages_and_is_noindex(): void
    {
        Page::factory()->create(['title' => 'Biblioteka', 'content' => '<p>Fond od 10.000 knjiga</p>']);
        Post::factory()->create(['title' => 'Nove knjige u biblioteci']);
        Post::factory()->draft()->create(['title' => 'Biblioteka nacrt']);

        $this->get('/?s=bibliote')
            ->assertOk()
            ->assertSee('Pronađeno: 2')
            ->assertSee('Biblioteka')
            ->assertSee('Nove knjige u biblioteci')
            ->assertDontSee('Biblioteka nacrt')
            ->assertSee('noindex');

        $this->get('/?s=xyzxyz')->assertOk()->assertSee('Nema rezultata');
    }

    // ---- Home ---------------------------------------------------------------

    public function test_home_page_shows_content_from_the_admin(): void
    {
        $slider = Gallery::findBySlug(Gallery::HOME_SLIDER);
        GalleryItem::factory()->for($slider)->create(['title' => 'Gimnazija otvara vrata', 'link_url' => '/upis-2020/', 'link_label' => 'Sve o upisu']);

        $bottom = Gallery::findBySlug(Gallery::HOME_BOTTOM);
        $item = GalleryItem::factory()->for($bottom)->create(['caption' => 'Maturska veče']);
        $item->tags()->attach(Tag::factory()->create(['name' => 'Matura', 'slug' => 'matura']));

        Post::factory()->create(['title' => 'Najnovija vijest']);
        Event::factory()->create(['title' => 'Zimski koncert', 'date' => today()->addDays(3)]);
        Event::factory()->past()->create(['title' => 'Prošli događaj']);
        AlumniTestimonial::factory()->create(['name' => 'Ana Anić', 'quote' => 'Najljepše godine.']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Gimnazija otvara vrata')
            ->assertSee('Sve o upisu')
            ->assertSee('data-f="matura"', false)
            ->assertSee('data-tags="matura"', false)
            ->assertSee('Maturska veče')
            ->assertSee('Najnovija vijest')
            ->assertSee('Zimski koncert')
            ->assertDontSee('Prošli događaj')
            ->assertSee('Najljepše godine.')
            ->assertSee('"@type":"HighSchool"', false)
            ->assertDontSee('Naši učenici');
    }

    public function test_home_page_works_with_no_content(): void
    {
        $this->get('/')->assertOk()->assertSee('Dobrodošli')->assertSee('Trenutno nema najava');
    }

    // ---- Menu ---------------------------------------------------------------

    public function test_header_menu_shows_published_items_and_refreshes_when_pages_change(): void
    {
        $history = Page::factory()->create(['title' => 'Istorijat škole', 'slug' => 'istorijat-skole']);
        $draft = Page::factory()->draft()->create(['title' => 'Tajna stranica', 'slug' => 'tajna']);
        $about = MenuItem::factory()->create(['label' => 'O nama', 'url' => null]);
        MenuItem::factory()->forPage($history)->create(['parent_id' => $about->id]);
        MenuItem::factory()->forPage($draft)->create();
        MenuItem::factory()->create(['type' => 'posts-index', 'label' => null, 'url' => null, 'sort_order' => 5]);

        $this->get('/')
            ->assertSee('O nama')
            ->assertSee('href="/istorijat-skole/"', false)
            ->assertSee('href="/vijesti/"', false)
            ->assertDontSee('Tajna stranica');

        $history->update(['title' => 'Istorija gimnazije']);

        $this->get('/')->assertSee('Istorija gimnazije');
    }

    // ---- Feed & sitemap -----------------------------------------------------

    public function test_rss_feed(): void
    {
        Post::factory()->create(['title' => 'Vijest za RSS', 'slug' => 'vijest-za-rss']);
        Post::factory()->draft()->create(['title' => 'Ne u feedu']);

        $this->get('/feed/')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
            ->assertSee('<link>http://localhost/vijest-za-rss/</link>', false)
            ->assertDontSee('Ne u feedu');
    }

    public function test_sitemap_lists_public_urls_only(): void
    {
        Page::factory()->create(['slug' => 'kontakt']);
        Page::factory()->draft()->create(['slug' => 'nacrt-strana']);
        $category = Category::factory()->create(['slug' => 'vijesti-iz-skole']);
        Post::factory()->create(['slug' => 'neka-vijest'])->syncCategories([$category->id]);
        Category::factory()->create(['slug' => 'prazna']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>http://localhost/kontakt/</loc>', false)
            ->assertSee('<loc>http://localhost/neka-vijest/</loc>', false)
            ->assertSee('<loc>http://localhost/category/vijesti-iz-skole/</loc>', false)
            ->assertDontSee('nacrt-strana')
            ->assertDontSee('/category/prazna/');
    }
}
