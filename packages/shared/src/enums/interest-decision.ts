export const InterestDecision = {
  LIKE: 'LIKE',
  PASS: 'PASS',
} as const;

export type InterestDecision = (typeof InterestDecision)[keyof typeof InterestDecision];
