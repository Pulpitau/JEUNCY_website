import type { VideoRoomStatus } from '@jeuncy/shared';
import { apiRequest } from './client';

export interface VideoRoom {
  id: number;
  host_id: number;
  participant_id: number | null;
  jitsi_room_name: string;
  status: VideoRoomStatus;
  scheduled_at: string | null;
  started_at: string | null;
  ended_at: string | null;
  created_at: string;
}

interface UserSummary {
  id: number;
  email: string;
}

export interface VideoRoomWithUsers extends VideoRoom {
  host: UserSummary;
  participant: UserSummary | null;
}

export interface PublicVideoRoom {
  jitsi_room_name: string;
  status: VideoRoomStatus;
  scheduled_at: string | null;
}

export interface VideoRoomInput {
  participant_email?: string;
  scheduled_at?: string;
}

export function listMyVideoRooms() {
  return apiRequest<VideoRoomWithUsers[]>('/video-rooms');
}

export function createVideoRoom(input: VideoRoomInput) {
  return apiRequest<VideoRoom>('/video-rooms', { method: 'POST', body: input });
}

export function startVideoRoom(id: number) {
  return apiRequest<VideoRoom>(`/video-rooms/${id}/start`, { method: 'POST' });
}

export function endVideoRoom(id: number) {
  return apiRequest<VideoRoom>(`/video-rooms/${id}/end`, { method: 'POST' });
}

export function getPublicVideoRoom(roomName: string) {
  return apiRequest<PublicVideoRoom>(`/video-rooms/room/${roomName}`);
}
