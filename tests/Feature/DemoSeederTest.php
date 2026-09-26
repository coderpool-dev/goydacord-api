<?php

namespace Tests\Feature;

use App\Models\Conversations\Call;
use App\Models\Conversations\Channel;
use App\Models\Conversations\Message;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_friends_conversation_and_active_call(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(3, User::query()->where('email', 'like', '%@demo.local')->count());

        $channel = Channel::query()->where('name', 'Демо-беседа')->firstOrFail();
        $this->assertSame(3, Message::query()->where('channels_id', $channel->id)->where('type', 'text')->count());
        $this->assertTrue(Call::query()->where('channel_id', $channel->id)->where('status', 'active')->exists());
    }

    public function test_can_be_run_again_without_duplicates(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(3, User::query()->where('email', 'like', '%@demo.local')->count());
        $this->assertSame(1, Channel::query()->where('name', 'Демо-беседа')->count());
    }
}
