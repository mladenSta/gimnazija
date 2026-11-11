<?php

namespace Tests\Unit;

use App\Support\YouTube;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class YouTubeTest extends TestCase
{
    /**
     * @return array<string, array{string, string|null}>
     */
    public static function links(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=6ixsPLXRSpY', '6ixsPLXRSpY'],
            'watch with time and list' => ['https://www.youtube.com/watch?v=6ixsPLXRSpY&t=42s&list=PL123', '6ixsPLXRSpY'],
            'short link' => ['https://youtu.be/mW7KCrpX1Fk?si=abc', 'mW7KCrpX1Fk'],
            'mobile' => ['https://m.youtube.com/watch?v=CdQzD-nRPhQ', 'CdQzD-nRPhQ'],
            'shorts' => ['https://youtube.com/shorts/hLU7bkIyxtU', 'hLU7bkIyxtU'],
            'embed' => ['https://www.youtube-nocookie.com/embed/hLU7bkIyxtU', 'hLU7bkIyxtU'],
            'without https' => ['www.youtube.com/watch?v=6ixsPLXRSpY', '6ixsPLXRSpY'],
            'other site' => ['https://vimeo.com/123456', null],
            'fake host' => ['https://youtube.com.evil.example/watch?v=6ixsPLXRSpY', null],
            'channel' => ['https://www.youtube.com/@gimnazija', null],
            'empty' => ['', null],
        ];
    }

    #[DataProvider('links')]
    public function test_video_id_from_link(string $url, ?string $id): void
    {
        $this->assertSame($id, YouTube::id($url));
    }
}
