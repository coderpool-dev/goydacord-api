<?php

namespace App\Http\Controllers\API\Conversations\WebRTC;

use App\Http\Controllers\Controller;
use App\Http\Requests\WebRTC\StoreCallDiagnosticsRequest;
use App\Models\Conversations\Call;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/** Клиентская диагностика звонка — в отдельный лог calls для разбора проблем со связью. */
class CallDiagnosticsController extends Controller
{
    public function __invoke(StoreCallDiagnosticsRequest $request, Call $call): Response
    {
        // Кто прислал и когда — берём с сервера, клиент подделать это не может.
        Log::channel('calls')->info('client_diagnostics', [
            'schema' => 1,
            'received_at' => now()->toIso8601String(),
            'call_id' => $call->call_id,
            'channel_id' => $call->channel_id,
            'user_id' => $request->user()->id,
            'login' => $request->user()->login,
            'client_agent' => mb_substr($request->userAgent() ?? '', 0, 250),
            ...$request->validated(),
        ]);

        return response()->noContent();
    }
}
