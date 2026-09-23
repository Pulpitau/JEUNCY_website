import { apiRequest } from './client';

// Gestes du candidat sur les offres partenaires (La bonne alternance).
//
// « Je garde » et « Passer », jamais « Ca m'interesse » : aucun match n'est
// possible sur une offre dont la candidature se fait ailleurs, et le mot ne
// doit pas apparaitre (MOBILE.md §3.2). L'enum du serveur n'a d'ailleurs pas
// de LIKE.
//
// La ligne est DENORMALISEE cote serveur (titre, employeur, ville, lien) :
// `LbaImportService` supprime chaque nuit les offres absentes de l'export,
// et une offre gardee doit survivre a sa disparition. C'est pour ca que la
// liste des offres gardees se lit ici et pas en rechargeant les offres.

export type ExternalDecision = 'KEEP' | 'PASS';

export interface ExternalInterest {
  id: number;
  /** Null une fois l'offre d'origine disparue de l'export. */
  external_job_offer_id: number | null;
  decision: ExternalDecision;
  company_name: string | null;
  title: string | null;
  city: string | null;
  apply_url: string;
  decided_at: string;
  /** Pose par « C'est fait » : le candidat a postule sur le site d'origine. */
  done_at: string | null;
}

export function decideExternalOffer(
  externalJobOfferId: number,
  decision: ExternalDecision,
) {
  return apiRequest<ExternalInterest>('/external-interests', {
    method: 'POST',
    body: { external_job_offer_id: externalJobOfferId, decision },
  });
}

/** Les offres gardees, plus recentes en premier. */
export function listKeptOffers() {
  return apiRequest<ExternalInterest[]>('/external-interests');
}

/**
 * « C'est fait » : le candidat dit avoir postule sur le site d'origine.
 *
 * Jeuncy n'en sait rien et ne peut pas le savoir — la candidature part chez
 * l'employeur. C'est une note que le candidat se laisse a lui-meme, et
 * l'ecran doit le dire ainsi plutot que de faire croire a un suivi.
 */
export function markExternalDone(id: number) {
  return apiRequest<ExternalInterest>(`/external-interests/${id}/done`, {
    method: 'PATCH',
  });
}

export function undoLastExternal() {
  return apiRequest<{ undone: { external_job_offer_id: number | null } }>(
    '/external-interests/last',
    { method: 'DELETE' },
  );
}
