import { useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { UserRole } from '@jeuncy/shared';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useAuthStore } from '@/store/auth-store';
import { applyToOffer } from '@/lib/api/applications';
import { ApiError } from '@/lib/api/client';

interface ApplyToOfferSectionProps {
  jobOfferId: number;
}

export function ApplyToOfferSection({ jobOfferId }: ApplyToOfferSectionProps) {
  const user = useAuthStore((state) => state.user);
  const [coverLetter, setCoverLetter] = useState('');
  const [error, setError] = useState<string | null>(null);

  const applyMutation = useMutation({
    mutationFn: () => applyToOffer(jobOfferId, coverLetter),
    onError: (mutationError) => {
      setError(
        mutationError instanceof ApiError
          ? mutationError.message
          : "Impossible d'envoyer ta candidature pour le moment.",
      );
    },
  });

  if (!user) {
    return (
      <div className="mt-6 rounded-md border border-border p-4">
        <p className="font-inter text-sm text-muted-foreground">
          <Link to="/login" className="text-primary hover:underline">
            Connecte-toi
          </Link>{' '}
          en tant que candidat pour postuler à cette offre.
        </p>
      </div>
    );
  }

  if (user.role !== UserRole.CANDIDATE) {
    return null;
  }

  if (applyMutation.isSuccess) {
    return (
      <div className="mt-6 rounded-md border border-jeuncy-orange bg-jeuncy-orange/10 p-4">
        <p className="font-inter text-sm text-foreground">
          Candidature envoyée ! Tu peux suivre son statut depuis{' '}
          <Link to="/mes-candidatures" className="text-primary hover:underline">
            Mes candidatures
          </Link>
          .
        </p>
      </div>
    );
  }

  return (
    <div className="mt-6 flex flex-col gap-3 rounded-md border border-border p-4">
      <Label htmlFor="cover-letter">Lettre de motivation (facultatif)</Label>
      <Textarea
        id="cover-letter"
        rows={4}
        value={coverLetter}
        onChange={(event) => setCoverLetter(event.target.value)}
        placeholder="Explique en quelques lignes pourquoi cette offre t'intéresse…"
      />
      {error && (
        <p role="alert" className="text-sm text-destructive">
          {error}
        </p>
      )}
      <Button
        type="button"
        variant="gradient"
        className="self-start"
        onClick={() => {
          setError(null);
          applyMutation.mutate();
        }}
        disabled={applyMutation.isPending}
      >
        {applyMutation.isPending ? 'Envoi…' : 'Postuler'}
      </Button>
    </div>
  );
}
