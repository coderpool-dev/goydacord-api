<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sanctum сам считает токен старше sanctum.expiration недействительным (перестаёт
 * пускать по нему), но строку из personal_access_tokens не удаляет — мёртвые
 * сессии годами копятся и висят в разделе «Сессии» настроек как живые устройства.
 */
class PruneExpiredTokens extends Command
{
    protected $signature = 'tokens:prune-expired';

    protected $description = 'Удаляет токены Sanctum старше срока действия (config/sanctum.php: expiration)';

    public function handle(): int
    {
        $minutes = (int) config('sanctum.expiration');

        if ($minutes <= 0) {
            $this->info('Токены бессрочные (sanctum.expiration=0) — нечего удалять.');

            return self::SUCCESS;
        }

        $deleted = PersonalAccessToken::where('created_at', '<', now()->subMinutes($minutes))->delete();

        if ($deleted > 0) {
            $this->info("Удалено просроченных токенов: {$deleted}.");
        }

        return self::SUCCESS;
    }
}
