<?php

namespace App\Http\Controllers\API\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportRequest;
use App\Services\Presence\GeoIpService;
use App\Services\Support\UserReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        private readonly UserReportService $reports,
        private readonly GeoIpService $geoIp,
    ) {}

    public function store(StoreReportRequest $request): JsonResponse
    {
        $this->reports->submit(
            $request->user(),
            (int) $request->validated('target_id'),
            $request->validated('reason'),
            $request->validated('comment'),
            $this->geoIp->resolveClientIp($request),
        );

        return $this->successResponse('Жалоба отправлена', [], 201);
    }
}
