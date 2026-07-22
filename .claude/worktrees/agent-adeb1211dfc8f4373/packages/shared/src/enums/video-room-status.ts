export const VideoRoomStatus = {
  SCHEDULED: 'SCHEDULED',
  LIVE: 'LIVE',
  ENDED: 'ENDED',
} as const;

export type VideoRoomStatus = (typeof VideoRoomStatus)[keyof typeof VideoRoomStatus];
