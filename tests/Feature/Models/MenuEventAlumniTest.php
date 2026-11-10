<?php

namespace Tests\Feature\Models;

use App\Enums\MenuItemType;
use App\Models\AlumniTestimonial;
use App\Models\Category;
use App\Models\Event;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuEventAlumniTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_tree_with_submenu_and_label_fallbacks(): void
    {
        $page = Page::factory()->create(['title' => 'Istorijat škole']);
        $category = Category::factory()->create(['name' => 'Računarski smjer']);

        $about = MenuItem::factory()->create(['label' => 'O nama', 'url' => null, 'sort_order' => 1]);
        MenuItem::factory()->forPage($page)->create(['parent_id' => $about->id, 'sort_order' => 2]);
        MenuItem::factory()->forCategory($category)->create(['parent_id' => $about->id, 'sort_order' => 1]);
        MenuItem::factory()->create(['type' => MenuItemType::PostsIndex, 'label' => null, 'url' => null, 'sort_order' => 0]);

        $roots = MenuItem::query()->roots()->with('children.page', 'children.category')->get();

        $this->assertSame(['Vijesti', 'O nama'], $roots->map->display_label->all());
        $this->assertSame(
            ['Računarski smjer', 'Istorijat škole'],
            $roots[1]->children->map->display_label->all(),
        );
    }

    public function test_deleting_a_page_removes_its_menu_item_and_children_follow_parent(): void
    {
        $page = Page::factory()->create();
        $parent = MenuItem::factory()->forPage($page)->create();
        MenuItem::factory()->create(['parent_id' => $parent->id]);

        $page->forceDelete();

        $this->assertSame(0, MenuItem::query()->count());
    }

    public function test_upcoming_events_exclude_past_and_unpublished_and_are_sorted(): void
    {
        $later = Event::factory()->create(['date' => today()->addDays(10)]);
        $today = Event::factory()->create(['date' => today()]);
        Event::factory()->past()->create();
        Event::factory()->unpublished()->create();

        $this->assertSame([$today->id, $later->id], Event::query()->upcoming()->pluck('id')->all());
    }

    public function test_published_alumni_ordered_by_sort_order(): void
    {
        $b = AlumniTestimonial::factory()->create(['sort_order' => 2]);
        $a = AlumniTestimonial::factory()->create(['sort_order' => 1]);
        AlumniTestimonial::factory()->create(['is_published' => false]);

        $this->assertSame([$a->id, $b->id], AlumniTestimonial::query()->published()->pluck('id')->all());
    }
}
