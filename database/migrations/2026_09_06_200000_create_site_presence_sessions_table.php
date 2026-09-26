<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_presence_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('path', 512)->default('/');
            $table->string('referrer', 1024)->nullable();
            $table->string('referrer_host', 255)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->index('last_seen_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_presence_sessions');
    }
};
