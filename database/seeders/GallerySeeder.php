<?php

namespace Database\Seeders;

use App\Enums\GalleryType;
use App\Models\Gallery;
use Illuminate\Database\Seeder;

/**
 * The system galleries used on the home page. Safe to re-run.
 */
class GallerySeeder extends Seeder
{
    public function run(): void
    {
        $galleries = [
            Gallery::HOME_SLIDER => ['name' => 'Početna – slajder', 'type' => GalleryType::Slider],
            Gallery::HOME_TOP => ['name' => 'Početna – gore', 'type' => GalleryType::Standard],
            Gallery::HOME_ABOUT => ['name' => 'Početna – o školi', 'type' => GalleryType::Standard],
            Gallery::HOME_BOTTOM => ['name' => 'Početna – dolje', 'type' => GalleryType::Standard],
        ];

        foreach ($galleries as $slug => $attributes) {
            Gallery::query()->updateOrCreate(
                ['slug' => $slug],
                [...$attributes, 'is_system' => true],
            );
        }
    }
}
