<?php

namespace Tests\Feature\Models;

use App\Enums\GalleryType;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Tag;
use Database\Seeders\GallerySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_three_system_galleries_idempotently(): void
    {
        $this->seed(GallerySeeder::class);
        $this->seed(GallerySeeder::class);

        $this->assertSame(3, Gallery::query()->count());
        $this->assertSame(GalleryType::Slider, Gallery::findBySlug(Gallery::HOME_SLIDER)?->type);
        $this->assertSame(GalleryType::Standard, Gallery::findBySlug(Gallery::HOME_BOTTOM)?->type);
        $this->assertTrue(Gallery::query()->where('is_system', false)->doesntExist());
    }

    public function test_system_gallery_cannot_be_deleted(): void
    {
        $this->seed(GallerySeeder::class);

        $this->expectException(LogicException::class);
        Gallery::findBySlug(Gallery::HOME_TOP)?->delete();
    }

    public function test_deleting_a_custom_gallery_removes_its_items(): void
    {
        $gallery = Gallery::factory()->create();
        GalleryItem::factory()->count(2)->for($gallery)->create();

        $gallery->delete();

        $this->assertSame(0, GalleryItem::query()->count());
    }

    public function test_items_are_ordered_and_have_tags_and_focal_point(): void
    {
        $gallery = Gallery::factory()->create();
        $second = GalleryItem::factory()->for($gallery)->create(['sort_order' => 2, 'focal_x' => 30, 'focal_y' => 70]);
        $first = GalleryItem::factory()->for($gallery)->create(['sort_order' => 1]);
        $tag = Tag::factory()->create(['name' => 'Ekskurzije', 'slug' => 'ekskurzije']);
        $second->tags()->attach($tag);

        $this->assertSame([$first->id, $second->id], $gallery->items()->pluck('id')->all());
        $this->assertSame('30% 70%', $second->object_position);
        $this->assertSame(1, $tag->galleryItems()->count());
    }
}
