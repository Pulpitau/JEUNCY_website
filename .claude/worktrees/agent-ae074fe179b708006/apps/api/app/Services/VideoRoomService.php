<?php

namespace App\Services;

use App\Enums\VideoRoomStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\VideoRoom;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class VideoRoomService
{
    public function createForUser(User $host, ?string $participantEmail, ?string $scheduledAt): VideoRoom
    {
        $participant = null;
        if ($participantEmail) {
            $participant = User::where('email', $participantEmail)->first();
            if (! $participant) {
                throw new ApiException('PARTICIPANT_NOT_FOUND', 'Aucun compte ne correspond à cet email.', 404);
            }
        }

        return VideoRoom::create([
            'host_id' => $host->id,
            'participant_id' => $participant?->id,
            // Non devinable (voir CLAUDE.md section 7) : c'est cet identifiant, pas
            // la cle primaire, qui sert de controle d'acces au lien d'invitation.
            'jitsi_room_name' => (string) Str::uuid(),
            'status' => VideoRoomStatus::SCHEDULED,
            'scheduled_at' => $scheduledAt,
        ]);
    }

    public function listForUser(User $user): Collection
    {
        return VideoRoom::where('host_id', $user->id)
            ->orWhere('participant_id', $user->id)
            ->with(['host', 'participant'])
            ->latest('created_at')
            ->get();
    }

    // Consultation via le lien d'invitation : aucune authentification, la salle
    // n'est accessible qu'a qui possede l'UUID (voir CLAUDE.md section 7).
    public function findPublicByRoomName(string $roomName): VideoRoom
    {
        $room = VideoRoom::where('jitsi_room_name', $roomName)->first();
        if (! $room) {
            throw new ApiException('VIDEO_ROOM_NOT_FOUND', "Cette salle n'existe pas.", 404);
        }

        return $room;
    }

    public function markStarted(User $user, VideoRoom $room): VideoRoom
    {
        $this->requireHost($user, $room);

        if ($room->status === VideoRoomStatus::SCHEDULED) {
            $room->update(['status' => VideoRoomStatus::LIVE, 'started_at' => now()]);
        }

        return $room;
    }

    public function markEnded(User $user, VideoRoom $room): VideoRoom
    {
        $this->requireHost($user, $room);
        $room->update(['status' => VideoRoomStatus::ENDED, 'ended_at' => now()]);

        return $room;
    }

    private function requireHost(User $user, VideoRoom $room): void
    {
        if ($room->host_id !== $user->id) {
            throw new ApiException('FORBIDDEN', "Cette salle ne t'appartient pas.", 403);
        }
    }
}
