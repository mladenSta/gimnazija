<?php

namespace Tests\Feature\Admin;

use App\Enums\ContentStatus;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PostTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Dan otvorenih vrata',
            'slug' => '',
            'excerpt' => '',
            'content' => '<p>Pozivamo buduće gimnazijalce.</p>',
            'status' => 'published',
            'published_at' => '',
            'category_ids' => [],
            'gallery_ids' => [],
            'featured_media_id' => null,
            'meta_title' => '',
            'meta_description' => '',
            ...$overrides,
        ];
    }

    // ---- Access -------------------------------------------------------------

    public function test_authors_see_only_their_own_posts(): void
    {
        $author = User::factory()->author()->create();
        Post::factory()->for($author, 'author')->create(['title' => 'Moja vijest']);
        Post::factory()->create(['title' => 'Tuđa vijest']);

        $this->actingAs($author)
            ->get(route('admin.posts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/posts/Index')
                ->has('posts.data', 1)
                ->where('posts.data.0.title', 'Moja vijest')
                ->where('posts.meta.last_page', 1)
                ->where('posts.meta.total', 1)
                ->has('posts.meta.links')
                ->where('counts.all', 1));
    }

    public function test_editors_and_admins_see_all_posts(): void
    {
        Post::factory()->count(3)->create();

        foreach ([User::factory()->editor()->create(), User::factory()->admin()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.posts.index'))
                ->assertInertia(fn (Assert $page) => $page->has('posts.data', 3));
        }
    }

    public function test_author_cannot_edit_update_or_delete_someone_elses_post(): void
    {
        $author = User::factory()->author()->create();
        $other = Post::factory()->create();

        $this->actingAs($author)->get(route('admin.posts.edit', $other))->assertForbidden();
        $this->actingAs($author)->put(route('admin.posts.update', $other), $this->payload())->assertForbidden();
        $this->actingAs($author)->delete(route('admin.posts.destroy', $other))->assertForbidden();
        $this->assertNotSoftDeleted($other);
    }

    public function test_editor_can_edit_and_delete_anyones_post(): void
    {
        $editor = User::factory()->editor()->create();
        $post = Post::factory()->create();

        $this->actingAs($editor)->get(route('admin.posts.edit', $post))->assertOk();
        $this->actingAs($editor)
            ->put(route('admin.posts.update', $post), $this->payload(['title' => 'Izmijenio urednik', 'slug' => $post->slug]))
            ->assertRedirect(route('admin.posts.edit', $post));
        $this->assertSame('Izmijenio urednik', $post->fresh()?->title);

        $this->actingAs($editor)->delete(route('admin.posts.destroy', $post))->assertRedirect();
        $this->assertSoftDeleted($post);
    }

    // ---- Create / slug ------------------------------------------------------

    public function test_author_creates_a_post_with_auto_slug_and_is_its_owner(): void
    {
        $author = User::factory()->author()->create();

        $this->actingAs($author)
            ->post(route('admin.posts.store'), $this->payload(['title' => 'Đački parlament – izbori']))
            ->assertRedirect();

        $post = Post::query()->sole();
        $this->assertSame('djacki-parlament-izbori', $post->slug);
        $this->assertSame($author->id, $post->user_id);
        $this->assertSame(ContentStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);
        $this->assertTrue($post->isPublished());
    }

    public function test_auto_slug_is_unique_across_posts_and_pages(): void
    {
        Page::factory()->create(['slug' => 'kontakt']);
        Post::factory()->create(['slug' => 'kontakt-2']);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload(['title' => 'Kontakt']));

        $this->assertTrue(Post::query()->where('slug', 'kontakt-3')->exists());
    }

    public function test_manual_slug_taken_by_a_page_is_rejected(): void
    {
        Page::factory()->create(['slug' => 'biblioteka']);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload(['slug' => 'biblioteka']))
            ->assertSessionHasErrors(['slug' => 'Ovaj link već koristi druga objava ili stranica.']);
    }

    public function test_reserved_and_invalid_slugs_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.posts.store'), $this->payload(['slug' => 'admin']))
            ->assertSessionHasErrors('slug');
        $this->actingAs($user)->post(route('admin.posts.store'), $this->payload(['slug' => 'Dan Škole']))
            ->assertSessionHasErrors('slug');
    }

    public function test_title_is_required_with_serbian_message(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload(['title' => '']))
            ->assertSessionHasErrors(['title' => 'Polje naslov je obavezno.']);
    }

    // ---- Status / schedule --------------------------------------------------

    public function test_draft_has_no_publish_date_and_is_not_public(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload(['status' => 'draft']));

        $post = Post::query()->sole();
        $this->assertNull($post->published_at);
        $this->assertFalse(Post::query()->published()->exists());
    }

    public function test_future_date_schedules_the_post(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload(['published_at' => now()->addDay()->format('Y-m-d\TH:i')]));

        $post = Post::query()->sole();
        $this->assertTrue($post->isScheduled());
        $this->assertFalse(Post::query()->published()->exists());
    }

    // ---- Content, images, categories ---------------------------------------

    public function test_content_is_sanitized(): void
    {
        $html = '<h1>Naslov</h1><p onclick="alert(1)" style="color:red" class="x">Tekst <a href="javascript:alert(1)">loš</a> '
            .'<a href="/kontakt/">dobar</a></p><script>alert(1)</script><img src="x" onerror="alert(1)">';

        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload(['content' => $html]));

        $content = (string) Post::query()->sole()->content;
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onclick', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertStringNotContainsString('style=', $content);
        $this->assertStringNotContainsString('javascript:', $content);
        $this->assertStringContainsString('<h2>Naslov</h2>', $content);
        $this->assertStringContainsString('<a href="/kontakt/">dobar</a>', $content);
    }

    public function test_leading_and_trailing_empty_paragraphs_are_removed(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload(['content' => '<p></p><p>Tekst</p><p><br></p><p></p>']));

        $this->assertSame('<p>Tekst</p>', Post::query()->sole()->content);
    }

    public function test_empty_editor_content_is_stored_as_null(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload(['content' => '<p></p>']));

        $this->assertNull(Post::query()->sole()->content);
    }

    public function test_featured_image_gallery_order_and_categories_are_saved(): void
    {
        [$featured, $g1, $g2] = Media::factory()->count(3)->create();
        [$news, $sport] = Category::factory()->count(2)->create();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload([
                'featured_media_id' => $featured->id,
                'gallery_ids' => [$g2->id, $g1->id],
                'category_ids' => [$sport->id, $news->id],
            ]));

        $post = Post::query()->sole();
        $this->assertSame($featured->id, $post->featured_media_id);
        $this->assertSame([$g2->id, $g1->id], $post->galleryImages()->pluck('media.id')->all());
        $this->assertSame([$sport->id, $news->id], $post->categories()->pluck('categories.id')->all());
    }

    public function test_inline_images_from_the_library_are_tracked(): void
    {
        $media = Media::factory()->create(['path' => '2026/11/hor.webp', 'variants' => [
            'medium' => ['path' => '2026/11/hor-800.webp', 'width' => 800, 'height' => 450],
        ]]);
        $html = '<p>Tekst</p><img src="'.$media->url('medium').'" alt="Hor"><img src="https://drugi-sajt.com/a.jpg">';

        $this->actingAs(User::factory()->create())
            ->post(route('admin.posts.store'), $this->payload(['content' => $html]));

        $post = Post::query()->sole();
        $this->assertSame([$media->id], $post->inlineImages()->pluck('media.id')->all());
        $this->assertTrue($media->fresh()?->isInUse());
    }

    // ---- Redirects ----------------------------------------------------------

    public function test_changing_slug_of_a_published_post_creates_a_301_redirect(): void
    {
        $post = Post::factory()->create(['slug' => 'stari-naslov']);
        $user = User::factory()->editor()->create();

        $this->actingAs($user)->put(route('admin.posts.update', $post), $this->payload(['slug' => 'novi-naslov']));
        $this->assertDatabaseHas('redirects', ['from_path' => '/stari-naslov/', 'to_path' => '/novi-naslov/', 'status_code' => 301]);

        // Second rename: no chains, old links go straight to the newest URL.
        $this->actingAs($user)->put(route('admin.posts.update', $post), $this->payload(['slug' => 'najnoviji']));
        $this->assertSame('/najnoviji/', Redirect::query()->where('from_path', '/stari-naslov/')->value('to_path'));
        $this->assertSame('/najnoviji/', Redirect::query()->where('from_path', '/novi-naslov/')->value('to_path'));

        // Renaming back removes the redirect away from the live URL.
        $this->actingAs($user)->put(route('admin.posts.update', $post), $this->payload(['slug' => 'stari-naslov']));
        $this->assertFalse(Redirect::query()->where('from_path', '/stari-naslov/')->exists());
    }

    public function test_changing_slug_of_a_draft_creates_no_redirect(): void
    {
        $post = Post::factory()->draft()->create(['slug' => 'nacrt']);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.posts.update', $post), $this->payload(['slug' => 'nacrt-2', 'status' => 'draft']));

        $this->assertSame(0, Redirect::query()->count());
    }

    // ---- Trash --------------------------------------------------------------

    public function test_trash_restore_and_force_delete(): void
    {
        $author = User::factory()->author()->create();
        $post = Post::factory()->for($author, 'author')->create();
        $post->syncGalleryImages([Media::factory()->create()->id]);

        $this->actingAs($author)->delete(route('admin.posts.destroy', $post));
        $this->assertSoftDeleted($post);

        $this->actingAs($author)
            ->get(route('admin.posts.index', ['status' => 'trash']))
            ->assertInertia(fn (Assert $page) => $page->has('posts.data', 1)->where('posts.data.0.status', 'trash'));

        $this->actingAs($author)->post(route('admin.posts.restore', $post));
        $this->assertNotSoftDeleted($post);

        $post->delete();
        $this->actingAs($author)->delete(route('admin.posts.force-delete', $post));
        $this->assertModelMissing($post);
        $this->assertDatabaseCount('mediables', 0);
    }

    public function test_index_filters_by_tab_search_and_category(): void
    {
        $user = User::factory()->admin()->create();
        $category = Category::factory()->create();
        Post::factory()->create(['title' => 'Matura 2026'])->syncCategories([$category->id]);
        Post::factory()->draft()->create(['title' => 'Nacrt o maturi']);
        Post::factory()->scheduled()->create(['title' => 'Zakazana vijest']);

        $this->actingAs($user)->get(route('admin.posts.index', ['status' => 'draft']))
            ->assertInertia(fn (Assert $p) => $p->has('posts.data', 1)->where('posts.data.0.status', 'draft')
                ->where('counts.published', 1)->where('counts.scheduled', 1)->where('counts.draft', 1));

        $this->actingAs($user)->get(route('admin.posts.index', ['search' => 'matur']))
            ->assertInertia(fn (Assert $p) => $p->has('posts.data', 2));

        $this->actingAs($user)->get(route('admin.posts.index', ['category' => $category->id]))
            ->assertInertia(fn (Assert $p) => $p->has('posts.data', 1)->where('posts.data.0.title', 'Matura 2026'));
    }

    public function test_edit_page_provides_form_data(): void
    {
        $media = Media::factory()->create();
        $post = Post::factory()->create(['featured_media_id' => $media->id]);
        $post->syncGalleryImages([$media->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.posts.edit', $post))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/posts/Edit')
                ->where('post.id', $post->id)
                ->where('post.featured_image.id', $media->id)
                ->where('post.featured_image.medium_url', $media->url('medium'))
                ->where('post.gallery.0.id', $media->id)
                ->has('upload.recommended.featured')
                ->has('statuses', 2));
    }
}
