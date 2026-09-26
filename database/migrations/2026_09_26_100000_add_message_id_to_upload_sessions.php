<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            // Keep the receipt until session expiry so retries return the same message.
            // No cascading FK: deleting a message must not make its upload reusable.
            $table->unsignedBigInteger('message_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('upload_sessions', fn (Blueprint $table) => $table->dropColumn('message_id'));
    }
};
