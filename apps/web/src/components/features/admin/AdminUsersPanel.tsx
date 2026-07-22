import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { UserRole } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { listAdminUsers, reactivateUser, suspendUser } from '@/lib/api/admin';
import { AdminPager } from './AdminPager';

const ROLE_OPTIONS = [
  { value: '', label: 'Tous les rôles' },
  { value: UserRole.CANDIDATE, label: 'Candidats' },
  { value: UserRole.COMPANY, label: 'Entreprises' },
  { value: UserRole.CFA, label: 'CFA' },
  { value: UserRole.ADMIN, label: 'Admins' },
];

export function AdminUsersPanel() {
  const queryClient = useQueryClient();
  const [role, setRole] = useState('');
  const [page, setPage] = useState(1);

  const usersQuery = useQuery({
    queryKey: ['admin', 'users', { role, page }],
    queryFn: () => listAdminUsers({ role: (role as UserRole) || undefined, page }),
  });

  function invalidate() {
    return queryClient.invalidateQueries({ queryKey: ['admin', 'users'] });
  }

  const suspendMutation = useMutation({ mutationFn: suspendUser, onSuccess: invalidate });
  const reactivateMutation = useMutation({
    mutationFn: reactivateUser,
    onSuccess: invalidate,
  });

  const users = usersQuery.data?.data ?? [];
  const lastPage = usersQuery.data?.last_page ?? 1;

  return (
    <div className="flex flex-col gap-4">
      <select
        className={cn(
          'flex h-10 w-full max-w-xs rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
        )}
        value={role}
        onChange={(event) => {
          setRole(event.target.value);
          setPage(1);
        }}
      >
        {ROLE_OPTIONS.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>

      {usersQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : usersQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les utilisateurs pour le moment, réessaie plus tard.
        </p>
      ) : users.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">Aucun utilisateur.</p>
      ) : (
        <div className="flex flex-col gap-3">
          {users.map((user) => (
            <div
              key={user.id}
              className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border p-4"
            >
              <div>
                <p className="font-poppins font-medium">{user.email}</p>
                <p className="text-xs text-muted-foreground">
                  {new Date(user.created_at).toLocaleDateString('fr-FR')}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Badge variant="outline">{user.role}</Badge>
                <Badge variant={user.is_suspended ? 'destructive' : 'default'}>
                  {user.is_suspended ? 'Suspendu' : 'Actif'}
                </Badge>
                {user.role !== UserRole.ADMIN &&
                  (user.is_suspended ? (
                    <Button
                      type="button"
                      size="sm"
                      variant="outline"
                      disabled={reactivateMutation.isPending}
                      onClick={() => reactivateMutation.mutate(user.id)}
                    >
                      Réactiver
                    </Button>
                  ) : (
                    <Button
                      type="button"
                      size="sm"
                      variant="ghost"
                      disabled={suspendMutation.isPending}
                      onClick={() => suspendMutation.mutate(user.id)}
                    >
                      Suspendre
                    </Button>
                  ))}
              </div>
            </div>
          ))}
        </div>
      )}

      <AdminPager page={page} lastPage={lastPage} onChange={setPage} />
    </div>
  );
}
