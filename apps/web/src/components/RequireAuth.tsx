import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import type { UserRole } from '@jeuncy/shared';
import { useAuthStore } from '@/store/auth-store';

interface RequireAuthProps {
  children: ReactNode;
  role?: UserRole;
}

export function RequireAuth({ children, role }: RequireAuthProps) {
  const user = useAuthStore((state) => state.user);
  const isLoading = useAuthStore((state) => state.isLoading);

  if (isLoading) {
    return (
      <div className="flex min-h-[calc(100vh-4rem)] items-center justify-center">
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      </div>
    );
  }

  if (!user) {
    return <Navigate to="/login" replace />;
  }

  if (role && user.role !== role) {
    return <Navigate to="/" replace />;
  }

  return children;
}
