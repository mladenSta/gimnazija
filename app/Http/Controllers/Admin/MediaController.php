<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MediaInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMediaRequest;
use App\Http\Requests\Admin\UpdateMediaRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\Media\ImageUploader;
use App\Services\Media\MediaDeleter;
use App\Support\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MediaController extends Controller
{
    private const PER_PAGE = 40;

    /**
     * Media library page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Media::class);

        $search = trim((string) $request->query('search', ''));

        return Inertia::render('admin/media/Index', [
            'media' => MediaResource::collection($this->query($search)->paginate(self::PER_PAGE)->withQueryString()),
            'filters' => ['search' => $search],
            'upload' => UploadSettings::toArray(),
        ]);
    }

    /**
     * JSON list for the media picker.
     */
    public function browse(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Media::class);

        $search = trim((string) $request->query('search', ''));

        return MediaResource::collection($this->query($search)->paginate(30)->withQueryString());
    }

    public function store(StoreMediaRequest $request, ImageUploader $uploader): JsonResponse
    {
        $media = $uploader->store($request->file('file'), $request->user());

        return MediaResource::make($media->load('uploader'))->response()->setStatusCode(201);
    }

    public function show(Media $media): MediaResource
    {
        Gate::authorize('view', $media);

        return MediaResource::make($media->load('uploader'));
    }

    public function update(UpdateMediaRequest $request, Media $media): MediaResource
    {
        $media->update($request->validated());

        return MediaResource::make($media->load('uploader'));
    }

    public function destroy(Media $media, MediaDeleter $deleter): JsonResponse
    {
        Gate::authorize('delete', $media);

        try {
            $deleter->delete($media);
        } catch (MediaInUseException $e) {
            return response()->json(['message' => $e->getMessage(), 'usages' => $media->usages()], 409);
        }

        return response()->json(null, 204);
    }

    /**
     * @return Builder<Media>
     */
    private function query(string $search): Builder
    {
        return Media::query()
            ->with('uploader:id,name')
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $q) use ($search) {
                    $q->where('original_name', 'like', "%{$search}%")
                        ->orWhere('alt', 'like', "%{$search}%")
                        ->orWhere('caption', 'like', "%{$search}%");
                });
            })
            ->latest('id');
    }
}
