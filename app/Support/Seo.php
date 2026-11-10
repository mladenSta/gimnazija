<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

/**
 * Meta data for public pages (title, description, canonical, Open Graph, JSON-LD).
 */
class Seo
{
    public const SITE_NAME = 'Gimnazija „Jovan Dučić" Doboj';

    public const DEFAULT_DESCRIPTION = 'JU Gimnazija „Jovan Dučić" Doboj – škola sa najdužom vaspitno-obrazovnom tradicijom u Doboju. Opšti, društveno-jezički i računarsko-informatički smjer.';

    /**
     * @param  array<string, mixed>|null  $jsonLd
     * @return array{title: string, description: string, canonical: string, image: string|null, type: string, noindex: bool, published_time: string|null, json_ld: array<string, mixed>|null}
     */
    public static function make(
        ?string $title = null,
        ?string $description = null,
        ?string $path = null,
        ?string $image = null,
        string $type = 'website',
        bool $noindex = false,
        ?string $publishedTime = null,
        ?array $jsonLd = null,
    ): array {
        $base = rtrim((string) config('app.url'), '/');

        return [
            'title' => $title ? "{$title} – ".self::SITE_NAME : self::SITE_NAME,
            'description' => trim((string) ($description ?: self::DEFAULT_DESCRIPTION)),
            'canonical' => $base.($path ?? '/'),
            'image' => $image,
            'type' => $type,
            'noindex' => $noindex,
            'published_time' => $publishedTime,
            'json_ld' => $jsonLd,
        ];
    }

    public static function url(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }

    /**
     * 301/302 to a site path, keeping the trailing slash
     * (Laravel's redirect()/url() helpers would strip it).
     */
    public static function redirect(string $path, int $status = 301): RedirectResponse
    {
        $target = preg_match('#^https?://#i', $path) ? $path : request()->getSchemeAndHttpHost().'/'.ltrim($path, '/');

        return new RedirectResponse($target, $status);
    }
}
