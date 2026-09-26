<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminActivityService;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    public function __construct(private readonly AdminActivityService $activity) {}

    public function show(): JsonResponse
    {
        return $this->successResponse('Активность', [
            'activity' => $this->activity->snapshot(),
        ]);
    }
}
