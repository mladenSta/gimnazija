<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EventRequest;
use App\Models\Event;
use App\Support\PaginatedProps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Event::class);

        $tab = $request->query('tab') === 'past' ? 'past' : 'upcoming';

        $events = Event::query()
            ->when(
                $tab === 'upcoming',
                fn ($q) => $q->whereDate('date', '>=', today())->orderBy('date')->orderBy('time'),
                fn ($q) => $q->whereDate('date', '<', today())->orderByDesc('date')->orderByDesc('time'),
            )
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'short_description' => $e->short_description,
                'date' => $e->date->format('Y-m-d'),
                'date_display' => $e->date->format('d.m.Y.'),
                'time' => $e->time !== null ? substr($e->time, 0, 5) : null,
                'location' => $e->location,
                'label' => $e->label,
                'is_published' => $e->is_published,
            ]);

        return Inertia::render('admin/events/Index', [
            'events' => PaginatedProps::from($events),
            'tab' => $tab,
            'counts' => [
                'upcoming' => Event::query()->whereDate('date', '>=', today())->count(),
                'past' => Event::query()->whereDate('date', '<', today())->count(),
            ],
            'labels' => Event::query()->whereNotNull('label')->distinct()->orderBy('label')->pluck('label'),
        ]);
    }

    public function store(EventRequest $request): RedirectResponse
    {
        Event::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Najava je dodata.']);

        return back();
    }

    public function update(EventRequest $request, Event $event): RedirectResponse
    {
        $event->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Najava je sačuvana.']);

        return back();
    }

    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);

        $event->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Najava je obrisana.']);

        return back();
    }
}
