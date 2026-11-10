<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->sentence(4), '.'),
            'short_description' => fake()->sentence(10),
            'date' => today()->addDays(fake()->numberBetween(1, 60)),
            'time' => fake()->optional()->randomElement(['10:00', '12:00', '17:00', '18:00']),
            'location' => fake()->optional()->randomElement(['Svečana sala', 'Hol škole', 'Kabinet informatike']),
            'label' => fake()->optional()->randomElement(['Takmičenje', 'Sekcije', 'Roditelji', 'Muzika']),
            'is_published' => true,
        ];
    }

    public function past(): static
    {
        return $this->state(fn () => ['date' => today()->subDays(5)]);
    }

    public function unpublished(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }
}
