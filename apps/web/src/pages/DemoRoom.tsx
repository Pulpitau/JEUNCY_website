import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { VideoRoomStatus } from '@jeuncy/shared';
import { getPublicVideoRoom } from '@/lib/api/video-rooms';
import { ApiError } from '@/lib/api/client';
import { JitsiRoom } from '@/components/features/video-rooms/JitsiRoom';

export function DemoRoom() {
  const { roomId } = useParams<{ roomId: string }>();

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

    return (
      <main className="mx-auto max-w-4xl px-4 py-12 text-center">
        <h1 className="font-poppins text-2xl font-bold">Salle introuvable</h1>
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
