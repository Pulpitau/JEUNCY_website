export const NotificationType = {
  NEW_APPLICATION: 'NEW_APPLICATION',
  APPLICATION_STATUS_CHANGED: 'APPLICATION_STATUS_CHANGED',
  PAYMENT_SUCCEEDED: 'PAYMENT_SUCCEEDED',
  VIDEO_ROOM_INVITE: 'VIDEO_ROOM_INVITE',
  JOB_OFFER_EXPIRING: 'JOB_OFFER_EXPIRING',
} as const;

export type NotificationType = (typeof NotificationType)[keyof typeof NotificationType];
