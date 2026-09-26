<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('game_status_text', 128)->nullable()->after('status_text');
        });

        DB::table('users')
            ->whereNotNull('status_text')
            ->where('status_text', 'like', 'Играет в %')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'game_status_text' => $user->status_text,
                        'status_text' => null,
                        'status_emoji' => null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('game_status_text');
        });
    }
};
