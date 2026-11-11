<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GalleryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryRequest;
use App\Http\Requests\Admin\UpdateGalleryRequest;
use App\Http\Resources\MediaResource;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\Tag;
use App\Services\Content\GallerySaver;
use App\Support\Slug;
use App\Support\UploadSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Gallery::class);

        $galleries = Gallery::query()
            ->withCount('items')
            ->with(['items' => fn ($q) => $q->with('media')->limit(1)])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get()
            ->map(fn (Gallery $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
                'type' => $g->type->value,
                'type_label' => $g->type->label(),
                'is_system' => $g->is_system,
                'usage' => $g->usage(),
                'items_count' => $g->items_count,
                'cover_url' => $g->items->first()?->media->url('thumb'),
                'can_delete' => $request->user()?->can('delete', $g) ?? false,
            ]);

        return Inertia::render('admin/galleries/Index', [
            'galleries' => $galleries,
            'types' => array_map(fn (GalleryType $t) => ['value' => $t->value, 'label' => $t->label()], GalleryType::cases()),
        ]);
    }

    public function store(StoreGalleryRequest $request): RedirectResponse
    {
        $name = (string) $request->validated('name');
        $base = Slug::make($name) ?: 'galerija';
        $slug = $base;
        $n = 2;
        while (Gallery::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        $gallery = Gallery::query()->create([
            'name' => $name,
            'slug' => $slug,
            'type' => $request->validated('type'),
            'is_system' => false,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Galerija je napravljena.']);

        return to_route('admin.galleries.edit', $gallery);
    }

    public function edit(Request $request, Gallery $gallery): Response
    {
        Gate::authorize('update', $gallery);

        $gallery->load(['items.media', 'items.tags']);

        return Inertia::render('admin/galleries/Edit', [
            'gallery' => [
                'id' => $gallery->id,
                'name' => $gallery->name,
                'slug' => $gallery->slug,
                'type' => $gallery->type->value,
                'is_system' => $gallery->is_system,
                'usage' => $gallery->usage(),
                'can_delete' => $request->user()?->can('delete', $gallery) ?? false,
                'items' => $gallery->items->map(fn (GalleryItem $item) => [
                    'id' => $item->id,
                    'media' => MediaResource::make($item->media)->resolve($request),
                    'caption' => $item->caption,
                    'focal_x' => $item->focal_x,
                    'focal_y' => $item->focal_y,
                    'tags' => $item->tags->pluck('name')->all(),
                    'badge' => $item->badge,
                    'title' => $item->title,
                    'subtitle' => $item->subtitle,
                    'link_label' => $item->link_label,
                    'link_url' => $item->link_url,
                ])->all(),
            ],
            'allTags' => Tag::query()->orderBy('name')->pluck('name'),
            'upload' => UploadSettings::toArray(),
        ]);
    }

    public function update(UpdateGalleryRequest $request, Gallery $gallery, GallerySaver $saver): RedirectResponse
    {
        /** @var array{name: string, items?: list<array<string, mixed>>} $data */
        $data = $request->validated();
        $saver->save($gallery, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Galerija je sačuvana.']);

        return to_route('admin.galleries.edit', $gallery);
    }

    public function destroy(Gallery $gallery): RedirectResponse
    {
        Gate::authorize('delete', $gallery);

        $gallery->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Galerija je obrisana. Fotografije su ostale u medijima.']);

        return to_route('admin.galleries.index');
    }
}
