<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use App\Events\ServerVoiceModerated;
use App\Models\Conversations\CallSession;
use App\Models\Servers\Server;
use App\Models\Servers\ServerMember;
use App\Models\Servers\ServerRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

/** Иерархия ролей, «Администратор», порядок ролей, права участников на канале, модерация голоса. */
class ServerRoleHierarchyTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    /** @return array{0: Server, 1: User} */
    private function serverWithOwner(): array
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        return [$server, $owner];
    }

    /** Участник с одной ролью заданных прав; роль встаёт выше всех существующих. */
    private function memberWithRole(Server $server, int $permissions): array
    {
        $user = $this->makeUser();
        $member = $this->addServerMember($server, $user);
        $role = $this->makeServerRole($server, ['permissions' => $permissions]);
        $member->roles()->attach($role->id);

        return [$user, $member, $role];
    }

    public function test_moderator_cannot_escalate_by_creating_role_with_permissions_they_lack(): void
    {
        [$server] = $this->serverWithOwner();
        [$mod] = $this->memberWithRole($server, ServerPermission::MANAGE_ROLES | ServerPermission::KICK_MEMBERS);
        Sanctum::actingAs($mod, ['*']);

        $this->postJson("/api/servers/{$server->id}/roles", ['name' => 'Админ', 'permissions' => ServerPermission::ADMINISTRATOR])
            ->assertForbidden();

        // Свои права выдавать можно; новая роль встаёт внизу, под ролью модератора.
        $role = $this->postJson("/api/servers/{$server->id}/roles", ['name' => 'Кикер', 'permissions' => ServerPermission::KICK_MEMBERS])
            ->assertCreated()->json('role');
        $this->assertSame(1, $role['position']);
    }

    public function test_moderator_cannot_edit_or_delete_role_at_or_above_their_own(): void
    {
        [$server] = $this->serverWithOwner();
        [$mod, , $modRole] = $this->memberWithRole($server, ServerPermission::MANAGE_ROLES);
        $adminRole = $this->makeServerRole($server, ['permissions' => ServerPermission::ADMINISTRATOR]);
        Sanctum::actingAs($mod, ['*']);

        $this->postJson("/api/servers/{$server->id}/roles/{$adminRole->id}", ['permissions' => 0])->assertForbidden();
        $this->deleteJson("/api/servers/{$server->id}/roles/{$adminRole->id}")->assertForbidden();
        $this->postJson("/api/servers/{$server->id}/roles/{$modRole->id}", ['name' => 'Я главный'])->assertForbidden();
    }

    public function test_moderator_cannot_assign_role_above_self_but_can_assign_lower(): void
    {
        [$server] = $this->serverWithOwner();
        $lowRole = $this->makeServerRole($server, ['permissions' => 0]);
        [$mod, $modMember] = $this->memberWithRole($server, ServerPermission::MANAGE_ROLES);
        $adminRole = $this->makeServerRole($server, ['permissions' => ServerPermission::ADMINISTRATOR]);
        Sanctum::actingAs($mod, ['*']);

        $this->putJson("/api/servers/{$server->id}/members/{$modMember->id}/roles", ['role_ids' => [$adminRole->id]])
            ->assertForbidden();

        $target = $this->addServerMember($server, $this->makeUser());
        $this->putJson("/api/servers/{$server->id}/members/{$target->id}/roles", ['role_ids' => [$lowRole->id]])
            ->assertOk();
        $this->assertTrue($target->roles()->whereKey($lowRole->id)->exists());
    }

    public function test_cannot_kick_or_ban_member_with_equal_or_higher_role(): void
    {
        [$server] = $this->serverWithOwner();
        [$mod] = $this->memberWithRole($server, ServerPermission::KICK_MEMBERS | ServerPermission::BAN_MEMBERS);
        [$admin, $adminMember] = $this->memberWithRole($server, ServerPermission::ADMINISTRATOR);
        $regular = $this->addServerMember($server, $this->makeUser());
        Sanctum::actingAs($mod, ['*']);

        $this->deleteJson("/api/servers/{$server->id}/members/{$adminMember->id}")->assertForbidden();
        $this->postJson("/api/servers/{$server->id}/bans", ['user_id' => $admin->id])->assertForbidden();
        $this->deleteJson("/api/servers/{$server->id}/members/{$regular->id}")->assertOk();
    }

    public function test_administrator_gets_all_permissions_in_server_list(): void
    {
        [$server] = $this->serverWithOwner();
        [$admin] = $this->memberWithRole($server, ServerPermission::ADMINISTRATOR);
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/servers')
            ->assertOk()
            ->assertJsonPath('servers.0.my_permissions', ServerPermission::ALL)
            ->assertJsonPath('servers.0.is_owner', false);
    }

    public function test_reorder_keeps_roles_at_or_above_actor_locked(): void
    {
        [$server, $owner] = $this->serverWithOwner();
        $low = $this->makeServerRole($server);
        $mid = $this->makeServerRole($server);
        [$mod, , $modRole] = $this->memberWithRole($server, ServerPermission::MANAGE_ROLES);
        $top = $this->makeServerRole($server);

        Sanctum::actingAs($mod, ['*']);
        // Поднять низшую роль над своей — нельзя.
        $this->putJson("/api/servers/{$server->id}/roles/positions", ['role_ids' => [$top->id, $low->id, $modRole->id, $mid->id]])
            ->assertForbidden();
        // Переставить роли ниже своей — можно.
        $this->putJson("/api/servers/{$server->id}/roles/positions", ['role_ids' => [$top->id, $modRole->id, $low->id, $mid->id]])
            ->assertOk();
        $this->assertGreaterThan($mid->fresh()->position, $low->fresh()->position);

        // Владелец двигает что угодно.
        Sanctum::actingAs($owner, ['*']);
        $this->putJson("/api/servers/{$server->id}/roles/positions", ['role_ids' => [$low->id, $mid->id, $modRole->id, $top->id]])
            ->assertOk();
        $this->assertSame(4, $low->fresh()->position);
        $this->assertSame(0, ServerRole::query()->where('server_id', $server->id)->where('is_default', true)->value('position'));
    }

    public function test_reorder_rejects_stale_role_list(): void
    {
        [$server, $owner] = $this->serverWithOwner();
        $a = $this->makeServerRole($server);
        $this->makeServerRole($server);
        Sanctum::actingAs($owner, ['*']);

        $this->putJson("/api/servers/{$server->id}/roles/positions", ['role_ids' => [$a->id]])->assertUnprocessable();
    }

    public function test_member_overwrite_hides_channel_from_one_member(): void
    {
        [$server, $owner] = $this->serverWithOwner();
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $user = $this->makeUser();
        $member = $this->addServerMember($server, $user);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/channels/{$channel->id}", [
            'member_overwrites' => [['member_id' => $member->id, 'allow' => 0, 'deny' => ServerPermission::VIEW_CHANNELS]],
        ])->assertOk()->assertJsonPath('channel.member_overwrites.0.member_id', $member->id);

        Sanctum::actingAs($user, ['*']);
        $ids = collect($this->getJson("/api/servers/{$server->id}/channels")->json('channels'))->pluck('id');
        $this->assertFalse($ids->contains($channel->id));
    }

    public function test_channel_permissions_need_manage_roles_and_hierarchy(): void
    {
        [$server] = $this->serverWithOwner();
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        [$channelManager] = $this->memberWithRole($server, ServerPermission::MANAGE_CHANNELS);
        $everyoneId = ServerRole::query()->where('server_id', $server->id)->where('is_default', true)->value('id');

        Sanctum::actingAs($channelManager, ['*']);
        $this->postJson("/api/servers/{$server->id}/channels/{$channel->id}", [
            'overwrites' => [['role_id' => $everyoneId, 'allow' => 0, 'deny' => ServerPermission::VIEW_CHANNELS]],
        ])->assertForbidden();

        [$mod] = $this->memberWithRole($server, ServerPermission::MANAGE_CHANNELS | ServerPermission::MANAGE_ROLES | ServerPermission::VIEW_CHANNELS);
        $adminRole = $this->makeServerRole($server, ['permissions' => ServerPermission::ADMINISTRATOR]);
        Sanctum::actingAs($mod, ['*']);
        // Роль выше своей — нельзя; @everyone и свои биты — можно.
        $this->postJson("/api/servers/{$server->id}/channels/{$channel->id}", [
            'overwrites' => [['role_id' => $adminRole->id, 'allow' => 0, 'deny' => ServerPermission::VIEW_CHANNELS]],
        ])->assertForbidden();
        $this->postJson("/api/servers/{$server->id}/channels/{$channel->id}", [
            'overwrites' => [['role_id' => $everyoneId, 'allow' => 0, 'deny' => ServerPermission::VIEW_CHANNELS]],
        ])->assertOk();
    }

    public function test_voice_mute_requires_permission_and_hierarchy_and_notifies_target(): void
    {
        Event::fake([ServerVoiceModerated::class]);
        [$server] = $this->serverWithOwner();
        $voice = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);
        [$mod] = $this->memberWithRole($server, ServerPermission::MUTE_MEMBERS);
        $target = $this->makeUser();
        $this->addServerMember($server, $target);
        [$admin] = $this->memberWithRole($server, ServerPermission::ADMINISTRATOR);

        Sanctum::actingAs($mod, ['*']);
        $this->postJson("/api/servers/{$server->id}/voice/members/{$target->id}/state", ['deafened' => true])->assertForbidden();
        $this->postJson("/api/servers/{$server->id}/voice/members/{$admin->id}/state", ['muted' => true])->assertForbidden();
        $this->postJson("/api/servers/{$server->id}/voice/members/{$target->id}/state", ['muted' => true])
            ->assertOk()->assertJsonPath('voice_muted', true);

        Event::assertDispatched(ServerVoiceModerated::class, fn (ServerVoiceModerated $e) => $e->userId === $target->id && $e->voiceMuted);
        $this->assertTrue(ServerMember::query()->where('user_id', $target->id)->value('voice_muted'));

        // Мьют модератора виден при входе в голосовой канал.
        Sanctum::actingAs($target, ['*']);
        $this->postJson("/api/server-channels/{$voice->id}/calls", ['session_id' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('call.voice_muted', true)->assertJsonPath('call.can_speak', true);
    }

    public function test_move_and_disconnect_voice_member(): void
    {
        Event::fake([ServerVoiceModerated::class]);
        [$server] = $this->serverWithOwner();
        $from = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);
        $to = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);
        [$mod] = $this->memberWithRole($server, ServerPermission::MOVE_MEMBERS);
        $target = $this->makeUser();
        $this->addServerMember($server, $target);

        Sanctum::actingAs($mod, ['*']);
        $this->postJson("/api/servers/{$server->id}/voice/members/{$target->id}/move", ['server_channel_id' => $to->id])
            ->assertStatus(422); // ещё не в голосе

        Sanctum::actingAs($target, ['*']);
        $this->postJson("/api/server-channels/{$from->id}/calls", ['session_id' => (string) Str::uuid()])->assertOk();

        Sanctum::actingAs($mod, ['*']);
        $this->postJson("/api/servers/{$server->id}/voice/members/{$target->id}/move", ['server_channel_id' => $to->id])->assertOk();
        Event::assertDispatched(ServerVoiceModerated::class, fn (ServerVoiceModerated $e) => $e->action === 'move' && $e->serverChannelId === $to->id);

        $this->postJson("/api/servers/{$server->id}/voice/members/{$target->id}/move", ['server_channel_id' => null])->assertOk();
        Event::assertDispatched(ServerVoiceModerated::class, fn (ServerVoiceModerated $e) => $e->action === 'disconnect');
        $this->assertFalse(CallSession::query()->where('user_id', $target->id)->exists());
    }

    public function test_joining_voice_channel_leaves_other_voice_channels_and_stale_heartbeat_is_superseded(): void
    {
        [$server, $owner] = $this->serverWithOwner();
        $dayz = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);
        $dota = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);
        $oldSession = (string) Str::uuid();
        $newSession = (string) Str::uuid();
        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/server-channels/{$dayz->id}/calls", ['session_id' => $oldSession])->assertOk();
        $this->postJson("/api/server-channels/{$dota->id}/calls", ['session_id' => $newSession])->assertOk();

        $this->assertFalse(CallSession::query()->where('server_channel_id', $dayz->id)->exists());
        $this->assertTrue(CallSession::query()->where('server_channel_id', $dota->id)->exists());
        $this->getJson("/api/server-channels/{$dayz->id}/calls/participants")->assertOk()->assertJsonCount(0, 'participants');

        // Старая вкладка продолжает пинговать dayz — сессия там не воскресает.
        $this->postJson("/api/server-channels/{$dayz->id}/calls/heartbeat", ['session_id' => $oldSession])
            ->assertOk()->assertJsonPath('superseded', true)->assertJsonPath('active', false);
        $this->assertFalse(CallSession::query()->where('server_channel_id', $dayz->id)->exists());
    }

    public function test_administrator_can_delete_others_messages_and_sees_can_manage_messages(): void
    {
        [$server] = $this->serverWithOwner();
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $author = $this->makeUser();
        $this->addServerMember($server, $author);
        [$admin] = $this->memberWithRole($server, ServerPermission::ADMINISTRATOR);
        $regular = $this->makeUser();
        $this->addServerMember($server, $regular);

        Sanctum::actingAs($author, ['*']);
        $messageId = $this->postJson('/api/server-channel-messages', ['server_channel_id' => $channel->id, 'message' => 'привет'])
            ->assertCreated()->json('message');

        Sanctum::actingAs($regular, ['*']);
        $this->deleteJson("/api/messages/{$messageId}")->assertForbidden();
        $this->assertFalse(collect($this->getJson("/api/servers/{$server->id}/channels")->json('channels'))
            ->firstWhere('id', $channel->id)['can_manage_messages']);

        Sanctum::actingAs($admin, ['*']);
        $this->assertTrue(collect($this->getJson("/api/servers/{$server->id}/channels")->json('channels'))
            ->firstWhere('id', $channel->id)['can_manage_messages']);
        $this->deleteJson("/api/messages/{$messageId}")->assertOk();
    }

    public function test_without_speak_permission_member_joins_suppressed_and_priority_speaker_is_flagged(): void
    {
        [$server, $owner] = $this->serverWithOwner();
        $voice = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);
        ServerRole::query()->where('server_id', $server->id)->where('is_default', true)
            ->update(['permissions' => ServerPermission::DEFAULT & ~ServerPermission::SPEAK]);
        $listener = $this->makeUser();
        $this->addServerMember($server, $listener);

        Sanctum::actingAs($listener, ['*']);
        $this->postJson("/api/server-channels/{$voice->id}/calls", ['session_id' => (string) Str::uuid()])
            ->assertOk()->assertJsonPath('call.can_speak', false);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/server-channels/{$voice->id}/calls", ['session_id' => (string) Str::uuid()])->assertOk();
        $participants = collect($this->getJson("/api/server-channels/{$voice->id}/calls/participants")->json('participants'))->keyBy('user_id');
        $this->assertTrue($participants[$owner->id]['priority_speaker']);
        $this->assertFalse($participants[$listener->id]['priority_speaker']);
    }
}
