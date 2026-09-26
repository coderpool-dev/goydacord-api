<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Exceptions\ApiException;
use App\Services\Conversations\ChannelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCalls;
use Tests\TestCase;

class ChannelServiceTest extends TestCase
{
    use InteractsWithCalls;
    use RefreshDatabase;

    private ChannelService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ChannelService::class);
    }

    public function test_is_member_ignores_removed_members(): void
    {
        $channel = $this->makeChannel();
        $member = $this->makeUser();
        $removed = $this->makeUser();
        $this->addMember($channel, $member);
        $this->addMember($channel, $removed, MembershipStatus::Removed);

        $this->assertTrue($this->service->isMember($member->id, $channel->id));
        $this->assertFalse($this->service->isMember($removed->id, $channel->id));
    }

    public function test_leave_rejects_non_member(): void
    {
        $this->expectException(ApiException::class);

        $this->service->leave($this->makeUser(), $this->makeChannel()->id);
    }

    public function test_leave_marks_membership_removed(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $membership = $this->addMember($channel, $user);

        $this->service->leave($user, $channel->id);

        $this->assertSame(MembershipStatus::Removed, $membership->fresh()->status);
    }

    public function test_leaving_twice_is_rejected(): void
    {
        $user = $this->makeUser();
        $channel = $this->makeChannel();
        $this->addMember($channel, $user);

        $this->service->leave($user, $channel->id);

        $this->expectException(ApiException::class);
        $this->service->leave($user, $channel->id);
    }

    public function test_admin_leaving_hands_admin_role_to_remaining_member(): void
    {
        $channel = $this->makeChannel();
        $admin = $this->makeUser();
        $member = $this->makeUser();
        $this->addMember($channel, $admin, MembershipStatus::Admin);
        $membership = $this->addMember($channel, $member);

        $this->service->leave($admin, $channel->id);

        $this->assertSame(MembershipStatus::Admin, $membership->fresh()->status);
    }
}
