<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Журнал аудита сервера как в Discord: кто, что и с кем сделал (роли, кики, баны, каналы,
        // права, приглашения, модерация голоса, удаление чужих сообщений).
        Schema::create('server_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action', 40);
            $table->string('target_type', 20)->nullable(); // user | role | channel | invite | server | message
            $table->unsignedBigInteger('target_id')->nullable();
            // Имя цели на момент действия — роль/канал могли потом удалить или переименовать.
            $table->string('target_label')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('server_id')->references('id')->on('servers');
            $table->index(['server_id', 'id']);
            $table->index(['server_id', 'action']);
            $table->index(['server_id', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_audit_logs');
    }
};
