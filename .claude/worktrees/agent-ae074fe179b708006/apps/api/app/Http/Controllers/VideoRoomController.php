<?php

namespace App\Http\Controllers;

use App\Http\Requests\VideoRoom\StoreVideoRoomRequest;
use App\Models\VideoRoom;
use App\Services\VideoRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoRoomController extends Controller
{
    public function __construct(private readonly VideoRoomService $service) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->listForUser($request->user()));
    }

    public function store(StoreVideoRoomRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $room = $this->service->createForUser(
            $request->user(),
            $validated['participant_email'] ?? null,
            $validated['scheduled_at'] ?? null,
        );

        return response()->json($room, 201);
    }

    public function start(Request $request, VideoRoom $videoRoom): JsonResponse
    {
        return response()->json($this->service->markStarted($request->user(), $videoRoom));
    }

    public function end(Request $request, VideoRoom $videoRoom): JsonResponse
    {
        return response()->json($this->service->markEnded($request->user(), $videoRoom));
    }
}
