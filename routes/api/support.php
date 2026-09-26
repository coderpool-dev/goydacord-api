<?php

use App\Http\Controllers\API\Support\ReportController;
use App\Http\Controllers\API\Support\SupportThreadController;
use Illuminate\Support\Facades\Route;

// Поддержка и жалобы
Route::prefix('support')->name('support.')->controller(SupportThreadController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::get('unread', 'unreadCount')->name('unread.show');
    Route::post('messages', 'storeMessage')->name('messages.store');
});

Route::post('reports', [ReportController::class, 'store'])->middleware('throttle:reports')->name('reports.store');
