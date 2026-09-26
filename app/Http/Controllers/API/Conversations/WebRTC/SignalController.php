<?php

namespace App\Http\Controllers\API\Conversations\WebRTC;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebRTC\SendSignalRequest;
use App\Models\Conversations\Call;
use App\Services\Conversations\SignalingService;
use Illuminate\Http\JsonResponse;

class SignalController extends Controller
{
    public function __construct(private readonly SignalingService $signaling) {}

    public function store(SendSignalRequest $request, Call $call): JsonResponse
    {
        $this->authorize('signal', $call);

        $this->signaling->relay($request->user(), $call, $request->signal(), $request->recipientId());

        return $this->successResponse('Сигнал отправлен', ['ok' => true]);
    }
}
