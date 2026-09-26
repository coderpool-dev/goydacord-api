<?php

namespace App\Policies;

use App\Models\Uploads\UploadSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class UploadSessionPolicy
{
    /** Чужую сессию не показываем вовсе: 404 вместо 403. */
    public function manage(User $user, UploadSession $upload): Response
    {
        return (int) $upload->user_id === (int) $user->id
            ? Response::allow()
            : Response::denyAsNotFound('Upload session not found');
    }
}
