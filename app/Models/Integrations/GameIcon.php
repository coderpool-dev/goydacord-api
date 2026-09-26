<?php

namespace App\Models\Integrations;

use App\Models\Presence\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GameIcon extends Model
{
    /** Приоритет источника иконки: чем выше, тем «настоящее» арт игры. */
    public const SOURCE_PRIORITY = [
        'folder' => 3,
        'folder+norm' => 3,
        'steam' => 2,
        'steam+norm' => 2,
        'exe' => 1,
        'exe+norm' => 1,
    ];

    protected $fillable = [
        'slug',
        'name',
        'file',
        'icon_hash',
        'source_priority',
        'uploaded_by',
    ];

    public static function priorityForSource(?string $source): int
    {
        return self::SOURCE_PRIORITY[(string) $source] ?? 1;
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function slugFromName(string $name): string
    {
        return Str::slug(trim($name));
    }

    public function iconUrl(): string
    {
        $url = asset('storage/game-icons/'.ltrim($this->file, '/'));

        if ($this->updated_at) {
            $url .= '?v='.$this->updated_at->timestamp;
        }

        return $url;
    }

    public static function findByName(string $name): ?self
    {
        $raw = trim($name);
        $slugs = [];

        if ($raw !== '') {
            $slugs[] = self::slugFromName($raw);
        }

        $canonical = Game::normalizePublicName($raw);
        if (is_string($canonical) && $canonical !== '') {
            $slugs[] = self::slugFromName($canonical);
        }

        $slugs = array_values(array_unique(array_filter($slugs)));
        if ($slugs === []) {
            return null;
        }

        return self::query()->whereIn('slug', $slugs)->first();
    }
}
