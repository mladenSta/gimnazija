<?php

namespace App\Support;

use App\Models\Redirect;

class RedirectResolver
{
    /**
     * Stored redirect for a request path (with or without trailing slash).
     */
    public static function find(string $path): ?Redirect
    {
        $path = '/'.trim(rawurldecode($path), '/');
        $candidates = array_unique([$path, rtrim($path, '/').'/']);

        return Redirect::query()->whereIn('from_path', $candidates)->first();
    }
}
