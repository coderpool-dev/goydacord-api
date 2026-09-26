<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Admin\AdminPrivilegeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdministratorController extends Controller
{
    public function __construct(private readonly AdminPrivilegeService $privileges) {}

    public function index(): JsonResponse
    {
        return $this->successResponse('Админы', [
            'admins' => UserResource::collection($this->privileges->admins()),
        ]);
    }

    public function store(StoreAdminRequest $request): JsonResponse
    {
        $user = $this->privileges->findByLogin($request->validated('login'));
        $granted = $this->privileges->grant($user);

        return $this->successResponse($granted ? 'Админ добавлен' : 'Уже админ', [
            'admin' => new UserResource($user->fresh()->load(['privileges', 'yandexMusicConnection'])),
        ], $granted ? 201 : 200);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->privileges->revoke($request->user(), $user);

        return $this->successResponse('Права админа сняты', ['user_id' => $user->id]);
    }
}
