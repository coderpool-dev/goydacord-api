<?php

use App\Broadcasting\ConversationChannel;
use App\Broadcasting\ServerChannelBroadcastChannel;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('channel.{channelId}', ConversationChannel::class);
Broadcast::channel('messages.{channelId}', ConversationChannel::class);
Broadcast::channel('server-messages.{serverChannelId}', ServerChannelBroadcastChannel::class);
Broadcast::channel('server-voice.{serverChannelId}', ServerChannelBroadcastChannel::class);

// Личный канал пользователя для WebRTC-сигналов.
Broadcast::channel('webrtc.{userId}', fn (User $user, int|string $userId) => (int) $user->id === (int) $userId);

// Новые иконки игр видят все вошедшие пользователи.
Broadcast::channel('game-icons', fn (User $user) => ['id' => $user->id]);
