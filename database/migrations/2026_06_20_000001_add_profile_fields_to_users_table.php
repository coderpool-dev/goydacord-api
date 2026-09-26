<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Баннер профиля — храним имя файла как у аватара (storage/banners/{id}.jpg).
            $table->string('banner')->nullable()->after('avatar');
            // Цвет-заглушка под баннер, когда картинки нет (hex, напр. #5865f2).
            $table->string('banner_color', 9)->nullable()->after('banner');
            // Присутствие, выбранное пользователем вручную: online | idle | dnd | invisible.
            $table->string('presence', 16)->default('online')->after('banner_color');
            // Кастом-статус: эмодзи + короткий текст ("🎮 играю в доту").
            $table->string('status_emoji', 32)->nullable()->after('presence');
            $table->string('status_text', 128)->nullable()->after('status_emoji');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['banner', 'banner_color', 'presence', 'status_emoji', 'status_text']);
        });
    }
};
