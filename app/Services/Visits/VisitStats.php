<?php

namespace App\Services\Visits;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the admin dashboard.
 */
class VisitStats
{
    private const MONTHS = ['jan', 'feb', 'mar', 'apr', 'maj', 'jun', 'jul', 'avg', 'sep', 'okt', 'nov', 'dec'];

    private const DAYS = ['ned', 'pon', 'uto', 'sri', 'čet', 'pet', 'sub'];

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $today = CarbonImmutable::today();

        return [
            'since' => $this->firstDate()?->format('d.m.Y.'),
            'periods' => [
                $this->period('Danas', $today, $today, $today->subDay(), $today->subDay(), 'u odnosu na jučer'),
                $this->period('Posljednjih 7 dana', $today->subDays(6), $today, $today->subDays(13), $today->subDays(7), 'u odnosu na 7 dana prije'),
                $this->period('Posljednjih 30 dana', $today->subDays(29), $today, $today->subDays(59), $today->subDays(30), 'u odnosu na 30 dana prije'),
            ],
            'daily' => $this->daily($today->subDays(29), $today),
            'months' => $this->months($today),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function period(string $label, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $prevFrom, CarbonImmutable $prevTo, string $compareLabel): array
    {
        $current = $this->totals($from, $to);
        $previous = $this->totals($prevFrom, $prevTo);
        $first = $this->firstDate();

        return [
            'label' => $label,
            'visitors' => $current['visitors'],
            'page_views' => $current['page_views'],
            // No comparison until the counter has run for the whole previous period.
            'change' => $first !== null && $first->lessThanOrEqualTo($prevFrom) && $previous['visitors'] > 0
                ? (int) round(($current['visitors'] - $previous['visitors']) / $previous['visitors'] * 100)
                : null,
            'compare_label' => $compareLabel,
        ];
    }

    /**
     * @return array{visitors: int, page_views: int}
     */
    private function totals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $row = DB::table('daily_visits')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('COALESCE(SUM(visitors), 0) AS visitors, COALESCE(SUM(page_views), 0) AS page_views')
            ->first();

        return ['visitors' => (int) ($row->visitors ?? 0), 'page_views' => (int) ($row->page_views ?? 0)];
    }

    /**
     * Every day in the range, zero when there were no visits.
     *
     * @return list<array{date: string, label: string, weekday: string, visitors: int, page_views: int}>
     */
    private function daily(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = DB::table('daily_visits')
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (object $r) => substr((string) $r->date, 0, 10));

        $days = [];

        for ($day = $from; $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $row = $rows->get($day->toDateString());
            $days[] = [
                'date' => $day->format('d.m.Y.'),
                'label' => $day->format('d.m.'),
                'weekday' => self::DAYS[$day->dayOfWeek],
                'visitors' => (int) ($row->visitors ?? 0),
                'page_views' => (int) ($row->page_views ?? 0),
            ];
        }

        return $days;
    }

    /**
     * This year, January up to the current month.
     *
     * @return list<array{label: string, visitors: int, page_views: int}>
     */
    private function months(CarbonImmutable $today): array
    {
        $rows = DB::table('daily_visits')
            ->whereBetween('date', [$today->startOfYear()->toDateString(), $today->toDateString()])
            ->selectRaw('MONTH(date) AS month, SUM(visitors) AS visitors, SUM(page_views) AS page_views')
            ->groupByRaw('MONTH(date)')
            ->get()
            ->keyBy('month');

        $months = [];

        for ($m = 1; $m <= $today->month; $m++) {
            $row = $rows->get($m);
            $months[] = [
                'label' => self::MONTHS[$m - 1].' '.$today->year,
                'visitors' => (int) ($row->visitors ?? 0),
                'page_views' => (int) ($row->page_views ?? 0),
            ];
        }

        return $months;
    }

    private function firstDate(): ?CarbonImmutable
    {
        $first = DB::table('daily_visits')->min('date');

        return $first !== null ? CarbonImmutable::parse((string) $first) : null;
    }
}
