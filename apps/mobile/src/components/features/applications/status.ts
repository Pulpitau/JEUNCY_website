import { ApplicationStatus } from '@jeuncy/shared';

import type { BadgeProps } from '@/components/ui/badge';

// Couleur du badge de statut : le candidat lit d'un coup d'oeil ou en est
// chaque candidature, sans avoir a lire le mot.
export function statusTone(status: ApplicationStatus): NonNullable<BadgeProps['tone']> {
  switch (status) {
    case ApplicationStatus.ACCEPTED:
      return 'success';
    case ApplicationStatus.INTERVIEW:
      return 'warm';
    case ApplicationStatus.REJECTED:
      return 'danger';
    case ApplicationStatus.SEEN:
      return 'accent';
    case ApplicationStatus.SENT:
    default:
      return 'neutral';
  }
}
