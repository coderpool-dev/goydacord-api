<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 32);            // harassment|spam|scam|content|other
            $table->text('comment')->nullable();
            $table->string('status', 16)->default('new'); // new|reviewed|dismissed
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            // Профиль нарушителя открывают по target_id — сколько на него всего жалоб.
            $table->index('target_id');
            // Повторные жалобы одного человека на одного и того же отсекает контроллер,
            // пока предыдущая не разобрана. Уникальный индекс сюда не годится: он бы
            // ломал перевод второй жалобы в тот же статус.
            $table->index(['reporter_id', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reports');
    }
};
