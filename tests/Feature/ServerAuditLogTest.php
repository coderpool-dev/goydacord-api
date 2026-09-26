<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use App\Models\Servers\ServerAuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class ServerAuditLogTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_moderation_actions_are_logged_and_visible_with_view_audit_log(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $victim = $this->makeUser();
        $victimRow = $this->addServerMember($server, $victim);
        Sanctum::actingAs($owner, ['*']);

        $role = $this->postJson("/api/servers/{$server->id}/roles", ['name' => 'Модер', 'permissions' => ServerPermission::KICK_MEMBERS])
            ->assertCreated()->json('role');
        $this->postJson("/api/servers/{$server->id}/roles/{$role['id']}", ['name' => 'Модератор'])->assertOk();
        $this->putJson("/api/servers/{$server->id}/members/{$victimRow->id}/roles", ['role_ids' => [$role['id']]])->assertOk();
        $this->putJson("/api/servers/{$server->id}/members/{$victimRow->id}/nickname", ['nickname' => 'Жертва'])->assertOk();
        $this->postJson("/api/servers/{$server->id}/channels/{$channel->id}", ['name' => 'новый'])->assertOk();

        Sanctum::actingAs($victim, ['*']);
        $messageId = $this->postJson('/api/server-channel-messages', ['server_channel_id' => $channel->id, 'message' => 'спам'])
            ->assertCreated()->json('message');
        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson("/api/messages/{$messageId}")->assertOk();
        $this->deleteJson("/api/servers/{$server->id}/members/{$victimRow->id}")->assertOk();
        $this->postJson("/api/servers/{$server->id}/bans", ['user_id' => $victim->id, 'reason' => 'флуд'])->assertCreated();

        $actions = ServerAuditLog::query()->where('server_id', $server->id)->orderBy('id')->pluck('action')->all();
        $this->assertSame(
            ['role.create', 'role.update', 'member.roles', 'member.nickname', 'channel.update', 'message.delete', 'member.kick', 'member.ban'],
            $actions,
        );

        $entries = $this->getJson("/api/servers/{$server->id}/audit-logs")->assertOk()->json('entries');
        $this->assertSame('member.ban', $entries[0]['action']);
        $this->assertSame('Жертва', $entries[0]['target_label']);
        $this->assertSame('флуд', $entries[0]['changes']['reason']);
        $this->assertSame(['Модератор'], collect($entries)->firstWhere('action', 'member.roles')['changes']['added']);

        $this->getJson("/api/servers/{$server->id}/audit-logs?action=role.update")
            ->assertOk()->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.changes.name.new', 'Модератор');

        // Без права — нельзя.
        $regular = $this->makeUser();
        $this->addServerMember($server, $regular);
        Sanctum::actingAs($regular, ['*']);
        $this->getJson("/api/servers/{$server->id}/audit-logs")->assertForbidden();
    }
}
