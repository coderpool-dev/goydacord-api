<?php

namespace App\Http\Requests\Concerns;

use App\Models\Servers\ServerChannel;

/** Запрос, в теле которого приходит текстовый канал сервера (server_channel_id) — зеркало TargetsChannel. */
trait TargetsServerChannel
{
    private ?ServerChannel $targetServerChannel = null;

    public function channel(): ServerChannel
    {
        return $this->targetServerChannel ??= ServerChannel::findOrFail($this->integer('server_channel_id'));
    }

    public function replyToId(): ?int
    {
        return $this->integer('reply_to_id') ?: null;
    }
}
