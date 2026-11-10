<?php

namespace Tests\Feature\Admin;

use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
    }

    private function upload(User $user, UploadedFile $file): TestResponse
    {
        return $this->actingAs($user)->postJson(route('admin.media.store'), ['file' => $file]);
    }

    public function test_guests_cannot_open_the_library(): void
    {
        $this->get(route('admin.media.index'))->assertRedirect(route('login'));
        $this->postJson(route('admin.media.store'))->assertUnauthorized();
    }

    public function test_every_role_can_open_the_library(): void
    {
        Media::factory()->count(3)->create();

        foreach ([User::factory()->admin()->create(), User::factory()->editor()->create(), User::factory()->author()->create()] as $user) {
            $this->actingAs($user)
                ->get(route('admin.media.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('admin/media/Index')
                    ->has('media.data', 3)
                    ->has('upload.recommended.slider')
                );
        }
    }

    public function test_upload_creates_webp_main_file_and_variants_without_keeping_original(): void
    {
        $user = User::factory()->author()->create();

        $response = $this->upload($user, UploadedFile::fake()->image('Đački parlament.jpg', 4000, 3000))
            ->assertCreated()
            ->assertJsonPath('data.width', 1920)
            ->assertJsonPath('data.height', 1440)
            ->assertJsonPath('data.mime', 'image/webp')
            ->assertJsonPath('data.original_name', 'Đački parlament.jpg');

        $media = Media::query()->findOrFail($response->json('data.id'));
        $dir = now()->format('Y/m');

        $this->assertSame("{$dir}/djacki-parlament.webp", $media->path);
        $this->assertSame($user->id, $media->user_id);
        // MySQL JSON columns don't keep key order, so compare as a set.
        $this->assertEqualsCanonicalizing(['large', 'medium', 'thumb'], array_keys($media->variants ?? []));
        $this->assertSame(1600, $media->variants['large']['width']);
        $this->assertSame(400, $media->variants['thumb']['width']);

        Storage::disk('uploads')->assertExists([
            "{$dir}/djacki-parlament.webp",
            "{$dir}/djacki-parlament-1600.webp",
            "{$dir}/djacki-parlament-800.webp",
            "{$dir}/djacki-parlament-400.webp",
        ]);

        // Only WebP files — the original JPG is not stored.
        $files = Storage::disk('uploads')->allFiles();
        $this->assertCount(4, $files);
        $this->assertEmpty(array_filter($files, fn (string $f) => ! str_ends_with($f, '.webp')));
    }

    public function test_small_image_is_not_upscaled_and_gets_only_smaller_variants(): void
    {
        $this->upload(User::factory()->create(), UploadedFile::fake()->image('mala.png', 700, 500))
            ->assertCreated()
            ->assertJsonPath('data.width', 700)
            ->assertJsonPath('data.height', 500);

        $media = Media::query()->sole();
        $this->assertSame(['thumb'], array_keys($media->variants ?? []));
    }

    public function test_portrait_image_is_fitted_on_its_long_side(): void
    {
        $this->upload(User::factory()->create(), UploadedFile::fake()->image('portret.jpg', 2000, 3000))
            ->assertCreated()
            ->assertJsonPath('data.width', 1280)
            ->assertJsonPath('data.height', 1920);
    }

    public function test_same_file_name_gets_a_numbered_suffix(): void
    {
        $user = User::factory()->create();
        $this->upload($user, UploadedFile::fake()->image('slika.jpg', 800, 600))->assertCreated();
        $this->upload($user, UploadedFile::fake()->image('slika.jpg', 800, 600))->assertCreated();

        $this->assertSame(
            [now()->format('Y/m').'/slika.webp', now()->format('Y/m').'/slika-2.webp'],
            Media::query()->orderBy('id')->pluck('path')->all(),
        );
    }

    public function test_non_images_are_rejected_with_a_serbian_message(): void
    {
        $this->upload(User::factory()->create(), UploadedFile::fake()->create('dokument.pdf', 100, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Fajl mora biti fotografija (JPG, PNG ili WebP).']);

        $this->assertSame(0, Media::query()->count());
    }

    public function test_too_large_files_are_rejected(): void
    {
        config(['cms.images.max_upload_kb' => 100]);

        $this->upload(User::factory()->create(), UploadedFile::fake()->image('velika.jpg', 800, 600)->size(200))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_browse_returns_paginated_json_and_searches(): void
    {
        Media::factory()->create(['original_name' => 'matura-2026.jpg']);
        Media::factory()->create(['original_name' => 'ekskurzija.jpg', 'alt' => 'Učenici u Grčkoj']);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.browse', ['search' => 'grčk']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.original_name', 'ekskurzija.jpg')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_show_includes_usages(): void
    {
        $media = Media::factory()->create();
        Post::factory()->create(['title' => 'Dan otvorenih vrata', 'featured_media_id' => $media->id]);

        $this->actingAs(User::factory()->create())
            ->getJson(route('admin.media.show', $media))
            ->assertOk()
            ->assertJsonPath('data.usages.0.type', 'Naslovna slika objave')
            ->assertJsonPath('data.usages.0.title', 'Dan otvorenih vrata');
    }

    public function test_alt_and_caption_can_be_updated(): void
    {
        $media = Media::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patchJson(route('admin.media.update', $media), ['alt' => 'Zgrada škole', 'caption' => 'Pogled sa ulice'])
            ->assertOk()
            ->assertJsonPath('data.alt', 'Zgrada škole');

        $this->assertSame('Pogled sa ulice', $media->fresh()?->caption);
    }

    public function test_unused_media_is_deleted_with_all_its_files(): void
    {
        $user = User::factory()->create();
        $id = $this->upload($user, UploadedFile::fake()->image('brisanje.jpg', 2000, 1500))->json('data.id');

        $this->actingAs($user)->deleteJson(route('admin.media.destroy', $id))->assertNoContent();

        $this->assertSame(0, Media::query()->count());
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_media_in_use_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $id = $this->upload($user, UploadedFile::fake()->image('koristi-se.jpg', 1000, 800))->json('data.id');
        Post::factory()->create(['title' => 'Vijest', 'featured_media_id' => $id]);

        $this->actingAs($user)
            ->deleteJson(route('admin.media.destroy', $id))
            ->assertConflict()
            ->assertJsonPath('message', 'Slika se koristi i ne može se obrisati.')
            ->assertJsonPath('usages.0.title', 'Vijest');

        $this->assertSame(1, Media::query()->count());
        $this->assertNotEmpty(Storage::disk('uploads')->allFiles());
    }
}
