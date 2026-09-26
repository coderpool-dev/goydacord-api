<?php

namespace App\Console\Commands;

use App\Services\Account\DemoGuestService;
use Illuminate\Console\Command;

class PruneDemoGuests extends Command
{
    protected $signature = 'demo:prune';

    protected $description = 'Delete demo guest accounts older than the demo TTL together with their server and chats';

    public function handle(DemoGuestService $demo): int
    {
        $deleted = $demo->pruneExpired();

        if ($deleted > 0) {
            $this->info("Removed {$deleted} demo guest(s).");
        }

        return self::SUCCESS;
    }
}
