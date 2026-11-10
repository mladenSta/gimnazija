<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AlumniTestimonialRequest;
use App\Http\Resources\MediaResource;
use App\Models\AlumniTestimonial;
use App\Support\UploadSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AlumniTestimonialController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AlumniTestimonial::class);

        $items = AlumniTestimonial::query()
            ->with('photo')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (AlumniTestimonial $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'generation' => $a->generation,
                'profession' => $a->profession,
                'quote' => $a->quote,
                'photo' => $a->photo ? MediaResource::make($a->photo)->resolve($request) : null,
                'is_published' => $a->is_published,
            ]);

        return Inertia::render('admin/alumni/Index', [
            'testimonials' => $items,
            'upload' => UploadSettings::toArray(),
        ]);
    }

    public function store(AlumniTestimonialRequest $request): RedirectResponse
    {
        AlumniTestimonial::query()->create([
            ...$request->validated(),
            'sort_order' => (int) AlumniTestimonial::query()->max('sort_order') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Poruka je dodata.']);

        return back();
    }

    public function update(AlumniTestimonialRequest $request, AlumniTestimonial $alumnus): RedirectResponse
    {
        $alumnus->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Poruka je sačuvana.']);

        return back();
    }

    public function destroy(AlumniTestimonial $alumnus): RedirectResponse
    {
        Gate::authorize('delete', $alumnus);

        $alumnus->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Poruka je obrisana.']);

        return back();
    }

    /**
     * Save the drag-and-drop order.
     */
    public function reorder(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', AlumniTestimonial::class);

        $ids = array_values((array) $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:alumni_testimonials,id'],
        ])['ids']);

        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                AlumniTestimonial::query()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return back();
    }
}
