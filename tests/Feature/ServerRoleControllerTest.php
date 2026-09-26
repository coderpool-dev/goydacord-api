<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use App\Models\Servers\Server;
use App\Models\Servers\ServerMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class ServerRoleControllerTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_owner_can_create_and_update_role(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        Sanctum::actingAs($owner, ['*']);
        $role = $this->postJson("/api/servers/{$server->id}/roles", [
            'name' => 'Модератор',
            'color' => '#ff0000',
            'permissions' => ServerPermission::KICK_MEMBERS,
        ])->assertCreated()->json('role');

        $this->assertSame('Модератор', $role['name']);
        $this->assertFalse($role['is_default']);

        $this->postJson("/api/servers/{$server->id}/roles/{$role['id']}", ['name' => 'Модер'])
            ->assertOk()->assertJsonPath('role.name', 'Модер');
    }

    public function test_default_role_cannot_be_deleted(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $membership = $this->addServerMember($server, $owner);
        $defaultRole = $membership->roles()->first();

        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson("/api/servers/{$server->id}/roles/{$defaultRole->id}")->assertStatus(422);
    }

    public function test_member_without_manage_channels_gets_403_creating_channel_role_with_it_gets_200(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $member = $this->makeUser();
        $this->addServerMember($server, $member);

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/servers/{$server->id}/channels", [
            'name' => 'staff', 'kind' => ServerChannelKind::Text->value,
        ])->assertStatus(403);

        Sanctum::actingAs($owner, ['*']);
        $role = $this->postJson("/api/servers/{$server->id}/roles", [
            'name' => 'Управляющий', 'permissions' => ServerPermission::MANAGE_CHANNELS,
        ])->assertCreated()->json('role');

        $this->putJson("/api/servers/{$server->id}/members/{$this->memberIdFor($server, $member)}/roles", [
            'role_ids' => [$role['id']],
        ])->assertOk();

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/servers/{$server->id}/channels", [
            'name' => 'staff', 'kind' => ServerChannelKind::Text->value,
        ])->assertCreated();
    }

    public function test_non_owner_without_manage_roles_cannot_create_role(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $member = $this->makeUser();
        $this->addServerMember($server, $member);

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/servers/{$server->id}/roles", ['name' => 'x'])->assertStatus(403);
    }

    private function memberIdFor(Server $server, User $user): int
    {
        return ServerMember::query()
            ->where('server_id', $server->id)
            ->where('user_id', $user->id)
            ->value('id');
    }
}
