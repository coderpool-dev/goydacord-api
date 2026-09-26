<?php

use App\Http\Controllers\API\Social\BlockedUserController;
use App\Http\Controllers\API\Social\FriendController;
use App\Http\Controllers\API\Social\FriendRequestController;
use Illuminate\Support\Facades\Route;

// Друзья и блокировки. Пользователь в адресе указывается логином — так его знает фронт.
// Заявка — отдельный ресурс: PATCH принимает её, DELETE отклоняет входящую или
// отменяет свою (так же устроены приглашения в GitHub API).
Route::missing(fn () => response()->json([
    'status' => 'error',
    'message' => 'Пользователь не найден',
], 404))->group(function () {
    Route::prefix('friends')->name('friends.')->group(function () {
        Route::controller(FriendController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('mutual/{user}', 'mutualFriends')->middleware('throttle:mutual-friends')->name('mutual.index');
            Route::delete('{user:login}', 'destroy')->name('destroy');
        });

        Route::prefix('requests')->name('requests.')->controller(FriendRequestController::class)->group(function () {
            Route::post('/', 'store')->name('store');
            Route::patch('{user:login}', 'update')->name('update');
            Route::delete('{user:login}', 'destroy')->name('destroy');
        });
    });

    Route::prefix('blocked-users')
        ->name('blocked-users.')
        ->controller(BlockedUserController::class)
        ->group(function () {
            Route::put('{user:login}', 'update')->name('update');
            Route::delete('{user:login}', 'destroy')->name('destroy');
        });
});
