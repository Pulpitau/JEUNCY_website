<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListVideoRoomsRequest;
use App\Models\VideoRoom;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;

class VideoRoomController extends Controller
{
    public function __construct(private readonly AdminService $service) {}

    public function index(ListVideoRoomsRequest $request): JsonResponse
    {
        return response()->json($this->service->listVideoRooms($request->validated()));
    }

    public function end(VideoRoom $videoRoom): JsonResponse
    {
        return response()->json($this->service->forceEndVideoRoom($videoRoom));
    }
}
