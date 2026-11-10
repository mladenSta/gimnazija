<?php

namespace Tests\Unit;

use App\Support\Slug;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SlugTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function titles(): array
    {
        return [
            'č ć š ž' => ['Čas šaha i žurka ćirilice', 'cas-saha-i-zurka-cirilice'],
            'đ → dj' => ['Đački parlament', 'djacki-parlament'],
            'dž → dz' => ['Džudo turnir', 'dzudo-turnir'],
            'punctuation and numbers' => ['Upis 2026. – sve informacije!', 'upis-2026-sve-informacije'],
        ];
    }

    #[DataProvider('titles')]
    public function test_slug_transliterates_serbian_latin(string $title, string $expected): void
    {
        $this->assertSame($expected, Slug::make($title));
    }
}
