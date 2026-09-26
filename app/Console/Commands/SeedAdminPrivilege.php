<?php

namespace App\Console\Commands;

use App\Services\Admin\AdminPrivilegeService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class SeedAdminPrivilege extends Command
{
    protected $signature = 'admin:seed {login? : логин пользователя}';

    protected $description = 'Выдать права администратора пользователю (логин из аргумента или ADMIN_SEED_LOGIN)';

    public function handle(AdminPrivilegeService $admins): int
    {
        $login = trim((string) ($this->argument('login') ?: config('app.admin_seed_login')));

        if ($login === '') {
            $this->error('Укажите логин: php artisan admin:seed <login> или ADMIN_SEED_LOGIN в .env');

            return self::FAILURE;
        }

        try {
            $user = $admins->findByLogin($login);
        } catch (ValidationException) {
            $this->error("Пользователь с логином {$login} не найден");

            return self::FAILURE;
        }

        $this->info($admins->grant($user)
            ? "Админ выдан: {$user->login} (#{$user->id})"
            : "Уже админ: {$user->login} (#{$user->id})");

        return self::SUCCESS;
    }
}
