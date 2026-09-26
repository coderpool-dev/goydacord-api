<?php

namespace App\Console\Commands;

use App\Models\Account\PersonalAccessToken;
use App\Services\Presence\GeoIpService;
use Illuminate\Console\Command;

/**
 * Дозаполняет город и страну у токенов, где их нет.
 *
 * Колонка city появилась позже самих токенов, а город пишется только при входе,
 * поэтому у всех, кто с тех пор не перелогинивался, он остался пустым.
 */
class BackfillTokenGeo extends Command
{
    protected $signature = 'tokens:backfill-geo
                            {--limit=0 : Обработать не больше N токенов (0 — все)}
                            {--dry-run : Показать, что изменилось бы, ничего не записывая}';

    protected $description = 'Fill in missing city/country on personal access tokens from their stored IP';

    /** ip-api отдаёт 45 запросов в минуту с одного адреса — держимся заметно ниже. */
    private const LOOKUPS_PER_MINUTE = 35;

    public function handle(GeoIpService $geoIp): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $tokensQuery = PersonalAccessToken::query()
            ->whereNotNull('ip_address')
            ->where(function ($query) {
                $query->whereNull('city')
                    ->orWhereNull('country')
                    ->orWhereIn('country', ['Unknown', '']);
            })
            ->orderByDesc('last_used_at');

        if ($limit > 0) {
            $tokensQuery->limit($limit);
        }

        $tokens = $tokensQuery->get();

        if ($tokens->isEmpty()) {
            $this->info('Токенов без гео не найдено.');

            return self::SUCCESS;
        }

        // Один IP на несколько токенов — разрешаем его один раз.
        $uniqueIps = $tokens->pluck('ip_address')->unique()->values();
        $this->info("Токенов: {$tokens->count()}, уникальных IP: {$uniqueIps->count()}.");

        $resolved = [];
        $apiCalls = 0;
        $progressBar = $this->output->createProgressBar($uniqueIps->count());
        $progressBar->start();

        foreach ($uniqueIps as $ip) {
            // Троттлим только реальные обращения — попадание в кэш ничего не стоит.
            $wasCached = $geoIp->isLookupCached($ip);
            $resolved[$ip] = $geoIp->lookupIp($ip);

            if (! $wasCached) {
                $apiCalls++;
                if ($apiCalls % self::LOOKUPS_PER_MINUTE === 0) {
                    $progressBar->clear();
                    $this->comment('Пауза 60 секунд, чтобы не упереться в лимит провайдера…');
                    $progressBar->display();
                    sleep(60);
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $updated = 0;
        $noCity = 0;

        foreach ($tokens as $token) {
            $location = $resolved[$token->ip_address] ?? null;
            if (! $location || $location['country'] === 'Unknown' || $location['country'] === 'local') {
                continue;
            }

            $changed = false;

            if (blank($token->country) || in_array($token->country, ['Unknown', ''], true)) {
                $token->country = $location['country'];
                $changed = true;
            }
            if (blank($token->city) && filled($location['city'])) {
                $token->city = $location['city'];
                $changed = true;
            }

            if (blank($token->city) && blank($location['city'])) {
                $noCity++;
            }

            if ($changed) {
                $updated++;
                if (! $dryRun) {
                    $token->save();
                }
            }
        }

        $prefix = $dryRun ? '[dry-run] ' : '';
        $this->info("{$prefix}Обновлено токенов: {$updated}.");

        if ($noCity > 0) {
            $this->warn("У {$noCity} токенов провайдер не знает города — обычно это мобильные операторы, CGNAT или VPN.");
        }

        return self::SUCCESS;
    }
}
