export const ApplicationSource = {
  SITE: 'SITE',
  APP: 'APP',
  MATCH: 'MATCH',
} as const;

export type ApplicationSource =
  (typeof ApplicationSource)[keyof typeof ApplicationSource];
