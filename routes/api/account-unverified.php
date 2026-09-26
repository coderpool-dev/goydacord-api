<?php

use App\Http\Controllers\API\Account\AuthController;
use App\Http\Controllers\API\Account\ProfileController;
use App\Http\Controllers\API\Account\SessionController;
use App\Http\Controllers\API\Uploads\StorageController;
use Illuminate\Support\Facades\Route;

// Доступно и без подтверждённой почты: экран «подтвердите почту», сессии, хранилище.
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->name('verification.send');

    Route::controller(SessionController::class)->name('sessions.')->group(function () {
        Route::get('sessions', 'index')->name('index');
        Route::delete('sessions/{tokenId}', 'destroy')->whereNumber('tokenId')->name('destroy');
        Route::post('logout-all', 'destroyAll')->name('destroy-all');
    });
});

Route::prefix('storage')->name('storage.')->controller(StorageController::class)->group(function () {
    Route::get('usage', 'show')->name('usage.show');
    Route::delete('attachments', 'destroyMany')->name('attachments.destroy-many');
    Route::delete('attachments/{attachment}', 'destroy')->name('attachments.destroy');
});
