<?php

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Models\Page;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(4), '.');

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Slug::make($title),
            'content' => '<p>'.fake()->paragraph().'</p><p>'.fake()->paragraph().'</p><p>'.fake()->paragraph().'</p>',
            'status' => ContentStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 60)),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => ContentStatus::Draft, 'published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => ['status' => ContentStatus::Published, 'published_at' => now()->addDays(3)]);
    }
}
