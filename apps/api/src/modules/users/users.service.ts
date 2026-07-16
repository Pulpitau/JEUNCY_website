import { Injectable } from '@nestjs/common';
import { UserRole } from '@jeuncy/shared';
import { PrismaService } from '../../prisma/prisma.service';

interface CreateUserInput {
  email: string;
  passwordHash: string | null;
  role: UserRole;
  googleId?: string;
}

@Injectable()
export class UsersService {
  constructor(private readonly prisma: PrismaService) {}

  findByEmail(email: string) {
    return this.prisma.user.findUnique({ where: { email } });
  }

  findById(id: string) {
    return this.prisma.user.findUnique({ where: { id } });
  }

  findByGoogleId(googleId: string) {
    return this.prisma.user.findUnique({ where: { googleId } });
  }

  create(input: CreateUserInput) {
    return this.prisma.user.create({ data: input });
  }

  updatePassword(id: string, passwordHash: string) {
    return this.prisma.user.update({ where: { id }, data: { passwordHash } });
  }

  linkGoogleId(id: string, googleId: string) {
    return this.prisma.user.update({ where: { id }, data: { googleId } });
  }
}
