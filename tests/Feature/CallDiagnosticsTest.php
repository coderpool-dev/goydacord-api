<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class CallDiagnosticsTest extends TestCase
{
    use InteractsWithCalls, RefreshDatabase;

    private function payload(): array
    {
        return [
            'session_id' => (string) Str::uuid(), 'sequence' => 1, 'dropped' => 0, 'build' => 'test',
            'events' => [[
                'event' => 'transport_stats', 'at' => now()->toIso8601String(), 'elapsed_ms' => 5000,
                'peer_id' => '184', 'pc_id' => (string) Str::uuid(),
                'data' => ['rtt_ms' => 120.5, 'relay_protocol' => 'tls'],
            ]],
        ];
    }

    public function test_logs_member_identity_from_auth_and_accepts_final_events_after_call_end(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        $call = $this->makeActiveCall($channel, $user, 'ended');
        Sanctum::actingAs($user);
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->once()->with('calls')->andReturn($logger);
        $logger->shouldReceive('info')->once()->with('client_diagnostics', Mockery::on(
            fn ($context) => $context['user_id'] === $user->id && $context['call_id'] === $call->call_id
                && $context['events'][0]['data']['rtt_ms'] === 120.5
        ));
        $this->postJson("/api/webrtc/{$call->call_id}/diagnostics", $this->payload() + ['user_id' => 999])->assertNoContent();
    }

    public function test_accepts_mic_and_bootstrap_status_events(): void
    {
        // Живой инцидент: у собеседника ни разу не создался peer (0 diagnostics-событий) —
        // но events-схема раньше не знала событий до создания peer (микрофон, bootstrap),
        // так что даже при их отправке они бы отклонялись как невалидные.
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        $call = $this->makeActiveCall($channel, $user);
        Sanctum::actingAs($user);
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->once()->with('calls')->andReturn($logger);
        $logger->shouldReceive('info')->once();
        $payload = $this->payload();
        $payload['events'] = [
            ['event' => 'mic_status', 'at' => now()->toIso8601String(), 'elapsed_ms' => 10,
                'peer_id' => '0', 'pc_id' => (string) Str::uuid(), 'data' => ['reason' => 'denied', 'code' => 'NotAllowedError']],
            ['event' => 'bootstrap_status', 'at' => now()->toIso8601String(), 'elapsed_ms' => 20,
                'peer_id' => '0', 'pc_id' => (string) Str::uuid(), 'data' => ['reason' => 'no_targets']],
        ];
        $this->postJson("/api/webrtc/{$call->call_id}/diagnostics", $payload)->assertNoContent();
    }

    public function test_accepts_connection_unstable_event_with_attempt_count(): void
    {
        // Живой инцидент: хронически нестабильная сеть у части участников заставляла
        // recovery-цикл пересоздавать peer сотнями раз за звонок без единого шанса на
        // успех — добавили признак "attempt" и отдельное событие для порога хронической
        // нестабильности, чтобы это было видно в логах, а не только по объёму /signal.
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        $call = $this->makeActiveCall($channel, $user);
        Sanctum::actingAs($user);
        $logger = Mockery::mock(LoggerInterface::class);
        Log::shouldReceive('channel')->once()->with('calls')->andReturn($logger);
        $logger->shouldReceive('info')->once();
        $payload = $this->payload();
        $payload['events'] = [
            ['event' => 'connection_unstable', 'at' => now()->toIso8601String(), 'elapsed_ms' => 30,
                'peer_id' => '184', 'pc_id' => (string) Str::uuid(),
                'data' => ['reason' => 'recreate_ice_restarts_exhausted', 'attempt' => 4]],
        ];
        $this->postJson("/api/webrtc/{$call->call_id}/diagnostics", $payload)->assertNoContent();
    }

    public function test_rejects_non_member(): void
    {
        $user = $this->makeUser();
        $call = $this->makeActiveCall($this->makeChannel(), $user);
        Sanctum::actingAs($this->makeUser());
        $this->postJson("/api/webrtc/{$call->call_id}/diagnostics", $this->payload())->assertForbidden();
    }

    public function test_rejects_sensitive_fields_and_oversized_batches(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        $call = $this->makeActiveCall($channel, $user);
        Sanctum::actingAs($user);
        $payload = $this->payload();
        $payload['events'][0]['data']['sdp'] = 'secret';
        $this->postJson("/api/webrtc/{$call->call_id}/diagnostics", $payload)->assertUnprocessable();
        $payload = $this->payload();
        $payload['events'] = array_fill(0, 25, $payload['events'][0]);
        $this->postJson("/api/webrtc/{$call->call_id}/diagnostics", $payload)->assertUnprocessable();
    }
}
