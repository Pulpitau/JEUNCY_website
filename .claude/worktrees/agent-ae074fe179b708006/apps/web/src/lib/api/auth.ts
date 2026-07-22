import type { UserRole } from '@jeuncy/shared';
import { apiRequest } from './client';
import type { AuthUser } from '@/store/auth-store';

interface AuthResponse {
  user: AuthUser;
  accessToken: string;
}

export function register(input: { email: string; password: string; role: UserRole }) {
  return apiRequest<AuthResponse>('/auth/register', { method: 'POST', body: input });
}

export function login(input: { email: string; password: string }) {
  return apiRequest<AuthResponse>('/auth/login', { method: 'POST', body: input });
}

export function logout() {
  return apiRequest<{ loggedOut: boolean }>('/auth/logout', { method: 'POST' });
}

export function fetchCurrentUser() {
  return apiRequest<AuthUser>('/auth/me');
}

export function refreshSession() {
  return apiRequest<{ accessToken: string }>('/auth/refresh', {
    method: 'POST',
    skipAuthRetry: true,
  });
}

export function forgotPassword(email: string) {
  return apiRequest<{ message: string }>('/auth/forgot-password', {
    method: 'POST',
    body: { email },
  });
}

export function resetPassword(token: string, newPassword: string) {
  return apiRequest<{ message: string }>('/auth/reset-password', {
    method: 'POST',
    body: { token, newPassword },
  });
}
