<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use App\Models\Conversations\Message;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

/**
 * Сообщения в текстовых каналах сервера идут через тот же MessageService, что и ЛС/группы
 * (см. дополнение "единый чат" в плане) — вложения/стикеры/edit/delete/реакции здесь и
 * есть регрессионный тест на баг MessagePolicy, который раньше резолвил доступ только
 * через channels_id и вернул бы 403 всем на сообщение сервера.
 */
class ServerChannelMessageControllerTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_member_can_send_attachment(): void
    {
        Event::fake();
        Storage::fake('public');
        Storage::fake('local');

        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);

        Sanctum::actingAs($owner, ['*']);

        $this->postJson('/api/server-channel-messages/attachment', [
            'server_channel_id' => $channel->id,
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertCreated();

        $this->assertDatabaseHas('messages', ['server_channel_id' => $channel->id, 'type' => 'image']);
    }

    public function test_member_can_send_sticker(): void
    {
        Event::fake();
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);

        Sanctum::actingAs($owner, ['*']);

        $this->postJson('/api/server-channel-messages/sticker', [
            'server_channel_id' => $channel->id,
            'sticker' => 'classic/cool',
        ])->assertCreated();

        $this->assertDatabaseHas('messages', ['server_channel_id' => $channel->id, 'type' => 'sticker']);
    }

    public function test_owner_can_edit_and_delete_own_server_message_via_shared_routes(): void
    {
        Event::fake();
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson('/api/server-channel-messages', [
            'server_channel_id' => $channel->id,
            'message' => 'before',
        ])->assertCreated();

        $message = Message::where('server_channel_id', $channel->id)->firstOrFail();

        // Общий messages/{message} роут, не отдельный server-роут — доступ решает MessagePolicy.
        $this->patchJson("/api/messages/{$message->id}", ['message' => 'after'])->assertOk();
        $message->refresh();
        $this->assertNotSame('after', $message->message);
        $this->assertNotNull($message->edited_at);

        $this->deleteJson("/api/messages/{$message->id}")->assertOk();
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_non_member_cannot_edit_or_delete_server_message(): void
    {
        Event::fake();
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson('/api/server-channel-messages', [
            'server_channel_id' => $channel->id,
            'message' => 'mine',
        ])->assertCreated();
        $message = Message::where('server_channel_id', $channel->id)->firstOrFail();

        $stranger = $this->makeUser();
        Sanctum::actingAs($stranger, ['*']);

        $this->patchJson("/api/messages/{$message->id}", ['message' => 'stolen'])->assertForbidden();
        $this->deleteJson("/api/messages/{$message->id}")->assertForbidden();
        $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])->assertForbidden();
        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_member_can_toggle_reaction_on_server_message(): void
    {
        Event::fake();
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);

        $member = $this->makeUser();
        $this->addServerMember($server, $member);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson('/api/server-channel-messages', [
            'server_channel_id' => $channel->id,
            'message' => 'react to me',
        ])->assertCreated();
        $message = Message::where('server_channel_id', $channel->id)->firstOrFail();

        Sanctum::actingAs($member, ['*']);
        $this->postJson("/api/messages/{$message->id}/reactions", ['emoji' => '👍'])
            ->assertOk()
            ->assertJsonPath('reacted', true);

        $this->getJson("/api/server-channel-messages/{$channel->id}")
            ->assertOk()
            ->assertJsonPath('data.0.reactions.0.emoji', '👍')
            ->assertJsonPath('data.0.reactions.0.count', 1);
    }

    public function test_moderator_with_manage_messages_can_delete_others_message_but_not_edit(): void
    {
        Event::fake();
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);

        $author = $this->makeUser();
        $this->addServerMember($server, $author);

        $moderator = $this->makeUser();
        $moderatorMembership = $this->addServerMember($server, $moderator);
        $modRole = $this->makeServerRole($server, ['permissions' => ServerPermission::MANAGE_MESSAGES]);
        $moderatorMembership->roles()->attach($modRole->id);

        $plainMember = $this->makeUser();
        $this->addServerMember($server, $plainMember);

        Sanctum::actingAs($author, ['*']);
        $this->postJson('/api/server-channel-messages', [
            'server_channel_id' => $channel->id,
            'message' => 'authored',
        ])->assertCreated();
        $message = Message::where('server_channel_id', $channel->id)->firstOrFail();

        // Право модерации не даёт редактировать чужое — только удалить.
        Sanctum::actingAs($moderator, ['*']);
        $this->patchJson("/api/messages/{$message->id}", ['message' => 'edited by mod'])->assertForbidden();

        Sanctum::actingAs($plainMember, ['*']);
        $this->deleteJson("/api/messages/{$message->id}")->assertForbidden();
        $this->assertDatabaseHas('messages', ['id' => $message->id]);

        Sanctum::actingAs($moderator, ['*']);
        $this->deleteJson("/api/messages/{$message->id}")->assertOk();
        $this->assertDatabaseMissing('messages', ['id' => $message->id]);
    }

    public function test_cannot_send_message_to_voice_channel(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $voice = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);

        Sanctum::actingAs($owner, ['*']);
        $this->postJson('/api/server-channel-messages', [
            'server_channel_id' => $voice->id,
            'message' => 'wrong channel',
        ])->assertStatus(403);
    }
}
