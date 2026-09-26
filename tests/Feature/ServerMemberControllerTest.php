<?php

namespace Tests\Feature;

use App\Enums\ServerMembershipStatus;
use App\Models\Servers\ServerMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class ServerMemberControllerTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_owner_can_kick_member(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $member = $this->makeUser();
        $membership = $this->addServerMember($server, $member);

        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson("/api/servers/{$server->id}/members/{$membership->id}")->assertOk();

        $this->assertSame(ServerMembershipStatus::Removed, $membership->fresh()->status);

        $this->getJson("/api/servers/{$server->id}/members")
            ->assertOk()
            ->assertJsonCount(1, 'members');
    }

    public function test_non_owner_cannot_kick_member(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $member = $this->makeUser();
        $membership = $this->addServerMember($server, $member);
        $other = $this->makeUser();
        $this->addServerMember($server, $other);

        Sanctum::actingAs($other, ['*']);
        $this->deleteJson("/api/servers/{$server->id}/members/{$membership->id}")->assertStatus(403);
    }

    public function test_owner_cannot_be_kicked(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $ownerMembership = $this->addServerMember($server, $owner);

        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson("/api/servers/{$server->id}/members/{$ownerMembership->id}")->assertStatus(422);
    }

    public function test_kicked_member_can_rejoin_via_invite(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $member = $this->makeUser();
        $membership = $this->addServerMember($server, $member);
        $invite = $this->makeServerInvite($server, $owner);

        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson("/api/servers/{$server->id}/members/{$membership->id}")->assertOk();

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/invites/{$invite->code}/join")->assertOk();

        $this->assertSame(ServerMembershipStatus::Member, $membership->fresh()->status);
        $this->assertSame(1, ServerMember::query()->where('server_id', $server->id)->where('user_id', $member->id)->count());
    }

    public function test_cannot_kick_member_from_another_server(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $otherOwner = $this->makeUser();
        $otherServer = $this->makeServer($otherOwner);
        $this->addServerMember($otherServer, $otherOwner);
        $foreignMember = $this->makeUser();
        $foreignMembership = $this->addServerMember($otherServer, $foreignMember);

        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson("/api/servers/{$server->id}/members/{$foreignMembership->id}")->assertStatus(404);
    }
}
