<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Живая presence-сессия участника звонка: одна строка на (звонок, устройство/вкладка).
     * Источник правды «кто реально в звонке прямо сейчас» — обновляется heartbeat-ом,
     * протухшие строки выметает reaper. На основе этого синхронизируется
     * channels_members.call_status (его читают старые запросы участников).
     */
    public function up(): void
    {
        Schema::create('call_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('call_id', 64);             // calls.call_id (uuid, 36 симв.)
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('channel_id');
            $table->string('session_id', 64);          // генерится клиентом, по одной на вкладку/устройство
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('call_id');
            $table->index('channel_id');
            $table->index(['user_id', 'call_id']);
            $table->index('last_seen_at');
            // Одна и та же вкладка (session_id) не дублируется в рамках звонка.
            $table->unique(['call_id', 'session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_sessions');
    }
};
