<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function __construct(private readonly AdminStatsService $stats) {}

    public function show(Request $request): JsonResponse
    {
        return $this->successResponse('Статистика', [
            'stats' => $this->stats->snapshot($request->integer('days', 14)),
        ]);
    }
}
