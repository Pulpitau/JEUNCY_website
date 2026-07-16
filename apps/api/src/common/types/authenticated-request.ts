import type { Request } from 'express';
import { UserRole } from '@jeuncy/shared';

export interface RequestUser {
  id: string;
  email: string;
  role: UserRole;
}

export interface AuthenticatedRequest extends Request {
  user: RequestUser;
}
