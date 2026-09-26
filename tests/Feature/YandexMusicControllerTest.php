<?php

namespace Tests\Feature;

use App\Models\Integrations\YandexMusicConnection;
use App\Models\User;
use App\Services\Integrations\YandexYnisonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class YandexMusicControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_requires_authentication(): void
    {
        $this->getJson('/api/integrations/yandex-music')->assertStatus(401);
    }

    public function test_device_auth_flow_connects_account(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user, ['*']);

        Http::fake([
            'oauth.yandex.ru/device/code' => Http::response([
                'device_code' => 'device-123',
                'user_code' => 'abc-def',
                'verification_url' => 'https://oauth.yandex.ru/device',
                'interval' => 1,
                'expires_in' => 300,
            ]),
            'oauth.yandex.ru/token' => Http::sequence()
                ->push(['error' => 'authorization_pending'], 400)
                ->push([
                    'access_token' => 'token-abc',
                    'refresh_token' => 'refresh-abc',
                    'expires_in' => 3600,
                ]),
            'api.music.yandex.net/account/status' => Http::response([
                'result' => [
                    'account' => [
                        'uid' => 42,
                        'login' => 'listener',
                    ],
                ],
            ]),
        ]);

        $this->postJson('/api/integrations/yandex-music/device-code')->assertOk()
            ->assertJsonPath('user_code', 'abc-def');

        $this->postJson('/api/integrations/yandex-music/poll')->assertOk()
            ->assertJsonPath('status', 'pending');

        $this->postJson('/api/integrations/yandex-music/poll')->assertOk()
            ->assertJsonPath('status', 'connected')
            ->assertJsonPath('login', 'listener');

        $this->assertDatabaseHas('yandex_music_connections', [
            'user_id' => $user->id,
            'yandex_login' => 'listener',
        ]);
    }

    public function test_sync_updates_profile_status_with_current_track(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'status_text' => null,
        ]);
        Sanctum::actingAs($user, ['*']);

        YandexMusicConnection::create([
            'user_id' => $user->id,
            'access_token' => 'token-abc',
            'yandex_login' => 'listener',
            'yandex_uid' => 42,
        ]);

        Http::fake([
            'api.music.yandex.net/queues' => Http::response([
                'result' => [
                    'queues' => [
                        ['id' => 'queue-1', 'modified' => '2026-06-30T12:00:00+03:00'],
                    ],
                ],
            ]),
            'api.music.yandex.net/queues/queue-1' => Http::response([
                'result' => [
                    'currentIndex' => 0,
                    'tracks' => [
                        [
                            'track' => [
                                'title' => 'Группа крови',
                                'artists' => [['name' => 'Кино']],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->postJson('/api/integrations/yandex-music/sync')->assertOk()
            ->assertJsonPath('track.title', 'Группа крови')
            ->assertJsonPath('track.artist', 'Кино');

        $user->refresh();
        $this->assertSame('Слушает Кино — Группа крови · Яндекс Музыка', $user->music_status_text);
        $this->assertNull($user->status_text);
    }

    public function test_sync_keeps_music_status_when_track_fetch_temporarily_fails(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'music_status_text' => 'Слушает Кино — Группа крови · Яндекс Музыка',
        ]);
        Sanctum::actingAs($user, ['*']);

        YandexMusicConnection::create([
            'user_id' => $user->id,
            'access_token' => 'token-abc',
            'last_track_key' => 'кино|группа крови',
        ]);

        Http::fake([
            'api.music.yandex.net/queues' => Http::response([
                'result' => ['queues' => []],
            ]),
        ]);

        $this->mock(YandexYnisonService::class, function ($mock): void {
            $mock->shouldReceive('fetchCurrentTrack')->once()->andReturn(null);
        });

        $this->postJson('/api/integrations/yandex-music/sync')->assertOk()
            ->assertJsonPath('track.title', 'Группа крови')
            ->assertJsonPath('track.artist', 'Кино');

        $this->assertSame('Слушает Кино — Группа крови · Яндекс Музыка', $user->fresh()->music_status_text);
    }

    public function test_sync_advances_progress_for_same_playing_track(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user, ['*']);

        $connection = YandexMusicConnection::create([
            'user_id' => $user->id,
            'access_token' => 'token-abc',
            'last_track_key' => 'кино|группа крови',
            'current_track_title' => 'Группа крови',
            'current_track_artist' => 'Кино',
            'current_track_duration_ms' => 300_000,
            'current_track_progress_ms' => 45_000,
            'current_track_paused' => false,
            'current_track_seen_at' => now()->subSeconds(10),
        ]);

        Http::fake([
            'api.music.yandex.net/queues' => Http::response(['result' => ['queues' => []]]),
        ]);

        // Ynison отдаёт прогресс 0 (как часто бывает между play/seek) — бэкенд должен
        // продолжить тикать от прошлого якоря, а не сбросить в 0.
        $this->mock(YandexYnisonService::class, function ($mock): void {
            $mock->shouldReceive('fetchCurrentTrack')->once()->andReturn([
                'title' => 'Группа крови',
                'artist' => 'Кино',
                'duration_ms' => 300_000,
                'progress_ms' => 0,
                'paused' => false,
            ]);
        });

        $this->postJson('/api/integrations/yandex-music/sync')->assertOk();

        $connection->refresh();
        $this->assertFalse((bool) $connection->current_track_paused);
        $this->assertSame(45_000, $connection->current_track_progress_ms);
        $this->assertTrue($connection->current_track_seen_at->lessThan(now()->subSeconds(8)));
    }

    public function test_sync_treats_paused_seek_event_as_playing_anchor(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user, ['*']);

        $connection = YandexMusicConnection::create([
            'user_id' => $user->id,
            'access_token' => 'token-abc',
            'last_track_key' => 'kino|blood type',
            'current_track_title' => 'Blood Type',
            'current_track_artist' => 'Kino',
            'current_track_duration_ms' => 300_000,
            'current_track_progress_ms' => 45_000,
            'current_track_paused' => false,
            'current_track_seen_at' => now()->subSeconds(10),
        ]);

        Http::fake([
            'api.music.yandex.net/queues' => Http::response(['result' => ['queues' => []]]),
        ]);

        $this->mock(YandexYnisonService::class, function ($mock): void {
            $mock->shouldReceive('fetchCurrentTrack')->once()->andReturn([
                'title' => 'Blood Type',
                'artist' => 'Kino',
                'duration_ms' => 300_000,
                'progress_ms' => 120_000,
                'paused' => true,
            ]);
        });

        $this->postJson('/api/integrations/yandex-music/sync')
            ->assertOk()
            ->assertJsonPath('track.paused', false)
            ->assertJsonPath('track.progress_ms', 120_000);

        $connection->refresh();
        $this->assertFalse((bool) $connection->current_track_paused);
        $this->assertSame(120_000, $connection->current_track_progress_ms);
        $this->assertTrue($connection->current_track_seen_at->greaterThan(now()->subSeconds(2)));
    }

    public function test_sync_resumes_from_paused_anchor_without_counting_paused_time(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user, ['*']);

        $connection = YandexMusicConnection::create([
            'user_id' => $user->id,
            'access_token' => 'token-abc',
            'last_track_key' => 'kino|blood type',
            'current_track_title' => 'Blood Type',
            'current_track_artist' => 'Kino',
            'current_track_duration_ms' => 300_000,
            'current_track_progress_ms' => 45_000,
            'current_track_paused' => true,
            'current_track_seen_at' => now()->subMinutes(5),
        ]);

        Http::fake([
            'api.music.yandex.net/queues' => Http::response(['result' => ['queues' => []]]),
        ]);

        $this->mock(YandexYnisonService::class, function ($mock): void {
            $mock->shouldReceive('fetchCurrentTrack')->once()->andReturn([
                'title' => 'Blood Type',
                'artist' => 'Kino',
                'duration_ms' => 300_000,
                'progress_ms' => 0,
                'paused' => false,
            ]);
        });

        $this->postJson('/api/integrations/yandex-music/sync')
            ->assertOk()
            ->assertJsonPath('track.paused', false)
            ->assertJsonPath('track.progress_ms', 45_000);

        $connection->refresh();
        $this->assertFalse((bool) $connection->current_track_paused);
        $this->assertSame(45_000, $connection->current_track_progress_ms);
        $this->assertTrue($connection->current_track_seen_at->greaterThan(now()->subSeconds(2)));
    }

    public function test_sync_treats_advancing_paused_progress_as_playing(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Sanctum::actingAs($user, ['*']);

        $connection = YandexMusicConnection::create([
            'user_id' => $user->id,
            'access_token' => 'token-abc',
            'last_track_key' => 'villian|nehvatka videnya',
            'current_track_title' => 'Nehvatka Videnya',
            'current_track_artist' => 'VILLIAN',
            'current_track_duration_ms' => 109_710,
            'current_track_progress_ms' => 32_000,
            'current_track_paused' => true,
            'current_track_seen_at' => now()->subSeconds(5),
        ]);

        Http::fake([
            'api.music.yandex.net/queues' => Http::response(['result' => ['queues' => []]]),
        ]);

        $this->mock(YandexYnisonService::class, function ($mock): void {
            $mock->shouldReceive('fetchCurrentTrack')->once()->andReturn([
                'title' => 'Nehvatka Videnya',
                'artist' => 'VILLIAN',
                'duration_ms' => 109_710,
                'progress_ms' => 38_000,
                'paused' => true,
            ]);
        });

        $this->postJson('/api/integrations/yandex-music/sync')
            ->assertOk()
            ->assertJsonPath('track.paused', false)
            ->assertJsonPath('track.progress_ms', 38_000);

        $connection->refresh();
        $this->assertFalse((bool) $connection->current_track_paused);
        $this->assertSame(38_000, $connection->current_track_progress_ms);
        $this->assertTrue($connection->current_track_seen_at->greaterThan(now()->subSeconds(2)));
    }

    public function test_disconnect_removes_connection_and_auto_status(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'music_status_text' => 'Слушает Кино — Группа крови · Яндекс Музыка',
        ]);
        Sanctum::actingAs($user, ['*']);

        YandexMusicConnection::create([
            'user_id' => $user->id,
            'access_token' => 'token-abc',
        ]);

        $this->deleteJson('/api/integrations/yandex-music')->assertOk();

        $this->assertDatabaseMissing('yandex_music_connections', ['user_id' => $user->id]);
        $this->assertNull($user->fresh()->music_status_text);
    }
}
