import { useQuery, useQueryClient } from '@tanstack/react-query';
import type { Href } from 'expo-router';

import { listNotifications, type Notification } from '@/lib/api/notifications';

export const NOTIFICATIONS_KEY = ['notifications'] as const;

// Notifications du compte connecte. Rafraichies toutes les 30 secondes
// quand l'application est au premier plan, comme la cloche du site : en
// attendant le push (phase 2), c'est ce qui rapproche le plus d'un temps
// reel sans coup de fil au serveur a chaque geste.
export function useNotifications(enabled = true) {
  return useQuery({
    queryKey: NOTIFICATIONS_KEY,
    queryFn: listNotifications,
    enabled,
    refetchInterval: 30_000,
    refetchIntervalInBackground: false,
  });
}

export function useInvalidateNotifications() {
  const queryClient = useQueryClient();

  return () => queryClient.invalidateQueries({ queryKey: NOTIFICATIONS_KEY });
}

export function unreadCount(notifications: Notification[] | undefined): number {
  return notifications?.filter((n) => !n.read).length ?? 0;
}

// Traduit le lien du SITE porte par une notification en ecran de l'app.
// Les liens des espaces entreprise / CFA (/mes-offres, /mes-paiements)
// n'ont pas encore d'ecran : ils renvoient null, et la notification
// s'affiche sans navigation plutot que d'ouvrir un ecran vide.
export function hrefForNotification(link: string | null): Href | null {
  if (!link) return null;

  const offre = /^\/offres\/(\d+)$/.exec(link);
  if (offre) return { pathname: '/offres/[id]', params: { id: offre[1] } };

  if (link === '/mes-candidatures') return '/candidatures';

  return null;
}
