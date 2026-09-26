<?php

namespace Tests\Feature;

use App\Enums\FriendStatus;
use App\Models\Social\Friend;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    public function test_is_friend_detects_accepted_friendship_both_directions(): void
    {
        $user = $this->makeUser();
        $friend = $this->makeUser();

        Friend::create(['users_id' => $user->id, 'friend_id' => $friend->id, 'status' => FriendStatus::Accepted]);

        $this->assertTrue($user->isFriend($friend->id));
        $this->assertTrue($friend->isFriend($user->id)); // обратное направление тоже считается дружбой
    }

    public function test_is_friend_false_for_pending_or_strangers(): void
    {
        $user = $this->makeUser();
        $friend = $this->makeUser();
        $stranger = $this->makeUser();

        Friend::create(['users_id' => $user->id, 'friend_id' => $friend->id, 'status' => FriendStatus::Pending]);

        $this->assertFalse($user->isFriend($friend->id));
        $this->assertFalse($user->isFriend($stranger->id));
    }

    public function test_is_blocked_with_is_symmetric(): void
    {
        $blocker = $this->makeUser();
        $blocked = $this->makeUser();

        Friend::create(['users_id' => $blocker->id, 'friend_id' => $blocked->id, 'status' => FriendStatus::Blocked]);

        $this->assertTrue($blocker->isBlockedWith($blocked->id));
        $this->assertTrue($blocked->isBlockedWith($blocker->id)); // блокировка взаимна для звонков
    }

    public function test_is_online_reflects_recent_activity(): void
    {
        $online = $this->makeUser(['last_online' => now()]);
        $offline = $this->makeUser(['last_online' => now()->subMinutes(5)]);

        $this->assertTrue($online->isOnline());
        $this->assertFalse($offline->isOnline());
    }
}
