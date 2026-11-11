<?php

namespace App\Models\Concerns;

use App\Support\YouTube;

/**
 * Up to two YouTube videos (video_url_1, video_url_2), shown below the text.
 */
trait HasVideos
{
    /**
     * YouTube IDs of the filled-in, valid video links, in order.
     *
     * @return list<string>
     */
    public function videoIds(): array
    {
        return array_values(array_filter([
            YouTube::id($this->video_url_1),
            YouTube::id($this->video_url_2),
        ]));
    }
}
