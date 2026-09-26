<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Приложение — только API, а фронт не шлёт заголовок Accept: application/json.
 * Без него Laravel на ошибку валидации или авторизации пытается ответить редиректом
 * на страницу входа, которой здесь нет, и запрос падает в 500 вместо 401/422.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
