<?php

namespace Tests\Feature;

use App\Enums\ChannelType;
use App\Enums\FriendStatus;
use App\Enums\MembershipStatus;
use App\Models\Conversations\Message;
use App\Models\Social\Friend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class ChannelControllerTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    private function befriend(User $user, User $friend): void
    {
        Friend::create([
            'users_id' => $user->id,
            'friend_id' => $friend->id,
            'status' => FriendStatus::Accepted,
        ]);
    }

    public function test_creates_private_channel_with_a_friend(): void
    {
        $user = $this->makeUser();
        $friend = $this->makeUser();
        $this->befriend($user, $friend);

        Sanctum::actingAs($user);

        $this->postJson('/api/channels', ['recipients' => [$friend->id]])
            ->assertCreated()
            ->assertJsonPath('is_private', true)
            ->assertJsonPath('count', 2);

        $this->assertDatabaseHas('channels', ['status' => ChannelType::Private]);
    }

    public function test_creates_group_channel_with_multiple_friends(): void
    {
        $user = $this->makeUser();
        $firstFriend = $this->makeUser();
        $secondFriend = $this->makeUser();
        $this->befriend($user, $firstFriend);
        $this->befriend($user, $secondFriend);

        Sanctum::actingAs($user);

        $this->postJson('/api/channels', ['name' => 'Team', 'recipients' => [$firstFriend->id, $secondFriend->id]])
            ->assertCreated()
            ->assertJsonPath('is_private', false)
            ->assertJsonPath('count', 3);

        $this->assertDatabaseHas('channels', ['name' => 'Team', 'status' => ChannelType::Group]);
        // Системное сообщение о создании беседы.
        $this->assertDatabaseHas('messages', ['type' => 'system']);
    }

    public function test_cannot_create_channel_with_non_friend(): void
    {
        $user = $this->makeUser();
        $stranger = $this->makeUser();

        Sanctum::actingAs($user);

        $this->postJson('/api/channels', ['recipients' => [$stranger->id]])
            ->assertForbidden();

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_existing_private_channel_is_reused(): void
    {
        $user = $this->makeUser();
        $friend = $this->makeUser();
        $this->befriend($user, $friend);

        Sanctum::actingAs($user);

        $this->postJson('/api/channels', ['recipients' => [$friend->id]])->assertCreated();
        $this->postJson('/api/channels', ['recipients' => [$friend->id]])
            ->assertOk()
            ->assertJsonPath('existing', true);

        $this->assertDatabaseCount('channels', 1);
    }

    public function test_get_returns_user_channels(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel(['name' => 'Mine']);
        $this->addMember($channel, $user, MembershipStatus::Admin);

        // Канал, в котором юзер не состоит — не должен попасть в список.
        $this->makeChannel(['name' => 'NotMine']);

        Sanctum::actingAs($user);

        $this->getJson('/api/channels')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Mine'])
            ->assertJsonMissing(['name' => 'NotMine']);
    }

    public function test_delete_leaves_channel(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user, MembershipStatus::Admin);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/channels/{$channel->id}")->assertOk();

        $this->assertDatabaseHas('channels_members', [
            'channels_id' => $channel->id,
            'users_id' => $user->id,
            'status' => MembershipStatus::Removed,
        ]);
    }

    public function test_read_marks_channel_read(): void
    {
        $author = $this->makeUser();
        $reader = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $author, MembershipStatus::Admin);
        $this->addMember($channel, $reader);

        $msg = Message::create([
            'user_id' => $author->id,
            'channels_id' => $channel->id,
            'message' => 'hi',
            'key_id' => 1,
        ]);

        Sanctum::actingAs($reader);

        $this->postJson("/api/channels/{$channel->id}/read")->assertOk();

        $this->assertDatabaseHas('channels_members', [
            'channels_id' => $channel->id,
            'users_id' => $reader->id,
            'last_read_message_id' => $msg->id,
        ]);
    }

    public function test_channels_require_authentication(): void
    {
        $this->getJson('/api/channels')->assertUnauthorized();
    }
}
