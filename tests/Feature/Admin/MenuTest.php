<?php

namespace Tests\Feature\Admin;

use App\Enums\MenuItemType;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_and_every_role_can_edit_the_menu(): void
    {
        $this->get(route('admin.menu.edit'))->assertRedirect(route('login'));

        foreach ([User::factory()->admin()->create(), User::factory()->editor()->create(), User::factory()->author()->create()] as $user) {
            $this->actingAs($user)->get(route('admin.menu.edit'))->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('admin/menu/Edit')->where('maxDepth', 2));
            $this->actingAs($user)->put(route('admin.menu.update'), ['items' => []])->assertRedirect(route('admin.menu.edit'));
        }
    }

    public function test_saves_a_tree_with_submenu_in_order(): void
    {
        $history = Page::factory()->create(['title' => 'Istorijat škole', 'slug' => 'istorijat-skole']);
        $contact = Page::factory()->create(['title' => 'Kontakt', 'slug' => 'kontakt']);
        $category = Category::factory()->create(['name' => 'Računarski smjer', 'slug' => 'racunarski-smjer']);

        $this->actingAs(User::factory()->create())->put(route('admin.menu.update'), ['items' => [
            ['type' => 'custom', 'label' => 'O nama', 'url' => null, 'children' => [
                ['type' => 'page', 'page_id' => $history->id],
                ['type' => 'category', 'category_id' => $category->id],
            ]],
            ['type' => 'posts-index', 'label' => null, 'children' => []],
            ['type' => 'page', 'page_id' => $contact->id, 'label' => 'Kontaktirajte nas', 'children' => []],
        ]])->assertRedirect();

        $roots = MenuItem::query()->roots()->with('page', 'category', 'children.page', 'children.category')->get();

        $this->assertSame(['O nama', 'Vijesti', 'Kontaktirajte nas'], $roots->map->display_label->all());
        $this->assertSame([null, '/vijesti/', '/kontakt/'], $roots->map->href->all());
        $this->assertSame(['Istorijat škole', 'Računarski smjer'], $roots[0]->children->map->display_label->all());
        $this->assertSame(['/istorijat-skole/', '/category/racunarski-smjer/'], $roots[0]->children->map->href->all());
    }

    public function test_existing_items_are_updated_moved_and_missing_ones_deleted(): void
    {
        $page = Page::factory()->create();
        $parent = MenuItem::factory()->create(['label' => 'O nama', 'url' => null, 'sort_order' => 0]);
        $child = MenuItem::factory()->forPage($page)->create(['parent_id' => $parent->id]);
        $removed = MenuItem::factory()->create(['label' => 'Staro', 'sort_order' => 1]);

        // Move the child to the top level, before its former parent; rename the parent; drop "Staro".
        $this->actingAs(User::factory()->create())->put(route('admin.menu.update'), ['items' => [
            ['id' => $child->id, 'type' => 'page', 'page_id' => $page->id, 'children' => []],
            ['id' => $parent->id, 'type' => 'custom', 'label' => 'Škola', 'url' => null, 'children' => []],
        ]])->assertRedirect();

        $this->assertModelMissing($removed);
        $this->assertSame([$child->id, $parent->id], MenuItem::query()->roots()->pluck('id')->all());
        $this->assertNull($child->fresh()?->parent_id);
        $this->assertSame('Škola', $parent->fresh()?->label);
        $this->assertSame(2, MenuItem::query()->count());
    }

    public function test_third_level_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())->put(route('admin.menu.update'), ['items' => [
            ['type' => 'custom', 'label' => 'A', 'children' => [
                ['type' => 'custom', 'label' => 'B', 'children' => [
                    ['type' => 'custom', 'label' => 'C'],
                ]],
            ]],
        ]])->assertSessionHasErrors('items.0.children.0.children');

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_item_validation(): void
    {
        $user = User::factory()->create();
        $missingPageId = (int) Page::query()->withTrashed()->max('id') + 1000;

        $this->actingAs($user)->put(route('admin.menu.update'), ['items' => [
            ['type' => 'page', 'page_id' => $missingPageId],
            ['type' => 'custom', 'label' => '', 'url' => '/kontakt/'],
            ['type' => 'custom', 'label' => 'Loš link', 'url' => 'javascript:alert(1)'],
            ['type' => 'nesto', 'label' => 'X'],
        ]])->assertSessionHasErrors([
            'items.0.page_id' => 'Stranica više ne postoji.',
            'items.1.label' => 'Unesite naziv stavke.',
            'items.2.url',
            'items.3.type',
        ]);
    }

    public function test_edit_page_lists_items_with_warning_for_unpublished_page(): void
    {
        $draft = Page::factory()->draft()->create(['title' => 'U pripremi']);
        MenuItem::factory()->forPage($draft)->create();

        $this->actingAs(User::factory()->create())
            ->get(route('admin.menu.edit'))
            ->assertInertia(fn (Assert $p) => $p
                ->has('items', 1)
                ->where('items.0.type', MenuItemType::Page->value)
                ->where('items.0.original_label', 'U pripremi')
                ->where('items.0.warning', 'Stranica nije objavljena – stavka se ne prikazuje.')
                ->has('pages', 1));
    }
}
