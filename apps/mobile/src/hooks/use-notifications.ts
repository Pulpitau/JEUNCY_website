import { NotificationType } from '@jeuncy/shared';
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

// Traduit une notification du SITE en ecran de l'application.
//
// LE TYPE PRIME SUR LE LIEN, et c'est necessaire : cote serveur, un match et
// une nouvelle candidature pointent tous les deux vers /mes-offres, qui est
// la page de gestion du site. Dans l'application, un match a son propre
// onglet — y envoyer l'employeur lui montre la carte du candidat et l'etat
// du dossier, la ou « Mes offres » ne lui montrerait que ses annonces.
//
// Un lien sans ecran correspondant renvoie null : la notification s'affiche
// alors sans navigation, plutot que d'ouvrir un ecran vide.
export function hrefForNotification(notification: Notification): Href | null {
  const { type, link } = notification;

  // Les deux faces du match menent au meme onglet, ou chaque partie lit ce
  // qui la concerne et ce qu'elle a a faire.
  if (type === NotificationType.NEW_MATCH || type === NotificationType.MATCH_CLOSED) {
    return '/matchs';
  }

  if (!link) return null;

  const offre = /^\/offres\/(\d+)$/.exec(link);
  if (offre) return { pathname: '/offres/[id]', params: { id: offre[1] } };

  if (link === '/mes-candidatures') return '/candidatures';
  if (link === '/mes-offres') return '/mes-offres';

  return null;
}
