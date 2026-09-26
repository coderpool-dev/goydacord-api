<?php

namespace App\Services\Admin;

use App\Exceptions\ApiException;
use App\Models\Admin\Privilege;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/** Выдача и снятие прав администратора. */
class AdminPrivilegeService
{
    public function admins(): Collection
    {
        return User::query()
            ->whereHas('privileges', fn ($query) => $query->where('name', Privilege::ADMIN))
            ->with(['privileges', 'yandexMusicConnection'])
            ->orderBy('id')
            ->get();
    }

    public function findByLogin(string $login): User
    {
        $user = User::query()
            ->whereRaw('LOWER(login) = ?', [mb_strtolower(trim($login))])
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => ['Пользователь с таким логином не найден'],
            ]);
        }

        return $user;
    }

    /** @return bool false — пользователь уже был админом */
    public function grant(User $user): bool
    {
        $privilege = Privilege::query()->firstOrCreate(['name' => Privilege::ADMIN]);

        if ($user->privileges()->whereKey($privilege->id)->exists()) {
            return false;
        }

        $user->privileges()->attach($privilege->id);

        return true;
    }

    public function revoke(User $actor, User $user): void
    {
        $privilege = Privilege::query()->where('name', Privilege::ADMIN)->first();

        if (! $privilege) {
            throw new ApiException('Привилегия admin не настроена', 404);
        }

        // Иначе в админку больше никто не попадёт.
        if ($privilege->users()->count() <= 1) {
            throw new ApiException('Нельзя снять последнего админа', 422);
        }

        if ((int) $actor->id === (int) $user->id) {
            throw new ApiException('Нельзя снять права у самого себя', 422);
        }

        $user->privileges()->detach($privilege->id);
    }
}
