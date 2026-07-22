import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { VideoRoomStatus } from '@jeuncy/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import {
  listMyVideoRooms,
  createVideoRoom,
  startVideoRoom,
  endVideoRoom,
  type VideoRoomWithUsers,
} from '@/lib/api/video-rooms';
import { ApiError } from '@/lib/api/client';

const STATUS_LABELS: Record<string, string> = {
  [VideoRoomStatus.SCHEDULED]: 'Programmée',
  [VideoRoomStatus.LIVE]: 'En cours',
  [VideoRoomStatus.ENDED]: 'Terminée',
};

const createRoomSchema = z.object({
  participant_email: z
    .string()
    .email('Adresse email invalide.')
    .optional()
    .or(z.literal('')),
  scheduled_at: z.string().optional().or(z.literal('')),
});

type CreateRoomValues = z.infer<typeof createRoomSchema>;

const ROOMS_QUERY_KEY = ['video-rooms', 'mine'];

function inviteLink(room: VideoRoomWithUsers): string {
  return `${window.location.origin}/demo/${room.jitsi_room_name}`;
}

function VideoRoomItem({ room }: { room: VideoRoomWithUsers }) {
  const queryClient = useQueryClient();
  const [copied, setCopied] = useState(false);

  function invalidate() {
    return queryClient.invalidateQueries({ queryKey: ROOMS_QUERY_KEY });
  }

  const startMutation = useMutation({
    mutationFn: () => startVideoRoom(room.id),
    onSuccess: invalidate,
  });
  const endMutation = useMutation({
    mutationFn: () => endVideoRoom(room.id),
    onSuccess: invalidate,
  });

  async function handleCopy() {
    await navigator.clipboard.writeText(inviteLink(room));
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  return (
    <div className="flex flex-col gap-2 rounded-md border border-border p-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="font-poppins font-medium">
            {room.participant ? room.participant.email : 'Lien ouvert (prospect)'}
          </p>
          {room.scheduled_at && (
            <p className="text-xs text-muted-foreground">
              Programmée pour le {new Date(room.scheduled_at).toLocaleString('fr-FR')}
            </p>
          )}
        </div>
        <Badge
          variant={
            room.status === VideoRoomStatus.LIVE
              ? 'default'
              : room.status === VideoRoomStatus.ENDED
                ? 'secondary'
                : 'outline'
          }
        >
          {STATUS_LABELS[room.status]}
        </Badge>
      </div>

      <div className="flex flex-wrap gap-2">
        <Button type="button" variant="outline" size="sm" onClick={handleCopy}>
          {copied ? 'Lien copié !' : "Copier le lien d'invitation"}
        </Button>
        <Link to={`/demo/${room.jitsi_room_name}`} target="_blank" rel="noreferrer">
          <Button type="button" variant="ghost" size="sm">
            Rejoindre
          </Button>
        </Link>
        {room.status === VideoRoomStatus.SCHEDULED && (
          <Button
            type="button"
            variant="gradient"
            size="sm"
            onClick={() => startMutation.mutate()}
            disabled={startMutation.isPending}
          >
            Marquer comme démarrée
          </Button>
        )}
        {room.status !== VideoRoomStatus.ENDED && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => endMutation.mutate()}
            disabled={endMutation.isPending}
          >
            Terminer
          </Button>
        )}
      </div>
    </div>
  );
}

export function MyVideoRooms() {
  const queryClient = useQueryClient();
  const [formError, setFormError] = useState<string | null>(null);

  const roomsQuery = useQuery({ queryKey: ROOMS_QUERY_KEY, queryFn: listMyVideoRooms });

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<CreateRoomValues>({ resolver: zodResolver(createRoomSchema) });

  const createMutation = useMutation({
    mutationFn: (values: CreateRoomValues) =>
      createVideoRoom({
        participant_email: values.participant_email || undefined,
        scheduled_at: values.scheduled_at || undefined,
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ROOMS_QUERY_KEY });
      reset();
      setFormError(null);
    },
    onError: (error) => {
      setFormError(
        error instanceof ApiError
          ? error.message
          : 'Impossible de créer la salle pour le moment.',
      );
    },
  });

  const rooms = roomsQuery.data ?? [];

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Mes visioconférences</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Crée une salle de démo et partage le lien avec un candidat ou un prospect.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Nouvelle salle de démo</CardTitle>
          <CardDescription>
            L'email du candidat est facultatif — laisse-le vide pour un lien ouvert à
            partager avec un prospect.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form
            onSubmit={handleSubmit((values) => createMutation.mutate(values))}
            noValidate
            className="flex flex-col gap-4"
          >
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="flex flex-col gap-2">
                <Label htmlFor="participant-email">Email du candidat (facultatif)</Label>
                <Input
                  id="participant-email"
                  type="email"
                  aria-invalid={!!errors.participant_email}
                  {...register('participant_email')}
                />
                {errors.participant_email && (
                  <p role="alert" className="text-sm text-destructive">
                    {errors.participant_email.message}
                  </p>
                )}
              </div>
              <div className="flex flex-col gap-2">
                <Label htmlFor="scheduled-at">Programmer pour (facultatif)</Label>
                <Input
                  id="scheduled-at"
                  type="datetime-local"
                  {...register('scheduled_at')}
                />
              </div>
            </div>
            {formError && (
              <p role="alert" className="text-sm text-destructive">
                {formError}
              </p>
            )}
            <Button
              type="submit"
              variant="gradient"
              className="self-start"
              disabled={createMutation.isPending}
            >
              {createMutation.isPending ? 'Création…' : 'Créer la salle'}
            </Button>
          </form>
        </CardContent>
      </Card>

      {roomsQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : roomsQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger tes salles de visio pour le moment, réessaie plus tard.
        </p>
      ) : rooms.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Aucune salle créée pour l'instant.
        </p>
      ) : (
        <div className="flex flex-col gap-4">
          {rooms.map((room) => (
            <VideoRoomItem key={room.id} room={room} />
          ))}
        </div>
      )}
    </main>
  );
}
