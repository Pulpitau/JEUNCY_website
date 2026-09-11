// L'API echange des dates au format ISO « AAAA-MM-JJ », sans heure. Toutes les
// conversions passent par ici pour eviter le piege classique : construire un
// Date a minuit UTC puis l'afficher en heure locale fait reculer d'un jour
// tout candidat a l'ouest de Greenwich — et en avancer un a l'est, ce qui est
// le cas de la France en ete.

const MIDI = 12;

/** « 2004-03-12 » → Date locale a midi (immunisee contre le decalage horaire). */
export function fromIsoDate(iso: string): Date {
  const [year, month, day] = iso.slice(0, 10).split('-').map(Number);

  return new Date(year, month - 1, day, MIDI);
}

/** Date → « 2004-03-12 », d'apres l'heure locale. */
export function toIsoDate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

const LONG = new Intl.DateTimeFormat('fr-FR', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
});
const MONTH_YEAR = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' });
const SHORT = new Intl.DateTimeFormat('fr-FR', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
});

/** « 12 mars 2004 » */
export function formatDateFr(iso: string | null | undefined): string {
  return iso ? LONG.format(fromIsoDate(iso)) : '';
}

/** « mars 2004 » — pour les periodes d'experience et de formation. */
export function formatMonthYearFr(iso: string | null | undefined): string {
  return iso ? MONTH_YEAR.format(fromIsoDate(iso)) : '';
}

/** « 12/03/2004 » — pour les champs de saisie. */
export function formatDateShortFr(iso: string | null | undefined): string {
  return iso ? SHORT.format(fromIsoDate(iso)) : '';
}

/** « mars 2022 – aujourd'hui » ou « sept. 2020 – juin 2022 ». */
export function formatPeriodFr(startIso: string, endIso: string | null): string {
  const debut = formatMonthYearFr(startIso);

  return endIso ? `${debut} – ${formatMonthYearFr(endIso)}` : `${debut} – aujourd'hui`;
}

/** Il y a N annees, a la meme date : borne des selecteurs de date de naissance. */
export function yearsAgo(years: number): Date {
  const date = new Date();
  date.setFullYear(date.getFullYear() - years);

  return date;
}
