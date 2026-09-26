<?php

namespace App\Services\Admin;

use App\Enums\ChannelType;
use App\Enums\MemberCallStatus;
use App\Enums\MembershipStatus;
use App\Models\Conversations\Call;
use App\Models\Conversations\CallSession;
use App\Models\Conversations\Channel;
use App\Models\Conversations\ChannelMember;
use App\Models\Conversations\Message;
use App\Models\Support\FeedbackMessage;
use App\Models\Support\SupportThread;
use App\Models\Support\UserReport;
use App\Models\User;
use App\Services\Presence\SitePresenceService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/** Живая картина для админки: кто в сети, какие идут звонки, кто сейчас на сайте. */
class AdminActivityService
{
    public function __construct(private readonly SitePresenceService $sitePresence) {}

    public function snapshot(): array
    {
        $onlineUsers = $this->onlineUsers();
        $activeCalls = $this->activeCalls();
        $liveVisitors = $this->sitePresence->liveVisitors();
        $todayStart = now()->startOfDay();

        $inCallIds = collect($activeCalls)
            ->flatMap(fn (array $call) => collect($call['participants'])->pluck('id'))
            ->unique();

        return [
            'generated_at' => now()->toIso8601String(),
            'online_users' => $onlineUsers,
            'active_calls' => $activeCalls,
            'live_visitors' => $liveVisitors,
            'counts' => [
                'online' => count($onlineUsers),
                'online_desktop' => collect($onlineUsers)->where('platform', 'desktop')->count(),
                'online_web' => collect($onlineUsers)->where('platform', 'web')->count(),
                'online_mobile' => collect($onlineUsers)->where('platform', 'mobile')->count(),
                'calls_active' => count($activeCalls),
                'participants_in_calls' => $inCallIds->count(),
                'live_visitors' => count($liveVisitors),
                'live_guests' => collect($liveVisitors)->where('is_guest', true)->count(),
                'support_open' => SupportThread::query()
                    ->where('status', SupportThread::STATUS_OPEN)
                    ->where('category', SupportThread::CATEGORY_INBOX)
                    ->count(),
                'feedback_new' => FeedbackMessage::query()->where('status', FeedbackMessage::STATUS_NEW)->count(),
                'reports_new' => UserReport::query()->where('status', UserReport::STATUS_NEW)->count(),
                'signups_today' => User::query()->real()->where('created_at', '>=', $todayStart)->count(),
                'messages_today' => Message::query()->where('created_at', '>=', $todayStart)->count(),
            ],
        ];
    }

    private function onlineUsers(): array
    {
        return User::query()
            ->real()
            ->where('last_online', '>=', now()->subSeconds(User::ONLINE_THRESHOLD_SECONDS))
            ->orderByDesc('last_online')
            ->get([
                'id', 'login', 'name', 'avatar', 'presence', 'status_emoji', 'status_text', 'game_status_text',
                'game_status_synced_at', 'music_status_text', 'last_online', 'last_platform', 'updated_at',
            ])
            ->map(fn (User $user) => $this->serializeUser($user, withPresence: true))
            ->all();
    }

    private function activeCalls(): array
    {
        $freshSessions = CallSession::query()
            ->where('last_seen_at', '>=', now()->subSeconds(CallSession::FRESH_SECONDS))
            ->orderByDesc('last_seen_at')
            ->get();

        if ($freshSessions->isEmpty()) {
            return [];
        }

        $calls = Call::query()
            ->whereIn('call_id', $freshSessions->pluck('call_id')->unique())
            ->active()
            ->with(['channel', 'initiator:id,login,name,avatar,updated_at'])
            ->get()
            ->keyBy('call_id');

        $users = User::query()
            ->whereIn('id', $freshSessions->pluck('user_id')->unique())
            ->get(['id', 'login', 'name', 'avatar', 'presence', 'updated_at'])
            ->keyBy('id');

        $membersByChannel = $this->loadChannelMembers($calls->pluck('channel_id')->filter()->unique());

        return $freshSessions->groupBy('call_id')
            ->map(function (Collection $sessions, string $callId) use ($calls, $users, $membersByChannel) {
                $call = $calls->get($callId);
                $participants = $this->participants($sessions, $users);

                return $call && $participants !== []
                    ? $this->serializeCall($call, $participants, $membersByChannel->get((int) $call->channel_id) ?? collect())
                    : null;
            })
            ->filter()
            ->sortByDesc('duration_seconds')
            ->values()
            ->all();
    }

    /**
     * Сессии идут от свежих к старым: с двух устройств берём последнюю.
     *
     * @param  Collection<int, CallSession>  $sessions
     * @param  Collection<int, User>  $users
     */
    private function participants(Collection $sessions, Collection $users): array
    {
        return $sessions
            ->filter(fn (CallSession $session) => $users->has((int) $session->user_id))
            ->unique('user_id')
            ->map(fn (CallSession $session) => [
                ...$this->serializeUser($users->get((int) $session->user_id)),
                'screen_sharing' => (bool) $session->screen_sharing,
                'last_seen_at' => $session->last_seen_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /** @param  Collection<int, ChannelMember>  $channelMembers */
    private function serializeCall(Call $call, array $participants, Collection $channelMembers): array
    {
        $startedAt = $call->created_at ?? now();

        return [
            'call_id' => (string) $call->call_id,
            'status' => $call->status->value,
            'started_at' => $startedAt->toIso8601String(),
            'duration_seconds' => max(0, (int) $startedAt->diffInSeconds(now())),
            'initiator' => $call->initiator ? $this->serializeUser($call->initiator) : null,
            'channel' => $this->serializeChannel($call->channel, $channelMembers),
            'participants' => $participants,
        ];
    }

    /** @return Collection<array-key, EloquentCollection<int, ChannelMember>> */
    private function loadChannelMembers(Collection $channelIds): Collection
    {
        if ($channelIds->isEmpty()) {
            return collect();
        }

        return ChannelMember::query()
            ->whereIn('channels_id', $channelIds)
            ->active()
            ->with('user:id,login,name,avatar,presence,last_online,updated_at')
            ->get()
            ->groupBy('channels_id')
            ->toBase();
    }

    /** @param  Collection<int, ChannelMember>  $members */
    private function serializeChannel(?Channel $channel, Collection $members): ?array
    {
        if (! $channel) {
            return null;
        }

        $memberRows = $members
            ->filter(fn (ChannelMember $member) => $member->user !== null)
            ->map(fn (ChannelMember $member) => [
                ...$this->serializeUser($member->user),
                'role' => $member->status === MembershipStatus::Admin ? 'admin' : 'member',
                'in_call' => $member->call_status === MemberCallStatus::InCall,
            ])
            ->values();

        $type = $channel->status === ChannelType::Group ? 'group' : 'private';
        $name = trim((string) $channel->name);

        return [
            'id' => (int) $channel->id,
            'type' => $type,
            'name' => $name !== '' ? $name : null,
            'display_name' => $name !== '' ? $name : $this->fallbackChannelName($channel, $type, $memberRows),
            'avatar' => $channel->avatar ? User::getAvatarUrl($channel->avatar) : null,
            'member_count' => $memberRows->count(),
            'members' => $memberRows->all(),
        ];
    }

    private function fallbackChannelName(Channel $channel, string $type, Collection $memberRows): string
    {
        $labels = $memberRows->map(fn (array $member) => $member['name'] ?: $member['login'])->filter()->values();

        return match (true) {
            $type === 'private' && $labels->count() >= 2 => $labels->take(2)->implode(' ↔ '),
            $labels->isNotEmpty() => $labels->take(4)->implode(', '),
            default => 'Чат #'.$channel->id,
        };
    }

    private function serializeUser(User $user, bool $withPresence = false): array
    {
        $payload = [
            'id' => (int) $user->id,
            'login' => (string) $user->login,
            'name' => (string) $user->name,
            'avatar' => User::getAvatarUrl($user->avatar, $user->updated_at?->toISOString()),
            'presence' => $user->presence ?? 'online',
            'online' => $user->isOnline(),
        ];

        if (! $withPresence) {
            return $payload;
        }

        return [
            ...$payload,
            'last_online' => $user->last_online,
            'status_emoji' => $user->status_emoji,
            'status_text' => $user->status_text,
            'game_status_text' => $user->game_status_text,
            'music_status_text' => $user->music_status_text,
            'platform' => $user->last_platform ?: 'web',
        ];
    }
}
