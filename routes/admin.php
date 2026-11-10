<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\PostController;
use Illuminate\Support\Facades\Route;

/*
| Admin panel (Inertia). Every route here requires a verified, logged-in user.
| Admin-only sections add ->middleware('role:admin').
*/

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::inertia('/', 'Dashboard')->name('dashboard');

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

        // Admin only
        Route::middleware('role:admin')->group(function () {
            Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
        });
    });
});
