<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('server_id');
            $table->unsignedBigInteger('category_id')->nullable(); // самоссылка: канал внутри категории
            $table->string('name');
            $table->unsignedTinyInteger('kind'); // App\Enums\ServerChannelKind: Text/Voice/Forum/Category
            $table->text('topic')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->foreign('server_id')->references('id')->on('servers');
            $table->foreign('category_id')->references('id')->on('server_channels');
            $table->index(['server_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_channels');
    }
};
