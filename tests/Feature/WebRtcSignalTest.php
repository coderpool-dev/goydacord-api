<?php

namespace Tests\Feature;

use App\Events\WebRTCSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class WebRtcSignalTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    private function signalUrl(string $callId): string
    {
        return "/api/webrtc/{$callId}/signal";
    }

    public function test_rejects_signal_from_non_member(): void
    {
        $channel = $this->makeChannel();
        $initiator = $this->makeUser();
        $this->addMember($channel, $initiator);
        $call = $this->makeActiveCall($channel, $initiator);

        $stranger = $this->makeUser();
        Sanctum::actingAs($stranger, ['*']);

        $response = $this->postJson($this->signalUrl($call->call_id), [
            'type' => 'offer',
            'sdp' => 'v=0...',
        ]);

        $response->assertStatus(403)->assertJsonPath('message', 'Not a channel member');
    }

    public function test_rejects_when_target_is_not_a_member(): void
    {
        $channel = $this->makeChannel();
        $sender = $this->makeUser();
        $this->addMember($channel, $sender);
        $call = $this->makeActiveCall($channel, $sender);

        $outsider = $this->makeUser(); // не состоит в канале
        Sanctum::actingAs($sender, ['*']);

        $response = $this->postJson($this->signalUrl($call->call_id), [
            'type' => 'offer',
            'sdp' => 'v=0...',
            'to_user_id' => $outsider->id,
        ]);

        $response->assertStatus(403)->assertJsonPath('message', 'Получатель не участник этого канала');
    }

    public function test_relays_signal_to_member_target(): void
    {
        Event::fake([WebRTCSignal::class]);

        $channel = $this->makeChannel();
        $sender = $this->makeUser();
        $target = $this->makeUser();
        $this->addMember($channel, $sender);
        $this->addMember($channel, $target);
        $call = $this->makeActiveCall($channel, $sender);

        Sanctum::actingAs($sender, ['*']);

        $response = $this->postJson($this->signalUrl($call->call_id), [
            'type' => 'offer',
            'sdp' => 'v=0...',
            'to_user_id' => $target->id,
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        Event::assertDispatched(WebRTCSignal::class, function (WebRTCSignal $event) use ($target, $sender) {
            return $event->toUserId === $target->id
                && $event->signal['from_user_id'] === $sender->id
                && $event->signal['type'] === 'offer';
        });
    }

    public function test_broadcasts_to_other_members_when_no_target(): void
    {
        Event::fake([WebRTCSignal::class]);

        $channel = $this->makeChannel();
        $sender = $this->makeUser();
        $other = $this->makeUser();
        $this->addMember($channel, $sender);
        $this->addMember($channel, $other);
        $call = $this->makeActiveCall($channel, $sender);

        Sanctum::actingAs($sender, ['*']);

        $this->postJson($this->signalUrl($call->call_id), [
            'type' => 'candidate',
            'candidate' => ['candidate' => 'abc'],
        ])->assertOk();

        Event::assertDispatched(WebRTCSignal::class, fn (WebRTCSignal $e) => $e->toUserId === $other->id);
        Event::assertNotDispatched(WebRTCSignal::class, fn (WebRTCSignal $e) => $e->toUserId === $sender->id);
    }

    public function test_returns_422_when_no_peers_available(): void
    {
        $channel = $this->makeChannel();
        $sender = $this->makeUser();
        $this->addMember($channel, $sender);
        $call = $this->makeActiveCall($channel, $sender);

        Sanctum::actingAs($sender, ['*']);

        $this->postJson($this->signalUrl($call->call_id), [
            'type' => 'offer',
            'sdp' => 'v=0...',
        ])->assertStatus(422)->assertJsonPath('message', 'В канале нет других участников');
    }

    public function test_validates_signal_type(): void
    {
        $channel = $this->makeChannel();
        $sender = $this->makeUser();
        $this->addMember($channel, $sender);
        $call = $this->makeActiveCall($channel, $sender);

        Sanctum::actingAs($sender, ['*']);

        $this->postJson($this->signalUrl($call->call_id), [
            'type' => 'bogus',
        ])->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $channel = $this->makeChannel();
        $initiator = $this->makeUser();
        $this->addMember($channel, $initiator);
        $call = $this->makeActiveCall($channel, $initiator);

        $this->postJson($this->signalUrl($call->call_id), [
            'type' => 'offer',
            'sdp' => 'v=0...',
        ])->assertStatus(401);
    }
}
