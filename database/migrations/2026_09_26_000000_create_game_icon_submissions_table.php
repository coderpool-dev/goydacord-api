<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_icon_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->string('file');
            $table->string('icon_hash', 64);
            $table->string('source', 32)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('pending_key')->nullable()->unique();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['uploaded_by', 'slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_icon_submissions');
    }
};
