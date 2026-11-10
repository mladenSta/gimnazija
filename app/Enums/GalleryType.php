<?php

namespace App\Enums;

enum GalleryType: string
{
    /** Hero slider: each item also has badge, title, subtitle and a button. */
    case Slider = 'slider';

    /** Photos with captions and tags. */
    case Standard = 'standard';

    public function label(): string
    {
        return match ($this) {
            self::Slider => 'Slajder',
            self::Standard => 'Galerija',
        };
    }
}
