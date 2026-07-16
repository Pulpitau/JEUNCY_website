import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
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
import { resetPassword } from '@/lib/api/auth';
import { ApiError } from '@/lib/api/client';

const resetPasswordSchema = z.object({
  newPassword: z.string().min(8, 'Le mot de passe doit contenir au moins 8 caractères.'),
});

type ResetPasswordFormValues = z.infer<typeof resetPasswordSchema>;

export function ResetPassword() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token');
  const [serverError, setServerError] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordFormValues>({ resolver: zodResolver(resetPasswordSchema) });

  async function onSubmit(values: ResetPasswordFormValues) {
    if (!token) return;
    setServerError(null);
    try {
      await resetPassword(token, values.newPassword);
      navigate('/login');
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
          <CardTitle className="text-2xl">Nouveau mot de passe</CardTitle>
          <CardDescription>
            Choisis un nouveau mot de passe pour ton compte.
          </CardDescription>
        </CardHeader>
        <CardContent>
          {!token ? (
            <p role="alert" className="text-sm text-destructive">
              Ce lien de réinitialisation est invalide. Redemande un email depuis la page{' '}
              <Link
                to="/forgot-password"
                className="font-medium text-primary hover:underline"
              >
                mot de passe oublié
              </Link>
              .
            </p>
          ) : (
            <form
              onSubmit={handleSubmit(onSubmit)}
              noValidate
              className="flex flex-col gap-4"
            >
              <div className="flex flex-col gap-2">
                <Label htmlFor="newPassword">Nouveau mot de passe</Label>
                <Input
                  id="newPassword"
                  type="password"
                  autoComplete="new-password"
                  aria-invalid={!!errors.newPassword}
                  aria-describedby={errors.newPassword ? 'password-error' : undefined}
                  {...register('newPassword')}
                />
                {errors.newPassword && (
                  <p
                    id="password-error"
                    role="alert"
                    className="text-sm text-destructive"
                  >
                    {errors.newPassword.message}
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
                {isSubmitting ? 'Mise à jour…' : 'Réinitialiser le mot de passe'}
              </Button>
            </form>
          )}
        </CardContent>
      </Card>
    </main>
  );
}
