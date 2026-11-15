<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class VisitCounterTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    /**
     * @param  array<string, string>  $headers
     */
    private function visit(string $uri, string $agent = self::CHROME, array $headers = []): void
    {
        $this->withHeaders(['User-Agent' => $agent, 'Accept' => 'text/html,application/xhtml+xml,*/*;q=0.8', ...$headers])
            ->get($uri);
    }

    /**
     * @return array{visitors: int, page_views: int}
     */
    private function today(): array
    {
        $row = DB::table('daily_visits')->where('date', now()->toDateString())->first();

        return ['visitors' => (int) ($row->visitors ?? 0), 'page_views' => (int) ($row->page_views ?? 0)];
    }

    public function test_counts_page_views_and_unique_visitors_per_day(): void
    {
        $post = Post::factory()->create();

        $this->visit('/');
        $this->visit($post->path());
        $this->visit('/', self::IPHONE);

        $this->assertSame(['visitors' => 2, 'page_views' => 3], $this->today());
        $this->assertSame(2, DB::table('visit_hashes')->count());
        // Only anonymous codes are stored, never the IP or browser.
        $this->assertSame(64, strlen((string) DB::table('visit_hashes')->value('hash')));
    }

    public function test_the_same_visitor_counts_again_the_next_day(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        $this->visit('/');
        $this->travel(1)->day();
        $this->visit('/');

        $this->assertSame(2, (int) DB::table('daily_visits')->sum('visitors'));
        $this->assertSame(['visitors' => 1, 'page_views' => 1], $this->today());
    }

    public function test_bots_previews_staff_errors_feeds_and_prefetch_are_not_counted(): void
    {
        $this->visit('/', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');
        $this->visit('/', 'facebookexternalhit/1.1');
        $this->visit('/', 'Viber/22.0');
        $this->visit('/', 'curl/8.4.0');
        $this->visit('/', '');
        $this->visit('/', self::CHROME, ['Sec-Purpose' => 'prefetch']);
        $this->visit('/', self::CHROME, ['Accept' => 'image/webp,*/*']);
        $this->visit('/nema-ove-stranice/');
        $this->visit('/feed/');

        $this->actingAs(User::factory()->create());
        $this->visit('/');

        $this->assertSame(0, DB::table('daily_visits')->count());
    }

    public function test_dashboard_shows_periods_daily_chart_and_months(): void
    {
        $today = now()->startOfDay();
        DB::table('daily_visits')->insert([
            ['date' => $today->toDateString(), 'visitors' => 30, 'page_views' => 80],
            ['date' => $today->copy()->subDay()->toDateString(), 'visitors' => 20, 'page_views' => 50],
            ['date' => $today->copy()->subDays(40)->toDateString(), 'visitors' => 5, 'page_views' => 9],
        ]);

        $this->actingAs(User::factory()->author()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Dashboard')
                ->where('visits.since', $today->copy()->subDays(40)->format('d.m.Y.'))
                ->where('visits.periods.0.visitors', 30)
                ->where('visits.periods.0.page_views', 80)
                ->where('visits.periods.0.change', 50) // 30 vs 20 yesterday
                ->where('visits.periods.1.visitors', 50)
                ->where('visits.periods.2.visitors', 50)
                ->has('visits.daily', 30)
                ->where('visits.daily.29.visitors', 30)
                ->where('visits.daily.28.visitors', 20)
                ->where('visits.daily.0.visitors', 0)
                ->has('visits.months', $today->month));
    }

    public function test_no_comparison_before_the_counter_ran_for_the_previous_period(): void
    {
        DB::table('daily_visits')->insert(['date' => now()->toDateString(), 'visitors' => 3, 'page_views' => 4]);

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('visits.periods.0.change', null)
                ->where('visits.periods.1.change', null));
    }
}
