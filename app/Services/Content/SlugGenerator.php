<?php

namespace App\Services\Content;

use App\Models\Page;
use App\Models\Post;
use App\Support\ReservedSlugs;
use App\Support\Slug;
use Illuminate\Database\Eloquent\Model;

/**
 * Posts and pages share one URL space (/{slug}/), so a slug must be unique
 * across both tables (including trashed items) and not a reserved word.
 */
class SlugGenerator
{
    public function isTaken(string $slug, ?Model $ignore = null): bool
    {
        if (ReservedSlugs::contains($slug)) {
            return true;
        }

        foreach ([Post::class, Page::class] as $class) {
            $query = $class::withTrashed()->where('slug', $slug);

            if ($ignore instanceof $class && $ignore->exists) {
                $query->whereKeyNot($ignore->getKey());
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Slug from a title, with -2, -3… appended when taken (WordPress style).
     */
    public function unique(string $title, ?Model $ignore = null): string
    {
        $base = Slug::make($title);
        $base = substr($base !== '' ? $base : 'bez-naslova', 0, 190);

        $slug = $base;
        $n = 2;
        while ($this->isTaken($slug, $ignore)) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }
}
