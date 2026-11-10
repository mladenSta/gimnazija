<?php

namespace Tests\Feature\Models;

use App\Models\AlumniTestimonial;
use App\Models\GalleryItem;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unused_media_is_not_in_use_and_can_be_deleted(): void
    {
        $media = Media::factory()->create();

        $this->assertFalse($media->isInUse());
        $media->delete();
        $this->assertModelMissing($media);
    }

    public function test_featured_image_counts_as_in_use(): void
    {
        $media = Media::factory()->create();
        Post::factory()->create(['featured_media_id' => $media->id]);

        $this->assertTrue($media->isInUse());
    }

    public function test_featured_image_of_trashed_post_still_counts(): void
    {
        $media = Media::factory()->create();
        Page::factory()->create(['featured_media_id' => $media->id])->delete();

        $this->assertTrue($media->isInUse());
    }

    public function test_gallery_and_inline_attachments_count_as_in_use(): void
    {
        $gallery = Media::factory()->create();
        $inline = Media::factory()->create();
        $post = Post::factory()->create();
        $post->syncGalleryImages([$gallery->id]);
        $post->syncInlineImages([$inline->id]);

        $this->assertTrue($gallery->isInUse());
        $this->assertTrue($inline->isInUse());
    }

    public function test_gallery_item_and_alumni_photo_count_as_in_use(): void
    {
        $inGallery = Media::factory()->create();
        $alumniPhoto = Media::factory()->create();
        GalleryItem::factory()->create(['media_id' => $inGallery->id]);
        AlumniTestimonial::factory()->create(['photo_media_id' => $alumniPhoto->id]);

        $this->assertTrue($inGallery->isInUse());
        $this->assertTrue($alumniPhoto->isInUse());
    }

    public function test_database_blocks_deleting_media_that_is_in_use(): void
    {
        $media = Media::factory()->create();
        Post::factory()->create(['featured_media_id' => $media->id]);

        $this->expectException(QueryException::class);
        $media->delete();
    }

    public function test_url_falls_back_to_original_when_variant_is_missing(): void
    {
        $media = Media::factory()->create(['path' => '2026/11/slika.webp', 'variants' => null]);

        $this->assertStringEndsWith('/wp-content/uploads/2026/11/slika.webp', $media->url('large'));
        $this->assertSame('', $media->srcset());
    }

    public function test_srcset_lists_variants_by_width(): void
    {
        $media = Media::factory()->create();

        $this->assertMatchesRegularExpression('#-400\.webp 400w, .*-800\.webp 800w, .*-1600\.webp 1600w$#', $media->srcset());
    }
}
