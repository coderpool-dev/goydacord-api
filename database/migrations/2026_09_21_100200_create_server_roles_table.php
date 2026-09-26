<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_roles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id');
            $table->string('name');
            $table->string('color')->default('#99aab5');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedBigInteger('permissions')->default(0); // битовая маска App\Enums\ServerPermission
            $table->boolean('is_default')->default(false); // роль @everyone — неудаляемая, выдаётся всем автоматически
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers');
            $table->index(['server_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_roles');
    }
};
