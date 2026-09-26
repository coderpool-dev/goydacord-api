<?php

namespace Tests\Feature;

use App\Enums\ServerPermission;
use App\Models\User;
use App\Services\Conversations\MessageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class QueryCountTest extends TestCase
{
    use InteractsWithCalls;
    use InteractsWithServers;
    use RefreshDatabase;

    public function test_channel_index_query_count_does_not_grow_with_channel_count(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->seedChannelsFor($user, 2);
        $few = $this->countQueries(fn () => $this->getJson('/api/channels')->assertOk());

        $this->seedChannelsFor($user, 8);
        $many = $this->countQueries(fn () => $this->getJson('/api/channels')->assertOk());

        $this->assertLessThanOrEqual(
            $few + 3,
            $many,
            "Список чатов не должен ходить в БД по разу на канал ({$few} запросов на 2 чата, {$many} на 10)",
        );
    }

    public function test_message_index_query_count_does_not_grow_with_message_count(): void
    {
        Event::fake();

        $user = $this->makeUser();
        $other = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);
        $this->addMember($channel, $other);
        Sanctum::actingAs($user);

        $messages = app(MessageService::class);
        $this->seedMessages($messages, $other, (int) $channel->id, 5);
        $few = $this->countQueries(fn () => $this->getJson("/api/messages/{$channel->id}")->assertOk());

        $this->seedMessages($messages, $other, (int) $channel->id, 20);
        $many = $this->countQueries(fn () => $this->getJson("/api/messages/{$channel->id}")->assertOk());

        $this->assertLessThanOrEqual(
            $few + 3,
            $many,
            "Список сообщений не должен ходить в БД по разу на сообщение ({$few} запросов на 5 сообщений, {$many} на 25)",
        );
    }

    public function test_server_channel_permissions_query_count_is_constant_for_regular_member(): void
    {
        $owner = $this->makeUser();
        $user = $this->makeUser();
        $server = $this->makeServer($owner);
        $member = $this->addServerMember($server, $user);
        Sanctum::actingAs($user);
        for ($i = 0; $i < 2; $i++) {
            $this->makeServerChannel($server);
        }
        $few = $this->countQueries(fn () => $this->getJson("/api/servers/{$server->id}/channels")->assertOk()->assertJsonCount(2, 'channels'));
        for ($i = 0; $i < 8; $i++) {
            $this->makeServerChannel($server);
        }
        $hidden = $this->makeServerChannel($server);
        $hidden->memberOverwrites()->create([
            'server_member_id' => $member->id, 'allow' => 0,
            'deny' => ServerPermission::VIEW_CHANNELS,
        ]);
        $many = $this->countQueries(fn () => $this->getJson("/api/servers/{$server->id}/channels")->assertOk()->assertJsonCount(10, 'channels'));
        $this->assertLessThanOrEqual($few + 1, $many, "Queries grew from {$few} to {$many}");
    }

    private function seedChannelsFor(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $channel = $this->makeChannel();
            $this->addMember($channel, $user);
            $this->addMember($channel, $this->makeUser());
        }
    }

    private function seedMessages(MessageService $messages, User $author, int $channelId, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $messages->storeText($author, $channelId, null, "msg-{$i}-".uniqid(), null);
        }
    }

    private function countQueries(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
