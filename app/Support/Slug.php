<?php

namespace App\Support;

use Illuminate\Support\Str;

class Slug
{
    /**
     * URL slug with Serbian transliteration: č/ć→c, š→s, ž→z, đ→dj, dž→dz.
     */
    public static function make(string $value): string
    {
        return Str::slug($value, '-', 'sr');
    }
}
