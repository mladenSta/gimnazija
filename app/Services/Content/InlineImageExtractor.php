<?php

namespace App\Services\Content;

use App\Models\Media;
use Illuminate\Support\Facades\Storage;

/**
 * Finds media-library images used inside content HTML, so they can be
 * tracked (and protected from deletion).
 */
class InlineImageExtractor
{
    /**
     * @return list<int>
     */
    public function mediaIds(?string $html): array
    {
        if ($html === null || ! preg_match_all('/<img[^>]+src="([^"]+)"/i', $html, $matches)) {
            return [];
        }

        $prefix = '/'.trim((string) parse_url(Storage::disk(config('cms.images.disk'))->url(''), PHP_URL_PATH), '/').'/';

        $paths = [];
        foreach ($matches[1] as $src) {
            $path = (string) parse_url(html_entity_decode($src), PHP_URL_PATH);

            if (str_starts_with($path, $prefix)) {
                $paths[] = rawurldecode(substr($path, strlen($prefix)));
            }
        }

        if ($paths === []) {
            return [];
        }

        $paths = array_values(array_unique($paths));

        // The <img> may point at the main file or at one of its variants.
        $ids = Media::query()
            ->whereIn('path', $paths)
            ->orWhere(function ($query) use ($paths) {
                foreach ($paths as $path) {
                    $query->orWhereJsonContains('variants->thumb->path', $path)
                        ->orWhereJsonContains('variants->medium->path', $path)
                        ->orWhereJsonContains('variants->large->path', $path);
                }
            })
            ->pluck('id')
            ->all();

        return array_values(array_map('intval', $ids));
    }
}
