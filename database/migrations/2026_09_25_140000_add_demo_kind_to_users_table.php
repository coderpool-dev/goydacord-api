<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Демо-вход с лендинга: guest — временный аккаунт посетителя (удаляется через сутки),
        // persona — постоянные демо-друзья, от чьего имени написана переписка в демо.
        // У настоящих пользователей null.
        Schema::table('users', function (Blueprint $table) {
            $table->string('demo_kind', 16)->nullable()->after('email_verified_at');
            $table->index(['demo_kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['demo_kind', 'created_at']);
            $table->dropColumn('demo_kind');
        });
    }
};
