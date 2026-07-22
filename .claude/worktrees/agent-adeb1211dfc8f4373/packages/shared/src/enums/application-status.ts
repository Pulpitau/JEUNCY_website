export const ApplicationStatus = {
  SENT: 'SENT',
  SEEN: 'SEEN',
  INTERVIEW: 'INTERVIEW',
  ACCEPTED: 'ACCEPTED',
  REJECTED: 'REJECTED',
} as const;

export type ApplicationStatus =
  (typeof ApplicationStatus)[keyof typeof ApplicationStatus];
