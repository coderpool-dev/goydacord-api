<?php

namespace Tests\Feature;

use App\Enums\FriendStatus;
use App\Enums\MembershipStatus;
use App\Models\Social\Friend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class ChannelMemberControllerTest extends TestCase
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

    public function test_member_can_add_a_friend(): void
    {
        $actor = $this->makeUser();
        $friend = $this->makeUser();
        $this->befriend($actor, $friend);

        $channel = $this->makeChannel();
        $this->addMember($channel, $actor, MembershipStatus::Admin);

        Sanctum::actingAs($actor);

        $this->postJson("/api/channels/{$channel->id}/members", ['recipients' => [$friend->id]])
            ->assertCreated();

        $this->assertDatabaseHas('channels_members', [
            'channels_id' => $channel->id,
            'users_id' => $friend->id,
            'status' => MembershipStatus::Member,
        ]);
    }

    public function test_non_friend_recipients_are_filtered_out(): void
    {
        $actor = $this->makeUser();
        $stranger = $this->makeUser();

        $channel = $this->makeChannel();
        $this->addMember($channel, $actor, MembershipStatus::Admin);

        Sanctum::actingAs($actor);

        $this->postJson("/api/channels/{$channel->id}/members", ['recipients' => [$stranger->id]])
            ->assertCreated();

        $this->assertDatabaseMissing('channels_members', [
            'channels_id' => $channel->id,
            'users_id' => $stranger->id,
        ]);
    }

    public function test_non_member_cannot_add_members(): void
    {
        $actor = $this->makeUser();
        $friend = $this->makeUser();
        $this->befriend($actor, $friend);

        $channel = $this->makeChannel();

        Sanctum::actingAs($actor);

        $this->postJson("/api/channels/{$channel->id}/members", ['recipients' => [$friend->id]])
            ->assertForbidden();
    }

    public function test_recipients_must_be_integers(): void
    {
        $actor = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $actor, MembershipStatus::Admin);

        Sanctum::actingAs($actor);

        $this->postJson("/api/channels/{$channel->id}/members", ['recipients' => ['not-an-id']])
            ->assertStatus(422);

        $this->postJson("/api/channels/{$channel->id}/members", ['recipients' => 'nope'])
            ->assertStatus(422);
    }

    public function test_member_can_list_members(): void
    {
        $actor = $this->makeUser();
        $other = $this->makeUser();

        $channel = $this->makeChannel(['name' => 'Squad']);
        $this->addMember($channel, $actor, MembershipStatus::Admin);
        $this->addMember($channel, $other);

        Sanctum::actingAs($actor);

        $this->getJson("/api/channels/{$channel->id}/members")
            ->assertOk()
            ->assertJsonPath('channel.name', 'Squad')
            ->assertJsonPath('channel.members_count', 2);
    }

    public function test_non_member_cannot_list_members(): void
    {
        $actor = $this->makeUser();
        $channel = $this->makeChannel();

        Sanctum::actingAs($actor);

        $this->getJson("/api/channels/{$channel->id}/members")->assertForbidden();
    }

    public function test_admin_can_kick_member_and_system_message_is_written(): void
    {
        $admin = $this->makeUser(['name' => 'Boss']);
        $member = $this->makeUser(['name' => 'Victim']);

        $channel = $this->makeChannel();
        $this->addMember($channel, $admin, MembershipStatus::Admin);
        $this->addMember($channel, $member);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/channels/{$channel->id}/members/{$member->id}")
            ->assertOk();

        $this->assertDatabaseHas('channels_members', [
            'channels_id' => $channel->id,
            'users_id' => $member->id,
            'status' => MembershipStatus::Removed,
        ]);

        $this->assertDatabaseHas('messages', [
            'channels_id' => $channel->id,
            'type' => 'system',
        ]);
    }

    public function test_non_admin_cannot_kick(): void
    {
        $admin = $this->makeUser();
        $member = $this->makeUser();
        $target = $this->makeUser();

        $channel = $this->makeChannel();
        $this->addMember($channel, $admin, MembershipStatus::Admin);
        $this->addMember($channel, $member);
        $this->addMember($channel, $target);

        Sanctum::actingAs($member);

        $this->deleteJson("/api/channels/{$channel->id}/members/{$target->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('channels_members', [
            'channels_id' => $channel->id,
            'users_id' => $target->id,
            'status' => MembershipStatus::Member,
        ]);
    }

    public function test_admin_cannot_kick_another_admin_or_self(): void
    {
        $admin = $this->makeUser();
        $coAdmin = $this->makeUser();

        $channel = $this->makeChannel();
        $this->addMember($channel, $admin, MembershipStatus::Admin);
        $this->addMember($channel, $coAdmin, MembershipStatus::Admin);

        Sanctum::actingAs($admin);

        $this->deleteJson("/api/channels/{$channel->id}/members/{$coAdmin->id}")->assertStatus(422);
        $this->deleteJson("/api/channels/{$channel->id}/members/{$admin->id}")->assertStatus(422);

        // Оба админа остаются на местах.
        $this->assertDatabaseHas('channels_members', [
            'users_id' => $coAdmin->id,
            'status' => MembershipStatus::Admin,
        ]);
        $this->assertDatabaseHas('channels_members', [
            'users_id' => $admin->id,
            'status' => MembershipStatus::Admin,
        ]);
    }
}
