<?php

namespace App\Http\Controllers\API\Integrations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Games\LookupGameIconRequest;
use App\Http\Requests\Games\SubmitGameIconRequest;
use App\Services\Integrations\GameIconService;
use Illuminate\Http\JsonResponse;

class GameIconController extends Controller
{
    public function __construct(private readonly GameIconService $icons) {}

    public function show(LookupGameIconRequest $request): JsonResponse
    {
        $payload = $this->icons->lookup($request->string('name')->toString());

        return $this->successResponse($payload['exists'] ? 'Иконка найдена' : 'Иконка не найдена', $payload);
    }

    /** Десктоп-клиент присылает приватную заявку на модерацию. */
    public function submitForReview(SubmitGameIconRequest $request): JsonResponse
    {
        $payload = $this->icons->submitForReview(
            $request->user(),
            $request->string('name')->toString(),
            $request->file('icon'),
            $request->input('source'),
        );

        return $this->successResponse(
            $payload['pending'] ? 'Иконка отправлена на модерацию' : 'Иконка уже опубликована',
            $payload,
            $payload['pending'] ? 202 : 200,
        );
    }
}
