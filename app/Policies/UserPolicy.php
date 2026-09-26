<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /** Игры и музыку пользователя видят он сам, его друзья и администраторы. */
    public function viewActivity(User $viewer, User $user): bool
    {
        return (int) $viewer->id === (int) $user->id
            || $viewer->isFriend($user->id)
            || $viewer->isAdmin();
    }
}
