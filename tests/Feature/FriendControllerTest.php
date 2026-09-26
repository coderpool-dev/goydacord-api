<?php

namespace Tests\Feature;

use App\Enums\FriendStatus;
use App\Models\Social\Friend;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FriendControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sending_a_reverse_request_accepts_the_existing_incoming_request(): void
    {
        [$sender, $recipient] = $this->users();

        Friend::create([
            'users_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendStatus::Pending,
        ]);

        Sanctum::actingAs($recipient);

        $this->postJson('/api/friends/requests', ['friend_login' => $sender->login])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('friend', [
            'users_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendStatus::Accepted,
        ]);
    }

    public function test_a_rejected_request_can_be_sent_again(): void
    {
        [$sender, $recipient] = $this->users();

        Friend::create([
            'users_id' => $recipient->id,
            'friend_id' => $sender->id,
            'status' => FriendStatus::Rejected,
        ]);

        Sanctum::actingAs($sender);

        $this->postJson('/api/friends/requests', ['friend_login' => $recipient->login])
            ->assertCreated()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('friend', [
            'users_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendStatus::Pending,
        ]);
    }

    public function test_friend_requests_do_not_have_a_separate_low_rate_limit(): void
    {
        [$sender, $recipient] = $this->users();

        Sanctum::actingAs($sender);

        for ($attempt = 0; $attempt < 31; $attempt++) {
            $response = $this->postJson('/api/friends/requests', [
                'friend_login' => $recipient->login,
            ]);

            $response->assertStatus($attempt === 0 ? 201 : 409);
        }
    }

    public function test_recipient_sees_a_request_sent_by_another_user(): void
    {
        [$sender, $recipient] = $this->users();

        Sanctum::actingAs($sender);
        $this->postJson('/api/friends/requests', ['friend_login' => $recipient->login])
            ->assertCreated();

        Sanctum::actingAs($recipient);
        $this->getJson('/api/friends')
            ->assertOk()
            ->assertJsonFragment([
                'login' => $sender->login,
                'status' => FriendStatus::Pending,
                'direction' => 'received',
            ]);
    }

    public function test_pending_request_hides_game_and_music_activity(): void
    {
        [$sender, $recipient] = $this->users();
        $sender->forceFill([
            'game_status_text' => 'Играет в Dota 2',
            'game_status_synced_at' => now(),
            'music_status_text' => 'Слушает Кино — Группа крови · Яндекс Музыка',
        ])->save();

        Friend::create([
            'users_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendStatus::Pending,
        ]);

        Sanctum::actingAs($recipient);
        $this->getJson('/api/friends')
            ->assertOk()
            ->assertJsonPath('friends.0.login', $sender->login)
            ->assertJsonPath('friends.0.status', FriendStatus::Pending)
            ->assertJsonPath('friends.0.game_status_text', null)
            ->assertJsonPath('friends.0.music_status_text', null)
            ->assertJsonPath('friends.0.yandex_music_now_playing', null);

        Sanctum::actingAs($sender);
        $this->getJson('/api/friends')
            ->assertOk()
            ->assertJsonPath('friends.0.login', $recipient->login)
            ->assertJsonPath('friends.0.status', FriendStatus::Pending)
            ->assertJsonPath('friends.0.game_status_text', null)
            ->assertJsonPath('friends.0.music_status_text', null);
    }

    public function test_pending_requester_cannot_read_activity_summary(): void
    {
        [$sender, $recipient] = $this->users();
        $recipient->forceFill([
            'game_status_text' => 'Играет в Dota 2',
            'game_status_synced_at' => now(),
        ])->save();

        Friend::create([
            'users_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendStatus::Pending,
        ]);

        Sanctum::actingAs($sender);
        $this->getJson("/api/users/{$recipient->id}/activity")
            ->assertOk()
            ->assertJsonPath('visible', false)
            ->assertJsonPath('featured_streak', null)
            ->assertJsonPath('game_streaks', [])
            ->assertJsonPath('history', []);
    }

    public function test_accepted_friend_can_see_game_activity(): void
    {
        [$sender, $recipient] = $this->users();
        $sender->forceFill([
            'game_status_text' => 'Играет в Dota 2',
            'game_status_synced_at' => now(),
        ])->save();

        Friend::create([
            'users_id' => $sender->id,
            'friend_id' => $recipient->id,
            'status' => FriendStatus::Accepted,
        ]);

        Sanctum::actingAs($recipient);
        $this->getJson('/api/friends')
            ->assertOk()
            ->assertJsonPath('friends.0.login', $sender->login)
            ->assertJsonPath('friends.0.game_status_text', 'Играет в Dota 2');
    }

    public function test_mutual_friends_endpoint_returns_only_shared_accepted_friends(): void
    {
        $currentUser = User::factory()->create(['login' => 'current', 'last_online' => now()]);
        $profileUser = User::factory()->create(['login' => 'profile', 'last_online' => now()]);
        $mutualFriend = User::factory()->create(['login' => 'shared', 'name' => 'Shared Friend', 'last_online' => now()]);
        $currentOnlyFriend = User::factory()->create(['login' => 'current_only', 'last_online' => now()]);
        $pendingSharedUser = User::factory()->create(['login' => 'pending_shared', 'last_online' => now()]);

        Friend::create(['users_id' => $currentUser->id, 'friend_id' => $mutualFriend->id, 'status' => FriendStatus::Accepted]);
        Friend::create(['users_id' => $profileUser->id, 'friend_id' => $mutualFriend->id, 'status' => FriendStatus::Accepted]);
        Friend::create(['users_id' => $currentUser->id, 'friend_id' => $currentOnlyFriend->id, 'status' => FriendStatus::Accepted]);
        Friend::create(['users_id' => $currentUser->id, 'friend_id' => $pendingSharedUser->id, 'status' => FriendStatus::Pending]);
        Friend::create(['users_id' => $profileUser->id, 'friend_id' => $pendingSharedUser->id, 'status' => FriendStatus::Accepted]);

        Sanctum::actingAs($currentUser);

        $this->getJson("/api/friends/mutual/{$profileUser->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Список общих друзей успешно получен')
            ->assertJsonPath('friends.0.login', 'shared')
            ->assertJsonPath('friends.0.name', 'Shared Friend')
            ->assertJsonPath('friends.0.status_display', 'друзья')
            ->assertJsonMissing(['login' => 'current_only'])
            ->assertJsonMissing(['login' => 'pending_shared']);
    }

    private function users(): array
    {
        $sender = User::factory()->create([
            'login' => 'sender',
            'date' => now()->subYears(20),
            'last_online' => now(),
        ]);
        $recipient = User::factory()->create([
            'login' => 'recipient',
            'date' => now()->subYears(20),
            'last_online' => now(),
        ]);

        return [$sender, $recipient];
    }
}
