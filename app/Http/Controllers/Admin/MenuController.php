<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateMenuRequest;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\Content\MenuTreeSaver;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function edit(): Response
    {
        $items = MenuItem::query()
            ->roots()
            ->with(['page' => fn ($q) => $q->withTrashed(), 'category', 'children.page' => fn ($q) => $q->withTrashed(), 'children.category'])
            ->get();

        return Inertia::render('admin/menu/Edit', [
            'items' => $items->map(fn (MenuItem $item) => $this->present($item))->all(),
            'pages' => Page::query()->orderBy('title')->get(['id', 'title', 'slug', 'status', 'published_at'])
                ->map(fn (Page $p) => ['id' => $p->id, 'title' => $p->title, 'slug' => $p->slug, 'published' => $p->isPublished()]),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'maxDepth' => MenuItem::MAX_DEPTH,
        ]);
    }

    public function update(UpdateMenuRequest $request, MenuTreeSaver $saver): RedirectResponse
    {
        /** @var list<array<string, mixed>> $items */
        $items = array_values((array) $request->validated('items', []));
        $saver->save($items);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Meni je sačuvan.']);

        return to_route('admin.menu.edit');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MenuItem $item): array
    {
        $warning = match (true) {
            $item->page?->trashed() => 'Stranica je u otpadu – stavka se ne prikazuje.',
            $item->page !== null && ! $item->page->isPublished() => 'Stranica nije objavljena – stavka se ne prikazuje.',
            default => null,
        };

        return [
            'id' => $item->id,
            'type' => $item->type->value,
            'page_id' => $item->page_id,
            'category_id' => $item->category_id,
            'url' => $item->url,
            'label' => $item->label,
            'original_label' => match (true) {
                $item->page !== null => $item->page->title,
                $item->category !== null => $item->category->name,
                default => null,
            },
            'href' => $item->href,
            'warning' => $warning,
            'children' => $item->relationLoaded('children')
                ? $item->children->map(fn (MenuItem $child) => $this->present($child))->all()
                : [],
        ];
    }
}
