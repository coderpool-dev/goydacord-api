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
            $table->string('music_status_text', 128)->nullable()->after('status_text');
        });

        DB::table('users')
            ->whereNotNull('status_text')
            ->where('status_text', 'like', 'Слушает %')
            ->where('status_text', 'like', '%Яндекс Музыка')
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'music_status_text' => $user->status_text,
                        'status_text' => null,
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('music_status_text');
        });
    }
};
