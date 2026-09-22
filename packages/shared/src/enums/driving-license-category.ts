export const DrivingLicenseCategory = {
  AM: 'AM',
  A1: 'A1',
  A2: 'A2',
  A: 'A',
  B1: 'B1',
  B: 'B',
  BE: 'BE',
  C: 'C',
  D: 'D',
} as const;

export type DrivingLicenseCategory =
  (typeof DrivingLicenseCategory)[keyof typeof DrivingLicenseCategory];
