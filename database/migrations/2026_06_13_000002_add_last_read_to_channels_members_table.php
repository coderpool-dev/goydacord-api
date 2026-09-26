<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels_members', function (Blueprint $table) {
            $table->unsignedBigInteger('last_read_message_id')->nullable()->after('call_status');
        });
    }

    public function down(): void
    {
        Schema::table('channels_members', function (Blueprint $table) {
            $table->dropColumn('last_read_message_id');
        });
    }
};
