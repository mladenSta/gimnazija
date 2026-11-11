<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * System gallery "Početna – o školi" for the "Tradicija koja gleda naprijed"
     * collage (it used to take the first 3 photos of "Početna – gore").
     * Pre-filled with those 3 photos so the home page looks the same.
     */
    public function up(): void
    {
        if (DB::table('galleries')->where('slug', 'home-about')->exists()) {
            return;
        }

        $now = now();
        $aboutId = DB::table('galleries')->insertGetId([
            'name' => 'Početna – o školi',
            'slug' => 'home-about',
            'type' => 'standard',
            'is_system' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $topId = DB::table('galleries')->where('slug', 'home-top')->value('id');

        if ($topId === null) {
            return;
        }

        $items = DB::table('gallery_items')
            ->where('gallery_id', $topId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(3)
            ->get(['media_id', 'caption', 'focal_x', 'focal_y']);

        foreach ($items->values() as $position => $item) {
            DB::table('gallery_items')->insert([
                'gallery_id' => $aboutId,
                'media_id' => $item->media_id,
                'caption' => $item->caption,
                'focal_x' => $item->focal_x,
                'focal_y' => $item->focal_y,
                'sort_order' => $position,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $id = DB::table('galleries')->where('slug', 'home-about')->value('id');

        if ($id !== null) {
            DB::table('gallery_items')->where('gallery_id', $id)->delete();
            DB::table('galleries')->where('id', $id)->delete();
        }
    }
};
