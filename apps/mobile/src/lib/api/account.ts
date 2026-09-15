import { apiRequest } from './client';

/** Toutes les donnees du compte, au format JSON (RGPD art. 20, portabilite). */
export function exportAccountData() {
  return apiRequest<Record<string, unknown>>('/account/export');
}

// Suppression du compte (RGPD art. 17, et exigence Apple 5.1.1(v)). L'email
// est redemande en confirmation. Un compte ayant paye est anonymise plutot
// que supprime : obligation legale de conservation des pieces comptables.
export function deleteAccount(confirmEmail: string) {
  return apiRequest<{ deleted: boolean }>('/account', {
    method: 'DELETE',
    body: { confirm_email: confirmEmail },
  });
}
