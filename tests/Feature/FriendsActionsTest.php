<?php

namespace Tests\Feature;

use App\Enums\FriendStatus;
use App\Models\Social\Friend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendsActionsTest extends TestCase
{
    use RefreshDatabase;

    private function users(): array
    {
        return [
            User::factory()->create(['login' => 'alice']),
            User::factory()->create(['login' => 'bob']),
        ];
    }

    public function test_cannot_add_self(): void
    {
        [$alice] = $this->users();
        Sanctum::actingAs($alice);

        $this->postJson('/api/friends/requests', ['friend_login' => 'alice'])
            ->assertStatus(400);
    }

    public function test_add_unknown_user_returns_404(): void
    {
        [$alice] = $this->users();
        Sanctum::actingAs($alice);

        $this->postJson('/api/friends/requests', ['friend_login' => 'ghost'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Пользователь не найден');
    }

    public function test_unknown_login_in_url_returns_404(): void
    {
        [$alice] = $this->users();
        Sanctum::actingAs($alice);

        $this->patchJson('/api/friends/requests/ghost')
            ->assertNotFound()
            ->assertJsonPath('message', 'Пользователь не найден');
    }

    public function test_accept_incoming_request(): void
    {
        [$alice, $bob] = $this->users();
        Friend::create(['users_id' => $bob->id, 'friend_id' => $alice->id, 'status' => FriendStatus::Pending]);

        Sanctum::actingAs($alice);

        $this->patchJson('/api/friends/requests/bob')->assertOk();

        $this->assertDatabaseHas('friend', [
            'users_id' => $bob->id,
            'friend_id' => $alice->id,
            'status' => FriendStatus::Accepted,
        ]);
    }

    public function test_reject_incoming_request_marks_rejected(): void
    {
        [$alice, $bob] = $this->users();
        Friend::create(['users_id' => $bob->id, 'friend_id' => $alice->id, 'status' => FriendStatus::Pending]);

        Sanctum::actingAs($alice);

        $this->deleteJson('/api/friends/requests/bob')
            ->assertOk()
            ->assertJsonPath('action', 'rejected');

        $this->assertDatabaseHas('friend', [
            'users_id' => $bob->id,
            'status' => FriendStatus::Rejected,
        ]);
    }

    public function test_reject_own_outgoing_request_cancels_it(): void
    {
        [$alice, $bob] = $this->users();
        Friend::create(['users_id' => $alice->id, 'friend_id' => $bob->id, 'status' => FriendStatus::Pending]);

        Sanctum::actingAs($alice);

        $this->deleteJson('/api/friends/requests/bob')
            ->assertOk()
            ->assertJsonPath('action', 'cancelled');

        $this->assertDatabaseMissing('friend', ['users_id' => $alice->id, 'friend_id' => $bob->id]);
    }

    public function test_remove_friendship(): void
    {
        [$alice, $bob] = $this->users();
        Friend::create(['users_id' => $alice->id, 'friend_id' => $bob->id, 'status' => FriendStatus::Accepted]);

        Sanctum::actingAs($alice);

        $this->deleteJson('/api/friends/bob')->assertOk();

        $this->assertDatabaseMissing('friend', ['users_id' => $alice->id, 'friend_id' => $bob->id]);
    }

    public function test_block_and_unblock(): void
    {
        [$alice, $bob] = $this->users();
        Sanctum::actingAs($alice);

        $this->putJson('/api/blocked-users/bob')->assertOk();
        $this->putJson('/api/blocked-users/bob')->assertOk();
        $this->assertDatabaseCount('friend', 1);
        $this->assertDatabaseHas('friend', [
            'users_id' => $alice->id,
            'friend_id' => $bob->id,
            'status' => FriendStatus::Blocked,
        ]);

        $this->deleteJson('/api/blocked-users/bob')->assertOk();
        $this->assertDatabaseMissing('friend', [
            'users_id' => $alice->id,
            'friend_id' => $bob->id,
            'status' => FriendStatus::Blocked,
        ]);
    }

    public function test_cannot_block_self(): void
    {
        [$alice] = $this->users();
        Sanctum::actingAs($alice);

        $this->putJson('/api/blocked-users/alice')->assertStatus(400);
    }
}
