export const UserRole = {
  CANDIDATE: 'CANDIDATE',
  COMPANY: 'COMPANY',
  CFA: 'CFA',
  ADMIN: 'ADMIN',
} as const;

export type UserRole = (typeof UserRole)[keyof typeof UserRole];
