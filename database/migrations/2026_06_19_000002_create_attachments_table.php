<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            // message_id заполняется после создания сообщения на complete-шаге.
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();   // владелец — для квоты
            $table->unsignedBigInteger('channel_id')->index();
            $table->string('disk_path')->unique();
            $table->string('name');
            $table->string('mime')->default('application/octet-stream');
            $table->unsignedBigInteger('size')->default(0);   // bigint: 10 ГБ > int32
            $table->string('kind')->default('file');          // image | file
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->boolean('encrypted')->default(false);
            $table->unsignedInteger('key_id')->nullable();
            // last_accessed_at — для LRU-вытеснения при нехватке места.
            $table->timestamp('last_accessed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
