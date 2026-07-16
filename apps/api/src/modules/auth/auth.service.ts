import { ConflictException, Injectable, UnauthorizedException } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { JwtService, type JwtSignOptions } from '@nestjs/jwt';
import * as bcrypt from 'bcrypt';
import { UserRole } from '@jeuncy/shared';
import { UsersService } from '../users/users.service';
import { MailService } from '../mail/mail.service';
import type { RegisterDto } from './dto/register.dto';
import type { User } from '../../../generated/prisma';

const PASSWORD_RESET_PURPOSE = 'password-reset';
const SALT_ROUNDS = 10;

interface TokenPair {
  accessToken: string;
  refreshToken: string;
}

interface ResetTokenPayload {
  sub: string;
  purpose: typeof PASSWORD_RESET_PURPOSE;
}

@Injectable()
export class AuthService {
  constructor(
    private readonly usersService: UsersService,
    private readonly jwtService: JwtService,
    private readonly configService: ConfigService,
    private readonly mailService: MailService,
  ) {}

  async validateUser(email: string, password: string) {
    const user = await this.usersService.findByEmail(email);
    // Meme message que mot de passe invalide : ne pas reveler si l'email existe.
    if (!user || !user.passwordHash) {
      throw new UnauthorizedException({
        code: 'INVALID_CREDENTIALS',
        message: 'Email ou mot de passe incorrect.',
      });
    }

    const isValid = await bcrypt.compare(password, user.passwordHash);
    if (!isValid) {
      throw new UnauthorizedException({
        code: 'INVALID_CREDENTIALS',
        message: 'Email ou mot de passe incorrect.',
      });
    }

    return user;
  }

  async register(dto: RegisterDto) {
    const existing = await this.usersService.findByEmail(dto.email);
    if (existing) {
      throw new ConflictException({
        code: 'EMAIL_ALREADY_EXISTS',
        message: 'Un compte existe déjà avec cet email.',
      });
    }

    const passwordHash = await bcrypt.hash(dto.password, SALT_ROUNDS);
    const user = await this.usersService.create({
      email: dto.email,
      passwordHash,
      role: dto.role,
    });

    return { user, tokens: this.issueTokens(user.id, user.email, user.role) };
  }

  login(user: User) {
    return { user, tokens: this.issueTokens(user.id, user.email, user.role) };
  }

  issueTokens(userId: string, email: string, role: UserRole): TokenPair {
    const accessToken = this.jwtService.sign(
      { sub: userId, email, role },
      {
        secret: this.configService.getOrThrow<string>('JWT_SECRET'),
        expiresIn: (this.configService.get<string>('JWT_EXPIRES_IN') ??
          '15m') as JwtSignOptions['expiresIn'],
      },
    );

    const refreshToken = this.jwtService.sign(
      { sub: userId },
      {
        secret: this.configService.getOrThrow<string>('JWT_REFRESH_SECRET'),
        expiresIn: (this.configService.get<string>('JWT_REFRESH_EXPIRES_IN') ??
          '7d') as JwtSignOptions['expiresIn'],
      },
    );

    return { accessToken, refreshToken };
  }

  async refreshTokens(refreshToken: string): Promise<TokenPair> {
    let payload: { sub: string };
    try {
      payload = await this.jwtService.verifyAsync<{ sub: string }>(refreshToken, {
        secret: this.configService.getOrThrow<string>('JWT_REFRESH_SECRET'),
      });
    } catch {
      throw new UnauthorizedException({
        code: 'INVALID_REFRESH_TOKEN',
        message: 'Session expirée, merci de te reconnecter.',
      });
    }

    const user = await this.usersService.findById(payload.sub);
    if (!user) {
      throw new UnauthorizedException({
        code: 'INVALID_REFRESH_TOKEN',
        message: 'Session expirée, merci de te reconnecter.',
      });
    }

    return this.issueTokens(user.id, user.email, user.role);
  }

  async validateGoogleUser(profile: { googleId: string; email: string }) {
    const existingByGoogleId = await this.usersService.findByGoogleId(profile.googleId);
    if (existingByGoogleId) {
      return existingByGoogleId;
    }

    const existingByEmail = await this.usersService.findByEmail(profile.email);
    if (existingByEmail) {
      // Compte cree via email/mot de passe : on associe le compte Google.
      return this.usersService.linkGoogleId(existingByEmail.id, profile.googleId);
    }

    // Nouvelle inscription via Google : role CANDIDATE par defaut, modifiable
    // plus tard dans le profil (pas de choix de role dans le flux OAuth).
    return this.usersService.create({
      email: profile.email,
      passwordHash: null,
      role: 'CANDIDATE',
      googleId: profile.googleId,
    });
  }

  async forgotPassword(email: string) {
    const user = await this.usersService.findByEmail(email);
    // Ne jamais reveler si l'email existe : on repond succes dans tous les cas.
    if (!user) {
      return;
    }

    const token = this.jwtService.sign(
      { sub: user.id, purpose: PASSWORD_RESET_PURPOSE },
      {
        secret: this.configService.getOrThrow<string>('JWT_SECRET'),
        expiresIn: '1h',
      },
    );

    const webOrigin =
      this.configService.get<string>('WEB_ORIGIN') ?? 'http://localhost:5173';
    const resetUrl = `${webOrigin}/reset-password?token=${token}`;
    await this.mailService.sendPasswordResetEmail(user.email, resetUrl);
  }

  async resetPassword(token: string, newPassword: string) {
    let payload: ResetTokenPayload;
    try {
      payload = await this.jwtService.verifyAsync<ResetTokenPayload>(token, {
        secret: this.configService.getOrThrow<string>('JWT_SECRET'),
      });
    } catch {
      throw new UnauthorizedException({
        code: 'INVALID_RESET_TOKEN',
        message: 'Ce lien de réinitialisation est invalide ou a expiré.',
      });
    }

    if (payload.purpose !== PASSWORD_RESET_PURPOSE) {
      throw new UnauthorizedException({
        code: 'INVALID_RESET_TOKEN',
        message: 'Ce lien de réinitialisation est invalide ou a expiré.',
      });
    }

    const passwordHash = await bcrypt.hash(newPassword, SALT_ROUNDS);
    await this.usersService.updatePassword(payload.sub, passwordHash);
  }
}
