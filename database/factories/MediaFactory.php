<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Media rows without real files (for tests). Use the upload service for real images.
 *
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->slug(3);
        $dir = now()->format('Y/m');

        return [
            'user_id' => null,
            'disk' => 'uploads',
            'path' => "{$dir}/{$name}.webp",
            'original_name' => "{$name}.jpg",
            'mime' => 'image/webp',
            'width' => 1920,
            'height' => 1080,
            'size' => fake()->numberBetween(80_000, 600_000),
            'alt' => null,
            'caption' => null,
            'variants' => [
                'thumb' => ['path' => "{$dir}/{$name}-400.webp", 'width' => 400, 'height' => 225],
                'medium' => ['path' => "{$dir}/{$name}-800.webp", 'width' => 800, 'height' => 450],
                'large' => ['path' => "{$dir}/{$name}-1600.webp", 'width' => 1600, 'height' => 900],
            ],
        ];
    }
}
