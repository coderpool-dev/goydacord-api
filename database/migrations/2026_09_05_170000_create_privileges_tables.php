<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privileges', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->timestamps();
        });

        Schema::create('privilege_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('privilege_id')->constrained('privileges')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'privilege_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privilege_user');
        Schema::dropIfExists('privileges');
    }
};
