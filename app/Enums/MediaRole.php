<?php

namespace App\Enums;

/**
 * How a media item is attached to a post or page (mediables.role).
 */
enum MediaRole: string
{
    /** "Other images" shown as a lightbox gallery below the content. */
    case Gallery = 'gallery';

    /** Image used inside the content HTML (tracked so it can't be deleted). */
    case Inline = 'inline';
}
