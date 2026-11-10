<?php

namespace Database\Factories;

use App\Enums\MenuItemType;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'parent_id' => null,
            'sort_order' => 0,
            'type' => MenuItemType::Custom,
            'url' => '/kontakt/',
            'label' => 'Kontakt',
        ];
    }

    public function forPage(?Page $page = null): static
    {
        return $this->state(fn () => [
            'type' => MenuItemType::Page,
            'page_id' => $page->id ?? Page::factory(),
            'url' => null,
            'label' => null,
        ]);
    }

    public function forCategory(?Category $category = null): static
    {
        return $this->state(fn () => [
            'type' => MenuItemType::Category,
            'category_id' => $category->id ?? Category::factory(),
            'url' => null,
            'label' => null,
        ]);
    }
}
