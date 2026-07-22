import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { VideoRoomStatus } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { endVideoRoomAsAdmin, listAdminVideoRooms } from '@/lib/api/admin';
import { AdminPager } from './AdminPager';

const STATUS_OPTIONS = [
  { value: '', label: 'Tous les statuts' },
  { value: VideoRoomStatus.SCHEDULED, label: 'Programmée' },
  { value: VideoRoomStatus.LIVE, label: 'En cours' },
  { value: VideoRoomStatus.ENDED, label: 'Terminée' },
];

export function AdminVideoRoomsPanel() {
  const queryClient = useQueryClient();
  const [status, setStatus] = useState('');
  const [page, setPage] = useState(1);

  const roomsQuery = useQuery({
    queryKey: ['admin', 'video-rooms', { status, page }],
    queryFn: () =>
      listAdminVideoRooms({ status: (status as VideoRoomStatus) || undefined, page }),
  });

  const endMutation = useMutation({
    mutationFn: endVideoRoomAsAdmin,
    onSuccess: () =>
      queryClient.invalidateQueries({ queryKey: ['admin', 'video-rooms'] }),
  });

  const rooms = roomsQuery.data?.data ?? [];
  const lastPage = roomsQuery.data?.last_page ?? 1;

  return (
    <div className="flex flex-col gap-4">
      <select
        className={cn(
          'flex h-10 w-full max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        )}
        value={status}
        onChange={(event) => {
          setStatus(event.target.value);
          setPage(1);
        }}
      >
        {STATUS_OPTIONS.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>

      {roomsQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : roomsQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les salles de visio pour le moment, réessaie plus tard.
        </p>
      ) : rooms.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">Aucune salle.</p>
      ) : (
        <div className="flex flex-col gap-3">
          {rooms.map((room) => (
            <div
              key={room.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border p-4"
            >
              <div>
                <p className="font-poppins font-medium">{room.host.email}</p>
                <p className="text-xs text-muted-foreground">
                  {room.participant
                    ? `avec ${room.participant.email}`
                    : 'Lien ouvert (prospect)'}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge
                  variant={room.status === VideoRoomStatus.LIVE ? 'default' : 'outline'}
                >
                  {room.status}
                </Badge>
                {room.status !== VideoRoomStatus.ENDED && (
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    disabled={endMutation.isPending}
                    onClick={() => endMutation.mutate(room.id)}
                  >
                    Terminer
                  </Button>
                )}
              </div>
            </div>
          ))}
        </div>
      )}

      <AdminPager page={page} lastPage={lastPage} onChange={setPage} />
    </div>
  );
}
