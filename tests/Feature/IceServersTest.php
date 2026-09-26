<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class IceServersTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/ice-servers')->assertStatus(401);
    }

    public function test_does_not_expose_decommissioned_turn_host(): void
    {
        Sanctum::actingAs($this->makeUser(), ['*']);

        $response = $this->getJson('/api/ice-servers')->assertOk();

        $this->assertStringNotContainsString('72.56.74.34', $response->getContent());
    }

    public function test_does_not_return_public_google_or_cloudflare_ice_servers(): void
    {
        Sanctum::actingAs($this->makeUser(), ['*']);

        $servers = $this->getJson('/api/ice-servers')->assertOk()->json('ice_servers');

        $allUrls = collect($servers)
            ->flatMap(fn ($server) => (array) ($server['urls'] ?? []))
            ->all();

        $this->assertNotContains('stun:stun.l.google.com:19302', $allUrls);
        $this->assertNotContains('stun:stun.cloudflare.com:3478', $allUrls);
        $this->assertNotContains('turn:turn.cloudflare.com:3478?transport=udp', $allUrls);
    }

    public function test_default_ice_servers_prefer_udp_turn_without_tcp_tls(): void
    {
        config([
            'services.webrtc.turn_host' => '203.0.113.10',
            'services.webrtc.turn_username' => 'zov',
            'services.webrtc.turn_credential' => 'secret',
            'services.webrtc.turn_tls_host' => 'turn.example.com',
            'services.webrtc.turn_tls_port' => 5349,
        ]);
        Sanctum::actingAs($this->makeUser(), ['*']);

        $servers = $this->getJson('/api/ice-servers')->assertOk()->json('ice_servers');

        $allUrls = collect($servers)
            ->flatMap(fn ($server) => (array) ($server['urls'] ?? []))
            ->all();

        $this->assertContains('turn:203.0.113.10:3478?transport=udp', $allUrls);
        $this->assertNotContains('turn:203.0.113.10:3478?transport=tcp', $allUrls);
        $this->assertNotContains('turns:turn.example.com:5349?transport=tcp', $allUrls);
    }

    public function test_with_shared_secret_each_user_gets_expiring_turn_credentials(): void
    {
        config([
            'services.webrtc.turn_host' => '203.0.113.10',
            'services.webrtc.turn_username' => 'zov',
            'services.webrtc.turn_credential' => 'secret',
            'services.webrtc.turn_secret' => 'coturn-shared-secret',
            'services.webrtc.turn_credential_ttl' => 3600,
        ]);
        $this->freezeTime();
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);

        $turn = collect($this->getJson('/api/ice-servers')->assertOk()->json('ice_servers'))->firstWhere('username');

        $expectedUsername = (now()->timestamp + 3600).':'.$user->id;
        $this->assertSame($expectedUsername, $turn['username']);
        $this->assertSame(base64_encode(hash_hmac('sha1', $expectedUsername, 'coturn-shared-secret', true)), $turn['credential']);
        $this->assertNotSame('secret', $turn['credential']);
    }

    public function test_fallback_ice_servers_include_tcp_and_tls_turn(): void
    {
        config([
            'services.webrtc.turn_host' => '203.0.113.10',
            'services.webrtc.turn_username' => 'zov',
            'services.webrtc.turn_credential' => 'secret',
            'services.webrtc.turn_tls_host' => 'turn.example.com',
            'services.webrtc.turn_tls_port' => 5349,
        ]);
        Sanctum::actingAs($this->makeUser(), ['*']);

        $servers = $this->getJson('/api/ice-servers?mode=fallback')->assertOk()->json('ice_servers');

        $allUrls = collect($servers)
            ->flatMap(fn ($server) => (array) ($server['urls'] ?? []))
            ->all();

        $this->assertContains('turn:203.0.113.10:3478?transport=udp', $allUrls);
        $this->assertContains('turn:203.0.113.10:3478?transport=tcp', $allUrls);
        $this->assertContains('turns:turn.example.com:5349?transport=tcp', $allUrls);
    }
}
