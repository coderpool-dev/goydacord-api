<?php

namespace Tests\Feature;

use App\Events\SupportReplyPosted;
use App\Models\Admin\Privilege;
use App\Models\User;
use App\Services\Support\SupportThreadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class SupportThreadControllerTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    public function test_admin_sees_thread_as_unread_until_opening_it(): void
    {
        $user = $this->makeUser();
        $admin = $this->makeAdmin();

        Sanctum::actingAs($user);
        $this->postJson('/api/support/messages', ['body' => 'Не работает звонок'])->assertCreated();

        Sanctum::actingAs($admin);
        $threadId = $this->getJson('/api/admin/support')
            ->assertOk()
            ->assertJsonPath('threads.0.unread', true)
            ->assertJsonPath('threads.0.last_message.body', 'Не работает звонок')
            ->assertJsonPath('threads.0.user.login', $user->login)
            ->json('threads.0.id');

        $this->getJson('/api/admin/support?unread=0')->assertJsonCount(0, 'threads');

        $this->getJson("/api/admin/support/{$threadId}")
            ->assertOk()
            ->assertJsonPath('thread.unread', false)
            ->assertJsonPath('messages.0.body', 'Не работает звонок')
            ->assertJsonMissingPath('thread.last_message');

        $this->getJson('/api/admin/support?unread=1')->assertJsonCount(0, 'threads');
        $this->getJson('/api/admin/support')->assertJsonPath('threads.0.unread', false);
    }

    public function test_user_sees_staff_reply_in_own_thread(): void
    {
        $user = $this->makeUser();
        $thread = app(SupportThreadService::class)->findOrCreateThreadForUser($user);

        Sanctum::actingAs($this->makeAdmin());
        $this->postJson("/api/admin/support/{$thread->id}/messages", ['body' => 'Уже чиним'])
            ->assertCreated()
            ->assertJsonPath('message.author_name', 'Администрация');

        Sanctum::actingAs($user);
        $this->getJson('/api/support')
            ->assertOk()
            ->assertJsonPath('thread.id', $thread->id)
            ->assertJsonPath('messages.0.body', 'Уже чиним')
            ->assertJsonPath('messages.0.is_staff', true);
    }

    public function test_staff_reply_is_pushed_to_user_and_counted_until_opened(): void
    {
        Event::fake([SupportReplyPosted::class]);
        $user = $this->makeUser();
        $thread = app(SupportThreadService::class)->findOrCreateThreadForUser($user);
        // Время в БД с точностью до секунды: ответ должен прийти позже, чем пользователь открывал чат.
        $this->travel(1)->seconds();

        Sanctum::actingAs($this->makeAdmin());
        $this->postJson("/api/admin/support/{$thread->id}/messages", ['body' => 'Уже чиним'])->assertCreated();
        $this->postJson("/api/admin/support/{$thread->id}/messages", ['body' => 'Починили'])->assertCreated();

        Event::assertDispatched(SupportReplyPosted::class, fn (SupportReplyPosted $e) => $e->userId === $user->id
            && $e->message['body'] === 'Починили'
            && $e->unread === 2
            && $e->broadcastOn()->name === "private-webrtc.{$user->id}");

        Sanctum::actingAs($user);
        $this->getJson('/api/support/unread')->assertOk()->assertJsonPath('unread', 2);
        $this->travel(1)->seconds();
        $this->getJson('/api/support')->assertOk();
        $this->getJson('/api/support/unread')->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_user_message_does_not_notify_user(): void
    {
        Event::fake([SupportReplyPosted::class]);
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/support/messages', ['body' => 'Привет'])->assertCreated();

        Event::assertNotDispatched(SupportReplyPosted::class);
        $this->getJson('/api/support/unread')->assertJsonPath('unread', 0);
    }

    public function test_empty_message_without_screenshot_is_rejected(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/support/messages', ['body' => '   '])->assertStatus(422);
        $this->assertDatabaseCount('support_messages', 0);
    }

    private function makeAdmin(): User
    {
        $admin = $this->makeUser();
        $admin->privileges()->attach(Privilege::query()->firstOrCreate(['name' => Privilege::ADMIN])->id);

        return $admin;
    }
}
