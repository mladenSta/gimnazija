<?php

namespace Database\Factories;

use App\Models\Category;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word()).' '.fake()->word();

        return [
            'name' => $name,
            'slug' => Slug::make($name),
            'description' => null,
        ];
    }
}
