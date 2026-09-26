<?php

namespace Tests\Unit;

use App\Services\Integrations\YandexYnisonService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YandexYnisonServiceTest extends TestCase
{
    public function test_uses_stable_device_id_for_same_access_token(): void
    {
        $service = app(YandexYnisonService::class);

        $first = $service->deviceIdForAccessToken('token-abc');
        $second = $service->deviceIdForAccessToken('token-abc');
        $other = $service->deviceIdForAccessToken('token-def');

        $this->assertSame($first, $second);
        $this->assertNotSame($first, $other);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $first);
    }

    public function test_parses_player_state_from_ynison_payload(): void
    {
        $service = app(YandexYnisonService::class);

        $track = $service->parsePlayerState([
            'player_state' => [
                'player_queue' => [
                    'current_playable_index' => 0,
                    'playable_list' => [
                        [
                            'title' => 'Группа крови',
                            'artists' => [['name' => 'Кино']],
                        ],
                    ],
                ],
            ],
        ], 'token');

        $this->assertSame('Группа крови', $track['title']);
        $this->assertSame('Кино', $track['artist']);
        $this->assertFalse($track['paused']);
        $this->assertArrayHasKey('progress_ms', $track);
    }

    public function test_enriches_artist_from_track_api_when_playable_has_title_only(): void
    {
        Http::fake([
            'api.music.yandex.net/tracks/*' => Http::response([
                'result' => [[
                    'title' => 'Kill',
                    'artists' => [['name' => 'Imagine Dragons']],
                ]],
            ]),
        ]);

        $service = app(YandexYnisonService::class);

        $track = $service->parsePlayerState([
            'player_state' => [
                'player_queue' => [
                    'current_playable_index' => 0,
                    'playable_list' => [
                        [
                            'title' => 'Kill',
                            'playable_id' => '12345',
                            'album_id_optional' => '67890',
                        ],
                    ],
                ],
            ],
        ], 'token');

        $this->assertSame('Kill', $track['title']);
        $this->assertSame('Imagine Dragons', $track['artist']);
    }

    public function test_enriches_zero_ynison_duration_from_track_api(): void
    {
        Http::fake([
            'api.music.yandex.net/tracks/*' => Http::response([
                'result' => [[
                    'title' => 'Kill',
                    'durationMs' => 180_000,
                    'artists' => [['name' => 'Imagine Dragons']],
                ]],
            ]),
        ]);

        $service = app(YandexYnisonService::class);

        $track = $service->parsePlayerState([
            'player_state' => [
                'status' => [
                    'duration_ms' => 0,
                    'progress_ms' => 12_000,
                    'paused' => false,
                ],
                'player_queue' => [
                    'current_playable_index' => 0,
                    'playable_list' => [
                        [
                            'title' => 'Kill',
                            'artists' => [['name' => 'Imagine Dragons']],
                            'playable_id' => '12345',
                        ],
                    ],
                ],
            ],
        ], 'token');

        $this->assertSame(180_000, $track['duration_ms']);
        $this->assertSame(12_000, $track['progress_ms']);
    }

    public function test_ignores_shadow_device_paused_status_for_current_track(): void
    {
        $service = app(YandexYnisonService::class);

        $track = $service->parsePlayerState([
            'player_state' => [
                'status' => [
                    'duration_ms' => 222_000,
                    'progress_ms' => 0,
                    'paused' => true,
                    'version' => ['timestamp_ms' => (int) round(microtime(true) * 1000)],
                ],
                'player_queue' => [
                    'current_playable_index' => 0,
                    'playable_list' => [
                        [
                            'title' => 'all the things you said',
                            'artists' => [['name' => 'Never']],
                        ],
                    ],
                ],
            ],
        ], 'token');

        $this->assertFalse($track['paused']);
        $this->assertSame(0, $track['progress_ms']);
    }

    public function test_returns_null_when_nothing_is_playing(): void
    {
        $service = app(YandexYnisonService::class);

        $this->assertNull($service->parsePlayerState([
            'player_state' => [
                'player_queue' => [
                    'current_playable_index' => -1,
                    'playable_list' => [],
                ],
            ],
        ], 'token'));
    }

    public function test_extrapolates_progress_from_status_timestamp(): void
    {
        $service = app(YandexYnisonService::class);
        $nowMs = (int) round(microtime(true) * 1000);

        $progress = $service->resolveLiveProgressMs([
            'progress_ms' => 10_000,
            'paused' => false,
            'version' => ['timestamp_ms' => $nowMs - 5_000],
        ], 150_000);

        $this->assertGreaterThanOrEqual(14_500, $progress);
        $this->assertLessThanOrEqual(15_500, $progress);
    }
}
