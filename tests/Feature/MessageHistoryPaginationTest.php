<?php

namespace Tests\Feature;

use App\Enums\ServerChannelKind;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

/** ?before={id} — страница сообщений старше указанного: так фронт догружает всю историю. */
class MessageHistoryPaginationTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    public function test_server_channel_history_pages_with_before_cursor(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        Sanctum::actingAs($owner, ['*']);

        $ids = [];
        for ($i = 1; $i <= 55; $i++) {
            $ids[] = $this->postJson('/api/server-channel-messages', ['server_channel_id' => $channel->id, 'message' => "m{$i}"])
                ->assertCreated()->json('message');
        }

        $latest = $this->getJson("/api/server-channel-messages/{$channel->id}")->assertOk()->json('data');
        $this->assertCount(50, $latest);
        $this->assertSame('m6', $latest[0]['message']);
        $this->assertSame('m55', $latest[49]['message']);

        $older = $this->getJson("/api/server-channel-messages/{$channel->id}?before={$latest[0]['id']}")->assertOk()->json('data');
        $this->assertSame(['m1', 'm2', 'm3', 'm4', 'm5'], array_column($older, 'message'));
        $this->assertSame($ids[0], $older[0]['id']);
    }
}
