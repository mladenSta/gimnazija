<?php

namespace App\Enums;

enum MenuItemType: string
{
    case Page = 'page';
    case Category = 'category';
    case Custom = 'custom';

    /** Link to the all-news archive (/vijesti/). */
    case PostsIndex = 'posts-index';

    public function label(): string
    {
        return match ($this) {
            self::Page => 'Stranica',
            self::Category => 'Kategorija',
            self::Custom => 'Prilagođeni link',
            self::PostsIndex => 'Sve vijesti',
        };
    }
}
