<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostAndPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_belongs_to_author(): void
    {
        $user = User::factory()->create();
        $post = Post::factory()->for($user, 'author')->create();

        $this->assertTrue($post->author->is($user));
        $this->assertSame(1, $user->posts()->count());
    }

    public function test_post_keeps_category_order_and_first_is_primary(): void
    {
        $post = Post::factory()->create();
        [$a, $b, $c] = Category::factory()->count(3)->create();

        $post->syncCategories([$c->id, $a->id, $b->id]);

        $post->load('categories');
        $this->assertSame([$c->id, $a->id, $b->id], $post->categories->pluck('id')->all());
        $this->assertTrue($post->primary_category?->is($c));
        $this->assertSame(1, $a->posts()->count());
    }

    public function test_deleting_category_detaches_it_from_posts(): void
    {
        $post = Post::factory()->create();
        $category = Category::factory()->create();
        $post->syncCategories([$category->id]);

        $category->delete();

        $this->assertSame(0, $post->categories()->count());
    }

    public function test_published_scope_hides_drafts_and_scheduled(): void
    {
        $published = Post::factory()->create();
        $draft = Post::factory()->draft()->create();
        $scheduled = Post::factory()->scheduled()->create();

        $this->assertSame([$published->id], Post::query()->published()->pluck('id')->all());
        $this->assertSame([$scheduled->id], Post::query()->scheduled()->pluck('id')->all());
        $this->assertSame([$draft->id], Post::query()->drafts()->pluck('id')->all());

        $this->assertTrue($published->isPublished());
        $this->assertFalse($scheduled->isPublished());
        $this->assertTrue($scheduled->isScheduled());
        $this->assertFalse($draft->isPublished());
    }

    public function test_scheduled_post_becomes_published_when_its_time_comes(): void
    {
        $post = Post::factory()->scheduled()->create();

        $this->travel(4)->days();

        $this->assertTrue(Post::query()->published()->whereKey($post->id)->exists());
    }

    public function test_gallery_images_keep_order_and_are_separate_from_inline_images(): void
    {
        $page = Page::factory()->create();
        [$m1, $m2, $m3] = Media::factory()->count(3)->create();

        $page->syncGalleryImages([$m3->id, $m1->id]);
        $page->syncInlineImages([$m2->id]);

        $this->assertSame([$m3->id, $m1->id], $page->galleryImages()->pluck('media.id')->all());
        $this->assertSame([$m2->id], $page->inlineImages()->pluck('media.id')->all());

        // Re-sync replaces only the gallery role.
        $page->syncGalleryImages([$m1->id]);
        $this->assertSame([$m1->id], $page->galleryImages()->pluck('media.id')->all());
        $this->assertSame([$m2->id], $page->inlineImages()->pluck('media.id')->all());
    }

    public function test_morph_map_stores_short_type_names(): void
    {
        $post = Post::factory()->create();
        $post->syncGalleryImages([Media::factory()->create()->id]);

        $this->assertSame('post', DB::table('mediables')->value('mediable_type'));
    }

    public function test_soft_delete_keeps_attachments_force_delete_removes_them(): void
    {
        $post = Post::factory()->create();
        $post->syncGalleryImages([Media::factory()->create()->id]);

        $post->delete();
        $this->assertSoftDeleted($post);
        $this->assertSame(1, DB::table('mediables')->count());

        $post->forceDelete();
        $this->assertSame(0, DB::table('mediables')->count());
    }

    public function test_featured_image_relation(): void
    {
        $media = Media::factory()->create();
        $page = Page::factory()->create(['featured_media_id' => $media->id]);

        $this->assertTrue($page->featuredImage?->is($media));
    }
}
