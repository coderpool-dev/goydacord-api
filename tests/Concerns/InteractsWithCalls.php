<?php

namespace Tests\Concerns;

use App\Enums\ChannelType;
use App\Enums\MemberCallStatus;
use App\Enums\MembershipStatus;
use App\Models\Conversations\Call;
use App\Models\Conversations\Channel;
use App\Models\Conversations\ChannelMember;
use App\Models\User;
use Illuminate\Support\Str;

trait InteractsWithCalls
{
    protected int $userSeq = 0;

    protected function makeUser(array $attributes = []): User
    {
        $this->userSeq++;

        return User::create(array_merge([
            'name' => 'User '.$this->userSeq,
            'login' => 'user'.$this->userSeq.'_'.Str::random(4),
            'email' => 'user'.$this->userSeq.'_'.Str::random(4).'@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('secret'),
        ], $attributes));
    }

    protected function makeChannel(array $attributes = []): Channel
    {
        return Channel::create(array_merge([
            'name' => 'Channel '.Str::random(5),
            'status' => ChannelType::Group,
        ], $attributes));
    }

    protected function addMember(
        Channel $channel,
        User $user,
        MembershipStatus $status = MembershipStatus::Member,
        MemberCallStatus $callStatus = MemberCallStatus::Idle
    ): ChannelMember {
        return ChannelMember::create([
            'channels_id' => $channel->id,
            'users_id' => $user->id,
            'status' => $status,
            'call_status' => $callStatus,
        ]);
    }

    protected function makeActiveCall(Channel $channel, User $initiator, string $status = 'active'): Call
    {
        return Call::create([
            'call_id' => (string) Str::uuid(),
            'channel_id' => $channel->id,
            'initiator_id' => $initiator->id,
            'status' => $status,
        ]);
    }

    /**
     * Плейн-текст токен Sanctum для HTTP-запросов.
     */
    protected function tokenFor(User $user, ?\DateTimeInterface $expiresAt = null): string
    {
        return $user->createToken('test', ['*'], $expiresAt)->plainTextToken;
    }
}
