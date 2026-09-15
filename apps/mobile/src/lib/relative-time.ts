// « il y a 5 min », « hier », « le 3 septembre » — pour dater une
// notification d'un coup d'oeil. Au-dela d'une semaine, la date complete
// est plus utile qu'un « il y a 12 jours ».
export function formatRelativeFr(iso: string, now: Date = new Date()): string {
  const date = new Date(iso);
  const seconds = Math.round((now.getTime() - date.getTime()) / 1000);

  if (seconds < 60) return "à l'instant";
  const minutes = Math.round(seconds / 60);
  if (minutes < 60) return `il y a ${minutes} min`;
  const hours = Math.round(minutes / 60);
  if (hours < 24) return `il y a ${hours} h`;
  const days = Math.round(hours / 24);
  if (days === 1) return 'hier';
  if (days < 7) return `il y a ${days} jours`;

  return new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'long' }).format(date);
}
