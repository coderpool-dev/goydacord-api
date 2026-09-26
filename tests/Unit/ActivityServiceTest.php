<?php

namespace Tests\Unit;

use App\Models\Presence\Game;
use App\Models\Presence\GameSession;
use App\Models\Presence\UserActivityDay;
use App\Models\User;
use App\Services\Presence\ActivityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    private ActivityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ActivityService::class);
    }

    public function test_calculates_game_streak_from_sessions(): void
    {
        Carbon::setTestNow('2026-06-30 12:00:00');
        $user = User::factory()->create();

        foreach (['2026-06-28', '2026-06-29', '2026-06-30'] as $date) {
            $this->createSession($user, 'Dota 2', Carbon::parse($date)->setTime(18, 0), Carbon::parse($date)->setTime(20, 0));
        }

        $this->createSession($user, 'CS2', Carbon::parse('2026-06-30 21:00'), Carbon::parse('2026-06-30 22:00'));

        $this->assertSame(['current' => 3, 'longest' => 3], $this->service->calculateGameStreak($user, 'Dota 2'));
        $this->assertSame(['current' => 1, 'longest' => 1], $this->service->calculateGameStreak($user, 'CS2'));
    }

    public function test_current_streak_survives_until_the_end_of_next_day(): void
    {
        Carbon::setTestNow('2026-06-30 12:00:00');
        $user = User::factory()->create();

        foreach (['2026-06-20', '2026-06-21', '2026-06-22', '2026-06-28', '2026-06-29'] as $date) {
            $this->createSession($user, 'Dota 2', Carbon::parse($date)->setTime(18, 0), Carbon::parse($date)->setTime(19, 0));
        }

        $this->assertSame(['current' => 2, 'longest' => 3], $this->service->calculateGameStreak($user, 'Dota 2'));
    }

    public function test_records_game_session_start_and_end(): void
    {
        $user = User::factory()->create();

        $session = $this->service->recordGameStart($user, 'Dota 2');

        $this->assertSame('Dota 2', $session->game?->name);
        $this->assertNull($session->ended_at);

        $ended = $this->service->recordGameEnd($user, 'Dota 2');

        $this->assertNotNull($ended?->ended_at);
        $this->assertSame(1, UserActivityDay::where('user_id', $user->id)->count());
        $this->assertSame(1, GameSession::where('user_id', $user->id)->count());
        $this->assertSame(1, Game::where('slug', 'dota 2')->count());
    }

    public function test_record_game_end_without_name_closes_all_open_sessions(): void
    {
        $user = User::factory()->create(['game_status_text' => null]);

        $this->createSession($user, 'Dota 2', now()->subHour());
        $this->createSession($user, 'Counter-Strike 2', now()->subMinutes(30));

        $ended = $this->service->recordGameEnd($user);

        $this->assertNotNull($ended?->ended_at);
        $this->assertSame(0, GameSession::where('user_id', $user->id)->whereNull('ended_at')->count());
    }

    public function test_closes_abandoned_sessions_and_keeps_confirmed_games(): void
    {
        $silent = User::factory()->create(['game_status_text' => null]);
        $stale = User::factory()->create([
            'game_status_text' => 'Играет в Dota 2',
            'game_status_synced_at' => now()->subMinutes(5),
        ]);
        $playing = User::factory()->create([
            'game_status_text' => 'Играет в Dota 2',
            'game_status_synced_at' => now(),
        ]);

        foreach ([$silent, $stale, $playing] as $user) {
            $this->createSession($user, 'Dota 2', now()->subHour());
        }

        $this->artisan('activity:close-abandoned-games')->assertSuccessful();

        $this->assertSame(0, GameSession::where('user_id', $silent->id)->whereNull('ended_at')->count());
        $this->assertSame(0, GameSession::where('user_id', $stale->id)->whereNull('ended_at')->count());
        $this->assertSame(1, GameSession::where('user_id', $playing->id)->whereNull('ended_at')->count());
    }

    public function test_summary_does_not_change_sessions(): void
    {
        $user = User::factory()->create(['game_status_text' => null]);
        $this->createSession($user, 'Dota 2', now()->subHour());

        $summary = $this->service->profileSummary($user);

        $this->assertTrue($summary['history'][0]['is_active']);
        $this->assertSame(1, GameSession::where('user_id', $user->id)->whereNull('ended_at')->count());
    }

    public function test_reuses_existing_game_row_for_same_title(): void
    {
        $this->service->recordGameStart(User::factory()->create(), 'Dota 2');
        $this->service->recordGameStart(User::factory()->create(), 'Dota 2');

        $this->assertSame(1, Game::where('slug', 'dota 2')->count());
        $this->assertSame(2, GameSession::count());
    }

    public function test_normalizes_game_names_and_ignores_launchers(): void
    {
        $user = User::factory()->create();
        $aliasUser = User::factory()->create();

        $this->service->recordGameStart($user, 'Arena Breakout: Infinite');
        $this->service->recordGameStart($user, 'Arena Breakout Infinite');
        $this->service->recordGameStart($aliasUser, 'Counter-Strike Global Offensive');

        $this->assertSame(1, Game::where('slug', 'arena breakout: infinite')->count());
        $this->assertSame(1, Game::where('slug', 'counter-strike 2')->count());
        $this->assertSame(1, GameSession::where('user_id', $user->id)->count());
        $this->assertSame(1, GameSession::where('user_id', $aliasUser->id)->count());

        $this->expectException(\InvalidArgumentException::class);
        $this->service->recordGameStart($user, '7 Days To Die Launcher');
    }

    public function test_featured_streak_prefers_active_game(): void
    {
        Carbon::setTestNow('2026-06-30 12:00:00');
        $user = User::factory()->create();

        foreach (['2026-06-28', '2026-06-29', '2026-06-30'] as $date) {
            $this->createSession($user, 'Dota 2', Carbon::parse($date)->setTime(18, 0), Carbon::parse($date)->setTime(20, 0));
        }

        $this->createSession($user, 'CS2', now());

        $featured = $this->service->resolveFeaturedStreak($this->service->gameStreaks($user), ['CS2']);

        $this->assertSame('CS2', $featured['game']);
        $this->assertTrue($featured['is_active']);
    }

    public function test_summary_keeps_more_than_forty_sessions_by_default(): void
    {
        Carbon::setTestNow('2026-06-30 12:00:00');
        $user = User::factory()->create();

        for ($i = 0; $i < 45; $i++) {
            $startedAt = Carbon::parse('2026-06-01 10:00:00')->addHours($i);
            $this->createSession($user, 'CS2', $startedAt, $startedAt->copy()->addMinutes(30));
        }

        $this->assertCount(45, $this->service->profileSummary($user)['history']);
    }

    private function createSession(User $user, string $gameName, Carbon $startedAt, ?Carbon $endedAt = null): GameSession
    {
        return GameSession::create([
            'user_id' => $user->id,
            'game_id' => Game::resolveByName($gameName)->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
        ]);
    }
}
