<?php

use App\Http\Controllers\API\Servers\ServerAuditLogController;
use App\Http\Controllers\API\Servers\ServerBanController;
use App\Http\Controllers\API\Servers\ServerChannelCallController;
use App\Http\Controllers\API\Servers\ServerChannelController;
use App\Http\Controllers\API\Servers\ServerChannelMessageController;
use App\Http\Controllers\API\Servers\ServerController;
use App\Http\Controllers\API\Servers\ServerInviteController;
use App\Http\Controllers\API\Servers\ServerMemberController;
use App\Http\Controllers\API\Servers\ServerRoleController;
use App\Http\Controllers\API\Servers\ServerVoiceModerationController;
use Illuminate\Support\Facades\Route;

// Серверы: каналы, участники, роли, баны, приглашения.
Route::prefix('servers')->name('servers.')->group(function () {
    Route::controller(ServerController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:server-create')->name('store');
        Route::post('{server}', 'update')->name('update');
        Route::delete('{server}', 'destroy')->name('destroy');
        Route::post('{server}/leave', 'leave')->name('leave');
        Route::post('{server}/read', 'markRead')->name('read');
    });

    Route::prefix('{server}/members')->scopeBindings()->name('members.')
        ->controller(ServerMemberController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::delete('{member}', 'destroy')->name('destroy');
            Route::put('{member}/nickname', 'updateNickname')
                ->middleware('throttle:settings-write')
                ->name('nickname.update');
        });

    Route::put(
        '{server}/members/{member}/roles',
        [ServerRoleController::class, 'syncMemberRoles'],
    )
        ->scopeBindings()
        ->middleware('throttle:settings-write')
        ->name('members.roles.update');

    Route::get('{server}/audit-logs', [ServerAuditLogController::class, 'index'])->name('audit-logs.index');

    Route::prefix('{server}/voice/members/{user}')->name('voice.')
        ->controller(ServerVoiceModerationController::class)->group(function () {
            Route::post('state', 'updateState')->middleware('throttle:settings-write')->name('state.update');
            Route::post('move', 'move')->middleware('throttle:settings-write')->name('move');
        });

    Route::prefix('{server}/roles')->scopeBindings()->name('roles.')
        ->controller(ServerRoleController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('throttle:settings-write')->name('store');
            // До {role}, иначе "positions" сматчится как id роли.
            Route::put('positions', 'reorder')->middleware('throttle:settings-write')->name('reorder');
            // PATCH — основной метод для JSON; POST оставлен для действующих клиентов.
            Route::patch('{role}', 'update')->middleware('throttle:settings-write')->name('update');
            Route::post('{role}', 'update')->middleware('throttle:settings-write')->name('update-legacy');
            Route::delete('{role}', 'destroy')->middleware('throttle:settings-write')->name('destroy');
        });

    Route::prefix('{server}/bans')->scopeBindings()->name('bans.')
        ->controller(ServerBanController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::delete('{ban}', 'destroy')->name('destroy');
        });

    Route::prefix('{server}/channels')->name('channels.')
        ->controller(ServerChannelController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('{serverChannel}', 'update')->name('update');
            Route::delete('{serverChannel}', 'destroy')->name('destroy');
        });

    Route::prefix('{server}/invites')->scopeBindings()->name('invites.')
        ->controller(ServerInviteController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->middleware('throttle:invite-create')->name('store');
            // По коду (фронт знает только его, id наружу не отдаётся) и только среди
            // приглашений этого сервера — {invite:code} скоупится через $server->invites().
            Route::delete('{invite:code}', 'destroy')->name('destroy');
        });
});

Route::post('invites/{code}/join', [ServerInviteController::class, 'join'])
    ->middleware('throttle:invite-join')->name('invites.join');

// Сообщения в текстовых каналах сервера — тот же MessageService, что у ЛС/групп.
// update/destroy/toggleReaction переиспользуют общие messages/{message}-роуты выше:
// Message id уникален независимо от канала, MessagePolicy сама резолвит доступ.
Route::prefix('server-channel-messages')->name('server-channel-messages.')
    ->controller(ServerChannelMessageController::class)->group(function () {
        Route::get('{serverChannel}', 'index')->name('index');
        Route::post('{serverChannel}/read', 'markRead')->name('read');
        Route::post('/', 'store')->name('store');
        Route::post('attachment', 'storeAttachment')->middleware('throttle:attachments')->name('attachment.store');
        Route::post('sticker', 'storeSticker')->name('sticker.store');
    });

// Голосовые каналы сервера: вход без звонка-приглашения, как в Discord.
Route::prefix('server-channels/{serverChannel}/calls')->name('server-channels.calls.')
    ->controller(ServerChannelCallController::class)->group(function () {
        Route::get('participants', 'participants')->name('participants.index');
        Route::post('/', 'store')->name('store');
        Route::post('leave', 'leave')->name('leave');
        Route::post('heartbeat', 'heartbeat')->name('heartbeat');
        Route::post('screen-preview', 'storeScreenPreview')->name('screen-preview.store');
        Route::get('screen-preview/{user}', 'showScreenPreview')->name('screen-preview.show');
    });
