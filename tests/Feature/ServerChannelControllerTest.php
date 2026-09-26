<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class ServerChannelControllerTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_member_can_list_channels_but_only_owner_can_create(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $this->makeServerChannel($server, ['name' => 'general']);

        $member = $this->makeUser();
        $this->addServerMember($server, $member);

        Sanctum::actingAs($member, ['*']);
        $this->getJson("/api/servers/{$server->id}/channels")->assertOk()->assertJsonCount(1, 'channels'); // general

        $this->postJson("/api/servers/{$server->id}/channels", [
            'name' => 'voice', 'kind' => ServerChannelKind::Voice->value,
        ])->assertStatus(403);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/channels", [
            'name' => 'voice', 'kind' => ServerChannelKind::Voice->value,
        ])->assertCreated()->assertJsonPath('channel.name', 'voice');

        $this->getJson("/api/servers/{$server->id}/channels")->assertOk()->assertJsonCount(2, 'channels');
    }

    public function test_non_member_cannot_see_channels(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);

        $stranger = $this->makeUser();
        Sanctum::actingAs($stranger, ['*']);

        $this->getJson("/api/servers/{$server->id}/channels")->assertStatus(403);
    }

    public function test_cannot_delete_last_text_channel(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $general = $this->makeServerChannel($server, ['name' => 'general', 'kind' => ServerChannelKind::Text]);

        Sanctum::actingAs($owner, ['*']);
        $this->deleteJson("/api/servers/{$server->id}/channels/{$general->id}")->assertStatus(422);
    }

    public function test_can_update_channel_name(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/channels/{$channel->id}", ['name' => 'renamed'])
            ->assertOk()->assertJsonPath('channel.name', 'renamed');
    }

    public function test_private_channel_hidden_from_stranger_role_visible_to_holder(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $ownerMembership = $this->addServerMember($server, $owner);
        $defaultRole = $ownerMembership->roles()->first();
        $general = $this->makeServerChannel($server, ['name' => 'general', 'kind' => ServerChannelKind::Text]);
        $secret = $this->makeServerChannel($server, ['name' => 'secret', 'kind' => ServerChannelKind::Text]);

        $outsider = $this->makeUser();
        $this->addServerMember($server, $outsider);

        $staffRole = $this->makeServerRole($server, ['permissions' => 0]);
        $insider = $this->makeUser();
        $insiderMembership = $this->addServerMember($server, $insider);
        $insiderMembership->roles()->attach($staffRole->id);

        // Приватность: @everyone теряет VIEW_CHANNELS на secret, staffRole его получает.
        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/channels/{$secret->id}", [
            'overwrites' => [
                ['role_id' => $defaultRole->id, 'deny' => ServerPermission::VIEW_CHANNELS],
                ['role_id' => $staffRole->id, 'allow' => ServerPermission::VIEW_CHANNELS],
            ],
        ])->assertOk();

        Sanctum::actingAs($outsider, ['*']);
        $channels = $this->getJson("/api/servers/{$server->id}/channels")->assertOk()->json('channels');
        $this->assertCount(1, $channels);
        $this->assertSame('general', $channels[0]['name']);

        Sanctum::actingAs($insider, ['*']);
        $channels = $this->getJson("/api/servers/{$server->id}/channels")->assertOk()->json('channels');
        $this->assertCount(2, $channels);

        Sanctum::actingAs($owner, ['*']);
        $channels = $this->getJson("/api/servers/{$server->id}/channels")->assertOk()->json('channels');
        $this->assertCount(2, $channels); // владелец видит всё независимо от оверрайдов
    }
}
