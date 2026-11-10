<?php

namespace App\Services\Content;

use App\Models\Redirect;

/**
 * Keeps old URLs working (301) when a published post/page changes its slug.
 */
class SlugRedirector
{
    public function slugChanged(string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === $newSlug) {
            return;
        }

        $from = "/{$oldSlug}/";
        $to = "/{$newSlug}/";

        // The new URL is live again, so it must not redirect anywhere.
        Redirect::query()->where('from_path', $to)->delete();

        // Avoid chains: anything that pointed at the old URL now points at the new one.
        Redirect::query()->where('to_path', $from)->update(['to_path' => $to]);

        Redirect::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301]);
    }
}
