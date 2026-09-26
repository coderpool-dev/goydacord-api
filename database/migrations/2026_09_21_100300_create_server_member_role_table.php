<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_member_role', function (Blueprint $table) {
            $table->unsignedBigInteger('server_member_id');
            $table->unsignedBigInteger('server_role_id');

            $table->foreign('server_member_id')->references('id')->on('server_members');
            $table->foreign('server_role_id')->references('id')->on('server_roles');
            $table->primary(['server_member_id', 'server_role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_member_role');
    }
};
