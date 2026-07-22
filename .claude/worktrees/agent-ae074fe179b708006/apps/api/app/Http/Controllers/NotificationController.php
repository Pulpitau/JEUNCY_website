<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->listForUser($request->user()));
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        return response()->json($this->service->markAsRead($request->user(), $notification));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->service->markAllAsRead($request->user());

        return response()->json(['marked' => true]);
    }
}
