<?php

namespace App\Console\Commands;

use App\Services\Account\UnverifiedUserService;
use Illuminate\Console\Command;

class PruneUnverifiedUsers extends Command
{
    protected $signature = 'users:prune-unverified';

    protected $description = 'Delete accounts that were not email-verified within the TTL window';

    public function handle(UnverifiedUserService $service): int
    {
        $deleted = $service->pruneExpired();

        if ($deleted > 0) {
            $this->info("Removed {$deleted} unverified user(s).");
        }

        return self::SUCCESS;
    }
}
