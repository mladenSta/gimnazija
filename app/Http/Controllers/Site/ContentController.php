<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Support\RedirectResolver;
use App\Support\Seo;
use App\Support\SerbianDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /{slug}/ — a page or a post (WordPress /%postname%/ structure).
 * Logged-in staff can preview drafts and scheduled items.
 */
class ContentController extends Controller
{
    public function show(Request $request, string $slug): Response|RedirectResponse
    {
        $preview = $request->user() !== null;

        $page = Page::query()->where('slug', $slug)->first();
        if ($page !== null && ($page->isPublished() || $preview)) {
            return $this->page($page);
        }

        $post = Post::query()->where('slug', $slug)->first();
        if ($post !== null && ($post->isPublished() || $preview)) {
            return $this->post($post);
        }

        if ($redirect = RedirectResolver::find($request->getPathInfo())) {
            return Seo::redirect($redirect->to_path, $redirect->status_code);
        }

        abort(404);
    }

    private function page(Page $page): Response
    {
        $page->load(['featuredImage', 'galleryImages']);

        return response()->view('site.page', [
            'page' => $page,
            'isPreview' => ! $page->isPublished(),
            'seo' => Seo::make(
                title: $page->meta_title ?: $page->title,
                description: $page->meta_description ?: $page->summary(),
                path: $page->path(),
                image: $page->featuredImage?->url('large'),
                noindex: ! $page->isPublished(),
            ),
        ]);
    }

    private function post(Post $post): Response
    {
        $post->load(['featuredImage', 'galleryImages', 'categories']);

        $more = Post::query()->published()
            ->whereKeyNot($post->id)
            ->with(['featuredImage'])
            ->latest('published_at')
            ->limit(3)
            ->get();

        $image = $post->featuredImage?->url('large');

        return response()->view('site.post', [
            'post' => $post,
            'more' => $more,
            'isPreview' => ! $post->isPublished(),
            'seo' => Seo::make(
                title: $post->meta_title ?: $post->title,
                description: $post->meta_description ?: $post->summary(),
                path: $post->path(),
                image: $image,
                type: 'article',
                noindex: ! $post->isPublished(),
                publishedTime: $post->published_at?->toIso8601String(),
                jsonLd: [
                    '@context' => 'https://schema.org',
                    '@type' => 'NewsArticle',
                    'headline' => $post->title,
                    'datePublished' => $post->published_at?->toIso8601String(),
                    'dateModified' => $post->updated_at?->toIso8601String(),
                    'image' => $image ? [$image] : [],
                    'mainEntityOfPage' => Seo::url($post->path()),
                    'publisher' => ['@type' => 'Organization', 'name' => 'JU Gimnazija „Jovan Dučić" Doboj'],
                ],
            ),
            'dateLabel' => SerbianDate::long($post->published_at),
        ]);
    }
}
