<?php

namespace App\Providers;

use App\Models\Account\PersonalAccessToken;
use App\Models\Conversations\Attachment;
use App\Models\Conversations\Call;
use App\Models\Conversations\Channel;
use App\Models\Conversations\Message;
use App\Models\Servers\Server;
use App\Models\Servers\ServerChannel;
use App\Models\Uploads\UploadSession;
use App\Models\User;
use App\Policies\AttachmentPolicy;
use App\Policies\CallPolicy;
use App\Policies\ChannelPolicy;
use App\Policies\MessagePolicy;
use App\Policies\ServerChannelPolicy;
use App\Policies\ServerPolicy;
use App\Policies\UploadSessionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Запросов в минуту на один лимит. У каждого лимита свой счётчик: безымянные
     * throttle:N,1 делят один счётчик на пользователя или IP, и частые пинги
     * расходовали лимит входа и восстановления пароля.
     */
    private const RATE_LIMITS_PER_MINUTE = [
        // Публичные эндпоинты, считаются по IP.
        'login' => 30,            // опечатки в пароле и смена аккаунта — обычное дело
        'register' => 10,
        'forgot-password' => 5,   // каждый запрос отправляет письмо
        'reset-password' => 10,
        'verify-email' => 10,
        'ping' => 120,
        'presence' => 120,
        'feedback' => 5,
        'game-icons' => 120,

        // Вошедшие пользователи, считаются по id.
        'api' => 1000,            // во время звонка клиент шлёт много сигналов и пингов
        'uploads' => 6000,        // большой файл уходит многими кусками
        'attachments' => 30,
        // Отдельный, более тесный потолок для сигналинга: баг в клиенте (похоже на SDP glare
        // при почти одновременном перезаходе обеих сторон в звонок) один раз дал 5000+ сигналов
        // за 6 секунд с одного пользователя под общим 'api' — этот путь и так самый горячий
        // по трафику в звонке, отдельный счётчик режет зацикливание быстрее, не трогая лимит
        // остальных эндпоинтов. 300/мин с запасом покрывает легитимный всплеск кандидатов
        // при обычном ICE-restart.
        'webrtc-signal' => 300,
        'call-diagnostics' => 30,
        'reports' => 10,
        'link-preview' => 60,
        'mutual-friends' => 120,
        'music-history' => 120,
        'server-create' => 10,
        'invite-create' => 30,
        'invite-join' => 30,
        'invite-preview' => 60,    // публичный, без логина — считается по IP
        'settings-write' => 60,    // роли, ники, модерация голоса, отключение уведомлений
        'game-icon-submissions' => 6,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(Call::class, CallPolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(Message::class, MessagePolicy::class);
        Gate::policy(Server::class, ServerPolicy::class);
        Gate::policy(ServerChannel::class, ServerChannelPolicy::class);
        Gate::policy(UploadSession::class, UploadSessionPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        foreach (self::RATE_LIMITS_PER_MINUTE as $name => $maxAttempts) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($maxAttempts)
                ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        }

        // Демо-вход каждый раз создаёт аккаунт с сервером и перепиской. Лимиты по IP на минуту
        // и сутки плюс общий потолок в час — на случай, если кнопку дёргают с множества адресов.
        RateLimiter::for('demo', fn (Request $request) => [
            Limit::perMinute(3)->by('demo-minute:'.$request->ip()),
            Limit::perDay(20)->by('demo-day:'.$request->ip()),
            Limit::perHour(300)->by('demo-global'),
        ]);

        RateLimiter::for('game-icon-daily', fn (Request $request) => Limit::perDay(30)
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
