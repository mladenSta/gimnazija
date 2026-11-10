<?php

namespace App\Support;

/**
 * Top-level paths that can't be used as post/page slugs (/{slug}/).
 */
class ReservedSlugs
{
    public const LIST = [
        'admin', 'api', 'category', 'confirm-password', 'dashboard', 'email', 'feed', 'forgot-password',
        'login', 'logout', 'page', 'passkeys', 'register', 'reset-password', 'search', 'settings',
        'sitemap', 'sitemap-xml', 'storage', 'tag', 'two-factor', 'two-factor-challenge', 'up',
        'user', 'vijesti', 'wp-admin', 'wp-content', 'wp-includes', 'wp-json', 'wp-login',
    ];

    public static function contains(string $slug): bool
    {
        return in_array($slug, self::LIST, true);
    }
}
