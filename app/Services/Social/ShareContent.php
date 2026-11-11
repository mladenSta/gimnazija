<?php

namespace App\Services\Social;

use App\Models\Post;
use Illuminate\Support\Str;

/**
 * Text and link of a post as it goes to the networks.
 */
final class ShareContent
{
    public static function url(Post $post): string
    {
        return rtrim((string) config('app.url'), '/').$post->path();
    }

    public static function title(Post $post): string
    {
        return trim(html_entity_decode($post->title, ENT_QUOTES | ENT_HTML5));
    }

    /**
     * Title, blank line, summary.
     */
    public static function text(Post $post, int $summaryLength): string
    {
        $summary = $post->summary($summaryLength);
        $title = self::title($post);

        return $summary !== '' && ! Str::startsWith($summary, $title)
            ? "{$title}\n\n{$summary}"
            : ($summary !== '' ? $summary : $title);
    }
}
