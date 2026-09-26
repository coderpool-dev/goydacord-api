<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_invites', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->unsignedBigInteger('server_id');
            $table->unsignedBigInteger('channel_id')->nullable(); // на какой канал ведёт приглашение
            $table->unsignedBigInteger('created_by');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers');
            $table->foreign('channel_id')->references('id')->on('server_channels');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_invites');
    }
};
