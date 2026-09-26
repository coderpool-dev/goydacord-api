<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use App\Enums\ServerMembershipStatus;
use App\Enums\ServerPermission;
use App\Models\Conversations\Call;
use App\Models\Servers\ServerInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class ServerModelTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_server_relations_resolve(): void
    {
        $owner = $this->makeUser();
        $member = $this->makeUser();
        $server = $this->makeServer($owner);

        // addServerMember сама заводит и вешает дефолтную @everyone-роль (как реальные
        // ServerService::create()/ServerInviteService::join()) — второй раз её создавать не нужно.
        $ownerMembership = $this->addServerMember($server, $owner);
        $this->addServerMember($server, $member);

        $textChannel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $voiceChannel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);
        $invite = $this->makeServerInvite($server, $owner, ['channel_id' => $textChannel->id]);

        $this->assertTrue($server->owner->is($owner));
        $this->assertCount(2, $server->members()->active()->get());
        $this->assertCount(2, $server->channels);
        $this->assertTrue($ownerMembership->roles->first()->is_default);
        $this->assertTrue($invite->server->is($server));
        $this->assertTrue($invite->channel->is($textChannel));
        $this->assertSame(ServerChannelKind::Voice, $voiceChannel->kind);
        $this->assertSame(ServerMembershipStatus::Member, $ownerMembership->status);
        $this->assertTrue(ServerPermission::has(ServerPermission::DEFAULT, ServerPermission::SEND_MESSAGES));
        $this->assertFalse(ServerPermission::has(ServerPermission::DEFAULT, ServerPermission::BAN_MEMBERS));
    }

    public function test_server_channel_category_nesting(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);

        $category = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Category]);
        $child = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text, 'category_id' => $category->id]);

        $this->assertTrue($child->category->is($category));
        $this->assertTrue($category->children->first()->is($child));
    }

    public function test_call_can_belong_to_a_server_voice_channel_instead_of_a_channel(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $voiceChannel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);

        $call = Call::create([
            'call_id' => (string) Str::uuid(),
            'server_channel_id' => $voiceChannel->id,
            'initiator_id' => $owner->id,
            'status' => 'active',
        ]);

        $this->assertNull($call->channel_id);
        $this->assertTrue($call->serverChannel->is($voiceChannel));
    }

    public function test_invite_usable_scope_respects_expiry_and_uses(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);

        $usable = $this->makeServerInvite($server, $owner);
        $expired = $this->makeServerInvite($server, $owner, ['expires_at' => now()->subMinute()]);
        $exhausted = $this->makeServerInvite($server, $owner, ['max_uses' => 1, 'uses' => 1]);
        $revoked = $this->makeServerInvite($server, $owner, ['revoked_at' => now()]);

        $usableCodes = ServerInvite::query()->usable()->pluck('code')->all();

        $this->assertContains($usable->code, $usableCodes);
        $this->assertNotContains($expired->code, $usableCodes);
        $this->assertNotContains($exhausted->code, $usableCodes);
        $this->assertNotContains($revoked->code, $usableCodes);
    }
}
