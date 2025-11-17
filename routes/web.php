<?php

use App\Http\Controllers\BlogController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\JelajahController;
use App\Http\Controllers\RuteController;
use App\Http\Controllers\UserController;

require __DIR__ . '/auth.php';

Route::get('/', [IndexController::class, 'index'])->name('index');

Route::prefix('jelajah')->group(function () {
    Route::get('', [JelajahController::class, 'index'])->name('jelajah.index');
});

Route::prefix('jalur-pendakian')->group(function () {
    Route::prefix('{slug}')->group(function () {
        Route::get('', [RuteController::class, 'slug'])->name('jalur-pendakian.slug');
        Route::get('prediksi-cuaca', [RuteController::class, 'prediksiCuaca'])->name('jalur-pendakian.slug.prediksi-cuaca');
        Route::get('segmentasi', [RuteController::class, 'segmentasi'])->name('jalur-pendakian.slug.segmentasi');
    });
});

Route::prefix('ulasan')->group(function () {
    Route::post('rute/{ruteSlug}', [CommentController::class, 'store'])
        ->name('ulasan.store')
        ->middleware(['auth']);
});

Route::prefix('profile')->middleware(['auth'])->group(function () {
    Route::get('', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('update', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('ulasan', [ProfileController::class, 'ulasan'])->name('profile.ulasan');
    Route::delete('ulasan/{id}/delete', [ProfileController::class, 'ulasanDelete'])->name('profile.ulasan.ulasan.delete');
});

Route::prefix('artikel')->group(function () {
    Route::get('', [BlogController::class, 'index'])->name('blog.index');
    Route::get('{slug}', [BlogController::class, 'slug'])->name('blog.slug');
});

Route::get('sitemap.xml', [IndexController::class, 'sitemap'])->name('sitemap');

/**
 * Live Cam Routes --- START
 */
use App\Http\Controllers\LiveCamController;

Route::prefix('live-cam')->name('live-cam.')->group(function () {
    // Public routes (no auth required - for viewers)
    Route::get('', [LiveCamController::class, 'index'])->name('index');
    Route::get('{id}', [LiveCamController::class, 'show'])->name('show');
    Route::get('{id}/status', [LiveCamController::class, 'getStatus'])->name('status');
    Route::get('{id}/chunk/{index}', [LiveCamController::class, 'getChunk'])->name('chunk.get');
    Route::get('{id}/quality', [LiveCamController::class, 'getQuality'])->name('quality');

    // Chat routes (public - guests can chat)
    Route::post('{id}/chat', [LiveCamController::class, 'sendChat'])->name('chat');

    // Viewer count tracking (public)
    Route::post('{id}/viewer-count', [LiveCamController::class, 'updateViewerCount'])->name('viewer-count');
});
/**
 * Live Cam Routes --- END
 */

/**
 * API --- START
 */
Route::prefix('api')->group(function () {
    Route::prefix('jelajah')->group(function () {
        Route::get('rute', [JelajahController::class, 'apiRute'])->name('api.jelajah.rute');
    });

    Route::prefix('rute')->group(function () {
        Route::prefix('{id}')->group(function () {
            Route::get('rute', [RuteController::class, 'apiRute'])->name('api.rute.rute');
            // Route::get('fitting-kalori', [RuteController::class, 'apiFittingKalori'])->name('api.rute.fitting-kalori');
            // Route::get('prediksi-cuaca', [RuteController::class, 'apiPrediksiCuaca'])->name('api.rute.prediksi-cuaca');
            Route::get('segmentasi', [RuteController::class, 'apiSegmentasi'])->name('api.rute.segmentasi');
        });
    });

    Route::prefix('ulasan')->group(function () {
        Route::get('rute/{ruteId}', [CommentController::class, 'apiIndex'])->name('api.rute.ulasan.index');
    });

    Route::prefix('profile')->middleware(['auth'])->group(function () {
        Route::get('ulasan', [ProfileController::class, 'apiUlasan'])->name('api.profile.ulasan');
        Route::delete('ulasan/{id}/delete', [ProfileController::class, 'apiUlasanDelete'])->name('api.profile.ulasan.delete');
    });

    Route::prefix('user')->group(function () {
        Route::post('toggle-theme', [UserController::class, 'apiToggleTheme'])->name('user.toggle-theme');
    });
});

require __DIR__ . '/api.php';

/**
 * API --- END
 */

require __DIR__ . '/admin.php';

// Route::middleware('auth')->group(function () {
//     Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
//     Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
//     Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
// });
