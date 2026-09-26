<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Голосовой канал сервера тоже может держать звонок — переиспользуем Call/CallService
 * целиком вместо отдельной presence-модели (см. план "Discord-style Серверы", решение #2).
 * channel_id становится nullable: у звонка задан ровно один из двух ключей.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->unsignedBigInteger('server_channel_id')->nullable()->after('channel_id');
            $table->foreign('server_channel_id')->references('id')->on('server_channels');
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropForeign(['server_channel_id']);
            $table->dropColumn('server_channel_id');
        });

        Schema::table('calls', function (Blueprint $table) {
            $table->unsignedBigInteger('channel_id')->nullable(false)->change();
        });
    }
};
