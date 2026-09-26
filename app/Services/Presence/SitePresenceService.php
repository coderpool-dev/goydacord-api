<?php

namespace App\Services\Presence;

use App\Models\Presence\SitePresenceSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Кто сейчас на сайте, включая гостей: открытая вкладка периодически присылает пинг. */
class SitePresenceService
{
    public const LIVE_SECONDS = 90;

    /** Старые сессии удаляем не на каждом пинге, а не чаще раза в минуту. */
    private const PRUNE_INTERVAL_SECONDS = 60;

    /** Кроме домена сайта из app.frontend_url, переходами внутри сайта считаются эти адреса. */
    private const LOCAL_HOSTS = ['goida.love', 'localhost', '127.0.0.1'];

    /** Часть домена источника => подпись в админке. */
    private const REFERRER_LABELS = [
        'google.' => 'Google',
        'yandex.' => 'Яндекс',
        'vk.com' => 'ВКонтакте',
        'vk.ru' => 'ВКонтакте',
        't.me' => 'Telegram',
        'telegram.' => 'Telegram',
        'discord.' => 'Discord',
    ];

    public function ping(?User $user, string $sessionKey, string $path, ?string $referrer, ?string $country = null): void
    {
        $sessionKey = Str::limit(trim($sessionKey), 64, '');

        if ($sessionKey === '') {
            return;
        }

        $referrer = $referrer ? Str::limit(trim($referrer), 1024, '') : null;
        $country = $country ? Str::limit(trim($country), 64, '') : null;
        $path = Str::limit(trim($path) !== '' ? trim($path) : '/', 512, '');
        $now = now();

        try {
            SitePresenceSession::query()->upsert([
                [
                    'session_key' => $sessionKey,
                    'user_id' => $user?->id,
                    'path' => $path,
                    'referrer' => $referrer,
                    'referrer_host' => $this->referrerHost($referrer),
                    'country' => $country,
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ], ['session_key'], [
                'user_id',
                'path',
                'referrer',
                'referrer_host',
                'last_seen_at',
                'updated_at',
            ]);
        } catch (UniqueConstraintViolationException) {
            SitePresenceSession::query()
                ->where('session_key', $sessionKey)
                ->update([
                    'user_id' => $user?->id,
                    'path' => $path,
                    'referrer' => $referrer,
                    'referrer_host' => $this->referrerHost($referrer),
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        if ($country !== null) {
            SitePresenceSession::query()
                ->where('session_key', $sessionKey)
                ->whereNull('country')
                ->update(['country' => $country]);
        }

        $this->pruneStale();
    }

    public function liveVisitors(): array
    {
        $this->pruneStale();

        return SitePresenceSession::query()
            ->where('last_seen_at', '>=', now()->subSeconds(self::LIVE_SECONDS))
            ->with('user:id,login,name,avatar')
            ->orderByDesc('last_seen_at')
            ->limit(80)
            ->get()
            ->map(fn (SitePresenceSession $session) => [
                'session_key' => $session->session_key,
                'user' => $session->user ? [
                    'id' => (int) $session->user->id,
                    'login' => $session->user->login,
                    'name' => $session->user->name,
                    'avatar' => $session->user->avatar,
                ] : null,
                'path' => $session->path,
                'referrer' => $session->referrer,
                'referrer_host' => $session->referrer_host,
                'referrer_label' => $this->referrerLabel($session->referrer_host, $session->referrer),
                'country' => $session->country,
                'is_guest' => $session->user === null,
                'seconds_on_site' => max(0, (int) $session->first_seen_at->diffInSeconds($session->last_seen_at)),
                'first_seen_at' => $session->first_seen_at->toIso8601String(),
                'last_seen_at' => $session->last_seen_at->toIso8601String(),
            ])
            ->all();
    }

    private function pruneStale(): void
    {
        if (! Cache::add('site-presence:pruned', true, self::PRUNE_INTERVAL_SECONDS)) {
            return;
        }

        SitePresenceSession::query()
            ->where('last_seen_at', '<', now()->subSeconds(self::LIVE_SECONDS * 4))
            ->delete();
    }

    private function referrerHost(?string $referrer): ?string
    {
        $host = $referrer ? parse_url($referrer, PHP_URL_HOST) : null;

        return is_string($host) && $host !== '' ? Str::lower($host) : null;
    }

    private function referrerLabel(?string $host, ?string $referrer): string
    {
        if (! $referrer || ! $host) {
            return 'Прямой заход';
        }

        $siteHost = (string) parse_url((string) config('app.frontend_url'), PHP_URL_HOST);
        $isSite = $siteHost !== '' && ($host === $siteHost || Str::endsWith($host, '.'.$siteHost));

        if ($isSite || in_array($host, self::LOCAL_HOSTS, true)) {
            return 'Внутри сайта';
        }

        foreach (self::REFERRER_LABELS as $needle => $label) {
            if (Str::contains($host, $needle)) {
                return $label;
            }
        }

        return $host;
    }
}
