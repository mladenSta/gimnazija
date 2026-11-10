<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    /**
     * RSS 2.0 of the latest news (/feed/, same as WordPress).
     */
    public function rss(): Response
    {
        $posts = Post::query()->published()->with(['featuredImage', 'categories'])->latest('published_at')->limit(20)->get();

        return response()
            ->view('site.feed', ['posts' => $posts, 'base' => rtrim((string) config('app.url'), '/')])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        return response()
            ->view('site.sitemap', [
                'base' => rtrim((string) config('app.url'), '/'),
                'pages' => Page::query()->published()->get(['slug', 'updated_at', 'status', 'published_at']),
                'posts' => Post::query()->published()->latest('published_at')->get(['slug', 'updated_at', 'status', 'published_at']),
                'categories' => Category::query()->whereHas('posts', fn ($q) => $q->published())->get(['slug', 'updated_at']),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
