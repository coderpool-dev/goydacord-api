<?php

namespace App\Data;

/** Результат создания беседы. */
final readonly class ChannelCreation
{
    public function __construct(
        public array $payload,
        // false — личный чат с этим человеком уже был, участники просто вернулись в него.
        public bool $created,
    ) {}
}
