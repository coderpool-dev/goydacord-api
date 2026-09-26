<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    /** У каждого публичного эндпоинта свой счётчик: пинги не расходуют лимит восстановления пароля. */
    public function test_public_endpoints_have_separate_counters(): void
    {
        Notification::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->getJson('/api/ping')->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'ghost@example.test'])->assertOk();
    }

    /** Обычные запросы пользователя не выбирают отдельный лимит эндпоинта. */
    public function test_general_traffic_does_not_exhaust_route_limit(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        Sanctum::actingAs($user);

        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/friends')->assertOk();
        }

        $this->getJson("/api/friends/mutual/{$other->id}")->assertOk();
    }

    public function test_route_limit_still_applies(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/forgot-password', ['email' => 'ghost@example.test'])->assertOk();
        }

        $this->postJson('/api/forgot-password', ['email' => 'ghost@example.test'])->assertStatus(429);
    }
}
