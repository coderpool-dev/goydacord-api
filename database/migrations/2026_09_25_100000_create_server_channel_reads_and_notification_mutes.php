<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // До какого сообщения пользователь дочитал текстовый канал сервера — жирные каналы, точка
        // у сервера и разделитель «Новые сообщения». У ЛС то же хранится в channels_members.
        Schema::create('server_channel_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('server_channel_id');
            $table->unsignedBigInteger('last_read_message_id')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'server_channel_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('server_channel_id')->references('id')->on('server_channels')->cascadeOnDelete();
        });

        // «Заглушить» чат / канал / сервер: без пушей и звука. muted_until = null — пока не включат.
        Schema::create('notification_mutes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->timestamp('muted_until')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'target_type', 'target_id']);
            $table->index(['target_type', 'target_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_mutes');
        Schema::dropIfExists('server_channel_reads');
    }
};
