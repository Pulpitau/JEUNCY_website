import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { exportAccountData, deleteAccount } from '@/lib/api/account';
import { logout as logoutRequest } from '@/lib/api/auth';
import { ApiError } from '@/lib/api/client';
import { useAuthStore } from '@/store/auth-store';

function downloadJson(data: unknown, filename: string) {
  const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  link.click();
  URL.revokeObjectURL(url);
}

export function AccountPrivacy() {
  const navigate = useNavigate();
  const user = useAuthStore((state) => state.user);
  const clearSession = useAuthStore((state) => state.clearSession);
  const [confirmEmail, setConfirmEmail] = useState('');

  const exportMutation = useMutation({
    mutationFn: exportAccountData,
    onSuccess: (data) => {
      downloadJson(
        data,
        `jeuncy-donnees-personnelles-${new Date().toISOString().slice(0, 10)}.json`,
      );
    },
  });

  const deleteMutation = useMutation({
    mutationFn: () => deleteAccount(confirmEmail),
    onSuccess: async () => {
      await logoutRequest().catch(() => undefined);
      clearSession();
      navigate('/');
    },
  });

  const emailMatches = confirmEmail.trim().toLowerCase() === user?.email.toLowerCase();

  return (
    <main className="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Confidentialité et données</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Gère les données personnelles associées à ton compte Jeuncy.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Exporter mes données</CardTitle>
          <CardDescription>
            Télécharge un fichier contenant l'ensemble des données personnelles liées à
            ton compte (profil, candidatures, offres, notifications, visioconférences…).
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          <Button
            type="button"
            variant="outline"
            className="self-start"
            disabled={exportMutation.isPending}
            onClick={() => exportMutation.mutate()}
          >
            {exportMutation.isPending ? 'Préparation…' : 'Télécharger mes données (JSON)'}
          </Button>
          {exportMutation.isError && (
            <p role="alert" className="font-inter text-sm text-destructive">
              Impossible de préparer l'export pour le moment, réessaie plus tard.
            </p>
          )}
        </CardContent>
      </Card>

      <Card className="border-destructive/40">
        <CardHeader>
          <CardTitle className="text-destructive">Supprimer mon compte</CardTitle>
          <CardDescription>
            Cette action est définitive : ton profil, tes fichiers (photo, CV générés,
            logo) et tes données personnelles sont supprimés. Si des paiements sont
            rattachés à ton compte, seules les données personnelles sont effacées — les
            pièces comptables sont conservées conformément à l'obligation légale de
            conservation.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-3">
          <div className="flex flex-col gap-2">
            <Label htmlFor="confirm-email">
              Pour confirmer, saisis ton adresse email ({user?.email})
            </Label>
            <Input
              id="confirm-email"
              type="email"
              value={confirmEmail}
              onChange={(event) => setConfirmEmail(event.target.value)}
              autoComplete="off"
            />
          </div>
          {deleteMutation.isError && (
            <p role="alert" className="font-inter text-sm text-destructive">
              {deleteMutation.error instanceof ApiError
                ? deleteMutation.error.message
                : 'Impossible de supprimer le compte pour le moment.'}
            </p>
          )}
          <Button
            type="button"
            variant="destructive"
            className="self-start"
            disabled={!emailMatches || deleteMutation.isPending}
            onClick={() => deleteMutation.mutate()}
          >
            {deleteMutation.isPending
              ? 'Suppression…'
              : 'Supprimer définitivement mon compte'}
          </Button>
        </CardContent>
      </Card>
    </main>
  );
}
