<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryItem>
 */
class GalleryItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'media_id' => Media::factory(),
            'caption' => fake()->optional()->words(3, true),
            'sort_order' => 0,
            'focal_x' => 50,
            'focal_y' => 50,
        ];
    }

    public function slide(): static
    {
        return $this->state(fn () => [
            'badge' => 'Upis 2026.',
            'title' => fake()->sentence(4),
            'subtitle' => fake()->sentence(12),
            'link_label' => 'Saznaj više',
            'link_url' => '/upis-2020/',
        ]);
    }
}
