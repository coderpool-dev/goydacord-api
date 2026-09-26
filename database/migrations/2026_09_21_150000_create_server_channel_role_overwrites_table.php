<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_channel_role_overwrites', function (Blueprint $table) {
            $table->unsignedBigInteger('server_channel_id');
            $table->unsignedBigInteger('server_role_id');
            $table->unsignedBigInteger('allow')->default(0); // битовая маска App\Enums\ServerPermission
            $table->unsignedBigInteger('deny')->default(0);
            $table->timestamps();

            $table->foreign('server_channel_id')->references('id')->on('server_channels');
            $table->foreign('server_role_id')->references('id')->on('server_roles');
            $table->primary(['server_channel_id', 'server_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_channel_role_overwrites');
    }
};
