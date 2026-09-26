<?php

namespace App\Http\Controllers\API\Conversations;

use App\Http\Controllers\Controller;
use App\Models\Conversations\Call;
use App\Models\Conversations\Channel;
use App\Services\Conversations\LiveKitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Звонки в ЛС/беседах через LiveKit. Клиент заранее узнаёт, включён ли LiveKit (status), чтобы
 * выбрать транспорт ещё до звонка: оба собеседника должны быть в одном режиме, иначе P2P-сторона
 * и SFU-сторона друг друга не услышат.
 */
class LiveKitController extends Controller
{
    public function __construct(private readonly LiveKitService $liveKit) {}

    public function status(): JsonResponse
    {
        return $this->successResponse('Статус LiveKit', ['enabled' => $this->liveKit->isEnabled()]);
    }

    /** Токен комнаты текущего звонка в этом чате. */
    public function callToken(Request $request, Channel $channel): JsonResponse
    {
        $this->authorize('call', $channel);

        $call = Call::activeIn((int) $channel->id);
        abort_if($call === null, 404, 'Звонок уже завершён');

        return $this->successResponse('Токен комнаты звонка', [
            'livekit' => $this->liveKit->connectionForCall($request->user(), $call),
            'call_id' => $call->call_id,
        ]);
    }
}
