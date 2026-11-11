<?php

namespace Tests\Feature\Admin;

use App\Enums\SocialNetwork;
use App\Models\Post;
use App\Models\Setting;
use App\Models\SocialShare;
use App\Models\User;
use App\Services\Media\ImageUploader;
use App\Services\Social\LinkedInPublisher;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SocialPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Sleep::fake();
    }

    private function configure(SocialNetwork ...$networks): void
    {
        $values = [];

        foreach ($networks as $network) {
            $keys = $network->settingKeys();
            $values[$keys['id']] = '12345';
            $values[$keys['token']] = "{$network->value}-token-abcd";
        }

        app(Settings::class)->put($values);
    }

    /**
     * @return array<string, mixed>
     */
    private function socialProp(User $user, Post $post): array
    {
        $props = [];
        $this->actingAs($user)->get(route('admin.posts.edit', $post))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $props = $page->toArray()['props']['social'];
            });

        return $props;
    }

    public function test_only_admins_can_open_and_save_settings(): void
    {
        foreach ([User::factory()->editor()->create(), User::factory()->author()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.settings.edit'))->assertForbidden();
            $this->actingAs($user)->put(route('admin.settings.update'), ['networks' => ['facebook' => ['account_id' => '1', 'token' => 'x']]])->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('admin/settings/Index')->has('networks', 3));
    }

    public function test_tokens_are_stored_encrypted_and_never_sent_back(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'networks' => ['facebook' => ['account_id' => ' 987654321 ', 'token' => 'EAAG-secret-WXYZ']],
        ])->assertRedirect(route('admin.settings.edit'));

        $raw = Setting::query()->where('key', 'social.facebook.token')->value('value');
        $this->assertNotSame('EAAG-secret-WXYZ', $raw);
        $this->assertStringNotContainsString('secret', (string) $raw);

        $this->actingAs($admin)->get(route('admin.settings.edit'))
            ->assertDontSee('EAAG-secret-WXYZ')
            ->assertInertia(fn (Assert $p) => $p
                ->where('networks.0.key', 'facebook')
                ->where('networks.0.account_id', '987654321')
                ->where('networks.0.has_token', true)
                ->where('networks.0.token_hint', '…WXYZ')
                ->where('networks.0.configured', true)
                ->where('networks.1.configured', false));
    }

    public function test_empty_token_keeps_the_saved_one_and_clear_removes_everything(): void
    {
        $admin = User::factory()->admin()->create();
        $this->configure(SocialNetwork::LinkedIn);

        // LinkedIn URN is reduced to the number; empty token = unchanged.
        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'networks' => ['linkedin' => ['account_id' => 'urn:li:organization:555', 'token' => '']],
        ])->assertSessionHasNoErrors();

        $settings = app(Settings::class);
        $this->assertSame('555', $settings->get('social.linkedin.id'));
        $this->assertSame('linkedin-token-abcd', $settings->get('social.linkedin.token'));

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'networks' => ['linkedin' => ['clear' => true]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Setting::query()->count());
    }

    public function test_account_id_must_be_numeric(): void
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), [
            'networks' => ['facebook' => ['account_id' => 'skola-doboj', 'token' => 'x']],
        ])->assertSessionHasErrors(['networks.facebook.account_id' => 'ID se sastoji samo od cifara.']);
    }

    public function test_buttons_show_only_for_configured_networks_and_for_admins_and_editors(): void
    {
        $post = Post::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->assertSame([], $this->socialProp($admin, $post));

        $this->configure(SocialNetwork::Facebook, SocialNetwork::LinkedIn);

        $social = $this->socialProp($admin, $post);
        $this->assertSame(['facebook', 'linkedin'], array_column($social, 'key'));
        $this->assertSame(['Objavi na FB', 'Objavi na LinkedIn'], array_column($social, 'button'));
        $this->assertNull($social[0]['disabled_reason']);

        $this->configure(SocialNetwork::Instagram);
        $this->assertCount(3, $this->socialProp(User::factory()->editor()->create(), $post));

        $author = User::factory()->author()->create();
        $own = Post::factory()->for($author, 'author')->create();
        $this->assertSame([], $this->socialProp($author, $own));
    }

    public function test_instagram_needs_a_featured_image_and_drafts_cant_be_shared(): void
    {
        $this->configure(SocialNetwork::Facebook, SocialNetwork::Instagram);
        $admin = User::factory()->admin()->create();

        $social = $this->socialProp($admin, Post::factory()->create());
        $this->assertNull($social[0]['disabled_reason']);
        $this->assertSame('Za Instagram je potrebna naslovna slika.', $social[1]['disabled_reason']);

        $draft = Post::factory()->draft()->create();
        $this->assertSame('Objava mora biti objavljena na sajtu.', $this->socialProp($admin, $draft)[0]['disabled_reason']);

        $this->actingAs($admin)->post(route('admin.posts.share', [$draft, 'facebook']))
            ->assertRedirect(route('admin.posts.edit', $draft));

        Http::assertNothingSent();
        $this->assertFalse(SocialShare::query()->sole()->success);
    }

    public function test_authors_cannot_share_and_unknown_networks_are_404(): void
    {
        $this->configure(SocialNetwork::Facebook);
        $author = User::factory()->author()->create();
        $post = Post::factory()->for($author, 'author')->create();

        $this->actingAs($author)->post(route('admin.posts.share', [$post, 'facebook']))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->post("/admin/posts/{$post->id}/share/twitter")->assertNotFound();
    }

    public function test_publishes_on_facebook_and_shows_success(): void
    {
        $this->configure(SocialNetwork::Facebook);
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(['title' => 'Dan škole', 'slug' => 'dan-skole', 'excerpt' => 'Proslava 80 godina.']);

        Http::fake(['graph.facebook.com/*' => Http::response(['id' => '12345_678'])]);

        $this->actingAs($admin)->post(route('admin.posts.share', [$post, 'facebook']))
            ->assertRedirect(route('admin.posts.edit', $post))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Objavljeno na mreži Facebook.']);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && str_ends_with($r->url(), '/12345/feed')
            && $r['message'] === "Dan škole\n\nProslava 80 godina."
            && $r['link'] === rtrim((string) config('app.url'), '/').'/dan-skole/'
            && $r['access_token'] === 'facebook-token-abcd');

        $share = SocialShare::query()->sole();
        $this->assertTrue($share->success);
        $this->assertSame('https://www.facebook.com/12345_678', $share->external_url);
        $this->assertSame($admin->id, $share->user_id);

        $panel = $this->socialProp($admin, $post)[0];
        $this->assertTrue($panel['last']['success']);
        $this->assertNotNull($panel['shared_at']);
    }

    public function test_facebook_error_is_logged_and_shown(): void
    {
        $this->configure(SocialNetwork::Facebook);
        $post = Post::factory()->create();

        Http::fake(['graph.facebook.com/*' => Http::response([
            'error' => ['message' => 'Error validating access token: Session has expired', 'code' => 190],
        ], 400)]);

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.posts.share', [$post, 'facebook']))
            ->assertInertiaFlash('toast.type', 'error');

        $share = SocialShare::query()->sole();
        $this->assertFalse($share->success);
        $this->assertStringContainsString('Token je istekao', $share->message);
        $this->assertStringContainsString('Session has expired', $share->message);
    }

    public function test_publishes_the_featured_image_on_instagram_as_jpeg(): void
    {
        Storage::fake('uploads');
        $this->configure(SocialNetwork::Instagram);
        $media = app(ImageUploader::class)->store(UploadedFile::fake()->image('visoka.jpg', 800, 1600));
        $post = Post::factory()->create(['title' => 'Takmičenje', 'slug' => 'takmicenje', 'featured_media_id' => $media->id]);

        Http::fake([
            'graph.facebook.com/*/12345/media' => Http::response(['id' => 'container-1']),
            'graph.facebook.com/*/container-1*' => Http::sequence()
                ->push(['status_code' => 'IN_PROGRESS'])
                ->push(['status_code' => 'FINISHED']),
            'graph.facebook.com/*/12345/media_publish' => Http::response(['id' => 'ig-99']),
            'graph.facebook.com/*/ig-99*' => Http::response(['permalink' => 'https://www.instagram.com/p/abc/']),
        ]);

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.posts.share', [$post, 'instagram']))
            ->assertInertiaFlash('toast.type', 'success');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/12345/media')
            && str_contains((string) $r['image_url'], "social/{$media->id}.jpg")
            && str_starts_with((string) $r['caption'], 'Takmičenje')
            && str_ends_with((string) $r['caption'], '/takmicenje/'));
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/media_publish') && $r['creation_id'] === 'container-1');

        // Portrait 1:2 is cropped to Instagram's tallest allowed ratio, 4:5.
        $jpeg = Storage::disk('uploads')->get("social/{$media->id}.jpg");
        $size = getimagesizefromstring((string) $jpeg);
        $this->assertSame('image/jpeg', $size['mime'] ?? null);
        $this->assertEqualsWithDelta(0.8, $size[0] / $size[1], 0.01);

        $this->assertSame('https://www.instagram.com/p/abc/', SocialShare::query()->sole()->external_url);
    }

    public function test_publishes_an_article_on_linkedin(): void
    {
        $this->configure(SocialNetwork::LinkedIn);
        $post = Post::factory()->create(['title' => 'Upis (2026)', 'slug' => 'upis', 'excerpt' => 'Sve o upisu #gimnazija']);

        Http::fake(['api.linkedin.com/rest/posts' => Http::response('', 201, ['x-restli-id' => 'urn:li:share:777'])]);

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.posts.share', [$post, 'linkedin']))
            ->assertInertiaFlash('toast.type', 'success');

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api.linkedin.com/rest/posts'
            && $r->hasHeader('Authorization', 'Bearer linkedin-token-abcd')
            && $r->hasHeader('LinkedIn-Version')
            && $r['author'] === 'urn:li:organization:12345'
            && $r['commentary'] === "Upis \\(2026\\)\n\nSve o upisu \\#gimnazija"
            && $r['content']['article']['source'] === rtrim((string) config('app.url'), '/').'/upis/'
            && $r['content']['article']['title'] === 'Upis (2026)');

        $this->assertSame('https://www.linkedin.com/feed/update/urn:li:share:777/', SocialShare::query()->sole()->external_url);
    }

    public function test_linkedin_error_message(): void
    {
        $this->configure(SocialNetwork::LinkedIn);
        $post = Post::factory()->create();

        Http::fake(['api.linkedin.com/*' => Http::response(['message' => 'Invalid access token', 'status' => 401], 401)]);

        $this->actingAs(User::factory()->admin()->create())->post(route('admin.posts.share', [$post, 'linkedin']))
            ->assertInertiaFlash('toast.type', 'error');

        $message = SocialShare::query()->sole()->message;
        $this->assertStringStartsWith('LinkedIn: Token je istekao', $message);
        $this->assertStringContainsString('Invalid access token', $message);
    }

    public function test_linkedin_reserved_characters_are_escaped(): void
    {
        $this->assertSame('a\\_b \\@c \\[d\\] e\\\\f', LinkedInPublisher::escape('a_b @c [d] e\\f'));
    }
}
