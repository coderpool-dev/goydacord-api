<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

/** Голосовые каналы через LiveKit: токен на вход и серверная модерация через RoomService. */
class LiveKitVoiceTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    private function enableLiveKit(): void
    {
        config([
            'services.livekit.url' => 'wss://api.example.test/livekit',
            'services.livekit.api_host' => 'http://livekit.test',
            'services.livekit.api_key' => 'devkey',
            'services.livekit.api_secret' => 'secret-secret-secret-secret-secret',
        ]);
    }

    /** @return array<string, mixed> */
    private function claims(string $jwt): array
    {
        [$header, $payload, $signature] = explode('.', $jwt);
        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', "{$header}.{$payload}", 'secret-secret-secret-secret-secret', true)), '+/', '-_'), '=');
        $this->assertSame($expected, $signature, 'подпись HS256 не сходится');

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    }

    public function test_join_without_livekit_config_keeps_p2p(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $voice = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/server-channels/{$voice->id}/calls", ['session_id' => 'tab-1'])
            ->assertOk()->assertJsonPath('call.livekit', null);
    }

    public function test_join_returns_room_token_with_nickname_and_mic_rights(): void
    {
        $this->enableLiveKit();
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $member = $this->makeUser();
        $this->addServerMember($server, $member);
        $server->members()->where('user_id', $member->id)->update(['nickname' => 'Бобик', 'voice_muted' => true]);
        $voice = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);

        Sanctum::actingAs($owner, ['*']);
        $ownerCall = $this->postJson("/api/server-channels/{$voice->id}/calls", ['session_id' => 'tab-1'])->assertOk()->json('call.livekit');
        $this->assertSame('wss://api.example.test/livekit', $ownerCall['url']);
        $this->assertSame("server-channel-{$voice->id}", $ownerCall['room']);
        $claims = $this->claims($ownerCall['token']);
        $this->assertSame('devkey', $claims['iss']);
        $this->assertSame((string) $owner->id, $claims['sub']);
        $this->assertTrue($claims['video']['roomJoin']);
        $this->assertContains('microphone', $claims['video']['canPublishSources']);

        // Заглушённый модератором входит без права публиковать микрофон, но с демкой.
        Sanctum::actingAs($member, ['*']);
        $memberCall = $this->postJson("/api/server-channels/{$voice->id}/calls", ['session_id' => 'tab-2'])->assertOk()->json('call.livekit');
        $claims = $this->claims($memberCall['token']);
        $this->assertSame('Бобик', $claims['name']);
        $this->assertNotContains('microphone', $claims['video']['canPublishSources']);
        $this->assertContains('screen_share', $claims['video']['canPublishSources']);
    }

    public function test_moderator_mute_and_disconnect_are_enforced_by_livekit(): void
    {
        $this->enableLiveKit();
        Http::fake(['livekit.test/*' => Http::response([], 200)]);

        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $member = $this->makeUser();
        $this->addServerMember($server, $member);
        $voice = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/server-channels/{$voice->id}/calls", ['session_id' => 'tab-1'])->assertOk();

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/voice/members/{$member->id}/state", ['muted' => true])->assertOk();

        Http::assertSent(function (Request $request) use ($voice, $member) {
            return str_ends_with($request->url(), '/twirp/livekit.RoomService/UpdateParticipant')
                && $request['room'] === "server-channel-{$voice->id}"
                && $request['identity'] === (string) $member->id
                && ! in_array('MICROPHONE', $request['permission']['can_publish_sources'], true)
                && str_starts_with($request->header('Authorization')[0], 'Bearer ');
        });

        $this->postJson("/api/servers/{$server->id}/voice/members/{$member->id}/move", ['server_channel_id' => null])->assertOk();
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/RemoveParticipant')
            && $request['identity'] === (string) $member->id);
    }

    public function test_status_tells_client_which_transport_to_use(): void
    {
        Sanctum::actingAs($this->makeUser(), ['*']);
        $this->getJson('/api/livekit')->assertOk()->assertJsonPath('enabled', false);

        $this->enableLiveKit();
        $this->getJson('/api/livekit')->assertOk()->assertJsonPath('enabled', true);
    }

    public function test_direct_call_member_gets_room_of_the_active_call(): void
    {
        $this->enableLiveKit();
        $alice = $this->makeUser();
        $bob = $this->makeUser();
        $stranger = $this->makeUser();
        $group = $this->makeChannel();
        $this->addMember($group, $alice);
        $this->addMember($group, $bob);

        Sanctum::actingAs($bob, ['*']);
        $this->postJson("/api/calls/{$group->id}/livekit")->assertNotFound();

        $call = $this->makeActiveCall($group, $alice);
        $livekit = $this->postJson("/api/calls/{$group->id}/livekit")->assertOk()->json('livekit');
        $this->assertSame("call-{$call->call_id}", $livekit['room']);
        $claims = $this->claims($livekit['token']);
        $this->assertSame((string) $bob->id, $claims['sub']);
        $this->assertSame("call-{$call->call_id}", $claims['video']['room']);

        Sanctum::actingAs($stranger, ['*']);
        $this->postJson("/api/calls/{$group->id}/livekit")->assertForbidden();
    }

    public function test_livekit_outage_does_not_break_moderation(): void
    {
        $this->enableLiveKit();
        Http::fake(['livekit.test/*' => Http::response(['code' => 'unavailable'], 503)]);

        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $member = $this->makeUser();
        $this->addServerMember($server, $member);
        $voice = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/server-channels/{$voice->id}/calls", ['session_id' => 'tab-1'])->assertOk();

        Sanctum::actingAs($owner, ['*']);
        $this->postJson("/api/servers/{$server->id}/voice/members/{$member->id}/state", ['muted' => true])->assertOk();
    }
}
