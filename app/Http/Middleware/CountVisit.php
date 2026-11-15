<?php

namespace App\Http\Middleware;

use App\Services\Visits\VisitCounter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts public page views (see VisitCounter). Runs after the response has
 * been sent, so visitors never wait for it, and never breaks a page.
 */
class CountVisit
{
    public function __construct(private VisitCounter $counter) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if ($this->counter->shouldCount($request, $response)) {
                $this->counter->record($request);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
