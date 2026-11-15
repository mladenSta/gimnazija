<?php

namespace App\Services\Visits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cookie-free visit counter for the public site.
 *
 * Every counted page load adds a page view for today. A visitor is counted
 * once per day, recognised by sha256(date + APP_KEY + IP + browser). The code
 * can't be turned back into an IP, changes every day, and old days' codes
 * are deleted, so no personal data is kept.
 */
class VisitCounter
{
    /**
     * Crawlers, link previews (Viber, WhatsApp, Facebook…), monitors and scripts.
     */
    private const BOTS = '/bot|crawl|spider|slurp|archiver|facebookexternalhit|meta-externalagent|preview|fetch|scan|monitor|uptime|lighthouse|headless|phantom|selenium|playwright|puppeteer|curl|wget|python|java\/|go-http|okhttp|axios|node|guzzle|httpclient|libwww|perl|ruby|php|whatsapp|telegram|viber|discord|skype|slack|embedly|pinterest|bingpreview|yandex|baidu|semrush|ahrefs|mj12|dotbot|petal|bytespider|gptbot|claude|perplexity|ccbot/i';

    public function shouldCount(Request $request, Response $response): bool
    {
        $agent = (string) $request->userAgent();

        return $request->isMethod('GET')
            && $response->getStatusCode() === 200
            && str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')
            // Real page loads ask for HTML; scripts and assets usually don't.
            && str_contains((string) $request->header('Accept'), 'text/html')
            // Browser link pre-loading isn't a visit.
            && ! in_array(strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose'))), ['prefetch', 'prerender', 'prefetch;prerender'], true)
            && $request->user() === null // staff browsing their own site
            && $agent !== ''
            && preg_match(self::BOTS, $agent) !== 1;
    }

    public function record(Request $request): void
    {
        $today = now()->toDateString();
        $hash = hash('sha256', $today.'|'.config('app.key').'|'.$request->ip().'|'.$request->userAgent());

        $isNew = DB::table('visit_hashes')->insertOrIgnore(['date' => $today, 'hash' => $hash]) > 0;

        DB::table('daily_visits')->upsert(
            ['date' => $today, 'visitors' => (int) $isNew, 'page_views' => 1],
            ['date'],
            [
                'page_views' => DB::raw('page_views + 1'),
                'visitors' => DB::raw('visitors + '.(int) $isNew),
            ],
        );

        // Forget earlier days' codes (cheap, only now and then).
        if ($isNew && random_int(1, 20) === 1) {
            DB::table('visit_hashes')->where('date', '<', $today)->delete();
        }
    }
}
