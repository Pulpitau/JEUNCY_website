export const WorkMode = {
  PRESENTIEL: 'PRESENTIEL',
  HYBRIDE: 'HYBRIDE',
  DISTANCIEL: 'DISTANCIEL',
} as const;

export type WorkMode = (typeof WorkMode)[keyof typeof WorkMode];
