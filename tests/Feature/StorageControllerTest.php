<?php

namespace Tests\Feature;

use App\Models\Conversations\Attachment;
use App\Models\Conversations\Channel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class StorageControllerTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    /** id передаются в query-строке: у DELETE не принято тело запроса. */
    public function test_bulk_delete_takes_ids_from_query_and_skips_foreign_files(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $own = $this->makeAttachment($user, $channel);
        $foreign = $this->makeAttachment($this->makeUser(), $channel);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/storage/attachments?ids[]={$own->id}&ids[]={$foreign->id}")
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        $this->assertDatabaseMissing('attachments', ['id' => $own->id]);
        $this->assertDatabaseHas('attachments', ['id' => $foreign->id]);
    }

    public function test_cannot_delete_foreign_file(): void
    {
        $foreign = $this->makeAttachment($this->makeUser(), $this->makeChannel());

        Sanctum::actingAs($this->makeUser());

        $this->deleteJson("/api/storage/attachments/{$foreign->id}")->assertForbidden();
        $this->assertDatabaseHas('attachments', ['id' => $foreign->id]);
    }

    private function makeAttachment(User $user, Channel $channel): Attachment
    {
        return Attachment::create([
            'user_id' => $user->id,
            'channel_id' => $channel->id,
            'disk_path' => "attachments/{$channel->id}/file-{$user->id}.bin",
            'name' => 'file.bin',
            'mime' => 'application/octet-stream',
            'size' => 10,
            'kind' => 'file',
            'encrypted' => false,
            'last_accessed_at' => now(),
        ]);
    }
}
