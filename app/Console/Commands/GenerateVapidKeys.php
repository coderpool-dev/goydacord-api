<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Сгенерировать пару VAPID-ключей для Web Push — один раз на окружение, положить в .env. */
class GenerateVapidKeys extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Сгенерировать VAPID-ключи для Web Push (VAPID_PUBLIC_KEY / VAPID_PRIVATE_KEY)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

        return self::SUCCESS;
    }
}
