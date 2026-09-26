<?php

namespace App\Services\Conversations;

use App\Events\MessageSent;
use App\Models\Conversations\Call;
use App\Models\Conversations\Message;
use App\Models\User;

/** Системные сообщения о звонке в ленте канала: «начал звонок», «продлился N», «никто не ответил». */
class CallMessageService
{
    public function postStartMessage(Call $call, User $initiator): void
    {
        $message = Message::createSystem(
            $call->channel_id,
            $initiator->id,
            'call_started',
            ['actor_name' => $initiator->name, 'call_id' => $call->call_id],
            "{$initiator->name} начал(а) звонок",
        );

        // По этому id сообщение потом превратится в итог звонка.
        $call->update(['system_message_id' => $message->id]);
    }

    /**
     * Отвеченный звонок: стартовое сообщение удаляется, а итог с длительностью пишется новым
     * в конец ленты, где звонок реально закончился. Пропущенный звонок: стартовое сообщение
     * обновляется на месте, длительности у него нет.
     */
    public function postSummary(Call $call): void
    {
        $message = $call->system_message_id ? Message::find($call->system_message_id) : null;

        if (! $message || $message->type !== 'system') {
            return;
        }

        $initiator = User::find($call->initiator_id);
        $actorName = $initiator->name ?? 'Кто-то';

        if (! $call->answered) {
            $this->markMissed($call, $message, $initiator, $actorName);

            return;
        }

        $endedAt = now();
        $duration = max(0, (int) $call->created_at->diffInSeconds($endedAt));

        $message->delete();

        Message::createSystem(
            $call->channel_id,
            $call->initiator_id,
            'call_ended',
            [
                'actor_name' => $actorName,
                'call_id' => $call->call_id,
                'duration' => $duration,
                'answered' => true,
                'started_at' => $call->created_at->toIso8601String(),
                'ended_at' => $endedAt->toIso8601String(),
            ],
            "{$actorName} начал(а) звонок, который продлился ".$this->formatDuration($duration),
        );
    }

    private function markMissed(Call $call, Message $message, ?User $initiator, string $actorName): void
    {
        $meta = [
            'event' => 'call_missed',
            'actor_name' => $actorName,
            'call_id' => $call->call_id,
            'answered' => false,
        ];
        $text = "{$actorName} звонил(а) · никто не ответил";

        $message->update(['meta' => $meta, 'message' => $text]);

        if ($initiator) {
            broadcast(new MessageSent($initiator, $text, $call->channel_id, 'system', $meta));
        }
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds} сек";
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $rest > 0 ? "{$minutes} мин {$rest} сек" : "{$minutes} мин";
    }
}
