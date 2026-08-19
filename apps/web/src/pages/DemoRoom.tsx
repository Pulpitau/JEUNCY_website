import { useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { VideoRoomStatus } from '@jeuncy/shared';
import { getPublicVideoRoom } from '@/lib/api/video-rooms';
import { ApiError } from '@/lib/api/client';
import { Button } from '@/components/ui/button';
import { JitsiRoom } from '@/components/features/video-rooms/JitsiRoom';

export function DemoRoom() {
  const { roomId } = useParams<{ roomId: string }>();
  const [hasConsented, setHasConsented] = useState(false);

  const roomQuery = useQuery({
    queryKey: ['video-rooms', 'public', roomId],
    queryFn: () => getPublicVideoRoom(roomId!),
    retry: false,
    enabled: !!roomId,
  });

  if (roomQuery.isLoading) {
    return (
      <main className="mx-auto max-w-4xl px-4 py-12">
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      </main>
    );
  }

  if (roomQuery.isError) {
    const message =
      roomQuery.error instanceof ApiError
        ? roomQuery.error.message
        : 'Cette salle est introuvable.';

    // Un lien perime et un lien errone n'appellent pas la meme reaction : le
    // premier se resout en redemandant un lien, le second en verifiant ce
    // qu'on a colle. Afficher "Salle introuvable" dans les deux cas laissait
    // un invite persuade de s'etre trompe alors que son lien avait simplement
    // vieilli (voir VideoRoomService::findPublicByRoomName, 410).
    const isExpired =
      roomQuery.error instanceof ApiError &&
      roomQuery.error.code === 'VIDEO_ROOM_EXPIRED';

    return (
      <main className="mx-auto max-w-4xl px-4 py-12 text-center">
        <h1 className="font-poppins text-2xl font-bold">
          {isExpired ? 'Lien expiré' : 'Salle introuvable'}
        </h1>
        <p className="mt-2 font-inter text-muted-foreground">{message}</p>
      </main>
    );
  }

  const room = roomQuery.data!;

  if (room.status === VideoRoomStatus.ENDED) {
    return (
      <main className="mx-auto max-w-4xl px-4 py-12 text-center">
        <h1 className="font-poppins text-2xl font-bold">Session terminée</h1>
        <p className="mt-2 font-inter text-muted-foreground">
          Cette session de démonstration Jeuncy est déjà terminée.
        </p>
      </main>
    );
  }

  if (!hasConsented) {
    return (
      <main className="mx-auto max-w-lg px-4 py-16">
        <h1 className="font-poppins text-2xl font-bold">Démo Jeuncy</h1>
        <div className="mt-4 rounded-md border border-border p-4 font-inter text-sm text-muted-foreground">
          <p>
            En rejoignant cette visioconférence, ta caméra et ton micro te seront
            demandés. Le flux audio/vidéo transite par l'infrastructure publique Jitsi
            (meet.jit.si) et n'est ni enregistré ni stocké par Jeuncy.
          </p>
          <p className="mt-3">
            Plus de détails dans notre{' '}
            <Link to="/confidentialite" className="text-primary hover:underline">
              politique de confidentialité
            </Link>
            .
          </p>
        </div>
        <Button
          type="button"
          variant="gradient"
          className="mt-6"
          onClick={() => setHasConsented(true)}
        >
          J'accepte et je rejoins la visio
        </Button>
      </main>
    );
  }

  return (
    <main className="mx-auto max-w-4xl px-4 py-8">
      <div className="mb-4">
        <h1 className="font-poppins text-2xl font-bold">Démo Jeuncy</h1>
        <p className="font-inter text-sm text-muted-foreground">
          Ta caméra et ton micro te seront demandés avant de rejoindre.
        </p>
      </div>
      <JitsiRoom roomName={room.jitsi_room_name} />
    </main>
  );
}
