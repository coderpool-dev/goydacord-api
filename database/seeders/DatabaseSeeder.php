<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Демо-данные нужны только для локальной проверки.
        if (app()->isProduction()) {
            return;
        }

        $this->call(DemoSeeder::class);
    }
}
