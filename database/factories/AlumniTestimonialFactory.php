<?php

namespace Database\Factories;

use App\Models\AlumniTestimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlumniTestimonial>
 */
class AlumniTestimonialFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'generation' => fake()->numberBetween(1980, 2024),
            'profession' => fake()->optional()->jobTitle(),
            'quote' => fake()->paragraph(),
            'photo_media_id' => null,
            'sort_order' => 0,
            'is_published' => true,
        ];
    }
}
