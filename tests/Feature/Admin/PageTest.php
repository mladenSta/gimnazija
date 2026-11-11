<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Istorijat škole',
            'slug' => '',
            'content' => '<p>Gimnazija je osnovana…</p>',
            'status' => 'published',
            'published_at' => '',
            'gallery_ids' => [],
            'featured_media_id' => null,
            'meta_title' => '',
            'meta_description' => '',
            ...$overrides,
        ];
    }

    public function test_every_role_sees_and_edits_all_pages(): void
    {
        $page = Page::factory()->create();

        foreach ([User::factory()->admin()->create(), User::factory()->editor()->create(), User::factory()->author()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.pages.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('admin/pages/Index')->has('pages.data', 1));

            $this->actingAs($user)->get(route('admin.pages.edit', $page))->assertOk();
            $this->actingAs($user)
                ->put(route('admin.pages.update', $page), $this->payload(['title' => "Izmjena {$user->role->value}", 'slug' => $page->slug]))
                ->assertRedirect(route('admin.pages.edit', $page));
        }

        $this->assertSame('Izmjena author', $page->fresh()?->title);
    }

    public function test_create_page_with_auto_slug_images_and_owner(): void
    {
        $author = User::factory()->author()->create();
        [$featured, $g1, $g2] = Media::factory()->count(3)->create();

        $this->actingAs($author)
            ->post(route('admin.pages.store'), $this->payload([
                'featured_media_id' => $featured->id,
                'gallery_ids' => [$g2->id, $g1->id],
            ]))
            ->assertRedirect();

        $page = Page::query()->sole();
        $this->assertSame('istorijat-skole', $page->slug);
        $this->assertSame($author->id, $page->user_id);
        $this->assertTrue($page->isPublished());
        $this->assertSame($featured->id, $page->featured_media_id);
        $this->assertSame([$g2->id, $g1->id], $page->galleryImages()->pluck('media.id')->all());
    }

    public function test_page_slug_cannot_reuse_a_post_slug(): void
    {
        Post::factory()->create(['slug' => 'dan-skole']);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.pages.store'), $this->payload(['slug' => 'dan-skole']))
            ->assertSessionHasErrors(['slug' => 'Ovaj link već koristi druga objava ili stranica.']);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.pages.store'), $this->payload(['title' => 'Dan škole']));
        $this->assertTrue(Page::query()->where('slug', 'dan-skole-2')->exists());
    }

    public function test_published_page_slug_change_creates_redirect(): void
    {
        $page = Page::factory()->create(['slug' => 'upis-2020']);

        $this->actingAs(User::factory()->editor()->create())
            ->put(route('admin.pages.update', $page), $this->payload(['slug' => 'upis']));

        $this->assertDatabaseHas('redirects', ['from_path' => '/upis-2020/', 'to_path' => '/upis/']);
    }

    public function test_edit_page_shows_menu_flag_and_resolved_images(): void
    {
        $media = Media::factory()->create();
        $page = Page::factory()->create(['featured_media_id' => $media->id]);
        MenuItem::factory()->forPage($page)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.pages.edit', $page))
            ->assertInertia(fn (Assert $p) => $p
                ->component('admin/pages/Edit')
                ->where('page.in_menu', true)
                ->where('page.featured_image.medium_url', $media->url('medium')));
    }

    public function test_trash_keeps_menu_item_force_delete_removes_it(): void
    {
        $user = User::factory()->author()->create();
        $page = Page::factory()->create();
        MenuItem::factory()->forPage($page)->create();

        $this->actingAs($user)->delete(route('admin.pages.destroy', $page));
        $this->assertSoftDeleted($page);
        $this->assertSame(1, MenuItem::query()->count());

        $this->actingAs($user)->post(route('admin.pages.restore', $page));
        $this->assertNotSoftDeleted($page);

        $page->delete();
        $this->actingAs($user)->delete(route('admin.pages.force-delete', $page));
        $this->assertModelMissing($page);
        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_index_tabs_and_search(): void
    {
        Page::factory()->create(['title' => 'Biblioteka']);
        Page::factory()->draft()->create(['title' => 'Bilten (nacrt)']);
        Page::factory()->create(['title' => 'Kontakt'])->delete();

        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.pages.index'))
            ->assertInertia(fn (Assert $p) => $p->has('pages.data', 2)->where('pages.meta.total', 2)->has('pages.meta.links')
                ->where('counts.all', 2)->where('counts.draft', 1)->where('counts.trash', 1));

        $this->actingAs($user)->get(route('admin.pages.index', ['search' => 'bil']))
            ->assertInertia(fn (Assert $p) => $p->has('pages.data', 1)->where('pages.data.0.title', 'Bilten (nacrt)'));

        $this->actingAs($user)->get(route('admin.pages.index', ['status' => 'trash']))
            ->assertInertia(fn (Assert $p) => $p->has('pages.data', 1)->where('pages.data.0.status', 'trash'));
    }

    public function test_youtube_videos_are_saved_validated_and_shown_below_the_text(): void
    {
        $user = User::factory()->author()->create();
        $image = Media::factory()->create();

        $this->actingAs($user)
            ->post(route('admin.pages.store'), $this->payload(['video_url_1' => 'https://vimeo.com/1', 'video_url_2' => 'nije link']))
            ->assertSessionHasErrors(['video_url_1', 'video_url_2']);

        $this->actingAs($user)
            ->post(route('admin.pages.store'), $this->payload([
                'video_url_1' => '',
                'video_url_2' => ' https://youtu.be/mW7KCrpX1Fk ',
                'gallery_ids' => [$image->id],
            ]))
            ->assertSessionHasNoErrors();

        $page = Page::query()->sole();
        $this->assertNull($page->video_url_1);
        $this->assertSame('https://youtu.be/mW7KCrpX1Fk', $page->video_url_2);
        $this->assertSame(['mW7KCrpX1Fk'], $page->videoIds());

        $this->actingAs($user)
            ->get(route('admin.pages.edit', $page))
            ->assertInertia(fn (Assert $p) => $p->where('page.video_url_2', 'https://youtu.be/mW7KCrpX1Fk'));

        // Text → video → gallery.
        $this->get('/istorijat-skole/')->assertOk()->assertSeeInOrder([
            'Gimnazija je osnovana',
            'https://www.youtube-nocookie.com/embed/mW7KCrpX1Fk',
            'post-gallery',
        ], false);
    }
}
