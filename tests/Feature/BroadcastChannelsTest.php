<?php

namespace Tests\Feature;

use App\Broadcasting\ConversationChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class BroadcastChannelsTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    public function test_member_joins_conversation_with_presence_data(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);

        $presence = app(ConversationChannel::class)->join($user, (string) $channel->id);

        $this->assertSame($user->id, $presence['id']);
        $this->assertSame($user->login, $presence['login']);
    }

    public function test_stranger_cannot_join_conversation(): void
    {
        $channel = $this->makeChannel();

        $this->assertFalse(app(ConversationChannel::class)->join($this->makeUser(), $channel->id));
    }
}
