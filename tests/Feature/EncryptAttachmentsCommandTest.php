<?php

namespace Tests\Feature;

use App\Models\Conversations\Message;
use App\Services\Conversations\EncryptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class EncryptAttachmentsCommandTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_command_encrypts_existing_attachments_and_is_idempotent(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);

        $oldPath = "attachments/{$channel->id}/legacy.jpg";
        $plain = 'legacy image bytes';
        Storage::disk('local')->put($oldPath, $plain);

        $message = Message::create([
            'user_id' => $user->id,
            'channels_id' => $channel->id,
            'type' => 'image',
            'message' => '',
            'key_id' => (int) config('app.encryption_actual'),
            'meta' => [
                'attachment' => [
                    'disk_path' => $oldPath,
                    'name' => 'legacy.jpg',
                    'mime' => 'image/jpeg',
                    'size' => strlen($plain),
                    'kind' => 'image',
                    'width' => 100,
                    'height' => 100,
                ],
            ],
        ]);

        $this->artisan('attachments:encrypt-existing')
            ->expectsOutput('Encrypted attachments: 1. Missing: 0.')
            ->assertSuccessful();

        $message->refresh();
        $attachment = $message->meta['attachment'];
        $newPath = $attachment['disk_path'];

        $this->assertTrue($attachment['encrypted']);
        $this->assertSame((int) config('app.encryption_actual'), $attachment['key_id']);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);

        $encrypted = Storage::disk('local')->get($newPath);
        $this->assertSame(
            $plain,
            app(EncryptionService::class)->decryptBinary($encrypted, $attachment['key_id'])
        );

        $this->artisan('attachments:encrypt-existing')
            ->expectsOutput('Encrypted attachments: 0. Missing: 0.')
            ->assertSuccessful();

        $this->assertSame($encrypted, Storage::disk('local')->get($newPath));
    }
}
