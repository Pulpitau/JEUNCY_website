import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { UserRole } from '@jeuncy/shared';
import { Button, buttonVariants } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PasswordInput } from '@/components/ui/password-input';
import { Label } from '@/components/ui/label';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { register as registerRequest } from '@/lib/api/auth';
import { ApiError, API_URL } from '@/lib/api/client';
import { useAuthStore } from '@/store/auth-store';
import { cn } from '@/lib/utils';
import { GoogleIcon } from '@/components/icons/GoogleIcon';

const ROLE_OPTIONS = [
  { value: 'CANDIDATE', label: 'Candidat' },
  { value: 'COMPANY', label: 'Entreprise' },
  { value: 'CFA', label: 'CFA' },
] as const;

// S'inscrire ne cree qu'une ligne `users` : la fiche (profil candidat,
// entreprise, CFA) n'existe qu'une fois le formulaire dedie enregistre. Sans
// elle, un candidat est invisible dans la CVtheque et une entreprise ne peut
// rien publier. Renvoyer vers l'accueil apres inscription laissait chacun se
// debrouiller pour trouver la page ; on l'y amene directement.
function landingRouteAfterSignup(role: string): string {
  switch (role) {
    case UserRole.CANDIDATE:
      return '/profile';
    case UserRole.COMPANY:
    case UserRole.CFA:
      return '/organization';
    default:
      return '/';
  }
}

const registerSchema = z.object({
  email: z.string().email('Adresse email invalide.'),
  password: z.string().min(8, 'Le mot de passe doit contenir au moins 8 caractères.'),
  role: z.enum(['CANDIDATE', 'COMPANY', 'CFA'], {
    errorMap: () => ({ message: 'Choisis un type de compte.' }),
  }),
});

type RegisterFormValues = z.infer<typeof registerSchema>;

const VALID_ROLES = ROLE_OPTIONS.map((option) => option.value);

export function Register() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const setSession = useAuthStore((state) => state.setSession);
  const [serverError, setServerError] = useState<string | null>(null);

  const roleParam = searchParams.get('role');
  const defaultRole = (VALID_ROLES as readonly string[]).includes(roleParam ?? '')
    ? (roleParam as RegisterFormValues['role'])
    : 'CANDIDATE';

  const {
    register: registerField,
    handleSubmit,
    watch,
    formState: { errors, isSubmitting },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { role: defaultRole },
  });

  // Le bouton Google reprend le type de compte choisi ci-dessous (transmis
  // au backend via le parametre "role", voir AuthController::googleRedirect)
  // — Google cree donc desormais un compte du bon type des la premiere
  // connexion, plus seulement un compte Candidat par defaut.
  const selectedRole = watch('role');
  const googleHref = `${API_URL}/auth/google?role=${selectedRole}`;

  async function onSubmit(values: RegisterFormValues) {
    setServerError(null);
    try {
      const { user, accessToken } = await registerRequest(values);
      setSession(user, accessToken);
      navigate(landingRouteAfterSignup(user.role));
    } catch (error) {
      setServerError(
        error instanceof ApiError ? error.message : 'Une erreur est survenue, réessaie.',
      );
    }
  }

  return (
    <main className="flex min-h-[calc(100vh-4rem)] items-center justify-center px-4 py-12">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-2xl">Crée ton compte</CardTitle>
          <CardDescription>Ton alternance commence ici.</CardDescription>
        </CardHeader>
        <CardContent>
          <p className="mb-4 font-inter text-sm text-muted-foreground">
            Choisis d'abord ton type de compte, puis crée-le en un clic avec{' '}
            <span className="font-medium text-foreground">Google</span>, ou remplis le{' '}
            <span className="font-medium text-foreground">formulaire</span> plus bas.
          </p>

          <form
            onSubmit={handleSubmit(onSubmit)}
            noValidate
            className="flex flex-col gap-4"
          >
            <fieldset className="flex flex-col gap-2">
              <legend className="text-sm font-medium font-inter">Je suis...</legend>
              <div className="flex gap-2">
                {ROLE_OPTIONS.map((option) => (
                  <label
                    key={option.value}
                    className="flex flex-1 cursor-pointer items-center justify-center rounded-md border border-input px-3 py-2 text-sm font-inter has-[:checked]:border-primary has-[:checked]:bg-primary/10"
                  >
                    <input
                      type="radio"
                      value={option.value}
                      className="sr-only"
                      {...registerField('role')}
                    />
                    {option.label}
                  </label>
                ))}
              </div>
              {errors.role && (
                <p role="alert" className="text-sm text-destructive">
                  {errors.role.message}
                </p>
              )}
            </fieldset>

            <a
              href={googleHref}
              className={cn(buttonVariants({ variant: 'outline' }), 'w-full gap-2')}
            >
              <GoogleIcon className="h-4 w-4" />
              Continuer avec Google en tant que{' '}
              {ROLE_OPTIONS.find((option) => option.value === selectedRole)?.label}
            </a>

            <div className="flex items-center gap-3">
              <div className="h-px flex-1 bg-border" />
              <span className="font-inter text-xs text-muted-foreground">
                ou avec le formulaire
              </span>
              <div className="h-px flex-1 bg-border" />
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                autoComplete="email"
                aria-invalid={!!errors.email}
                aria-describedby={errors.email ? 'email-error' : undefined}
                {...registerField('email')}
              />
              {errors.email && (
                <p id="email-error" role="alert" className="text-sm text-destructive">
                  {errors.email.message}
                </p>
              )}
            </div>

            <div className="flex flex-col gap-2">
              <Label htmlFor="password">Mot de passe</Label>
              <PasswordInput
                id="password"
                autoComplete="new-password"
                aria-invalid={!!errors.password}
                aria-describedby={errors.password ? 'password-error' : undefined}
                {...registerField('password')}
              />
              {errors.password && (
                <p id="password-error" role="alert" className="text-sm text-destructive">
                  {errors.password.message}
                </p>
              )}
            </div>

            {serverError && (
              <p role="alert" className="text-sm text-destructive">
                {serverError}
              </p>
            )}

            {/* Information prealable a la collecte (RGPD art. 13) : le profil
                candidat etant visible par defaut dans la CVtheque, il doit le
                savoir AVANT de creer son compte, pas seulement en fouillant la
                politique de confidentialite. */}
            {selectedRole === 'CANDIDATE' && (
              <p className="rounded-md border border-border bg-muted/40 px-3 py-2 font-inter text-xs leading-relaxed text-muted-foreground">
                Ton profil sera visible par les entreprises et CFA abonnés, qui pourront
                te contacter directement. Tu pourras t'en retirer en un clic depuis ton
                profil à tout moment.{' '}
                <Link to="/confidentialite" className="text-primary hover:underline">
                  En savoir plus
                </Link>
              </p>
            )}

            <Button
              type="submit"
              variant="gradient"
              disabled={isSubmitting}
              className="mt-2"
            >
              {isSubmitting ? 'Création…' : 'Créer mon compte'}
            </Button>
          </form>

          <p className="mt-6 text-center text-sm font-inter text-muted-foreground">
            Déjà un compte ?{' '}
            <Link to="/login" className="font-medium text-primary hover:underline">
              Se connecter
            </Link>
          </p>
        </CardContent>
      </Card>
    </main>
  );
}
