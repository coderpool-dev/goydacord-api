<?php

namespace Tests\Unit;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use App\Models\Servers\ServerChannelMemberOverwrite;
use App\Models\Servers\ServerChannelRoleOverwrite;
use App\Services\Servers\ServerChannelPermissionResolver;
use App\Services\Servers\ServerRoleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCalls;
use Tests\Concerns\InteractsWithServers;
use Tests\TestCase;

class ServerChannelPermissionResolverTest extends TestCase
{
    use InteractsWithCalls, InteractsWithServers, RefreshDatabase;

    private function resolver(): ServerChannelPermissionResolver
    {
        return new ServerChannelPermissionResolver(new ServerRoleService);
    }

    public function test_no_overwrites_falls_back_to_base_role_permissions(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $member = $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);

        $permissions = $this->resolver()->effectivePermissions($member->load('roles'), $channel);

        $this->assertTrue(ServerPermission::has($permissions, ServerPermission::VIEW_CHANNELS));
        $this->assertTrue(ServerPermission::has($permissions, ServerPermission::SEND_MESSAGES));
    }

    public function test_deny_overwrite_removes_base_permission(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $member = $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $role = $member->fresh()->roles()->first();

        ServerChannelRoleOverwrite::create([
            'server_channel_id' => $channel->id,
            'server_role_id' => $role->id,
            'allow' => 0,
            'deny' => ServerPermission::SEND_MESSAGES,
        ]);

        $permissions = $this->resolver()->effectivePermissions($member->load('roles'), $channel);

        $this->assertTrue(ServerPermission::has($permissions, ServerPermission::VIEW_CHANNELS));
        $this->assertFalse(ServerPermission::has($permissions, ServerPermission::SEND_MESSAGES));
    }

    public function test_allow_wins_over_deny_across_conflicting_roles(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $member = $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $defaultRole = $member->fresh()->roles()->first();

        $extraRole = $this->makeServerRole($server, ['permissions' => 0]);
        $member->roles()->attach($extraRole->id);

        // Одна роль запрещает CONNECT_VOICE, другая явно разрешает — allow побеждает.
        ServerChannelRoleOverwrite::create([
            'server_channel_id' => $channel->id,
            'server_role_id' => $defaultRole->id,
            'allow' => 0,
            'deny' => ServerPermission::CONNECT_VOICE,
        ]);
        ServerChannelRoleOverwrite::create([
            'server_channel_id' => $channel->id,
            'server_role_id' => $extraRole->id,
            'allow' => ServerPermission::CONNECT_VOICE,
            'deny' => 0,
        ]);

        $permissions = $this->resolver()->effectivePermissions($member->load('roles'), $channel);

        $this->assertTrue(ServerPermission::has($permissions, ServerPermission::CONNECT_VOICE));
    }

    public function test_view_channels_can_be_denied_to_hide_a_private_channel(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $member = $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $role = $member->fresh()->roles()->first();

        ServerChannelRoleOverwrite::create([
            'server_channel_id' => $channel->id,
            'server_role_id' => $role->id,
            'allow' => 0,
            'deny' => ServerPermission::VIEW_CHANNELS,
        ]);

        $permissions = $this->resolver()->effectivePermissions($member->load('roles'), $channel);

        $this->assertFalse(ServerPermission::has($permissions, ServerPermission::VIEW_CHANNELS));
    }

    public function test_role_deny_beats_explicit_everyone_allow(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $member = $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $everyone = $member->fresh()->roles()->first();
        $muted = $this->makeServerRole($server, ['permissions' => 0]);
        $member->roles()->attach($muted->id);

        // Канал явно открыт @everyone, но закрыт роли — как в Discord, роль применяется после @everyone.
        ServerChannelRoleOverwrite::create([
            'server_channel_id' => $channel->id, 'server_role_id' => $everyone->id,
            'allow' => ServerPermission::SEND_MESSAGES, 'deny' => 0,
        ]);
        ServerChannelRoleOverwrite::create([
            'server_channel_id' => $channel->id, 'server_role_id' => $muted->id,
            'allow' => 0, 'deny' => ServerPermission::SEND_MESSAGES,
        ]);

        $permissions = $this->resolver()->effectivePermissions($member->load('roles'), $channel);

        $this->assertFalse(ServerPermission::has($permissions, ServerPermission::SEND_MESSAGES));
    }

    public function test_member_overwrite_is_applied_last(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $member = $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $everyone = $member->fresh()->roles()->first();

        ServerChannelRoleOverwrite::create([
            'server_channel_id' => $channel->id, 'server_role_id' => $everyone->id,
            'allow' => 0, 'deny' => ServerPermission::VIEW_CHANNELS,
        ]);
        ServerChannelMemberOverwrite::create([
            'server_channel_id' => $channel->id, 'server_member_id' => $member->id,
            'allow' => ServerPermission::VIEW_CHANNELS, 'deny' => ServerPermission::SEND_MESSAGES,
        ]);

        $permissions = $this->resolver()->effectivePermissions($member->load('roles'), $channel);

        $this->assertTrue(ServerPermission::has($permissions, ServerPermission::VIEW_CHANNELS));
        $this->assertFalse(ServerPermission::has($permissions, ServerPermission::SEND_MESSAGES));
    }

    public function test_administrator_ignores_channel_denies(): void
    {
        $owner = $this->makeUser();
        $server = $this->makeServer($owner);
        $member = $this->addServerMember($server, $owner);
        $channel = $this->makeServerChannel($server, ['kind' => ServerChannelKind::Text]);
        $everyone = $member->fresh()->roles()->first();
        $admin = $this->makeServerRole($server, ['permissions' => ServerPermission::ADMINISTRATOR]);
        $member->roles()->attach($admin->id);

        ServerChannelRoleOverwrite::create([
            'server_channel_id' => $channel->id, 'server_role_id' => $everyone->id,
            'allow' => 0, 'deny' => ServerPermission::VIEW_CHANNELS,
        ]);

        $this->assertSame(ServerPermission::ALL, $this->resolver()->effectivePermissions($member->load('roles'), $channel));
    }
}
