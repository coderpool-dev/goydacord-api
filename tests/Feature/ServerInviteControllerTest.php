<?php

namespace Tests\Feature;

use App\Models\Servers\ServerInvite;
use App\Services\Servers\ServerInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class ServerInviteControllerTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_failed_join_rolls_back_membership_roles_and_invite_usage(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $invite = $this->makeServerInvite($server, $owner, ['max_uses' => 1]);
        $user = $this->makeUser();
        ServerInvite::updating(function () {
            throw new \RuntimeException('simulated counter failure');
        });
        try {
            app(ServerInviteService::class)->join($user, $invite->code);
            $this->fail('Expected counter failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('simulated counter failure', $exception->getMessage());
        } finally {
            ServerInvite::flushEventListeners();
        }
        $this->assertDatabaseMissing('server_members', ['server_id' => $server->id, 'user_id' => $user->id]);
        $this->assertSame(0, (int) $invite->fresh()->uses);
        app(ServerInviteService::class)->join($user, $invite->code);
        $this->assertSame(1, (int) $invite->fresh()->uses);
        $other = $this->makeUser();
        Sanctum::actingAs($other);
        $this->postJson("/api/invites/{$invite->code}/join")->assertNotFound();
        $this->assertDatabaseMissing('server_members', ['server_id' => $server->id, 'user_id' => $other->id]);
    }

    public function test_full_flow_create_server_invite_join_and_message(): void
    {
        // Шаг 1: владелец создаёт сервер (авто general-канал).
        $owner = $this->makeUser();
        Sanctum::actingAs($owner, ['*']);
        $server = $this->postJson('/api/servers', ['name' => 'Дружеский сервер'])->json('server');
        $generalId = $this->getJson("/api/servers/{$server['id']}/channels")->json('channels.0.id');

        // Шаг 2: владелец шлёт сообщение в general.
        $this->postJson('/api/server-channel-messages', [
            'server_channel_id' => $generalId,
            'message' => 'Привет всем!',
        ])->assertCreated();

        // Шаг 3: владелец создаёт инвайт.
        $invite = $this->postJson("/api/servers/{$server['id']}/invites", [])
            ->assertCreated()->json('invite');
        $this->assertNotEmpty($invite['code']);

        // Шаг 4: второй пользователь вступает по коду.
        $joiner = $this->makeUser();
        Sanctum::actingAs($joiner, ['*']);
        $this->postJson("/api/invites/{$invite['code']}/join")
            ->assertOk()->assertJsonPath('server.id', $server['id']);

        $this->assertDatabaseHas('server_members', ['server_id' => $server['id'], 'user_id' => $joiner->id]);

        // Шаг 5: второй пользователь видит сервер в своём списке и читает сообщение владельца.
        $this->getJson('/api/servers')->assertOk()->assertJsonCount(1, 'servers');

        $this->getJson("/api/server-channel-messages/{$generalId}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message', 'Привет всем!');
    }

    public function test_stranger_cannot_send_message_before_joining(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $server->channels()->create(['name' => 'general', 'kind' => 1]);

        $stranger = $this->makeUser();
        Sanctum::actingAs($stranger, ['*']);

        $this->postJson('/api/server-channel-messages', [
            'server_channel_id' => $channel->id,
            'message' => 'hi',
        ])->assertStatus(403);
    }

    public function test_expired_invite_cannot_be_joined(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $invite = $this->makeServerInvite($server, $owner, ['expires_at' => now()->subMinute()]);

        $joiner = $this->makeUser();
        Sanctum::actingAs($joiner, ['*']);

        $this->postJson("/api/invites/{$invite->code}/join")->assertStatus(404);
    }

    public function test_only_owner_can_revoke_invite(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $invite = $this->makeServerInvite($server, $owner);

        $member = $this->makeUser();
        $this->addServerMember($server, $member);
        Sanctum::actingAs($member, ['*']);

        $this->deleteJson("/api/servers/{$server->id}/invites/{$invite->code}")->assertStatus(403);

        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson("/api/servers/{$server->id}/invites/{$invite->code}")->assertOk();
        $this->assertNotNull($invite->fresh()->revoked_at);
    }

    public function test_member_without_create_invite_permission_cannot_create_invite(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $member = $this->makeUser();
        $membership = $this->addServerMember($server, $member);

        // Снимаем CREATE_INVITE у дефолтной роли — «настраивать, кому можно приглашать».
        $membership->roles()->first()->update(['permissions' => 0]);

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/servers/{$server->id}/invites", [])->assertStatus(403);
    }

    public function test_public_invite_preview_works_without_authentication(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner, ['name' => 'Превью-сервер']);
        $this->addServerMember($server, $owner);
        $invite = $this->makeServerInvite($server, $owner);

        $this->getJson("/api/invites/{$invite->code}")
            ->assertOk()
            ->assertJsonPath('usable', true)
            ->assertJsonPath('server.name', 'Превью-сервер');

        $revoked = $this->makeServerInvite($server, $owner, ['revoked_at' => now()]);
        $this->getJson("/api/invites/{$revoked->code}")
            ->assertOk()
            ->assertJsonPath('usable', false);
    }

    public function test_invite_is_revoked_by_code_only_within_its_server(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $invite = $this->makeServerInvite($server, $owner);

        $otherOwner = $this->makeUser();
        $otherServer = $this->makeServer($otherOwner);
        $this->addServerMember($otherServer, $otherOwner);

        // Владелец чужого сервера не может отозвать приглашение через свой сервер.
        Sanctum::actingAs($otherOwner, ['*']);
        $this->deleteJson("/api/servers/{$otherServer->id}/invites/{$invite->code}")->assertNotFound();
        $this->assertNull($invite->fresh()->revoked_at);

        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson("/api/servers/{$server->id}/invites/{$invite->code}")->assertOk();
        $this->assertNotNull($invite->fresh()->revoked_at);

        $this->getJson("/api/invites/{$invite->code}")->assertOk()->assertJsonPath('usable', false);
    }

    public function test_invite_list_shows_creator_with_avatar(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $this->makeServerInvite($server, $owner);
        Sanctum::actingAs($owner, ['*']);

        $this->getJson("/api/servers/{$server->id}/invites")
            ->assertOk()
            ->assertJsonPath('invites.0.creator.id', $owner->id)
            ->assertJsonPath('invites.0.creator.login', $owner->login)
            ->assertJsonStructure(['invites' => [['creator' => ['id', 'name', 'login', 'avatar']]]]);
    }
}
