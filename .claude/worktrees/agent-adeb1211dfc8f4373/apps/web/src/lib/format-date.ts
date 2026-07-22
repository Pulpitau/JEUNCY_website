export function formatMonthYear(date: string): string {
  return new Date(date).toLocaleDateString('fr-FR', {
    month: '2-digit',
    year: 'numeric',
  });
}
