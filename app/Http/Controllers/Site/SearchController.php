<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * WordPress-style search: /?s=term (posts and pages).
 */
class SearchController extends Controller
{
    private const PER_PAGE = 10;

    public function __invoke(Request $request): Response
    {
        $term = trim((string) $request->query('s', ''));
        $page = max(1, $request->integer('page', 1));
        $results = collect();

        if (mb_strlen($term) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
            $match = fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('title', 'like', $like)->orWhere('content', 'like', $like));

            $pages = Page::query()->published()->tap($match)->limit(50)->get()
                ->map(fn (Page $p) => ['type' => 'Stranica', 'title' => $p->title, 'url' => $p->path(), 'summary' => $p->summary(200), 'date' => null, 'sort' => 1, 'time' => 0]);

            $posts = Post::query()->published()->tap($match)->latest('published_at')->limit(200)->get()
                ->map(fn (Post $p) => ['type' => 'Vijest', 'title' => $p->title, 'url' => $p->path(), 'summary' => $p->summary(200), 'date' => $p->published_at, 'sort' => 2, 'time' => -($p->published_at?->getTimestamp() ?? 0)]);

            // Pages first, then news (newest first).
            $results = $pages->concat($posts)->sortBy([['sort', 'asc'], ['time', 'asc']])->values();
        }

        $paginator = new LengthAwarePaginator(
            $results->forPage($page, self::PER_PAGE)->values(),
            $results->count(),
            self::PER_PAGE,
            $page,
            ['path' => '/', 'query' => ['s' => $term]],
        );

        return response()->view('site.search', [
            'term' => $term,
            'results' => $paginator,
            'seo' => Seo::make(title: $term !== '' ? "Pretraga: {$term}" : 'Pretraga', noindex: true),
        ]);
    }
}
