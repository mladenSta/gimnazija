<?php

use App\Http\Controllers\Admin\MediaController;
use Illuminate\Support\Facades\Route;

/*
| Admin panel (Inertia). Every route here requires a verified, logged-in user.
| Admin-only sections add ->middleware('role:admin').
*/

Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    Route::inertia('/', 'Dashboard')->name('dashboard');

    Route::name('admin.')->group(function () {
        Route::get('media', [MediaController::class, 'index'])->name('media.index');
        Route::get('media/browse', [MediaController::class, 'browse'])->name('media.browse');
        Route::post('media', [MediaController::class, 'store'])->name('media.store');
        Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');
        Route::patch('media/{media}', [MediaController::class, 'update'])->name('media.update');
        Route::delete('media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
    });
});
