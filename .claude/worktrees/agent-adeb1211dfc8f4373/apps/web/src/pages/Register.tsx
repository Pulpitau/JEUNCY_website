import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { register as registerRequest } from '@/lib/api/auth';
import { ApiError } from '@/lib/api/client';
import { useAuthStore } from '@/store/auth-store';

const ROLE_OPTIONS = [
  { value: 'CANDIDATE', label: 'Candidat' },
  { value: 'COMPANY', label: 'Entreprise' },
  { value: 'CFA', label: 'CFA' },
] as const;

const registerSchema = z.object({
  email: z.string().email('Adresse email invalide.'),
  password: z.string().min(8, 'Le mot de passe doit contenir au moins 8 caractères.'),
  role: z.enum(['CANDIDATE', 'COMPANY', 'CFA'], {
    errorMap: () => ({ message: 'Choisis un type de compte.' }),
  }),
});

type RegisterFormValues = z.infer<typeof registerSchema>;

export function Register() {
  const navigate = useNavigate();
  const setSession = useAuthStore((state) => state.setSession);
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register: registerField,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { role: 'CANDIDATE' },
  });

  async function onSubmit(values: RegisterFormValues) {
    setServerError(null);
    try {
      const { user, accessToken } = await registerRequest(values);
      setSession(user, accessToken);
      navigate('/');
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
              <Input
                id="password"
                type="password"
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
