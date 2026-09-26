<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DISPLAY_ALIASES = [
        'abinfinite' => 'Arena Breakout: Infinite',
        'ab infinite' => 'Arena Breakout: Infinite',
        'dota 2 beta' => 'Dota 2',
    ];

    public function up(): void
    {
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('name', 160);
            $table->timestamps();
        });

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->foreignId('game_id')->nullable()->after('user_id')->constrained('games')->cascadeOnDelete();
        });

        if (! Schema::hasColumn('game_sessions', 'game_name')) {
            return;
        }

        $sessions = DB::table('game_sessions')->select('id', 'game_name')->get();
        $gameIdsBySlug = [];

        foreach ($sessions as $session) {
            $rawName = trim((string) $session->game_name);
            if ($rawName === '') {
                continue;
            }

            $slug = mb_strtolower(preg_replace('/\s+/u', ' ', $rawName) ?? '');
            $displayName = self::DISPLAY_ALIASES[$slug] ?? $rawName;
            $canonicalSlug = mb_strtolower(preg_replace('/\s+/u', ' ', $displayName) ?? '');

            if (! isset($gameIdsBySlug[$canonicalSlug])) {
                $existingId = DB::table('games')->where('slug', $canonicalSlug)->value('id');
                if ($existingId) {
                    $gameIdsBySlug[$canonicalSlug] = (int) $existingId;
                    DB::table('games')->where('id', $existingId)->update(['name' => $displayName]);
                } else {
                    $gameIdsBySlug[$canonicalSlug] = (int) DB::table('games')->insertGetId([
                        'slug' => $canonicalSlug,
                        'name' => $displayName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('game_sessions')
                ->where('id', $session->id)
                ->update(['game_id' => $gameIdsBySlug[$canonicalSlug]]);
        }

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropColumn('game_name');
        });
    }

    public function down(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->string('game_name', 160)->nullable()->after('user_id');
        });

        $sessions = DB::table('game_sessions')
            ->leftJoin('games', 'games.id', '=', 'game_sessions.game_id')
            ->select('game_sessions.id', 'games.name')
            ->get();

        foreach ($sessions as $session) {
            DB::table('game_sessions')
                ->where('id', $session->id)
                ->update(['game_name' => $session->name]);
        }

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_id');
        });

        Schema::dropIfExists('games');
    }
};
