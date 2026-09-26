<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_stats', function (Blueprint $table) {
            $table->string('key', 64)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('stats_daily', function (Blueprint $table) {
            $table->date('day')->primary();
            $table->unsignedInteger('online_peak')->default(0);
            $table->unsignedInteger('signups')->default(0);
            $table->unsignedInteger('calls_started')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stats_daily');
        Schema::dropIfExists('app_stats');
    }
};
