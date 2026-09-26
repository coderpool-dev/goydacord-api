<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminUserListingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly AdminUserListingService $users) {}

    public function index(Request $request): JsonResponse
    {
        $search = $request->query('q');

        return $this->successResponse('Пользователи', $this->users->listUsers(is_string($search) ? $search : null));
    }
}
