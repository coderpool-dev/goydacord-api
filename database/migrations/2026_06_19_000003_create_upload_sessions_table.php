<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Сессия докачиваемой (chunked) загрузки: пока клиент шлёт куски,
        // здесь копится received_size, а байты пишутся во временный файл tmp_path.
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('channel_id');
            $table->string('filename');
            $table->string('mime')->default('application/octet-stream');
            $table->unsignedBigInteger('total_size');
            $table->unsignedBigInteger('received_size')->default(0);
            $table->string('tmp_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};
