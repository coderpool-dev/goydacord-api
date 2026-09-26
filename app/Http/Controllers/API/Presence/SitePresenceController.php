<?php

namespace App\Http\Controllers\API\Presence;

use App\Http\Controllers\Controller;
use App\Http\Requests\Presence\PingSitePresenceRequest;
use App\Services\Presence\GeoIpService;
use App\Services\Presence\SitePresenceService;
use Illuminate\Http\JsonResponse;

/** Пинг с открытой вкладки сайта — для счётчика посетителей в админке. Гости тоже считаются. */
class SitePresenceController extends Controller
{
    public function __construct(
        private readonly SitePresenceService $presence,
        private readonly GeoIpService $geoIp,
    ) {}

    public function ping(PingSitePresenceRequest $request): JsonResponse
    {
        $this->presence->ping(
            // Роут публичный: пользователя определяем по токену, только если он передан.
            $request->user('sanctum'),
            $request->validated('session_key'),
            $request->validated('path') ?? '/',
            $request->validated('referrer'),
            $this->geoIp->countryFromRequest($request),
        );

        return $this->successResponse('Присутствие обновлено');
    }
}
