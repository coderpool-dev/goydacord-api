<?php

namespace Tests\Feature;

use App\Enums\ChannelType;
use App\Enums\MemberCallStatus;
use App\Models\Admin\Privilege;
use App\Models\Conversations\Call;
use App\Models\Conversations\CallSession;
use App\Models\Support\FeedbackMessage;
use App\Models\Support\UserReport;
use App\Models\User;
use App\Services\Support\SupportThreadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    public function test_non_admin_is_forbidden(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/admin/reports')->assertForbidden();
    }

    public function test_admin_lists_new_reports_and_searches_by_comment(): void
    {
        $reporter = $this->makeUser();
        $spammer = $this->makeUser();
        $other = $this->makeUser();

        $this->makeReport($reporter, $spammer, 'шлёт рекламу в личку');
        $this->makeReport($reporter, $other, 'просто не нравится');
        $this->makeReport($reporter, $other, 'уже разобрано', UserReport::STATUS_REVIEWED);

        Sanctum::actingAs($this->makeAdmin());

        $this->getJson('/api/admin/reports?q=рекламу')
            ->assertOk()
            ->assertJsonCount(1, 'reports')
            ->assertJsonPath('reports.0.target.id', $spammer->id)
            ->assertJsonPath('counts.new', 2)
            ->assertJsonPath('counts.total', 3);
    }

    public function test_admin_updates_report_status(): void
    {
        $report = $this->makeReport($this->makeUser(), $this->makeUser(), 'спам');

        Sanctum::actingAs($this->makeAdmin());

        $this->patchJson("/api/admin/reports/{$report->id}", ['status' => UserReport::STATUS_REVIEWED])
            ->assertOk()
            ->assertJsonPath('report.status', UserReport::STATUS_REVIEWED);
    }

    public function test_admin_manages_feedback(): void
    {
        $fresh = $this->makeFeedback(FeedbackMessage::STATUS_NEW);
        $this->makeFeedback(FeedbackMessage::STATUS_ARCHIVED);

        Sanctum::actingAs($this->makeAdmin());

        // Архив по умолчанию не показывается.
        $this->getJson('/api/admin/feedback')->assertOk()->assertJsonCount(1, 'messages');

        $this->patchJson("/api/admin/feedback/{$fresh->id}", ['status' => FeedbackMessage::STATUS_READ])
            ->assertOk()
            ->assertJsonPath('message_item.status', FeedbackMessage::STATUS_READ);

        $this->deleteJson("/api/admin/feedback/{$fresh->id}")->assertOk();
        $this->assertDatabaseMissing('feedback_messages', ['id' => $fresh->id]);
    }

    public function test_admin_grants_and_revokes_admin_rights(): void
    {
        $user = $this->makeUser(['login' => 'FutureAdmin']);

        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/admin/admins', ['login' => 'futureadmin'])->assertCreated();
        $this->postJson('/api/admin/admins', ['login' => 'futureadmin'])
            ->assertOk()
            ->assertJsonPath('message', 'Уже админ');
        $this->assertTrue($user->fresh()->isAdmin());

        $this->deleteJson("/api/admin/admins/{$user->id}")->assertOk();
        $this->assertFalse($user->fresh()->isAdmin());
    }

    public function test_granting_unknown_login_is_validation_error(): void
    {
        Sanctum::actingAs($this->makeAdmin());

        $this->postJson('/api/admin/admins', ['login' => 'nobody'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['login']);
    }

    public function test_last_admin_cannot_be_revoked(): void
    {
        $admin = $this->makeAdmin();
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/admin/admins/{$admin->id}")->assertStatus(422);
        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_admin_replies_to_support_thread_and_closes_it(): void
    {
        $thread = app(SupportThreadService::class)->findOrCreateThreadForUser($this->makeUser());

        Sanctum::actingAs($this->makeAdmin());

        $this->postJson("/api/admin/support/{$thread->id}/messages", ['body' => 'Здравствуйте, уже чиним'])
            ->assertCreated();

        $this->patchJson("/api/admin/support/{$thread->id}", ['status' => 'closed'])
            ->assertOk()
            ->assertJsonPath('thread.status', 'closed');
    }

    public function test_activity_shows_live_calls_with_fresh_participants_only(): void
    {
        $admin = $this->makeAdmin();
        [$alice, $bob, $carol] = [$this->makeUser(['name' => 'Alice']), $this->makeUser(['name' => 'Bob']), $this->makeUser()];
        $chat = $this->makeChannel(['name' => '', 'status' => ChannelType::Private]);
        $this->addMember($chat, $alice, callStatus: MemberCallStatus::InCall);
        $this->addMember($chat, $bob, callStatus: MemberCallStatus::InCall);
        $call = $this->makeActiveCall($chat, $alice);
        $endedCall = $this->makeActiveCall($this->makeChannel(), $carol, 'ended');

        $this->joinCall($call, $alice, 'alice-laptop', now()->subSeconds(20));
        $this->joinCall($call, $alice, 'alice-phone', now()->subSeconds(5), screenSharing: true);
        $this->joinCall($call, $bob, 'bob-web', now()->subSeconds(10));
        $this->joinCall($call, $carol, 'carol-stale', now()->subMinutes(5));
        $this->joinCall($endedCall, $carol, 'carol-web', now());
        Sanctum::actingAs($admin);

        $activity = $this->getJson('/api/admin/activity')->assertOk()->json('activity');

        $this->assertCount(1, $activity['active_calls']);
        $liveCall = $activity['active_calls'][0];
        $this->assertSame($call->call_id, $liveCall['call_id']);
        $this->assertSame($alice->id, $liveCall['initiator']['id']);
        $this->assertSame('Alice ↔ Bob', $liveCall['channel']['display_name']);
        $this->assertSame([$alice->id, $bob->id], array_column($liveCall['participants'], 'id'));
        $this->assertTrue($liveCall['participants'][0]['screen_sharing'], 'С двух устройств берётся последняя сессия');
        $this->assertSame(2, $activity['counts']['participants_in_calls']);
    }

    private function joinCall(Call $call, User $user, string $sessionId, \DateTimeInterface $lastSeenAt, bool $screenSharing = false): void
    {
        CallSession::create([
            'call_id' => $call->call_id,
            'user_id' => $user->id,
            'channel_id' => $call->channel_id,
            'session_id' => $sessionId,
            'last_seen_at' => $lastSeenAt,
            'screen_sharing' => $screenSharing,
        ]);
    }

    private function makeAdmin(): User
    {
        $admin = $this->makeUser();
        $privilege = Privilege::query()->firstOrCreate(['name' => Privilege::ADMIN]);
        $admin->privileges()->attach($privilege->id);

        return $admin;
    }

    private function makeReport(User $reporter, User $target, string $comment, string $status = UserReport::STATUS_NEW): UserReport
    {
        return UserReport::create([
            'reporter_id' => $reporter->id,
            'target_id' => $target->id,
            'reason' => 'spam',
            'comment' => $comment,
            'status' => $status,
            'ip' => '127.0.0.1',
        ]);
    }

    private function makeFeedback(string $status): FeedbackMessage
    {
        return FeedbackMessage::create([
            'name' => 'Гость',
            'email' => 'guest@example.test',
            'body' => 'Сообщение с сайта',
            'status' => $status,
        ]);
    }
}
