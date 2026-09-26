<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_messages', function (Blueprint $table) {
            $table->id();
            // Автор — гость с лендинга, поэтому имя и почта хранятся строками,
            // а user_id заполняется только если форму отправил залогиненный.
            $table->string('name', 120);
            $table->string('email', 190);
            $table->text('body');
            $table->string('status', 16)->default('new'); // new|read|archived
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip', 45)->nullable();
            $table->string('country', 64)->nullable();
            $table->string('page', 512)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_messages');
    }
};
