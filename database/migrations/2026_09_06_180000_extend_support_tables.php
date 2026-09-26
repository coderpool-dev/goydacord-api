<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_threads', function (Blueprint $table) {
            $table->string('category', 16)->default('inbox')->after('status');
            $table->index(['category', 'last_message_at']);
        });

        Schema::table('support_messages', function (Blueprint $table) {
            $table->json('attachment')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('support_messages', function (Blueprint $table) {
            $table->dropColumn('attachment');
        });

        Schema::table('support_threads', function (Blueprint $table) {
            $table->dropIndex(['category', 'last_message_at']);
            $table->dropColumn('category');
        });
    }
};
