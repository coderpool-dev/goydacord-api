<?php

namespace Tests\Feature;

use App\Enums\FriendStatus;
use App\Models\Admin\Privilege;
use App\Models\Integrations\YandexMusicTrackHistory;
use App\Models\Presence\Game;
use App\Models\Presence\GameSession;
use App\Models\Social\Friend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_stranger_gets_hidden_activity_and_music(): void
    {
        $player = $this->playerWithActivity();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/users/{$player->id}/activity")
            ->assertOk()
            ->assertJsonPath('visible', false)
            ->assertJsonPath('history', []);

        $this->getJson("/api/users/{$player->id}/yandex-music/history")
            ->assertOk()
            ->assertJsonPath('visible', false)
            ->assertJsonPath('tracks', []);
    }

    public function test_user_sees_own_activity_and_music(): void
    {
        $player = $this->playerWithActivity();

        Sanctum::actingAs($player);

        $this->assertActivityVisible($player);
    }

    public function test_friend_sees_activity_and_music(): void
    {
        $player = $this->playerWithActivity();
        $friend = User::factory()->create();
        Friend::create([
            'users_id' => $friend->id,
            'friend_id' => $player->id,
            'status' => FriendStatus::Accepted,
        ]);

        Sanctum::actingAs($friend);

        $this->assertActivityVisible($player);
    }

    public function test_admin_sees_activity_and_music_without_friendship(): void
    {
        $player = $this->playerWithActivity();
        $admin = User::factory()->create();
        $admin->privileges()->attach(Privilege::query()->firstOrCreate(['name' => Privilege::ADMIN])->id);

        Sanctum::actingAs($admin);

        $this->assertActivityVisible($player);
    }

    private function assertActivityVisible(User $player): void
    {
        $this->getJson("/api/users/{$player->id}/activity")
            ->assertOk()
            ->assertJsonPath('visible', true)
            ->assertJsonPath('history.0.game', 'Dota 2');

        $this->getJson("/api/users/{$player->id}/yandex-music/history")
            ->assertOk()
            ->assertJsonPath('visible', true)
            ->assertJsonPath('tracks.0.title', 'Группа крови');
    }

    private function playerWithActivity(): User
    {
        $player = User::factory()->create();

        GameSession::create([
            'user_id' => $player->id,
            'game_id' => Game::resolveByName('Dota 2')->id,
            'started_at' => now()->subHours(3),
            'ended_at' => now()->subHours(2),
        ]);

        YandexMusicTrackHistory::create([
            'user_id' => $player->id,
            'track_key' => 'кино|группа крови',
            'title' => 'Группа крови',
            'artist' => 'Кино',
            'play_count' => 1,
            'first_played_at' => now()->subHour(),
            'last_played_at' => now()->subHour(),
        ]);

        return $player;
    }
}
