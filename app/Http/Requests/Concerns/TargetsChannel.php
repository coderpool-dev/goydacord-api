<?php

namespace App\Http\Requests\Concerns;

use App\Models\Conversations\Channel;

/** Запрос, в теле которого приходит беседа (channels_id) и, возможно, ответ на сообщение. */
trait TargetsChannel
{
    private ?Channel $targetChannel = null;

    public function channel(): Channel
    {
        return $this->targetChannel ??= Channel::findOrFail($this->integer('channels_id'));
    }

    public function replyToId(): ?int
    {
        return $this->integer('reply_to_id') ?: null;
    }
}
