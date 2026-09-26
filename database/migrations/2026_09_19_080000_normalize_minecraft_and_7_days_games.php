<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    private const BAD_ICON_SLUGS = [
        '7-days-to-die-launcher',
        'minecraft-1122',
        'minecraft-1201',
        'minecraft-forge-1201',
    ];

    public function up(): void
    {
        $canonicalIds = [];

        DB::table('games')
            ->orderBy('id')
            ->get()
            ->each(function (object $game) use (&$canonicalIds) {
                $canonicalName = self::normalizePublicName((string) $game->name)
                    ?? self::normalizePublicName((string) $game->slug);

                if ($canonicalName === null || $canonicalName === '') {
                    return;
                }

                $canonicalSlug = self::slugFromName($canonicalName);
                $canonicalId = $canonicalIds[$canonicalSlug]
                    ?? DB::table('games')->where('slug', $canonicalSlug)->value('id');

                if (! $canonicalId) {
                    DB::table('games')
                        ->where('id', $game->id)
                        ->update([
                            'slug' => $canonicalSlug,
                            'name' => $canonicalName,
                            'updated_at' => now(),
                        ]);

                    $canonicalIds[$canonicalSlug] = (int) $game->id;

                    return;
                }

                $canonicalId = (int) $canonicalId;
                $canonicalIds[$canonicalSlug] = $canonicalId;

                if ($canonicalId === (int) $game->id) {
                    if ((string) $game->name !== $canonicalName) {
                        DB::table('games')
                            ->where('id', $game->id)
                            ->update(['name' => $canonicalName, 'updated_at' => now()]);
                    }

                    return;
                }

                DB::table('game_sessions')
                    ->where('game_id', $game->id)
                    ->update(['game_id' => $canonicalId]);

                DB::table('games')->where('id', $game->id)->delete();
            });

        DB::table('game_icons')
            ->whereIn('slug', self::BAD_ICON_SLUGS)
            ->orderBy('id')
            ->get()
            ->each(function (object $icon) {
                if ($icon->file) {
                    Storage::disk('public')->delete('game-icons/'.ltrim((string) $icon->file, '/'));
                }

                DB::table('game_icons')->where('id', $icon->id)->delete();
            });
    }

    private static function slugFromName(string $gameName): string
    {
        return mb_strtolower(self::canonicalizeName($gameName));
    }

    private static function normalizePublicName(string $gameName): ?string
    {
        $normalized = self::canonicalizeName($gameName);
        if ($normalized === '') {
            return null;
        }

        $aliases = [
            '7daystodie' => '7 Days to Die',
            '7 days to die' => '7 Days to Die',
            '7 days todie' => '7 Days to Die',
            'minecraft' => 'Minecraft',
            'minecraft.windows' => 'Minecraft',
            'minecraft 1.12.2' => 'Minecraft',
            'minecraft* 1.20.1' => 'Minecraft',
            'minecraft* forge 1.20.1' => 'Minecraft',
        ];

        return $aliases[mb_strtolower($normalized)] ?? $normalized;
    }

    private static function canonicalizeName(string $gameName): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($gameName)) ?? '';
        $normalized = preg_replace('/\s*\((?:inactive|disabled|неактивно|paused)\)\s*$/iu', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    public function down(): void
    {
        // Нормализация необратима: игровые сессии уже сведены к правильным каноническим играм.
    }
};
