<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API
|--------------------------------------------------------------------------
| Здесь видны границы доступа. Маршруты по областям лежат в routes/api/.
| Порядок подключения сохраняет действующие URL и методы клиентов.
*/

require __DIR__.'/api/public.php';

Route::middleware(['auth:sanctum', 'demo.restrict'])->group(function () {
    // Загрузка по частям использует отдельный лимит запросов.
    require __DIR__.'/api/uploads.php';

    Route::middleware('throttle:api')->group(function () {
        // Профиль, сессии и хранилище доступны до подтверждения почты.
        require __DIR__.'/api/account-unverified.php';

        Route::middleware('verified.email')->group(function () {
            require __DIR__.'/api/account.php';
            require __DIR__.'/api/conversations.php';
            require __DIR__.'/api/servers.php';
            require __DIR__.'/api/social.php';
            require __DIR__.'/api/support.php';
            require __DIR__.'/api/integrations.php';
            require __DIR__.'/api/admin.php';
        });
    });
});
