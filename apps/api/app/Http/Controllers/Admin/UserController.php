<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListUsersRequest;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(ListUsersRequest $request): JsonResponse
    {
        return response()->json($this->service->listUsers($request->validated()));
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        return response()->json($this->service->suspendUser($request->user(), $user));
    }

    public function promoteToStaff(Request $request, User $user): JsonResponse
    {
        return response()->json($this->service->setStaffRole($request->user(), $user, true));
    }

    public function demoteFromStaff(Request $request, User $user): JsonResponse
    {
        return response()->json($this->service->setStaffRole($request->user(), $user, false));
    }

    public function reactivate(User $user): JsonResponse
    {
        return response()->json($this->service->reactivateUser($user));
    }
}
