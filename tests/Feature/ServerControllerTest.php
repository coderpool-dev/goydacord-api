<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class ServerControllerTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_creates_server_with_default_role_and_general_channel(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/servers', ['name' => 'Мой сервер']);

        $response->assertCreated()->assertJsonPath('server.name', 'Мой сервер');
        $serverId = $response->json('server.id');

        $this->assertDatabaseHas('servers', ['id' => $serverId, 'owner_id' => $user->id]);
        $this->assertDatabaseHas('server_members', ['server_id' => $serverId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('server_roles', ['server_id' => $serverId, 'is_default' => true]);
        $this->assertDatabaseHas('server_channels', [
            'server_id' => $serverId, 'name' => 'general', 'kind' => ServerChannelKind::Text->value,
        ]);
    }

    public function test_index_lists_only_servers_the_user_is_a_member_of(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $stranger = $this->makeUser();
        Sanctum::actingAs($stranger, ['*']);

        $this->getJson('/api/servers')->assertOk()->assertJsonCount(0, 'servers');

        Sanctum::actingAs($owner, ['*']);
        $this->getJson('/api/servers')->assertOk()->assertJsonCount(1, 'servers');
    }

    public function test_only_owner_can_update_or_delete_server(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $member = $this->makeUser();
        $this->addServerMember($server, $member);
        Sanctum::actingAs($member, ['*']);

        $this->postJson("/api/servers/{$server->id}", ['name' => 'Взлом'])->assertStatus(403);
        $this->deleteJson("/api/servers/{$server->id}")->assertStatus(403);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}", ['name' => 'Новое имя'])
            ->assertOk()->assertJsonPath('server.name', 'Новое имя');
        $this->deleteJson("/api/servers/{$server->id}")->assertOk();
        $this->assertDatabaseMissing('servers', ['id' => $server->id]);
    }

    public function test_owner_cannot_leave_but_member_can(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $member = $this->makeUser();
        $this->addServerMember($server, $member);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/leave")->assertStatus(422);

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/servers/{$server->id}/leave")->assertOk();
        $this->assertDatabaseHas('server_members', ['server_id' => $server->id, 'user_id' => $member->id, 'status' => 0]);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/servers', ['name' => 'x'])->assertStatus(401);
    }
}
