<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use App\Events\ServerMentioned;
use App\Models\Conversations\Message;
use App\Models\Servers\Server;
use App\Models\Servers\ServerChannelMemberOverwrite;
use App\Models\Servers\ServerChannelRoleOverwrite;
use App\Models\Servers\ServerRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

/** Упоминания <@id>, <@&role>, @everyone, @here в каналах сервера и ники на сервере. */
class ServerMentionAndNicknameTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    /** @return array{0: Server, 1: User, 2: int} сервер, владелец, id текстового канала */
    private function serverWithChannel(): array
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);

        return [$server, $owner, (int) $channel->id];
    }

    private function send(User $as, int $channelId, string $text): Message
    {
        Sanctum::actingAs($as, ['*']);
        $id = $this->postJson('/api/server-channel-messages', ['server_channel_id' => $channelId, 'message' => $text])
            ->assertCreated()->json('message');

        return Message::query()->findOrFail($id);
    }

    public function test_user_mention_is_stored_and_notifies_only_the_mentioned_user(): void
    {
        Event::fake([ServerMentioned::class]);
        [$server, $owner, $channelId] = $this->serverWithChannel();
        $friend = $this->makeUser();
        $this->addServerMember($server, $friend);
        $bystander = $this->makeUser();
        $this->addServerMember($server, $bystander);
        $outsider = $this->makeUser();

        $message = $this->send($owner, $channelId, "привет <@{$friend->id}> и <@{$outsider->id}>");

        $this->assertSame([$friend->id], $message->mentions['users']);
        Event::assertDispatched(ServerMentioned::class, fn (ServerMentioned $e) => $e->userId === $friend->id
            && str_contains($e->excerpt, '@'.$friend->name));
        Event::assertDispatchedTimes(ServerMentioned::class, 1);

        $this->getJson("/api/server-channel-messages/{$channelId}")
            ->assertOk()->assertJsonPath('data.0.mentions.users.0', $friend->id);
    }

    public function test_everyone_needs_mention_everyone_and_skips_those_who_cannot_see_channel(): void
    {
        Event::fake([ServerMentioned::class]);
        [$server, $owner, $channelId] = $this->serverWithChannel();
        $member = $this->makeUser();
        $memberRow = $this->addServerMember($server, $member);
        $hidden = $this->makeUser();
        $hiddenRow = $this->addServerMember($server, $hidden);
        ServerChannelMemberOverwrite::create([
            'server_channel_id' => $channelId, 'server_member_id' => $hiddenRow->id,
            'allow' => 0, 'deny' => ServerPermission::VIEW_CHANNELS,
        ]);

        // Обычный участник без MENTION_EVERYONE — @everyone остаётся текстом.
        $plain = $this->send($member, $channelId, 'всем @everyone');
        $this->assertNull($plain->mentions);
        Event::assertNotDispatched(ServerMentioned::class);

        $this->send($owner, $channelId, 'внимание @everyone!');
        Event::assertDispatched(ServerMentioned::class, fn (ServerMentioned $e) => $e->userId === $member->id);
        Event::assertNotDispatched(ServerMentioned::class, fn (ServerMentioned $e) => $e->userId === $hidden->id);
        Event::assertNotDispatched(ServerMentioned::class, fn (ServerMentioned $e) => $e->userId === $owner->id);
        $this->assertNotNull($memberRow);
    }

    public function test_role_mention_respects_mentionable_flag(): void
    {
        Event::fake([ServerMentioned::class]);
        [$server, , $channelId] = $this->serverWithChannel();
        $sender = $this->makeUser();
        $this->addServerMember($server, $sender);
        $target = $this->makeUser();
        $targetRow = $this->addServerMember($server, $target);
        $role = $this->makeServerRole($server, ['mentionable' => false]);
        $targetRow->roles()->attach($role->id);

        $this->assertNull($this->send($sender, $channelId, "<@&{$role->id}> сюда")->mentions);
        Event::assertNotDispatched(ServerMentioned::class);

        $role->update(['mentionable' => true]);
        $message = $this->send($sender, $channelId, "<@&{$role->id}> сюда");
        $this->assertSame([$role->id], $message->mentions['roles']);
        Event::assertDispatched(ServerMentioned::class, fn (ServerMentioned $e) => $e->userId === $target->id);
    }

    public function test_editing_recomputes_mentions_without_new_notification(): void
    {
        [$server, $owner, $channelId] = $this->serverWithChannel();
        $friend = $this->makeUser();
        $this->addServerMember($server, $friend);
        $message = $this->send($owner, $channelId, 'без упоминаний');

        Event::fake([ServerMentioned::class]);
        $this->patchJson("/api/messages/{$message->id}", ['message' => "теперь <@{$friend->id}>"])->assertOk();

        $this->assertSame([$friend->id], $message->fresh()->mentions['users']);
        Event::assertNotDispatched(ServerMentioned::class);
    }

    public function test_member_can_change_own_nickname_and_reset_it(): void
    {
        [$server] = $this->serverWithChannel();
        $user = $this->makeUser();
        $member = $this->addServerMember($server, $user);
        Sanctum::actingAs($user, ['*']);

        $this->putJson("/api/servers/{$server->id}/members/{$member->id}/nickname", ['nickname' => '  Капитан  '])
            ->assertOk()->assertJsonPath('member.nickname', 'Капитан');
        $this->putJson("/api/servers/{$server->id}/members/{$member->id}/nickname", ['nickname' => ''])
            ->assertOk()->assertJsonPath('member.nickname', null);
    }

    public function test_own_nickname_needs_change_nickname_permission(): void
    {
        [$server] = $this->serverWithChannel();
        ServerRole::query()->where('server_id', $server->id)->where('is_default', true)
            ->update(['permissions' => ServerPermission::DEFAULT & ~ServerPermission::CHANGE_NICKNAME]);
        $user = $this->makeUser();
        $member = $this->addServerMember($server, $user);
        Sanctum::actingAs($user, ['*']);

        $this->putJson("/api/servers/{$server->id}/members/{$member->id}/nickname", ['nickname' => 'Нельзя'])->assertForbidden();
    }

    public function test_changing_others_nickname_needs_manage_nicknames_and_hierarchy(): void
    {
        [$server] = $this->serverWithChannel();
        $mod = $this->makeUser();
        $modRow = $this->addServerMember($server, $mod);
        $modRole = $this->makeServerRole($server, ['permissions' => ServerPermission::MANAGE_NICKNAMES]);
        $regular = $this->addServerMember($server, $this->makeUser());
        $admin = $this->addServerMember($server, $this->makeUser());
        $adminRole = $this->makeServerRole($server, ['permissions' => ServerPermission::ADMINISTRATOR]);
        $admin->roles()->attach($adminRole->id);

        Sanctum::actingAs($mod, ['*']);
        $this->putJson("/api/servers/{$server->id}/members/{$regular->id}/nickname", ['nickname' => 'Новичок'])->assertForbidden();

        $modRow->roles()->attach($modRole->id);
        $this->putJson("/api/servers/{$server->id}/members/{$regular->id}/nickname", ['nickname' => 'Новичок'])
            ->assertOk()->assertJsonPath('member.nickname', 'Новичок');
        $this->putJson("/api/servers/{$server->id}/members/{$admin->id}/nickname", ['nickname' => 'Шеф'])->assertForbidden();
    }

    public function test_voice_participant_shows_server_nickname(): void
    {
        [$server, $owner] = $this->serverWithChannel();
        $voice = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Voice]);
        $server->members()->where('user_id', $owner->id)->update(['nickname' => 'Хозяин']);
        Sanctum::actingAs($owner, ['*']);

        $this->postJson("/api/server-channels/{$voice->id}/calls", ['session_id' => (string) Str::uuid()])->assertOk();
        $this->getJson("/api/server-channels/{$voice->id}/calls/participants")
            ->assertOk()->assertJsonPath('participants.0.name', 'Хозяин');
        $this->assertTrue(ServerChannelRoleOverwrite::query()->doesntExist());
    }
}
