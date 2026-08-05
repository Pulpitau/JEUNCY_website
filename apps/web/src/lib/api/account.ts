import { apiRequest } from './client';

export function exportAccountData() {
  return apiRequest<Record<string, unknown>>('/account/export');
}

export function deleteAccount(confirmEmail: string) {
  return apiRequest<{ deleted: boolean }>('/account', {
    method: 'DELETE',
    body: { confirm_email: confirmEmail },
  });
}
