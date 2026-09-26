<?php

namespace App\Services\Servers;

use App\Enums\ServerChannelKind;
use App\Enums\ServerPermission;
use App\Models\Servers\Server;
use App\Models\Servers\ServerChannel;
use App\Models\Servers\ServerChannelMemberOverwrite;
use App\Models\Servers\ServerChannelRoleOverwrite;
use App\Models\Servers\ServerMember;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Каналы внутри сервера: создание, список, обновление, удаление. */
class ServerChannelService
{
    public function __construct(
        private readonly ServerService $servers,
        private readonly ServerRoleService $roles,
        private readonly ServerChannelPermissionResolver $resolver,
    ) {}

    /**
     * Только каналы, которые $user реально видит (VIEW_CHANNELS с учётом оверрайдов) —
     * без этого приватность канала была бы чисто декоративной. Владелец видит всё.
     */
    public function listForServer(Server $server, User $user): Collection
    {
        $channels = ServerChannel::query()
            ->with(['roleOverwrites', 'memberOverwrites'])
            ->where('server_id', $server->id)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        if ($this->servers->isOwner((int) $user->id, (int) $server->id)) {
            return $channels;
        }

        $member = $this->servers->activeMembershipWithRoles((int) $user->id, (int) $server->id);

        if (! $member) {
            return $channels->take(0);
        }

        return $channels
            ->filter(fn (ServerChannel $channel) => ServerPermission::has(
                $this->resolver->effectivePermissions($member, $channel),
                ServerPermission::VIEW_CHANNELS,
            ))
            ->values();
    }

    public function create(Server $server, array $validated): ServerChannel
    {
        $kind = ServerChannelKind::from((int) $validated['kind']);

        if ($kind === ServerChannelKind::Category) {
            $validated['category_id'] = null;
        }

        if (! empty($validated['category_id'])) {
            $this->assertCategoryInServer((int) $validated['category_id'], (int) $server->id);
        }

        $position = (int) ServerChannel::query()->where('server_id', $server->id)->max('position') + 1;

        return ServerChannel::create([
            'server_id' => $server->id,
            'category_id' => $validated['category_id'] ?? null,
            'name' => trim($validated['name']),
            'kind' => $kind,
            'topic' => $validated['topic'] ?? null,
            'position' => $position,
        ]);
    }

    public function update(ServerChannel $channel, ServerActor $actor, array $validated): ServerChannel
    {
        if (array_key_exists('name', $validated) && $validated['name'] !== null) {
            $name = trim($validated['name']);

            if ($name === '') {
                throw ValidationException::withMessages(['name' => ['Введите название канала']]);
            }

            $channel->name = $name;
        }

        if (array_key_exists('topic', $validated)) {
            $channel->topic = $validated['topic'];
        }

        if (! empty($validated['category_id']) && $channel->kind !== ServerChannelKind::Category) {
            $this->assertCategoryInServer((int) $validated['category_id'], (int) $channel->server_id);
            $channel->category_id = $validated['category_id'];
        } elseif (array_key_exists('category_id', $validated) && $validated['category_id'] === null) {
            // Явный null — вынести канал из категории.
            $channel->category_id = null;
        }

        DB::transaction(function () use ($channel, $actor, $validated) {
            $channel->save();

            if (array_key_exists('overwrites', $validated)) {
                $this->syncRoleOverwrites($channel, $actor, $validated['overwrites'] ?? []);
            }

            if (array_key_exists('member_overwrites', $validated)) {
                $this->syncMemberOverwrites($channel, $actor, $validated['member_overwrites'] ?? []);
            }
        });

        return $channel->fresh();
    }

    public function delete(ServerChannel $channel): void
    {
        if ($channel->kind === ServerChannelKind::Text
            && ServerChannel::query()
                ->where('server_id', $channel->server_id)
                ->where('kind', ServerChannelKind::Text)
                ->count() <= 1
        ) {
            throw ValidationException::withMessages(['channel' => ['На сервере должен остаться хотя бы один текстовый канал']]);
        }

        DB::transaction(function () use ($channel) {
            ServerChannelRoleOverwrite::query()->where('server_channel_id', $channel->id)->delete();
            ServerChannelMemberOverwrite::query()->where('server_channel_id', $channel->id)->delete();
            // Удалили категорию — её каналы остаются, просто без категории (как в Discord).
            ServerChannel::query()->where('category_id', $channel->id)->update(['category_id' => null]);
            $channel->delete();
        });
    }

    /**
     * Полная замена переопределений по ролям. Менять можно только строки ролей ниже своей
     * высшей (@everyone — всегда) и только те биты, что есть у тебя самого; строки, которые
     * не менялись, не проверяются — иначе модератор не смог бы сохранить канал, где уже
     * настроена роль выше него.
     *
     * @param  array<int, array{role_id: int, allow?: int, deny?: int}>  $overwrites
     */
    private function syncRoleOverwrites(ServerChannel $channel, ServerActor $actor, array $overwrites): void
    {
        $roles = $this->roles->listForServer($channel->server)->keyBy('id');
        $old = ServerChannelRoleOverwrite::query()
            ->where('server_channel_id', $channel->id)
            ->get()
            ->mapWithKeys(fn (ServerChannelRoleOverwrite $overwrite) => [(int) $overwrite->server_role_id => [$overwrite->allow, $overwrite->deny]]);

        $new = collect();
        foreach ($overwrites as $overwrite) {
            $roleId = (int) $overwrite['role_id'];
            if (! $roles->has($roleId)) {
                continue;
            }
            [$allow, $deny] = $this->normalizeOverwrite($overwrite);
            if ($allow !== 0 || $deny !== 0) {
                $new[$roleId] = [$allow, $deny];
            }
        }

        foreach ($old->keys()->merge($new->keys())->unique() as $roleId) {
            $changed = $this->changedBits($old->get($roleId), $new->get($roleId));
            if ($changed === 0) {
                continue;
            }
            $this->roles->assertCanManageRole($actor, $roles[$roleId]);
            $this->roles->assertCanGrant($actor, $changed);
        }

        ServerChannelRoleOverwrite::query()->where('server_channel_id', $channel->id)->delete();
        foreach ($new as $roleId => [$allow, $deny]) {
            ServerChannelRoleOverwrite::create([
                'server_channel_id' => $channel->id,
                'server_role_id' => $roleId,
                'allow' => $allow,
                'deny' => $deny,
            ]);
        }
    }

    /**
     * Полная замена переопределений для отдельных участников — применяются последними.
     *
     * @param  array<int, array{member_id: int, allow?: int, deny?: int}>  $overwrites
     */
    private function syncMemberOverwrites(ServerChannel $channel, ServerActor $actor, array $overwrites): void
    {
        $memberIds = ServerMember::query()
            ->where('server_id', $channel->server_id)
            ->active()
            ->pluck('id')
            ->map(fn ($id) => (int) $id);
        $old = ServerChannelMemberOverwrite::query()
            ->where('server_channel_id', $channel->id)
            ->get()
            ->mapWithKeys(fn (ServerChannelMemberOverwrite $overwrite) => [(int) $overwrite->server_member_id => [$overwrite->allow, $overwrite->deny]]);

        $new = collect();
        foreach ($overwrites as $overwrite) {
            $memberId = (int) $overwrite['member_id'];
            if (! $memberIds->contains($memberId)) {
                continue;
            }
            [$allow, $deny] = $this->normalizeOverwrite($overwrite);
            if ($allow !== 0 || $deny !== 0) {
                $new[$memberId] = [$allow, $deny];
            }
        }

        foreach ($old->keys()->merge($new->keys())->unique() as $memberId) {
            $this->roles->assertCanGrant($actor, $this->changedBits($old->get($memberId), $new->get($memberId)));
        }

        ServerChannelMemberOverwrite::query()->where('server_channel_id', $channel->id)->delete();
        foreach ($new as $memberId => [$allow, $deny]) {
            ServerChannelMemberOverwrite::create([
                'server_channel_id' => $channel->id,
                'server_member_id' => $memberId,
                'allow' => $allow,
                'deny' => $deny,
            ]);
        }
    }

    /** @return array{0: int, 1: int} [allow, deny] только из переопределяемых на канале битов */
    private function normalizeOverwrite(array $overwrite): array
    {
        $allow = ((int) ($overwrite['allow'] ?? 0)) & ServerPermission::CHANNEL_OVERRIDABLE;
        $deny = ((int) ($overwrite['deny'] ?? 0)) & ServerPermission::CHANNEL_OVERRIDABLE & ~$allow;

        return [$allow, $deny];
    }

    /** @param  array{0: int, 1: int}|null  $old @param  array{0: int, 1: int}|null  $new */
    private function changedBits(?array $old, ?array $new): int
    {
        [$oldAllow, $oldDeny] = $old ?? [0, 0];
        [$newAllow, $newDeny] = $new ?? [0, 0];

        return ($oldAllow ^ $newAllow) | ($oldDeny ^ $newDeny);
    }

    private function assertCategoryInServer(int $categoryId, int $serverId): void
    {
        $exists = ServerChannel::query()
            ->whereKey($categoryId)
            ->where('server_id', $serverId)
            ->where('kind', ServerChannelKind::Category)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['category_id' => ['Категория не найдена на этом сервере']]);
        }
    }
}
