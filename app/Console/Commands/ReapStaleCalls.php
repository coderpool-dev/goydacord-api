<?php

namespace App\Console\Commands;

use App\Services\Conversations\CallService;
use Illuminate\Console\Command;

class ReapStaleCalls extends Command
{
    protected $signature = 'calls:reap-stale';

    protected $description = 'Removes stale call sessions and ends calls that stayed understaffed';

    public function handle(CallService $callService): int
    {
        $ended = $callService->reapStaleCalls();

        if ($ended > 0) {
            $this->info("Finalized {$ended} stale call(s).");
        }

        return self::SUCCESS;
    }
}
