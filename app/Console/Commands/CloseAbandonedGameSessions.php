<?php

namespace App\Console\Commands;

use App\Services\Presence\ActivityService;
use Illuminate\Console\Command;

class CloseAbandonedGameSessions extends Command
{
    protected $signature = 'activity:close-abandoned-games';

    protected $description = 'Closes game sessions whose client stopped confirming the game status';

    public function handle(ActivityService $activity): int
    {
        $closed = $activity->closeAbandonedSessions();

        if ($closed > 0) {
            $this->info("Closed {$closed} abandoned game session(s).");
        }

        return self::SUCCESS;
    }
}
