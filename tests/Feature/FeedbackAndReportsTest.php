<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class FeedbackAndReportsTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    public function test_guest_can_send_feedback(): void
    {
        $this->postJson('/api/feedback', [
            'name' => 'Иван',
            'email' => 'Ivan@Example.test',
            'body' => 'Хочу тёмную тему',
            'consent' => true,
        ])->assertCreated();

        $this->assertDatabaseHas('feedback_messages', [
            'email' => 'ivan@example.test',
            'user_id' => null,
        ]);
    }

    public function test_feedback_author_is_detected_by_token(): void
    {
        $user = $this->makeUser();

        $this->withToken($this->tokenFor($user))
            ->postJson('/api/feedback', [
                'name' => 'Иван',
                'email' => 'ivan@example.test',
                'body' => 'Хочу тёмную тему',
                'consent' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('feedback_messages', ['user_id' => $user->id]);
    }

    public function test_user_cannot_report_self(): void
    {
        $user = $this->makeUser();
        Sanctum::actingAs($user);

        $this->postJson('/api/reports', ['target_id' => $user->id, 'reason' => 'spam'])->assertStatus(422);
    }

    public function test_repeated_report_does_not_duplicate_open_one(): void
    {
        $target = $this->makeUser();
        Sanctum::actingAs($this->makeUser());

        $this->postJson('/api/reports', ['target_id' => $target->id, 'reason' => 'spam'])->assertCreated();
        $this->postJson('/api/reports', ['target_id' => $target->id, 'reason' => 'spam'])->assertCreated();

        $this->assertDatabaseCount('user_reports', 1);
    }
}
