<?php

use App\Http\Controllers\Site\ArchiveController;
use App\Http\Controllers\Site\ContentController;
use App\Http\Controllers\Site\FeedController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Middleware\CountVisit;
use App\Http\Middleware\EnsureTrailingSlash;
use App\Support\RedirectResolver;
use App\Support\ReservedSlugs;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Public website (Blade). URLs keep the WordPress structure:
|   /                         home (and /?s=term search)
|   /vijesti/                 all news, /vijesti/page/2/
|   /category/{slug}/         category news, /category/{slug}/page/2/
|   /feed/                    RSS
|   /sitemap.xml
|   /{slug}/                  page or post
| Loaded last, so the catch-all never shadows admin/auth routes.
*/

$slug = '(?!(?:'.implode('|', array_map('preg_quote', ReservedSlugs::LIST)).')$)[a-z0-9%][a-z0-9%\-]*';

Route::middleware([EnsureTrailingSlash::class, CountVisit::class])->group(function () use ($slug) {
    Route::get('/', HomeController::class)->name('home');

    Route::get('vijesti/', [ArchiveController::class, 'news'])->name('news');
    Route::get('vijesti/page/{page}/', [ArchiveController::class, 'news'])->whereNumber('page')->name('news.page');

    Route::get('category/{slug}/', [ArchiveController::class, 'category'])->where('slug', '[a-z0-9%\-]+')->name('category');
    Route::get('category/{slug}/page/{page}/', [ArchiveController::class, 'category'])->where('slug', '[a-z0-9%\-]+')->whereNumber('page')->name('category.page');

    Route::get('feed/', [FeedController::class, 'rss'])->name('feed');
    Route::get('sitemap.xml', [FeedController::class, 'sitemap'])->name('sitemap');

    Route::get('{slug}/', [ContentController::class, 'show'])->where('slug', $slug)->name('content');
});

// Anything else: stored 301 (old WordPress URLs, renamed slugs), otherwise 404.
Route::fallback(function (Request $request) {
    if ($redirect = RedirectResolver::find($request->getPathInfo())) {
        return Seo::redirect($redirect->to_path, $redirect->status_code);
    }

    abort(404);
});
