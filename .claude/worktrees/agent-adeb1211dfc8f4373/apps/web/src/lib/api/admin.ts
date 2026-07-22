import type {
  JobOfferStatus,
  PaymentStatus,
  UserRole,
  VideoRoomStatus,
} from '@jeuncy/shared';
import { apiRequest } from './client';
import type { Paginated } from './job-offers';

export interface AdminStats {
  users: {
    total: number;
    candidates: number;
    companies: number;
    cfa_organizations: number;
    suspended: number;
  };
  job_offers: {
    total: number;
    published: number;
    draft: number;
    expired: number;
    archived: number;
  };
  applications: { total: number };
  payments: { succeeded_count: number; revenue_cents: number };
  video_rooms: { total: number; live: number };
}

export interface AdminUser {
  id: number;
  email: string;
  role: UserRole;
  is_suspended: boolean;
  created_at: string;
}

interface OrgSummary {
  id: number;
  name: string;
}

export interface AdminJobOffer {
  id: number;
  title: string;
  status: JobOfferStatus;
  city: string | null;
  created_at: string;
  company: OrgSummary | null;
  cfa_organization: OrgSummary | null;
}

interface UserSummary {
  id: number;
  email: string;
}

export interface AdminPayment {
  id: number;
  amount_cents: number;
  currency: string;
  status: PaymentStatus;
  created_at: string;
  user: UserSummary;
  job_offer: { id: number; title: string };
}

export interface AdminVideoRoom {
  id: number;
  jitsi_room_name: string;
  status: VideoRoomStatus;
  scheduled_at: string | null;
  created_at: string;
  host: UserSummary;
  participant: UserSummary | null;
}

export function fetchAdminStats() {
  return apiRequest<AdminStats>('/admin/stats');
}

export function listAdminUsers(filters: { role?: UserRole; page?: number }) {
  const params = new URLSearchParams();
  if (filters.role) params.set('role', filters.role);
  if (filters.page) params.set('page', String(filters.page));
  const query = params.toString();

  return apiRequest<Paginated<AdminUser>>(`/admin/users${query ? `?${query}` : ''}`);
}

export function suspendUser(id: number) {
  return apiRequest<AdminUser>(`/admin/users/${id}/suspend`, { method: 'POST' });
}

export function reactivateUser(id: number) {
  return apiRequest<AdminUser>(`/admin/users/${id}/reactivate`, { method: 'POST' });
}

export function listAdminJobOffers(filters: { status?: JobOfferStatus; page?: number }) {
  const params = new URLSearchParams();
  if (filters.status) params.set('status', filters.status);
  if (filters.page) params.set('page', String(filters.page));
  const query = params.toString();

  return apiRequest<Paginated<AdminJobOffer>>(
    `/admin/job-offers${query ? `?${query}` : ''}`,
  );
}

export function archiveJobOfferAsAdmin(id: number) {
  return apiRequest<AdminJobOffer>(`/admin/job-offers/${id}/archive`, { method: 'POST' });
}

export function listAdminPayments(filters: { status?: PaymentStatus; page?: number }) {
  const params = new URLSearchParams();
  if (filters.status) params.set('status', filters.status);
  if (filters.page) params.set('page', String(filters.page));
  const query = params.toString();

  return apiRequest<Paginated<AdminPayment>>(
    `/admin/payments${query ? `?${query}` : ''}`,
  );
}

export function listAdminVideoRooms(filters: {
  status?: VideoRoomStatus;
  page?: number;
}) {
  const params = new URLSearchParams();
  if (filters.status) params.set('status', filters.status);
  if (filters.page) params.set('page', String(filters.page));
  const query = params.toString();

  return apiRequest<Paginated<AdminVideoRoom>>(
    `/admin/video-rooms${query ? `?${query}` : ''}`,
  );
}

export function endVideoRoomAsAdmin(id: number) {
  return apiRequest<AdminVideoRoom>(`/admin/video-rooms/${id}/end`, { method: 'POST' });
}
