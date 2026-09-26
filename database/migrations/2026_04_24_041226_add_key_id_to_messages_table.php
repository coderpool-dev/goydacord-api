<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Номер ключа шифрования, которым зашифрован текст: ключи можно менять, не перешифровывая историю. */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->integer('key_id')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('key_id');
        });
    }
};
