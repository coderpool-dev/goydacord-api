<?php

use App\Http\Controllers\API\Uploads\UploadController;
use Illuminate\Support\Facades\Route;

// Большой файл уходит многими кусками, поэтому у загрузок свой, большой лимит.
Route::prefix('uploads')->name('uploads.')->middleware('throttle:uploads')
    ->controller(UploadController::class)->group(function () {
        Route::post('/', 'store')->name('store');
        Route::get('{upload}', 'show')->name('show');
        Route::patch('{upload}', 'update')->name('update');
        Route::post('{upload}/complete', 'complete')->name('complete');
    });
