<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('game_status_synced_at')->nullable()->after('game_status_text');
        });

        // Уже сохранённые тексты статусов считаем свежими на момент миграции,
        // чтобы не сбросить их у людей, которые сейчас реально играют.
        DB::table('users')
            ->whereNotNull('game_status_text')
            ->update(['game_status_synced_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('game_status_synced_at');
        });
    }
};
