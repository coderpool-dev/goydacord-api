<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class ApiErrorResponsesTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    /** 404 не раскрывает имена классов моделей. */
    public function test_missing_model_returns_generic_message(): void
    {
        Sanctum::actingAs($this->makeUser());

        $this->getJson('/api/channels/999999/members')
            ->assertNotFound()
            ->assertExactJson([
                'status' => 'error',
                'message' => 'Не найдено',
            ]);
    }

    public function test_guest_gets_json_401(): void
    {
        $this->getJson('/api/channels')
            ->assertUnauthorized()
            ->assertJsonPath('status', 'error');
    }

    public function test_unverified_user_can_manage_sessions_but_not_use_app(): void
    {
        $user = $this->makeUser(['email_verified_at' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/sessions')->assertOk();
        $this->getJson('/api/channels')
            ->assertForbidden()
            ->assertJsonPath('code', 'EMAIL_NOT_VERIFIED');
    }
}
