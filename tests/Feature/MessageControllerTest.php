<?php

namespace Tests\Feature;

use App\Models\Conversations\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class MessageControllerTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    public function test_member_can_post_message_and_it_is_stored_encrypted(): void
    {
        Event::fake();

        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);

        Sanctum::actingAs($user);

        $this->postJson('/api/messages/', [
            'channels_id' => $channel->id,
            'message' => 'hello world',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'success');

        // Сообщение в БД хранится зашифрованным, а не в открытом виде.
        $stored = Message::where('channels_id', $channel->id)->first();
        $this->assertNotNull($stored);
        $this->assertNotSame('hello world', $stored->message);
        $this->assertSame((int) config('app.encryption_actual'), (int) $stored->key_id);
    }

    public function test_non_member_cannot_post_message(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();

        Sanctum::actingAs($user);

        $this->postJson('/api/messages/', [
            'channels_id' => $channel->id,
            'message' => 'sneaky',
        ])->assertForbidden();

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_post_message_validation(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);

        Sanctum::actingAs($user);

        $this->postJson('/api/messages/', ['channels_id' => $channel->id, 'message' => ''])
            ->assertStatus(422);

        $this->postJson('/api/messages/', ['channels_id' => $channel->id, 'message' => str_repeat('a', 1001)])
            ->assertStatus(422);

        $this->postJson('/api/messages/', ['message' => 'no channel'])
            ->assertStatus(422);
    }

    public function test_member_gets_messages_decrypted(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);

        Sanctum::actingAs($user);

        $this->postJson('/api/messages/', [
            'channels_id' => $channel->id,
            'message' => 'decrypt me',
        ])->assertCreated();

        $this->getJson("/api/messages/{$channel->id}/")
            ->assertOk()
            ->assertJsonFragment(['message' => 'decrypt me']);
    }

    public function test_get_messages_keeps_system_messages_plaintext(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);

        Message::createSystem($channel->id, $user->id, 'channel_created', ['actor_name' => $user->name], 'создал беседу');

        Sanctum::actingAs($user);

        $this->getJson("/api/messages/{$channel->id}/")
            ->assertOk()
            ->assertJsonFragment(['type' => 'system', 'message' => 'создал беседу']);
    }

    public function test_non_member_cannot_read_messages(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();

        Sanctum::actingAs($user);

        $this->getJson("/api/messages/{$channel->id}/")->assertForbidden();
    }

    public function test_messages_require_authentication(): void
    {
        $channel = $this->makeChannel();

        $this->postJson('/api/messages/', ['channels_id' => $channel->id, 'message' => 'x'])
            ->assertUnauthorized();
    }

    public function test_member_can_reply_to_message_in_same_channel(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);

        $this->postJson('/api/messages/', [
            'channels_id' => $channel->id,
            'message' => 'original',
        ])->assertCreated();

        $original = Message::where('channels_id', $channel->id)->firstOrFail();

        $this->postJson('/api/messages/', [
            'channels_id' => $channel->id,
            'message' => 'reply',
            'reply_to_id' => $original->id,
        ])->assertCreated();

        $this->getJson("/api/messages/{$channel->id}/")
            ->assertOk()
            ->assertJsonFragment([
                'reply_to_id' => $original->id,
                'message' => 'original',
            ]);
    }

    public function test_reply_to_message_from_another_channel_is_rejected(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $otherChannel = $this->makeChannel();
        $this->addMember($channel, $user);
        $this->addMember($otherChannel, $user);
        Sanctum::actingAs($user);

        $this->postJson('/api/messages/', [
            'channels_id' => $otherChannel->id,
            'message' => 'foreign',
        ])->assertCreated();

        $foreign = Message::where('channels_id', $otherChannel->id)->firstOrFail();

        $this->postJson('/api/messages/', [
            'channels_id' => $channel->id,
            'message' => 'invalid reply',
            'reply_to_id' => $foreign->id,
        ])->assertUnprocessable();
    }

    public function test_owner_can_edit_message_and_it_remains_encrypted(): void
    {
        Event::fake();
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);

        $this->postJson('/api/messages/', [
            'channels_id' => $channel->id,
            'message' => 'before',
        ])->assertCreated();

        $message = Message::where('channels_id', $channel->id)->firstOrFail();

        $this->patchJson("/api/messages/{$message->id}", [
            'message' => 'after',
        ])->assertOk();

        $message->refresh();
        $this->assertNotSame('after', $message->message);
        $this->assertNotNull($message->edited_at);

        $this->getJson("/api/messages/{$channel->id}/")
            ->assertOk()
            ->assertJsonFragment(['message' => 'after'])
            ->assertJsonPath('data.0.edited_at', fn ($value) => is_string($value));
    }

    public function test_member_cannot_edit_or_delete_another_users_message(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $owner);
        $this->addMember($channel, $other);

        Sanctum::actingAs($owner);
        $this->postJson('/api/messages/', [
            'channels_id' => $channel->id,
            'message' => 'mine',
        ])->assertCreated();
        $message = Message::where('channels_id', $channel->id)->firstOrFail();

        Sanctum::actingAs($other);
        $this->patchJson("/api/messages/{$message->id}", ['message' => 'stolen'])->assertForbidden();
        $this->deleteJson("/api/messages/{$message->id}")->assertForbidden();
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_member_can_toggle_reaction(): void
    {
        Event::fake();
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);

        $this->postJson('/api/messages/', [
            'channels_id' => $channel->id,
            'message' => 'react to me',
        ])->assertCreated();
        $message = Message::where('channels_id', $channel->id)->firstOrFail();

        $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('reacted', true);

        $this->getJson("/api/messages/{$channel->id}/")
            ->assertOk()
            ->assertJsonPath('data.0.reactions.0.emoji', '👍')
            ->assertJsonPath('data.0.reactions.0.count', 1)
            ->assertJsonPath('data.0.reactions.0.reacted', true);

        $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('reacted', false);

        $this->assertDatabaseCount('message_reactions', 0);
    }

    public function test_owner_can_delete_message_and_attachment_file(): void
    {
        Event::fake();
        Storage::fake('local');
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        Sanctum::actingAs($user);

        Storage::disk('local')->put('attachments/test.txt', 'content');
        $message = Message::create([
            'user_id' => $user->id,
            'channels_id' => $channel->id,
            'type' => 'file',
            'message' => '',
            'meta' => ['attachment' => ['disk_path' => 'attachments/test.txt']],
            'key_id' => 1,
        ]);

        $this->deleteJson("/api/messages/{$message->id}")->assertOk();

        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
        Storage::disk('local')->assertMissing('attachments/test.txt');
    }
}
