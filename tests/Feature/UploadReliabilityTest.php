<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Events\MessageSent;
use App\Exceptions\UploadException;
use App\Models\Conversations\Message;
use App\Models\Uploads\UploadSession;
use App\Services\Conversations\AttachmentService;
use App\Services\Conversations\MessageService;
use App\Services\Uploads\UploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class UploadReliabilityTest extends TestCase
{
    use InteractsWithCalls, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Event::fake();
        config(['uploads.encrypt_max_bytes' => 4, 'uploads.min_free_bytes' => 0]);
    }

    private function startUpload(int $channelId, int $size = 80): string
    {
        return $this->postJson('/api/uploads', [
            'channels_id' => $channelId, 'filename' => 'probe.bin',
            'size' => $size, 'mime' => 'application/octet-stream',
        ])->assertCreated()->json('upload_id');
    }

    private function chunk(string $id, string $content, int $offset = 0): TestResponse
    {
        return $this->call('PATCH', "/api/uploads/{$id}", [], [], [],
            $this->transformHeadersToServerVars(['Upload-Offset' => (string) $offset]), $content);
    }

    public function test_removed_member_cannot_append_or_publish_an_existing_upload(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $member = $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $this->chunk($id, str_repeat('x', 80))->assertOk();
        $member->update(['status' => MembershipStatus::Removed]);

        $this->chunk($id, 'x')->assertForbidden();
        $this->postJson("/api/uploads/{$id}/complete")->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_pending_uploads_reserve_quota_and_completion_does_not_double_count(): void
    {
        config(['uploads.user_quota_bytes' => 100]);
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $this->postJson('/api/uploads', ['channels_id' => $channel->id, 'filename' => 'second.bin', 'size' => 80])->assertStatus(413);
        $this->chunk($id, str_repeat('x', 80))->assertOk();
        $this->postJson("/api/uploads/{$id}/complete")->assertCreated();
        $this->startUpload($channel->id, 20);
        $this->assertSame(80, app(AttachmentService::class)->usedBytes($user->id));
    }

    public function test_ordinary_attachments_cannot_consume_reserved_space(): void
    {
        config(['uploads.user_quota_bytes' => 100]);
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $this->startUpload($channel->id);
        $this->expectException(UploadException::class);
        app(AttachmentService::class)->store(UploadedFile::fake()->createWithContent('other.bin', str_repeat('y', 30)), $user->id, $channel->id);
    }

    public function test_complete_rechecks_quota_for_legacy_uploads(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $this->chunk($id, str_repeat('x', 80))->assertOk();
        config(['uploads.user_quota_bytes' => 50]);
        $this->postJson("/api/uploads/{$id}/complete")->assertStatus(413);
        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_complete_is_idempotent_and_cannot_recreate_a_deleted_message(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $this->chunk($id, str_repeat('x', 80))->assertOk();
        $messageId = $this->postJson("/api/uploads/{$id}/complete")->assertCreated()->json('message');
        $this->postJson("/api/uploads/{$id}/complete")->assertCreated()->assertJsonPath('message', $messageId);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseCount('attachments', 1);
        $this->chunk($id, 'x')->assertStatus(409);
        Message::findOrFail($messageId)->delete();
        $this->postJson("/api/uploads/{$id}/complete")->assertStatus(410);
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_complete_respects_the_chunk_lock(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $this->chunk($id, str_repeat('x', 80))->assertOk();
        $lock = Cache::lock("upload-session:{$id}", 120);
        $lock->get();
        try {
            $this->postJson("/api/uploads/{$id}/complete")->assertStatus(409);
            $this->assertDatabaseCount('messages', 0);
        } finally {
            $lock->release();
        }
        $this->postJson("/api/uploads/{$id}/complete")->assertCreated();
    }

    public function test_failure_after_copy_keeps_source_and_retry_creates_only_one_attachment(): void
    {
        $this->assertRecoverableFinalization(4);
    }

    public function test_failure_after_encryption_keeps_source_and_retry_creates_only_one_attachment(): void
    {
        $this->assertRecoverableFinalization(1000);
    }

    private function assertRecoverableFinalization(int $encryptionThreshold): void
    {
        config(['uploads.encrypt_max_bytes' => $encryptionThreshold]);
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $this->chunk($id, str_repeat('x', 80))->assertOk();
        $upload = UploadSession::findOrFail($id);
        $this->mock(MessageService::class)->shouldReceive('storeFinalizedAttachment')->once()->andThrow(new \RuntimeException('simulated DB outage'));
        try {
            app(UploadService::class)->complete($upload, $user, '', null);
            $this->fail('Expected simulated DB outage');
        } catch (\RuntimeException $exception) {
            $this->assertSame('simulated DB outage', $exception->getMessage());
        }
        $this->assertDatabaseCount('attachments', 0);
        $this->assertDatabaseCount('messages', 0);
        Storage::disk('local')->assertExists($upload->tmp_path);
        $this->assertSame(str_repeat('x', 80), Storage::disk('local')->get($upload->tmp_path));
        $this->app->forgetInstance(MessageService::class);
        $this->postJson("/api/uploads/{$id}/complete")->assertCreated();
        $this->assertDatabaseCount('attachments', 1);
        $this->assertDatabaseCount('messages', 1);
        Storage::disk('local')->assertMissing($upload->tmp_path);
        $this->assertSame([$upload->finalPath()], Storage::disk('local')->allFiles('attachments'));
    }

    public function test_failure_after_message_insert_rolls_back_message_and_does_not_broadcast(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $this->chunk($id, str_repeat('x', 80))->assertOk();
        $upload = UploadSession::findOrFail($id);
        $realMessages = app(MessageService::class);
        $this->mock(MessageService::class)->shouldReceive('storeFinalizedAttachment')->once()
            ->andReturnUsing(function (...$arguments) use ($realMessages) {
                $realMessages->storeFinalizedAttachment(...$arguments);
                throw new \RuntimeException('failure after message insert');
            });
        try {
            app(UploadService::class)->complete($upload, $user, '', null);
            $this->fail('Expected failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('failure after message insert', $exception->getMessage());
        }
        $this->assertDatabaseCount('messages', 0);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertNull($upload->fresh()->message_id);
        Event::assertNotDispatched(MessageSent::class);
        $this->app->forgetInstance(MessageService::class);
        $this->postJson("/api/uploads/{$id}/complete")->assertCreated();
        Event::assertDispatchedTimes(MessageSent::class, 1);
    }

    public function test_reaping_completed_receipt_keeps_the_published_file(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $this->chunk($id, str_repeat('x', 80))->assertOk();
        $this->postJson("/api/uploads/{$id}/complete")->assertCreated();
        $upload = UploadSession::findOrFail($id);
        $upload->forceFill(['updated_at' => now()->subHours((int) config('uploads.session_ttl_hours') + 1)])->save();
        $this->artisan('attachments:prune')->assertExitCode(0);
        $this->assertDatabaseMissing('upload_sessions', ['id' => $id]);
        $this->assertDatabaseCount('messages', 1);
        Storage::disk('local')->assertExists($upload->finalPath());
    }

    public function test_retry_discards_file_bytes_not_committed_to_database(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $upload = UploadSession::findOrFail($id);
        // Simulate termination after writing bytes, before updating received_size.
        Storage::disk('local')->put($upload->tmp_path, 'uncommitted bytes');
        $this->chunk($id, str_repeat('x', 80))->assertOk();
        $this->assertSame(str_repeat('x', 80), Storage::disk('local')->get($upload->tmp_path));
        $this->postJson("/api/uploads/{$id}/complete")->assertCreated();
    }

    public function test_reaper_releases_reservation_and_removes_failed_destination(): void
    {
        config(['uploads.user_quota_bytes' => 100]);
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);
        $id = $this->startUpload($channel->id);
        $upload = UploadSession::findOrFail($id);
        Storage::disk('local')->put($upload->finalPath(), 'orphan');
        $upload->forceFill(['updated_at' => now()->subHours((int) config('uploads.session_ttl_hours') + 1)])->save();
        $this->artisan('attachments:prune')->assertExitCode(0);
        $this->assertDatabaseMissing('upload_sessions', ['id' => $id]);
        Storage::disk('local')->assertMissing($upload->tmp_path);
        Storage::disk('local')->assertMissing($upload->finalPath());
        $this->startUpload($channel->id);
    }
}
