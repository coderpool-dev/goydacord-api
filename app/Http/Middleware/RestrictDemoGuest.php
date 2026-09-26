<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Гостю демо-входа закрыто всё, через что можно достучаться до настоящих людей: заявки в друзья,
 * вход на чужие серверы по приглашению, поддержка и жалобы. Остальное в демо работает по-настоящему.
 */
class RestrictDemoGuest
{
    private const BLOCKED_ROUTES = [
        'friends.requests.store',
        'friends.requests.update',
        'invites.join',
        'support.messages.store',
        'reports.store',
        'yandex-music.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isDemoGuest() && ($request->routeIs(...self::BLOCKED_ROUTES) || $this->changesCredentials($request))) {
            throw new ApiException('В демо это недоступно. Зарегистрируйтесь — это бесплатно и займёт минуту', 403, [
                'code' => 'DEMO_RESTRICTED',
            ]);
        }

        return $next($request);
    }

    /**
     * Имя и аватар гость менять может, почту и пароль — нет: иначе чужой адрес можно было бы
     * на сутки занять демо-аккаунтом, и его владелец не смог бы зарегистрироваться.
     */
    private function changesCredentials(Request $request): bool
    {
        return $request->routeIs('auth.profile.update')
            && ($request->has('email') || $request->has('new_password'));
    }
}
