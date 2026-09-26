<?php

namespace App\Console\Commands;

use App\Services\Account\UserDeletionService;
use Illuminate\Console\Command;

class DeleteUserByLogin extends Command
{
    protected $signature = 'users:delete {login : User login to delete}';

    protected $description = 'Permanently delete a user account and related data';

    public function handle(UserDeletionService $service): int
    {
        $login = (string) $this->argument('login');

        if (! $service->deleteByLogin($login)) {
            $this->error("User @{$login} not found.");

            return self::FAILURE;
        }

        $this->info("Deleted user @{$login}.");

        return self::SUCCESS;
    }
}
