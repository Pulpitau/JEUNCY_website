<?php

namespace App\Http\Controllers;

use App\Services\VideoRoomService;
use Illuminate\Http\JsonResponse;

class PublicVideoRoomController extends Controller
{
    public function __construct(private readonly VideoRoomService $service) {}

    public function show(string $roomName): JsonResponse
    {
        $room = $this->service->findPublicByRoomName($roomName);

        // Ne renvoie jamais les FK host_id/participant_id ni les comptes lies :
        // le lien est cense etre le seul controle d'acces (voir CLAUDE.md section 7).
        return response()->json([
            'jitsi_room_name' => $room->jitsi_room_name,
            'status' => $room->status,
            'scheduled_at' => $room->scheduled_at,
        ]);
    }
}
