<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Visits\VisitStats;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(VisitStats $stats): Response
    {
        return Inertia::render('Dashboard', [
            'visits' => $stats->summary(),
        ]);
    }
}
