import {
  BadRequestException,
  Body,
  Controller,
  Get,
  HttpCode,
  HttpStatus,
  Post,
  Req,
  Res,
  UseGuards,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import type { Request, Response } from 'express';
import { AuthService } from './auth.service';
import { RegisterDto } from './dto/register.dto';
import { LoginDto } from './dto/login.dto';
import { ForgotPasswordDto } from './dto/forgot-password.dto';
import { ResetPasswordDto } from './dto/reset-password.dto';
import { LocalAuthGuard } from './guards/local-auth.guard';
import { GoogleAuthGuard } from './guards/google-auth.guard';
import { JwtAuthGuard } from '../../common/guards/jwt-auth.guard';
import { CurrentUser } from '../../common/decorators/current-user.decorator';
import { UsersService } from '../users/users.service';
import type { RequestUser } from '../../common/types/authenticated-request';
import type { User } from '../../../generated/prisma';

const REFRESH_COOKIE_NAME = 'jeuncy_refresh_token';
const REFRESH_COOKIE_MAX_AGE_MS = 7 * 24 * 60 * 60 * 1000; // doit correspondre a JWT_REFRESH_EXPIRES_IN

// Requete peuplee par LocalStrategy/GoogleStrategy : req.user est l'utilisateur
// Prisma complet (contrairement a JwtStrategy qui ne pose que id/email/role).
interface StrategyAuthenticatedRequest extends Request {
  user: User;
}

function sanitizeUser(user: User) {
  const { passwordHash: _passwordHash, ...safe } = user;
  return safe;
}

@Controller('auth')
export class AuthController {
  constructor(
    private readonly authService: AuthService,
    private readonly usersService: UsersService,
    private readonly configService: ConfigService,
  ) {}

  private setRefreshCookie(response: Response, refreshToken: string) {
    response.cookie(REFRESH_COOKIE_NAME, refreshToken, {
      httpOnly: true,
      secure: this.configService.get<string>('NODE_ENV') === 'production',
      sameSite: 'lax',
      path: '/auth',
      maxAge: REFRESH_COOKIE_MAX_AGE_MS,
    });
  }

  private clearRefreshCookie(response: Response) {
    response.clearCookie(REFRESH_COOKIE_NAME, { path: '/auth' });
  }

  @Post('register')
  async register(
    @Body() dto: RegisterDto,
    @Res({ passthrough: true }) response: Response,
  ) {
    const { user, tokens } = await this.authService.register(dto);
    this.setRefreshCookie(response, tokens.refreshToken);
    return { user: sanitizeUser(user), accessToken: tokens.accessToken };
  }

  @UseGuards(LocalAuthGuard)
  @HttpCode(HttpStatus.OK)
  @Post('login')
  login(
    @Req() request: StrategyAuthenticatedRequest,
    @Body() _dto: LoginDto,
    @Res({ passthrough: true }) response: Response,
  ) {
    // req.user est peuple par LocalStrategy (voir strategies/local.strategy.ts).
    const { user, tokens } = this.authService.login(request.user);
    this.setRefreshCookie(response, tokens.refreshToken);
    return { user: sanitizeUser(user), accessToken: tokens.accessToken };
  }

  @HttpCode(HttpStatus.OK)
  @Post('refresh')
  async refresh(@Req() request: Request, @Res({ passthrough: true }) response: Response) {
    const refreshToken = (request.cookies as Record<string, string> | undefined)?.[
      REFRESH_COOKIE_NAME
    ];
    if (!refreshToken) {
      throw new BadRequestException({
        code: 'MISSING_REFRESH_TOKEN',
        message: 'Aucune session active.',
      });
    }

    const tokens = await this.authService.refreshTokens(refreshToken);
    this.setRefreshCookie(response, tokens.refreshToken);
    return { accessToken: tokens.accessToken };
  }

  @UseGuards(JwtAuthGuard)
  @HttpCode(HttpStatus.OK)
  @Post('logout')
  logout(@Res({ passthrough: true }) response: Response) {
    this.clearRefreshCookie(response);
    return { loggedOut: true };
  }

  @UseGuards(JwtAuthGuard)
  @Get('me')
  async me(@CurrentUser() currentUser: RequestUser) {
    const user = await this.usersService.findById(currentUser.id);
    if (!user) {
      throw new BadRequestException({
        code: 'USER_NOT_FOUND',
        message: 'Utilisateur introuvable.',
      });
    }
    return sanitizeUser(user);
  }

  @HttpCode(HttpStatus.OK)
  @Post('forgot-password')
  async forgotPassword(@Body() dto: ForgotPasswordDto) {
    await this.authService.forgotPassword(dto.email);
    // Reponse identique que l'email existe ou non (ne pas reveler les comptes existants).
    return { message: 'Si ce compte existe, un email a été envoyé.' };
  }

  @HttpCode(HttpStatus.OK)
  @Post('reset-password')
  async resetPassword(@Body() dto: ResetPasswordDto) {
    await this.authService.resetPassword(dto.token, dto.newPassword);
    return { message: 'Mot de passe mis à jour.' };
  }

  @UseGuards(GoogleAuthGuard)
  @Get('google')
  googleAuth() {
    // La redirection vers Google est geree par GoogleAuthGuard/passport-google-oauth20.
  }

  @UseGuards(GoogleAuthGuard)
  @Get('google/callback')
  googleCallback(
    @Req() request: StrategyAuthenticatedRequest,
    @Res() response: Response,
  ) {
    const tokens = this.authService.issueTokens(
      request.user.id,
      request.user.email,
      request.user.role,
    );
    this.setRefreshCookie(response, tokens.refreshToken);

    // Pas d'access token dans l'URL (evite qu'il finisse dans l'historique du
    // navigateur) : le cookie httpOnly pose plus haut suffit, la page
    // /auth/callback appelle /auth/refresh comme au demarrage normal de l'app.
    const webOrigin =
      this.configService.get<string>('WEB_ORIGIN') ?? 'http://localhost:5173';
    response.redirect(`${webOrigin}/auth/callback`);
  }
}
