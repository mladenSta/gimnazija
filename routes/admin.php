<?php

use App\Http\Controllers\Admin\AlumniTestimonialController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\GalleryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MenuController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\PostShareController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

/*
| Admin panel (Inertia). Every route here requires a verified, logged-in user.
| Admin-only sections add ->middleware('role:admin').
*/

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');

    Route::name('admin.')->group(function () {
        // Media library (all roles)
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/browse', [MediaController::class, 'browse'])->name('media.browse');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

        // Posts (all roles; authors only their own — see PostPolicy)
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
        Route::post('posts', [PostController::class, 'store'])->name('posts.store');
        Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit')->withTrashed();
        Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update')->withTrashed();
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
        Route::post('posts/{post}/restore', [PostController::class, 'restore'])->name('posts.restore')->withTrashed();
        Route::delete('posts/{post}/force', [PostController::class, 'forceDelete'])->name('posts.force-delete')->withTrashed();
        // Publish on Facebook / Instagram / LinkedIn (admins and editors — see PostPolicy::share)
        Route::post('posts/{post}/share/{network}', [PostShareController::class, 'store'])
            ->name('posts.share')
            ->middleware('throttle:20,1');

        // Pages (all roles manage all pages)
        Route::get('pages', [PageController::class, 'index'])->name('pages.index');
        Route::get('pages/create', [PageController::class, 'create'])->name('pages.create');
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{page}/edit', [PageController::class, 'edit'])->name('pages.edit')->withTrashed();
        Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update')->withTrashed();
        Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');
        Route::post('pages/{page}/restore', [PageController::class, 'restore'])->name('pages.restore')->withTrashed();
        Route::delete('pages/{page}/force', [PageController::class, 'forceDelete'])->name('pages.force-delete')->withTrashed();

        // Main menu (all roles)
        Route::get('menu', [MenuController::class, 'edit'])->name('menu.edit');
        Route::put('menu', [MenuController::class, 'update'])->name('menu.update');

        // Galleries (all roles; home-page galleries can't be deleted)
        Route::get('galleries', [GalleryController::class, 'index'])->name('galleries.index');
        Route::post('galleries', [GalleryController::class, 'store'])->name('galleries.store');
        Route::get('galleries/{gallery}/edit', [GalleryController::class, 'edit'])->name('galleries.edit');
        Route::put('galleries/{gallery}', [GalleryController::class, 'update'])->name('galleries.update');
        Route::delete('galleries/{gallery}', [GalleryController::class, 'destroy'])->name('galleries.destroy');

        // Events / "Najave" (all roles)
        Route::get('events', [EventController::class, 'index'])->name('events.index');
        Route::post('events', [EventController::class, 'store'])->name('events.store');
        Route::put('events/{event}', [EventController::class, 'update'])->name('events.update');
        Route::delete('events/{event}', [EventController::class, 'destroy'])->name('events.destroy');

        // Admin only
        Route::middleware('role:admin')->group(function () {
            Route::get('alumni', [AlumniTestimonialController::class, 'index'])->name('alumni.index');
            Route::post('alumni', [AlumniTestimonialController::class, 'store'])->name('alumni.store');
            Route::put('alumni/reorder', [AlumniTestimonialController::class, 'reorder'])->name('alumni.reorder');
            Route::put('alumni/{alumnus}', [AlumniTestimonialController::class, 'update'])->name('alumni.update');
            Route::delete('alumni/{alumnus}', [AlumniTestimonialController::class, 'destroy'])->name('alumni.destroy');

            Route::get('users', [UserController::class, 'index'])->name('users.index');
            Route::post('users', [UserController::class, 'store'])->name('users.store');
            Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        });
    });
});
