<?php

namespace Tests\Feature\Admin;

use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Media;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\GallerySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GallerySeeder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function item(Media $media, array $overrides = []): array
    {
        return ['id' => null, 'media_id' => $media->id, 'caption' => null, 'focal_x' => 50, 'focal_y' => 50, 'tags' => [], ...$overrides];
    }

    public function test_every_role_sees_galleries_with_system_ones_first(): void
    {
        Gallery::factory()->create(['name' => 'Aaa custom']);

        foreach ([User::factory()->admin()->create(), User::factory()->editor()->create(), User::factory()->author()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.galleries.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('admin/galleries/Index')
                    ->has('galleries', 4)
                    ->where('galleries.0.is_system', true)
                    ->where('galleries.3.name', 'Aaa custom'));
        }
    }

    public function test_create_custom_gallery_with_unique_slug(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.galleries.store'), ['name' => 'Ekskurzija 2026', 'type' => 'standard'])->assertRedirect();
        $this->actingAs($user)->post(route('admin.galleries.store'), ['name' => 'Ekskurzija 2026', 'type' => 'standard']);

        $this->assertSame(['ekskurzija-2026', 'ekskurzija-2026-2'], Gallery::query()->where('is_system', false)->orderBy('id')->pluck('slug')->all());
    }

    public function test_save_items_in_order_with_focal_point_and_tags(): void
    {
        $gallery = Gallery::findBySlug(Gallery::HOME_BOTTOM);
        [$a, $b] = Media::factory()->count(2)->create();

        $this->actingAs(User::factory()->author()->create())
            ->put(route('admin.galleries.update', $gallery), ['name' => 'Početna – dolje', 'items' => [
                $this->item($b, ['caption' => 'Matursko veče', 'focal_x' => 30, 'focal_y' => 70, 'tags' => ['Matura', 'Đaci']]),
                $this->item($a, ['tags' => ['matura']]),
            ]])
            ->assertRedirect(route('admin.galleries.edit', $gallery));

        $items = $gallery?->items()->with('tags')->get();
        $this->assertSame([$b->id, $a->id], $items?->pluck('media_id')->all());
        $this->assertSame('30% 70%', $items?->first()?->object_position);
        $this->assertSame('Matursko veče', $items?->first()?->caption);
        // "matura" reuses the "Matura" tag (same slug); "Đaci" → djaci.
        $this->assertEqualsCanonicalizing(['matura', 'djaci'], Tag::query()->pluck('slug')->all());
        $this->assertSame(['matura'], $items?->last()?->tags->pluck('slug')->all());
    }

    public function test_resave_updates_existing_removes_missing_and_cleans_orphan_tags(): void
    {
        $gallery = Gallery::findBySlug(Gallery::HOME_TOP);
        $keep = GalleryItem::factory()->for($gallery)->create();
        $drop = GalleryItem::factory()->for($gallery)->create();
        $drop->tags()->attach(Tag::factory()->create(['slug' => 'staro']));

        $this->actingAs(User::factory()->create())
            ->put(route('admin.galleries.update', $gallery), ['name' => 'Gore', 'items' => [
                $this->item($keep->media, ['id' => $keep->id, 'caption' => 'Novi opis']),
            ]]);

        $this->assertModelMissing($drop);
        $this->assertSame('Novi opis', $keep->fresh()?->caption);
        $this->assertSame('Gore', $gallery?->fresh()?->name);
        $this->assertFalse(Tag::query()->where('slug', 'staro')->exists());
        // The photo itself stays in the media library.
        $this->assertModelExists($drop->media);
    }

    public function test_slider_fields_are_saved_only_for_slider_galleries(): void
    {
        $slider = Gallery::findBySlug(Gallery::HOME_SLIDER);
        $standard = Gallery::findBySlug(Gallery::HOME_TOP);
        $media = Media::factory()->create();
        $slide = ['title' => 'Upis 2026', 'badge' => '🎓 Upis', 'subtitle' => 'Četiri odjeljenja', 'link_label' => 'Sve o upisu', 'link_url' => '/upis-2020/'];

        $user = User::factory()->create();
        $this->actingAs($user)->put(route('admin.galleries.update', $slider), ['name' => 'Slajder', 'items' => [$this->item($media, $slide)]]);
        $this->actingAs($user)->put(route('admin.galleries.update', $standard), ['name' => 'Gore', 'items' => [$this->item($media, $slide)]]);

        $this->assertSame('/upis-2020/', $slider?->items()->first()?->link_url);
        $this->assertNull($standard?->items()->first()?->title);
    }

    public function test_validation_of_focal_point_and_button_link(): void
    {
        $gallery = Gallery::findBySlug(Gallery::HOME_SLIDER);
        $media = Media::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('admin.galleries.update', $gallery), ['name' => 'X', 'items' => [
                $this->item($media, ['focal_x' => 150, 'link_url' => 'javascript:alert(1)']),
            ]])
            ->assertSessionHasErrors(['items.0.focal_x', 'items.0.link_url']);
    }

    public function test_system_galleries_cannot_be_deleted_custom_ones_can(): void
    {
        $user = User::factory()->admin()->create();
        $system = Gallery::findBySlug(Gallery::HOME_BOTTOM);
        $custom = Gallery::factory()->create();
        GalleryItem::factory()->for($custom)->create();

        $this->actingAs($user)->delete(route('admin.galleries.destroy', $system))->assertForbidden();
        $this->assertModelExists($system);

        $this->actingAs($user)->delete(route('admin.galleries.destroy', $custom))->assertRedirect(route('admin.galleries.index'));
        $this->assertModelMissing($custom);
        $this->assertSame(0, GalleryItem::query()->count());
    }

    public function test_edit_page_returns_items_with_resolved_media(): void
    {
        $gallery = Gallery::findBySlug(Gallery::HOME_TOP);
        $item = GalleryItem::factory()->for($gallery)->create();
        $item->tags()->attach(Tag::factory()->create(['name' => 'Nastava']));

        $this->actingAs(User::factory()->create())
            ->get(route('admin.galleries.edit', $gallery))
            ->assertInertia(fn (Assert $p) => $p->component('admin/galleries/Edit')
                ->where('gallery.items.0.media.id', $item->media_id)
                ->where('gallery.items.0.tags', ['Nastava'])
                ->where('gallery.can_delete', false)
                ->where('allTags', ['Nastava'])
                ->has('upload.recommended.slider'));
    }
}
