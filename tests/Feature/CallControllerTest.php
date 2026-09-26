<?php

namespace Tests\Feature;

use App\Enums\MemberCallStatus;
use App\Enums\MembershipStatus;
use App\Models\Conversations\Call;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class CallControllerTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    public function test_non_member_cannot_use_calls_in_channel(): void
    {
        $channel = $this->makeChannel();
        $member = $this->makeUser();
        $this->addMember($channel, $member, MembershipStatus::Member, MemberCallStatus::InCall);
        $this->makeActiveCall($channel, $member);

        Sanctum::actingAs($this->makeUser());

        $this->postJson("/api/calls/{$channel->id}")->assertForbidden();
        $this->postJson("/api/calls/{$channel->id}/accept", ['session_id' => 'stranger'])->assertForbidden();
        $this->postJson("/api/calls/{$channel->id}/decline")->assertForbidden();
        $this->postJson("/api/calls/{$channel->id}/leave", ['session_id' => 'stranger'])->assertForbidden();
        $this->postJson("/api/calls/{$channel->id}/heartbeat", ['session_id' => 'stranger'])->assertForbidden();
        $this->getJson("/api/calls/{$channel->id}/screen-preview/{$member->id}")->assertForbidden();

        $this->assertSame(1, Call::query()->count());
        $this->assertDatabaseCount('call_sessions', 0);
    }

    public function test_member_starts_call_and_last_participant_leaving_ends_it(): void
    {
        $channel = $this->makeChannel();
        $user = $this->makeUser();
        $this->addMember($channel, $user);

        Sanctum::actingAs($user);

        $this->postJson("/api/calls/{$channel->id}")
            ->assertCreated()
            ->assertJsonPath('call.status', 'active');

        $this->postJson("/api/calls/{$channel->id}/leave")
            ->assertOk()
            ->assertJsonPath('message', 'Вы вышли, звонок завершён');
    }
}
