import { Module, type Provider } from '@nestjs/common';
import { JwtModule } from '@nestjs/jwt';
import { PassportModule } from '@nestjs/passport';
import { AuthController } from './auth.controller';
import { AuthService } from './auth.service';
import { LocalStrategy } from './strategies/local.strategy';
import { JwtStrategy } from './strategies/jwt.strategy';
import { GoogleStrategy } from './strategies/google.strategy';
import { UsersModule } from '../users/users.module';
import { MailModule } from '../mail/mail.module';

// GoogleStrategy exige un clientID valide des la construction : ne l'enregistrer
// que si Google OAuth est configure, pour ne pas empecher toute l'API de
// demarrer tant que GOOGLE_CLIENT_ID/SECRET restent des placeholders.
const authProviders: Provider[] = [AuthService, LocalStrategy, JwtStrategy];
if (process.env.GOOGLE_CLIENT_ID) {
  authProviders.push(GoogleStrategy);
}

@Module({
  imports: [PassportModule, JwtModule.register({}), UsersModule, MailModule],
  controllers: [AuthController],
  providers: authProviders,
})
export class AuthModule {}
