<?php

namespace Database\Factories;

use App\Enums\GalleryType;
use App\Models\Gallery;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
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
            'type' => GalleryType::Standard,
            'is_system' => false,
        ];
    }

    public function slider(): static
    {
        return $this->state(fn () => ['type' => GalleryType::Slider]);
    }
}
