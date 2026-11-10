<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Http\Resources\PageFormResource;
use App\Models\Page;
use App\Models\User;
use App\Services\Content\ContentSaver;
use App\Support\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PageController extends Controller
{
    private const TABS = ['all', 'published', 'scheduled', 'draft', 'trash'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Page::class);

        /** @var User $user */
        $user = $request->user();
        $tab = in_array($request->query('status'), self::TABS, true) ? (string) $request->query('status') : 'all';
        $search = trim((string) $request->query('search', ''));

        $pages = $this->applyTab(Page::query(), $tab)
            ->with(['author:id,name'])
            ->withCount('menuItems')
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', "%{$search}%"))
            ->orderBy('title')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (Page $page) => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'status' => $page->trashed() ? 'trash' : ($page->isScheduled() ? 'scheduled' : $page->status->value),
                'updated_at' => $page->updated_at?->format('d.m.Y.'),
                'author' => $page->author?->name,
                'in_menu' => $page->menu_items_count > 0,
                'can' => [
                    'update' => $user->can('update', $page),
                    'delete' => $user->can('delete', $page),
                ],
            ]);

        $counts = [];
        foreach (self::TABS as $t) {
            $counts[$t] = $this->applyTab(Page::query(), $t)->count();
        }

        return Inertia::render('admin/pages/Index', [
            'pages' => $pages,
            'filters' => ['status' => $tab, 'search' => $search],
            'counts' => $counts,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Page::class);

        return Inertia::render('admin/pages/Edit', [
            'page' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(PageRequest $request, ContentSaver $saver): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $page = $saver->save(new Page, $request->validated(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stranica je sačuvana.']);

        return to_route('admin.pages.edit', $page);
    }

    public function edit(Page $page): Response
    {
        Gate::authorize('update', $page);

        $page->load(['featuredImage', 'galleryImages', 'author:id,name']);

        return Inertia::render('admin/pages/Edit', [
            'page' => PageFormResource::make($page)->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(PageRequest $request, Page $page, ContentSaver $saver): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saver->save($page, $request->validated(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stranica je sačuvana.']);

        return to_route('admin.pages.edit', $page);
    }

    public function destroy(Page $page): RedirectResponse
    {
        Gate::authorize('delete', $page);

        $page->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stranica je premještena u otpad.']);

        return back();
    }

    public function restore(Page $page): RedirectResponse
    {
        Gate::authorize('restore', $page);

        $page->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stranica je vraćena iz otpada.']);

        return back();
    }

    /**
     * Permanent delete. Also removes the page's menu items (and their submenus).
     */
    public function forceDelete(Page $page): RedirectResponse
    {
        Gate::authorize('forceDelete', $page);

        $page->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Stranica je trajno obrisana.']);

        return back();
    }

    /**
     * @param  Builder<Page>  $query
     * @return Builder<Page>
     */
    private function applyTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'published' => $query->published(),
            'scheduled' => $query->scheduled(),
            'draft' => $query->drafts(),
            'trash' => $query->onlyTrashed(),
            default => $query,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statuses' => array_map(
                fn (ContentStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                ContentStatus::cases(),
            ),
            'upload' => UploadSettings::toArray(),
            'siteUrl' => rtrim((string) config('app.url'), '/'),
        ];
    }
}
