<?php

use App\Http\Controllers\API\Conversations\CallController;
use App\Http\Controllers\API\Conversations\ChannelController;
use App\Http\Controllers\API\Conversations\ChannelMemberController;
use App\Http\Controllers\API\Conversations\IceServersController;
use App\Http\Controllers\API\Conversations\LiveKitController;
use App\Http\Controllers\API\Conversations\MessageController;
use App\Http\Controllers\API\Conversations\WebRTC\CallDiagnosticsController;
use App\Http\Controllers\API\Conversations\WebRTC\SignalController;
use Illuminate\Support\Facades\Route;

// Беседы и участники
Route::prefix('channels')->name('channels.')->group(function () {
    Route::controller(ChannelController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::post('{channel}/read', 'markRead')->name('read');
        // POST, а не PATCH: аватар беседы уходит в multipart.
        Route::post('{channel}', 'update')->name('update');
        Route::delete('{channel}', 'leave')->name('leave');
    });

    Route::prefix('{channel}/members')->name('members.')
        ->controller(ChannelMemberController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('{member}', 'destroy')->name('destroy');
            Route::patch('{member}/role', 'update')->name('role.update');
        });
});

// Сообщения
Route::prefix('messages')->name('messages.')->controller(MessageController::class)->group(function () {
    Route::get('stickers/recent', 'recentStickers')->name('stickers.recent');
    Route::get('{channel}', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::post('attachment', 'storeAttachment')->middleware('throttle:attachments')->name('attachment.store');
    Route::post('sticker', 'storeSticker')->name('sticker.store');
    Route::post('{message}/reactions', 'toggleReaction')->name('reactions.toggle');
    Route::patch('{message}', 'update')->name('update');
    Route::delete('{message}', 'destroy')->name('destroy');
});

// Звонки и WebRTC
Route::post('webrtc/{call}/diagnostics', CallDiagnosticsController::class)
    ->middleware('throttle:call-diagnostics')->name('webrtc.diagnostics.store');
Route::get('ice-servers', IceServersController::class)->name('ice-servers.index');
// Звонки через LiveKit (SFU): включён ли он и токен комнаты звонка.
Route::get('livekit', [LiveKitController::class, 'status'])->name('livekit.status.show');
Route::post('calls/{channel}/livekit', [LiveKitController::class, 'callToken'])->name('calls.livekit.token.issue');
Route::post('webrtc/{call}/signal', [SignalController::class, 'store'])
    ->middleware('throttle:webrtc-signal')
    ->name('webrtc.signal.store');

Route::prefix('calls')->name('calls.')->controller(CallController::class)->group(function () {
    Route::get('active', 'active')->name('active.index');
    Route::post('{channel}', 'store')->name('store');
    Route::post('{channel}/accept', 'accept')->name('accept');
    Route::post('{channel}/decline', 'decline')->name('decline');
    Route::post('{channel}/leave', 'leave')->name('leave');
    Route::post('{channel}/heartbeat', 'heartbeat')->name('heartbeat');
    Route::post('{channel}/screen-preview', 'storeScreenPreview')->name('screen-preview.store');
    Route::get('{channel}/screen-preview/{user}', 'showScreenPreview')->name('screen-preview.show');
});
