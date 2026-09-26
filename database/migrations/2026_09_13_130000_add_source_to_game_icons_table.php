<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_icons', function (Blueprint $table) {
            // sha256 картинки — чтобы не переливать одинаковые иконки.
            $table->string('icon_hash', 64)->nullable()->after('file');
            // Приоритет источника: 3 = арт из папки игры, 1 = иконка из .exe.
            // Лучший источник может заменить худший, худший — нет.
            $table->unsignedTinyInteger('source_priority')->default(1)->after('icon_hash');
        });
    }

    public function down(): void
    {
        Schema::table('game_icons', function (Blueprint $table) {
            $table->dropColumn(['icon_hash', 'source_priority']);
        });
    }
};
