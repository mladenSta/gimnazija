<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Http\Resources\PostFormResource;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\Content\ContentSaver;
use App\Support\UploadSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PostController extends Controller
{
    private const TABS = ['all', 'published', 'scheduled', 'draft', 'trash'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Post::class);

        /** @var User $user */
        $user = $request->user();
        $tab = in_array($request->query('status'), self::TABS, true) ? (string) $request->query('status') : 'all';
        $search = trim((string) $request->query('search', ''));
        $categoryId = $request->integer('category') ?: null;

        $posts = $this->applyTab($this->visibleTo($user), $tab)
            ->with(['author:id,name', 'categories:id,name', 'featuredImage'])
            ->when($search !== '', fn (Builder $q) => $q->where('title', 'like', "%{$search}%"))
            ->when($categoryId, fn (Builder $q) => $q->whereHas('categories', fn (Builder $c) => $c->whereKey($categoryId)))
            ->orderByRaw('published_at IS NULL DESC')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Post $post) => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->trashed() ? 'trash' : ($post->isScheduled() ? 'scheduled' : $post->status->value),
                'date' => $post->published_at?->format('d.m.Y. H:i'),
                'author' => $post->author?->name,
                'categories' => $post->categories->pluck('name')->all(),
                'thumb_url' => $post->featuredImage?->url('thumb'),
                'can' => [
                    'update' => $user->can('update', $post),
                    'delete' => $user->can('delete', $post),
                ],
            ]);

        $counts = [];
        foreach (self::TABS as $t) {
            $counts[$t] = $this->applyTab($this->visibleTo($user), $t)->count();
        }

        return Inertia::render('admin/posts/Index', [
            'posts' => $posts,
            'filters' => ['status' => $tab, 'search' => $search, 'category' => $categoryId],
            'counts' => $counts,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Post::class);

        return Inertia::render('admin/posts/Edit', [
            'post' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(PostRequest $request, ContentSaver $saver): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $post = $saver->save(new Post, $request->validated(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Objava je sačuvana.']);

        return to_route('admin.posts.edit', $post);
    }

    public function edit(Post $post): Response
    {
        Gate::authorize('update', $post);

        $post->load(['featuredImage', 'galleryImages', 'categories', 'author:id,name']);

        return Inertia::render('admin/posts/Edit', [
            'post' => PostFormResource::make($post)->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(PostRequest $request, Post $post, ContentSaver $saver): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $saver->save($post, $request->validated(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Objava je sačuvana.']);

        return to_route('admin.posts.edit', $post);
    }

    /**
     * Move to trash (soft delete).
     */
    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Objava je premještena u otpad.']);

        return back();
    }

    public function restore(Post $post): RedirectResponse
    {
        Gate::authorize('restore', $post);

        $post->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Objava je vraćena iz otpada.']);

        return back();
    }

    public function forceDelete(Post $post): RedirectResponse
    {
        Gate::authorize('forceDelete', $post);

        $post->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Objava je trajno obrisana.']);

        return back();
    }

    /**
     * @return Builder<Post>
     */
    private function visibleTo(User $user): Builder
    {
        return Post::query()->when(! $user->canManageAllPosts(), fn (Builder $q) => $q->where('user_id', $user->id));
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
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
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'parent_id']),
            'statuses' => array_map(
                fn (ContentStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                ContentStatus::cases(),
            ),
            'upload' => UploadSettings::toArray(),
            'siteUrl' => rtrim((string) config('app.url'), '/'),
        ];
    }
}
