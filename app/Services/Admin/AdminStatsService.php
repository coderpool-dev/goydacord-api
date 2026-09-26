<?php

namespace App\Services\Admin;

use App\Models\Admin\AppStat;
use App\Models\Admin\DailyStat;
use App\Models\Conversations\Call;
use App\Models\Conversations\CallSession;
use App\Models\Conversations\Message;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdminStatsService
{
    public const PEAK_KEY = 'online_peak';

    public const PEAK_AT_KEY = 'online_peak_at';

    /** Пинг «я в сети» обновляет общий и дневной пик онлайна. */
    public function recordOnlinePresence(): int
    {
        $online = User::countOnline();

        $this->updateAllTimePeak($online);
        $this->updateDailyPeak($online);

        return $online;
    }

    public function snapshot(int $days = 14): array
    {
        $online = User::countOnline();
        $todayStart = now()->startOfDay();
        $liveCalls = $this->liveCallCounts();

        return [
            'users_total' => User::query()->real()->count(),
            'users_online' => $online,
            // Пик записывает пинг онлайна, а текущее значение могло обогнать его между пингами.
            'online_peak' => max($online, (int) AppStat::getValue(self::PEAK_KEY, 0)),
            'online_peak_at' => AppStat::getValue(self::PEAK_AT_KEY),
            'calls_active' => $liveCalls['calls'],
            'calls_participants_now' => $liveCalls['participants'],
            'calls_today' => Call::query()->where('created_at', '>=', $todayStart)->count(),
            'signups_today' => User::query()->real()->where('created_at', '>=', $todayStart)->count(),
            'series' => $this->series(max(7, min(30, $days))),
        ];
    }

    private function updateAllTimePeak(int $online): void
    {
        if ($online <= (int) AppStat::getValue(self::PEAK_KEY, 0)) {
            return;
        }

        AppStat::setValue(self::PEAK_KEY, $online);
        AppStat::setValue(self::PEAK_AT_KEY, now()->toIso8601String());
    }

    private function updateDailyPeak(int $online): void
    {
        $today = DailyStat::query()->firstOrCreate(
            ['day' => now()->toDateString()],
            ['online_peak' => 0, 'signups' => 0, 'calls_started' => 0],
        );

        if ($online > (int) $today->online_peak) {
            $today->update(['online_peak' => $online]);
        }
    }

    /** @return array{calls: int, participants: int} */
    private function liveCallCounts(): array
    {
        $counts = CallSession::query()
            ->whereIn('call_id', Call::query()->active()->select('call_id'))
            ->where('last_seen_at', '>=', now()->subSeconds(CallSession::FRESH_SECONDS))
            ->toBase()
            ->selectRaw('COUNT(DISTINCT call_id) as calls, COUNT(DISTINCT user_id) as participants')
            ->first();

        return [
            'calls' => (int) ($counts->calls ?? 0),
            'participants' => (int) ($counts->participants ?? 0),
        ];
    }

    /** @return list<array{day: string, online_peak: int, signups: int, calls: int}> */
    private function series(int $days): array
    {
        $from = now()->subDays($days - 1)->startOfDay();
        $to = now()->endOfDay();

        $daily = DailyStat::query()
            ->whereBetween('day', [$from->toDateString(), $to->toDateString()])
            ->get()
            ->keyBy(fn (DailyStat $row) => $row->day->toDateString());

        $signups = $this->countPerDay(User::query()->real()->toBase(), $from);
        $calls = $this->countPerDay(Call::query()->toBase(), $from);
        $activeUsers = $this->dailyActiveUsersByDay($from);

        $points = [];
        for ($cursor = $from->copy(); $cursor->lte($to); $cursor->addDay()) {
            $key = $cursor->toDateString();
            $points[] = [
                'day' => $key,
                // Пик из пинга онлайна, а для дней до его появления — оценка по активности.
                'online_peak' => max((int) ($daily->get($key)->online_peak ?? 0), (int) ($activeUsers[$key] ?? 0)),
                'signups' => (int) ($signups[$key] ?? 0),
                'calls' => (int) ($calls[$key] ?? 0),
            ];
        }

        return $points;
    }

    /** @return array<string, int> */
    private function countPerDay(Builder $query, Carbon $from): array
    {
        return $query
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $from)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    /**
     * Уникальные пользователи с активностью за день: сообщения, звонки, сессии в звонках.
     *
     * @return array<string, int>
     */
    private function dailyActiveUsersByDay(Carbon $from): array
    {
        $messageActivity = Message::query()
            ->selectRaw('DATE(created_at) as day, user_id')
            ->where('created_at', '>=', $from)
            ->whereNotNull('user_id');

        $callActivity = Call::query()
            ->selectRaw('DATE(created_at) as day, initiator_id as user_id')
            ->where('created_at', '>=', $from)
            ->whereNotNull('initiator_id');

        $sessionActivity = CallSession::query()
            ->selectRaw('DATE(last_seen_at) as day, user_id')
            ->where('last_seen_at', '>=', $from)
            ->whereNotNull('user_id');

        return DB::query()
            ->fromSub($messageActivity->union($callActivity)->union($sessionActivity), 'activity')
            ->selectRaw('day, COUNT(DISTINCT user_id) as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->map(fn ($total) => (int) $total)
            ->all();
    }
}
