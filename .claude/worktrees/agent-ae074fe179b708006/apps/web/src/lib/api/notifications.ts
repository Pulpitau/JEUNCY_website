import type { NotificationType } from '@jeuncy/shared';
import { apiRequest } from './client';

export interface Notification {
  id: number;
  user_id: number;
  type: NotificationType;
  message: string;
  link: string | null;
  read: boolean;
  created_at: string;
}

export function listNotifications() {
  return apiRequest<Notification[]>('/notifications');
}

export function markNotificationRead(id: number) {
  return apiRequest<Notification>(`/notifications/${id}/read`, { method: 'POST' });
}

export function markAllNotificationsRead() {
  return apiRequest<{ marked: boolean }>('/notifications/read-all', { method: 'POST' });
}
