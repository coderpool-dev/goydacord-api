<?php

use App\Http\Middleware\EnsureEmailIsVerifiedForApi;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\RestrictDemoGuest;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);
        // За nginx/Cloudflare без этого request()->ip() всегда 127.0.0.1.
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'verified.email' => EnsureEmailIsVerifiedForApi::class,
            'demo.restrict' => RestrictDemoGuest::class,
        ]);
        // Роута login нет: гостю отвечаем 401, а не редиректом. Задаётся именно здесь,
        // иначе Laravel после этого колбэка поставит редирект на route('login').
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->api(prepend: [ForceJsonResponse::class]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        // Ретеншн истории и чистка брошенных загрузок (срок — uploads.retention_days).
        $schedule->command('attachments:prune')->dailyAt('04:00');
        $schedule->command('calls:reap-stale')->everyMinute()->withoutOverlapping();
        // Десктоп мог закрыться, не сообщив о конце игры: такие сессии закрываем по TTL статуса.
        $schedule->command('activity:close-abandoned-games')->everyMinute()->withoutOverlapping();
        $schedule->command('users:prune-unverified')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('demo:prune')->everyFifteenMinutes()->withoutOverlapping();
        // Sanctum не удаляет просроченные токены сам — без чистки они висят в «Сессиях».
        $schedule->command('tokens:prune-expired')->dailyAt('04:10');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(fn (AuthenticationException $e) => response()->json([
            'status' => 'error',
            'message' => 'Не авторизован',
        ], 401));

        $exceptions->render(fn (ValidationException $e) => response()->json([
            'status' => 'error',
            'message' => 'Ошибка валидации',
            'errors' => $e->errors(),
        ], 422));

        // До этого колбэка Laravel уже превратил ModelNotFoundException и
        // AuthorizationException в HTTP-исключения, поэтому 403/404 приходят сюда.
        $exceptions->render(function (HttpExceptionInterface $e) {
            $message = match (true) {
                // Текст исключения содержит имя класса модели — наружу его не отдаём.
                $e->getPrevious() instanceof ModelNotFoundException => 'Не найдено',
                $e->getMessage() !== '' => $e->getMessage(),
                $e->getStatusCode() === 403 => 'Нет доступа',
                $e->getStatusCode() === 404 => 'Не найдено',
                default => 'Ошибка',
            };

            return response()->json([
                'status' => 'error',
                'message' => $message,
            ], $e->getStatusCode(), $e->getHeaders());
        });

        // Ответы с ошибкой собираются вне middleware, и HandleCors их не видит.
        // Без заголовка браузер прячет от фронта даже текст ошибки.
        $exceptions->respond(function (Response $response) {
            $origin = request()->headers->get('Origin', '');

            if ($origin !== '' && in_array($origin, config('cors.allowed_origins'), true)) {
                $response->headers->set('Access-Control-Allow-Origin', $origin);
                $response->headers->set('Access-Control-Allow-Credentials', 'false');
            }

            return $response;
        });
    })->create();
