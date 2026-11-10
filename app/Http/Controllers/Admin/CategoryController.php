<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        $categories = Category::query()
            ->withCount('posts')
            ->with('parent:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'description' => $c->description,
                'parent_id' => $c->parent_id,
                'parent_name' => $c->parent?->name,
                'posts_count' => $c->posts_count,
            ]);

        return Inertia::render('admin/categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategorija je dodata.']);

        return to_route('admin.categories.index');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategorija je sačuvana.']);

        return to_route('admin.categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        // Posts stay; they only lose this category. Subcategories move up a level.
        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategorija je obrisana.']);

        return to_route('admin.categories.index');
    }
}
