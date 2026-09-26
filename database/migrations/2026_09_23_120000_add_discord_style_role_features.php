<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server_roles', function (Blueprint $table) {
            // Показывать участников с этой ролью отдельной группой в списке участников.
            $table->boolean('hoist')->default(false)->after('is_default');
        });

        Schema::table('server_members', function (Blueprint $table) {
            // Заглушен / без звука модератором во всех голосовых каналах сервера (server mute/deafen).
            $table->boolean('voice_muted')->default(false)->after('nickname');
            $table->boolean('voice_deafened')->default(false)->after('voice_muted');
        });

        Schema::create('server_channel_member_overwrites', function (Blueprint $table) {
            $table->unsignedBigInteger('server_channel_id');
            $table->unsignedBigInteger('server_member_id');
            $table->unsignedBigInteger('allow')->default(0); // битовая маска App\Enums\ServerPermission
            $table->unsignedBigInteger('deny')->default(0);
            $table->timestamps();

            $table->foreign('server_channel_id')->references('id')->on('server_channels');
            $table->foreign('server_member_id')->references('id')->on('server_members');
            $table->primary(['server_channel_id', 'server_member_id'], 'server_channel_member_overwrites_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_channel_member_overwrites');

        Schema::table('server_members', function (Blueprint $table) {
            $table->dropColumn(['voice_muted', 'voice_deafened']);
        });

        Schema::table('server_roles', function (Blueprint $table) {
            $table->dropColumn('hoist');
        });
    }
};
