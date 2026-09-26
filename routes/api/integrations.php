<?php

use App\Http\Controllers\API\Integrations\GameIconController;
use App\Http\Controllers\API\Integrations\LinkPreviewController;
use App\Http\Controllers\API\Integrations\YandexMusicController;
use Illuminate\Support\Facades\Route;

// Интеграция с Яндекс Музыкой
Route::prefix('integrations/yandex-music')->name('yandex-music.')
    ->controller(YandexMusicController::class)->group(function () {
        Route::get('/', 'show')->name('show');
        Route::post('device-code', 'startDeviceAuth')->name('device-code.start');
        Route::post('poll', 'pollDeviceAuth')->name('poll');
        Route::post('token', 'connectWithToken')->name('token.connect');
        Route::post('sync', 'sync')->name('sync');
        Route::delete('/', 'disconnect')->name('disconnect');
    });

Route::get('users/{user}/yandex-music/history', [YandexMusicController::class, 'history'])
    ->middleware('throttle:music-history')
    ->name('users.yandex-music.history');

Route::get('link-preview', [LinkPreviewController::class, 'show'])
    ->middleware('throttle:link-preview')
    ->name('link-preview.show');

Route::post('games/icons', [GameIconController::class, 'submitForReview'])
    ->middleware(['throttle:game-icon-submissions', 'throttle:game-icon-daily'])
    ->name('games.icons.store');
