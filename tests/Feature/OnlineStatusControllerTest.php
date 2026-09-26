<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class OnlineStatusControllerTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    public function test_heartbeat_updates_last_online(): void
    {
        $user = $this->makeUser(['last_online' => now()->subHour()]);

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/online')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $fresh = $user->fresh();
        $this->assertTrue(Carbon::parse($fresh->last_online)->gt(now()->subMinute()));
        $this->assertNotNull($fresh->last_platform);
    }

    public function test_heartbeat_requires_authentication(): void
    {
        $this->postJson('/api/auth/online')->assertUnauthorized();
    }
}
