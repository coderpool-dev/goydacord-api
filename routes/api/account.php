<?php

use App\Http\Controllers\API\Account\ProfileController;
use App\Http\Controllers\API\Account\PushSubscriptionController;
use App\Http\Controllers\API\Conversations\NotificationMuteController;
use App\Http\Controllers\API\Presence\ActivityController;
use App\Http\Controllers\API\Presence\OnlineStatusController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::post('broadcasting/auth', fn (Request $request) => Broadcast::auth($request))
    ->name('broadcasting.auth');

// Web Push: ключ VAPID и подписка браузера на уведомления.
Route::prefix('push')->name('push.')->controller(PushSubscriptionController::class)->group(function () {
    Route::get('key', 'key')->name('key.show');
    Route::post('subscriptions', 'store')->name('subscriptions.store');
    Route::delete('subscriptions', 'destroy')->name('subscriptions.destroy');
});

// «Заглушить» чат / канал / сервер.
Route::prefix('notification-mutes')
    ->name('notification-mutes.')
    ->controller(NotificationMuteController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:settings-write')->name('store');
        Route::delete('/', 'destroy')->middleware('throttle:settings-write')->name('destroy');
    });

// Профиль.
Route::prefix('auth')->name('auth.')->group(function () {
    // POST, а не PATCH: аватар уходит в multipart, а PHP не разбирает multipart в PATCH.
    Route::post('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('online', OnlineStatusController::class)->name('online.ping');
});

// Игровая активность.
Route::controller(ActivityController::class)->group(function () {
    Route::post('activity/game', 'update')->name('activity.game.update');
    Route::post('activity/game-ping', 'pingGame')->name('activity.game.ping');
    Route::get('users/{user}/activity', 'show')->name('users.activity');
});
