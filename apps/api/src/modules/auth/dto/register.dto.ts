import { IsEmail, IsIn, IsString, MinLength } from 'class-validator';
import { UserRole } from '@jeuncy/shared';

// ADMIN est volontairement exclu : ce role n'est jamais auto-attribuable.
const REGISTERABLE_ROLES: UserRole[] = ['CANDIDATE', 'COMPANY', 'CFA'];

export class RegisterDto {
  @IsEmail()
  email!: string;

  @IsString()
  @MinLength(8, { message: 'Le mot de passe doit contenir au moins 8 caractères.' })
  password!: string;

  @IsIn(REGISTERABLE_ROLES)
  role!: UserRole;
}
