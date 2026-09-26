<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class ServerBanControllerTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_owner_can_ban_member_and_it_removes_membership(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $member = $this->makeUser();
        $this->addServerMember($server, $member);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/bans", [
            'user_id' => $member->id,
            'reason' => 'спам',
        ])->assertCreated()->assertJsonPath('ban.reason', 'спам');

        $this->assertDatabaseHas('server_bans', ['server_id' => $server->id, 'user_id' => $member->id]);

        $this->getJson("/api/servers/{$server->id}/members")
            ->assertOk()
            ->assertJsonCount(1, 'members');
    }

    public function test_banned_user_cannot_join_via_invite(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $invite = $this->makeServerInvite($server, $owner);

        $stranger = $this->makeUser();

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/bans", ['user_id' => $stranger->id])->assertCreated();

        Sanctum::actingAs($stranger, ['*']);
        $this->postJson("/api/invites/{$invite->code}/join")->assertStatus(403);
    }

    public function test_unban_restores_ability_to_join(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $invite = $this->makeServerInvite($server, $owner);

        $stranger = $this->makeUser();

        Sanctum::actingAs($owner, ['*']);
        $ban = $this->postJson("/api/servers/{$server->id}/bans", ['user_id' => $stranger->id])
            ->assertCreated()->json('ban');

        $this->deleteJson("/api/servers/{$server->id}/bans/{$ban['id']}")->assertOk();
        $this->assertDatabaseMissing('server_bans', ['id' => $ban['id']]);

        Sanctum::actingAs($stranger, ['*']);
        $this->postJson("/api/invites/{$invite->code}/join")->assertOk();
    }

    public function test_owner_cannot_be_banned(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/bans", ['user_id' => $owner->id])->assertStatus(422);
    }

    public function test_non_owner_cannot_ban(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $member = $this->makeUser();
        $this->addServerMember($server, $member);
        $target = $this->makeUser();
        $this->addServerMember($server, $target);

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/servers/{$server->id}/bans", ['user_id' => $target->id])->assertStatus(403);
    }

    public function test_cannot_ban_same_user_twice(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $member = $this->makeUser();
        $this->addServerMember($server, $member);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/bans", ['user_id' => $member->id])->assertCreated();
        $this->postJson("/api/servers/{$server->id}/bans", ['user_id' => $member->id])->assertStatus(422);
    }
}
