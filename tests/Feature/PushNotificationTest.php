<?php

namespace Tests\Feature;

use App\Enums\ChannelType;
use App\Models\Account\PushSubscription;
use App\Services\Account\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use InteractsWithCalls, RefreshDatabase;

    /** @var array<int, array{users: int[], payload: array}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $sent = &$this->sent;
        // Реальные push-сервисы не трогаем — ловим, кому и что ушло бы.
        $this->app->instance(PushNotificationService::class, new class($sent) extends PushNotificationService
        {
            public function __construct(private array &$sink) {}

            public function sendToUsers(array $userIds, array $payload): void
            {
                $this->sink[] = ['users' => array_values($userIds), 'payload' => $payload];
            }
        });
    }

    public function test_subscribe_and_unsubscribe(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user, ['*']);
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';

        $this->postJson('/api/push/subscriptions', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'pk', 'auth' => 'au']])->assertOk();
        $this->postJson('/api/push/subscriptions', ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'pk2', 'auth' => 'au2']])->assertOk();
        $this->assertSame(1, PushSubscription::query()->count());
        $this->assertSame('pk2', PushSubscription::query()->value('p256dh'));

        $this->postJson('/api/push/subscriptions', ['endpoint' => 'http://insecure.test/x', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])
            ->assertUnprocessable();

        $this->deleteJson('/api/push/subscriptions', ['endpoint' => $endpoint])->assertOk();
        $this->assertSame(0, PushSubscription::query()->count());
    }

    public function test_direct_message_and_incoming_call_push_the_other_member_only(): void
    {
        $alice = $this->makeUser();
        $bob = $this->makeUser();
        $channel = $this->makeChannel(['status' => ChannelType::Private]);
        $this->addMember($channel, $alice);
        $this->addMember($channel, $bob);
        Sanctum::actingAs($alice, ['*']);

        $this->postJson('/api/messages', ['channels_id' => $channel->id, 'message' => 'привет, Боб'])->assertCreated();
        $message = collect($this->sent)->firstWhere('payload.kind', 'message');
        $this->assertSame([$bob->id], $message['users']);
        $this->assertSame('привет, Боб', $message['payload']['body']);
        $this->assertSame("/channels/{$channel->id}", $message['payload']['url']);

        // Звонок — в беседе (для лички нужна дружба, это проверяется отдельно в тестах звонков).
        $group = $this->makeChannel();
        $this->addMember($group, $alice);
        $this->addMember($group, $bob);
        $this->postJson("/api/calls/{$group->id}")->assertSuccessful();
        $call = collect($this->sent)->firstWhere('payload.kind', 'call');
        $this->assertSame([$bob->id], $call['users']);
        $this->assertStringContainsString('звонит вам', $call['payload']['body']);
        $this->assertNotEmpty($call['payload']['call_id']);
        $this->assertSame($group->id, $call['payload']['channel_id']);
        $this->assertSame($alice->id, $call['payload']['initiator_id']);
        $this->assertSame($alice->login, $call['payload']['initiator_login']);
    }
}
