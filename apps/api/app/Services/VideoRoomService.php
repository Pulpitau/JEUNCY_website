<?php

namespace App\Services;

use App\Enums\VideoRoomStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\VideoRoom;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class VideoRoomService
{
    // Marge apres l'heure prevue d'une visio datee : couvre un retard, un
    // depassement ou un report de derniere minute, sans laisser le lien
    // ouvert les jours suivants.
    public const LINK_GRACE_HOURS = 24;

    // Visio sans date : le lien est envoye "pour quand tu veux", il lui faut
    // une fenetre plus large — mais bornee.
    public const LINK_DEFAULT_DAYS = 7;

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
            'expires_at' => $this->expiryFor($scheduledAt),
        ]);
    }

    // Duree de vie du lien d'invitation. Le nom de salle est le seul controle
    // d'acces de la page publique (pas d'authentification), donc un lien sans
    // echeance transfere ou retrouve dans un historique donne un acces
    // permanent.
    //
    // Deux cas :
    // - visio datee : LINK_GRACE_HOURS apres l'heure prevue, de quoi couvrir un
    //   report de derniere minute ou un depassement, sans laisser le lien
    //   ouvert la semaine suivante.
    // - visio sans date (lien envoye "quand tu veux") : LINK_DEFAULT_DAYS.
    private function expiryFor(?string $scheduledAt): Carbon
    {
        return $scheduledAt !== null
            ? Carbon::parse($scheduledAt)->addHours(self::LINK_GRACE_HOURS)
            : now()->addDays(self::LINK_DEFAULT_DAYS);
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

        // expires_at nul = salle creee avant l'introduction de l'echeance :
        // comportement d'origine conserve, on n'invalide pas retroactivement
        // des liens deja distribues.
        //
        // 410 Gone et non 404 : le lien a existe, il est perime. Le frontend
        // peut ainsi distinguer "cette salle n'existe pas" (lien errone) de
        // "ce lien a expire" (demander un nouveau lien a l'hote), deux
        // situations qui n'appellent pas le meme message.
        if ($room->expires_at !== null && $room->expires_at->isPast()) {
            throw new ApiException(
                'VIDEO_ROOM_EXPIRED',
                "Ce lien d'invitation a expiré. Demande un nouveau lien à ton interlocuteur.",
                410,
            );
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
