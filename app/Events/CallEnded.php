<?php

namespace App\Events;

use App\Models\Conversations\Call;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Звонок завершился. В отличие от CallParticipantLeft это видят не только те, кто был
 * в звонке, а любой участник беседы — иначе баннер «Присоединиться» у тех, кто не зашёл,
 * не узнаёт о конце звонка до следующего опроса /calls/active (до 15 с, а если вкладка
 * не в фокусе — polling вообще стоит) и остаётся висеть, пока по нему не кликнут вхолостую.
 */
class CallEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $call;

    public function __construct(Call $call)
    {
        $this->call = $call;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('channel.'.$this->call->channel_id)];
    }

    public function broadcastAs(): string
    {
        return 'call.ended';
    }

    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->call->channel_id,
            'call_id' => $this->call->call_id,
        ];
    }
}
