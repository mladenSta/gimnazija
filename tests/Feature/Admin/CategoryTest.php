<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_manage_categories(): void
    {
        $category = Category::factory()->create();

        foreach ([User::factory()->editor()->create(), User::factory()->author()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.categories.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.categories.store'), ['name' => 'X'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.categories.destroy', $category))->assertForbidden();
        }

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/categories/Index')->has('categories', 1));
    }

    public function test_admin_creates_category_with_transliterated_slug(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.categories.store'), ['name' => 'Računarski smjer', 'slug' => ''])
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', ['name' => 'Računarski smjer', 'slug' => 'racunarski-smjer']);
    }

    public function test_duplicate_slug_is_rejected(): void
    {
        Category::factory()->create(['slug' => 'vijesti']);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.categories.store'), ['name' => 'Vijesti'])
            ->assertSessionHasErrors(['slug' => 'Ovaj link već koristi druga kategorija.']);
    }

    public function test_category_cannot_become_its_own_ancestor(): void
    {
        $parent = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $parent->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.categories.update', $parent), ['name' => $parent->name, 'slug' => $parent->slug, 'parent_id' => $child->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_deleting_a_category_keeps_its_posts(): void
    {
        $category = Category::factory()->create();
        $post = Post::factory()->create();
        $post->syncCategories([$category->id]);

        $this->actingAs(User::factory()->admin()->create())
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect();

        $this->assertModelMissing($category);
        $this->assertModelExists($post);
        $this->assertSame(0, $post->categories()->count());
    }
}
