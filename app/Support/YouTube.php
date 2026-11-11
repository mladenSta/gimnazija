<?php

namespace App\Support;

/**
 * YouTube links → video ID / embed URL.
 *
 * Accepts the usual forms people paste:
 *   https://www.youtube.com/watch?v=ID (with extra &t=…, &list=…)
 *   https://youtu.be/ID
 *   https://www.youtube.com/shorts/ID, /live/ID, /embed/ID
 *   https://m.youtube.com/…, https://www.youtube-nocookie.com/embed/ID
 */
class YouTube
{
    public static function id(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = "https://{$url}";
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = (string) preg_replace('/^(www|m|music)\./', '', $host);
        $path = (string) parse_url($url, PHP_URL_PATH);

        $id = null;

        if ($host === 'youtu.be') {
            $id = explode('/', trim($path, '/'))[0];
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            if (preg_match('#^/(?:shorts|live|embed|v)/([^/?]+)#', $path, $m)) {
                $id = $m[1];
            } elseif ($path === '/watch') {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }

    /**
     * Privacy-friendly embed (no cookies until the video is played).
     */
    public static function embedUrl(string $id): string
    {
        return "https://www.youtube-nocookie.com/embed/{$id}?rel=0";
    }
}
