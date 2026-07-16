import { ConflictException, UnauthorizedException } from '@nestjs/common';
import * as bcrypt from 'bcrypt';
import { AuthService } from './auth.service';
import { UsersService } from '../users/users.service';
import { MailService } from '../mail/mail.service';

describe('AuthService', () => {
  let authService: AuthService;
  let usersService: jest.Mocked<UsersService>;
  let jwtService: { sign: jest.Mock; verifyAsync: jest.Mock };
  let configService: { get: jest.Mock; getOrThrow: jest.Mock };
  let mailService: jest.Mocked<MailService>;

  const baseUser = {
    id: 'user-1',
    email: 'lea@example.com',
    passwordHash: null as string | null,
    googleId: null,
    role: 'CANDIDATE',
    createdAt: new Date(),
    updatedAt: new Date(),
  };

  beforeEach(() => {
    usersService = {
      findByEmail: jest.fn(),
      findById: jest.fn(),
      findByGoogleId: jest.fn(),
      create: jest.fn(),
      updatePassword: jest.fn(),
      linkGoogleId: jest.fn(),
    } as unknown as jest.Mocked<UsersService>;

    jwtService = {
      sign: jest.fn().mockReturnValue('signed-token'),
      verifyAsync: jest.fn(),
    };

    configService = {
      get: jest.fn((key: string) =>
        key === 'WEB_ORIGIN' ? 'http://localhost:5173' : undefined,
      ),
      getOrThrow: jest.fn((key: string) => `secret-for-${key}`),
    };

    mailService = {
      sendPasswordResetEmail: jest.fn(),
    } as unknown as jest.Mocked<MailService>;

    authService = new AuthService(
      usersService,
      // @ts-expect-error mock partiel suffisant pour les methodes utilisees
      jwtService,
      configService,
      mailService,
    );
  });

  describe('validateUser', () => {
    it('rejette un email inconnu', async () => {
      usersService.findByEmail.mockResolvedValue(null);

      await expect(
        authService.validateUser('inconnu@example.com', 'password123'),
      ).rejects.toThrow(UnauthorizedException);
    });

    it('rejette un compte sans mot de passe (Google uniquement)', async () => {
      usersService.findByEmail.mockResolvedValue({
        ...baseUser,
        passwordHash: null,
      } as never);

      await expect(
        authService.validateUser(baseUser.email, 'password123'),
      ).rejects.toThrow(UnauthorizedException);
    });

    it('rejette un mauvais mot de passe', async () => {
      const hash = await bcrypt.hash('bonMotDePasse', 10);
      usersService.findByEmail.mockResolvedValue({
        ...baseUser,
        passwordHash: hash,
      } as never);

      await expect(
        authService.validateUser(baseUser.email, 'mauvaisMotDePasse'),
      ).rejects.toThrow(UnauthorizedException);
    });

    it('retourne l’utilisateur si email et mot de passe sont corrects', async () => {
      const hash = await bcrypt.hash('bonMotDePasse', 10);
      const user = { ...baseUser, passwordHash: hash };
      usersService.findByEmail.mockResolvedValue(user as never);

      await expect(
        authService.validateUser(baseUser.email, 'bonMotDePasse'),
      ).resolves.toEqual(user);
    });
  });

  describe('register', () => {
    it('refuse un email déjà utilisé', async () => {
      usersService.findByEmail.mockResolvedValue(baseUser as never);

      await expect(
        authService.register({
          email: baseUser.email,
          password: 'password123',
          role: 'CANDIDATE',
        }),
      ).rejects.toThrow(ConflictException);
      expect(usersService.create).not.toHaveBeenCalled();
    });

    it('cree le compte avec un mot de passe hashe et emet des tokens', async () => {
      usersService.findByEmail.mockResolvedValue(null);
      usersService.create.mockResolvedValue(baseUser as never);

      const result = await authService.register({
        email: baseUser.email,
        password: 'password123',
        role: 'CANDIDATE',
      });

      expect(usersService.create).toHaveBeenCalledWith(
        expect.objectContaining({ email: baseUser.email, role: 'CANDIDATE' }),
      );
      const createCallArg = usersService.create.mock.calls[0][0] as {
        passwordHash: string;
      };
      expect(createCallArg.passwordHash).not.toBe('password123');
      expect(result.tokens.accessToken).toBe('signed-token');
      expect(result.tokens.refreshToken).toBe('signed-token');
    });
  });

  describe('refreshTokens', () => {
    it('rejette un refresh token invalide', async () => {
      jwtService.verifyAsync.mockRejectedValue(new Error('invalid'));

      await expect(authService.refreshTokens('bad-token')).rejects.toThrow(
        UnauthorizedException,
      );
    });

    it('rejette si l’utilisateur du token n’existe plus', async () => {
      jwtService.verifyAsync.mockResolvedValue({ sub: 'ghost-user' });
      usersService.findById.mockResolvedValue(null);

      await expect(authService.refreshTokens('valid-token')).rejects.toThrow(
        UnauthorizedException,
      );
    });

    it('emet une nouvelle paire de tokens pour un refresh token valide', async () => {
      jwtService.verifyAsync.mockResolvedValue({ sub: baseUser.id });
      usersService.findById.mockResolvedValue(baseUser as never);

      const tokens = await authService.refreshTokens('valid-token');

      expect(tokens.accessToken).toBe('signed-token');
      expect(tokens.refreshToken).toBe('signed-token');
    });
  });

  describe('forgotPassword', () => {
    it('ne fait rien si l’email est inconnu (ne revele pas les comptes existants)', async () => {
      usersService.findByEmail.mockResolvedValue(null);

      await authService.forgotPassword('inconnu@example.com');

      expect(mailService.sendPasswordResetEmail).not.toHaveBeenCalled();
    });

    it('envoie un email de reinitialisation si le compte existe', async () => {
      usersService.findByEmail.mockResolvedValue(baseUser as never);

      await authService.forgotPassword(baseUser.email);

      expect(mailService.sendPasswordResetEmail).toHaveBeenCalledWith(
        baseUser.email,
        expect.stringContaining('/reset-password?token='),
      );
    });
  });

  describe('resetPassword', () => {
    it('rejette un token dont le purpose n’est pas password-reset', async () => {
      jwtService.verifyAsync.mockResolvedValue({
        sub: baseUser.id,
        purpose: 'autre-chose',
      });

      await expect(
        authService.resetPassword('token', 'nouveauMotDePasse'),
      ).rejects.toThrow(UnauthorizedException);
      expect(usersService.updatePassword).not.toHaveBeenCalled();
    });

    it('met a jour le mot de passe pour un token valide', async () => {
      jwtService.verifyAsync.mockResolvedValue({
        sub: baseUser.id,
        purpose: 'password-reset',
      });

      await authService.resetPassword('token', 'nouveauMotDePasse');

      expect(usersService.updatePassword).toHaveBeenCalledWith(
        baseUser.id,
        expect.any(String),
      );
    });
  });
});
