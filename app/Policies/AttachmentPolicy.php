<?php

namespace App\Policies;

use App\Models\Conversations\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AttachmentPolicy
{
    public function delete(User $user, Attachment $attachment): Response
    {
        return (int) $attachment->user_id === (int) $user->id
            ? Response::allow()
            : Response::deny('Нет доступа');
    }
}
