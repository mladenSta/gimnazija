<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * /vijesti/ and /category/{slug}/ with WordPress paging (/page/2/).
 */
class ArchiveController extends Controller
{
    private const PER_PAGE = 12;

    public function news(int $page = 1): Response|RedirectResponse
    {
        return $this->render(
            Post::query()->published(),
            '/vijesti/',
            $page,
            title: 'Vijesti',
            lead: 'Novosti, događaji i uspjesi naših učenika.',
        );
    }

    public function category(string $slug, int $page = 1): Response|RedirectResponse
    {
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        return $this->render(
            Post::query()->published()->whereHas('categories', fn (Builder $q) => $q->whereKey($category->id)),
            $category->path(),
            $page,
            title: $category->name,
            lead: $category->description,
        );
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function render(Builder $query, string $base, int $page, string $title, ?string $lead): Response|RedirectResponse
    {
        if ($page === 1 && request()->route()?->parameter('page') !== null) {
            return Seo::redirect($base);
        }

        $posts = $query->with(['featuredImage'])
            ->latest('published_at')
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        if ($page > 1 && $page > $posts->lastPage()) {
            abort(404);
        }

        $path = $page > 1 ? "{$base}page/{$page}/" : $base;

        return response()->view('site.archive', [
            'posts' => $posts,
            'title' => $title,
            'lead' => $lead,
            'base' => $base,
            'seo' => Seo::make(
                title: $page > 1 ? "{$title} – strana {$page}" : $title,
                description: $lead,
                path: $path,
            ),
        ]);
    }
}
