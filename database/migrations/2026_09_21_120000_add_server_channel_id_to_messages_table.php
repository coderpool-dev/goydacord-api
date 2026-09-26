<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Текстовые каналы серверов переиспользуют Message/EncryptService/AttachmentService/
 * MessageResource целиком (вложения, реакции, шифрование - всё бесплатно) вместо
 * отдельного пайплайна сообщений (см. план "Discord-style Серверы", решение #3).
 * channels_id становится nullable: у сообщения задан ровно один из двух ключей.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->unsignedBigInteger('server_channel_id')->nullable()->after('channels_id');
            $table->foreign('server_channel_id')->references('id')->on('server_channels');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('channels_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['server_channel_id']);
            $table->dropColumn('server_channel_id');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('channels_id')->nullable(false)->change();
        });
    }
};
