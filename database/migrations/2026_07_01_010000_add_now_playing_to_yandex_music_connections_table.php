<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yandex_music_connections', function (Blueprint $table) {
            $table->string('current_track_title')->nullable()->after('last_track_key');
            $table->string('current_track_artist')->nullable()->after('current_track_title');
            $table->string('current_track_album')->nullable()->after('current_track_artist');
            $table->string('current_track_cover_url')->nullable()->after('current_track_album');
            $table->string('current_track_url')->nullable()->after('current_track_cover_url');
            $table->unsignedInteger('current_track_duration_ms')->nullable()->after('current_track_url');
            $table->unsignedInteger('current_track_progress_ms')->nullable()->after('current_track_duration_ms');
            $table->boolean('current_track_paused')->default(false)->after('current_track_progress_ms');
            $table->timestamp('current_track_seen_at')->nullable()->after('current_track_paused');
        });
    }

    public function down(): void
    {
        Schema::table('yandex_music_connections', function (Blueprint $table) {
            $table->dropColumn([
                'current_track_title',
                'current_track_artist',
                'current_track_album',
                'current_track_cover_url',
                'current_track_url',
                'current_track_duration_ms',
                'current_track_progress_ms',
                'current_track_paused',
                'current_track_seen_at',
            ]);
        });
    }
};
