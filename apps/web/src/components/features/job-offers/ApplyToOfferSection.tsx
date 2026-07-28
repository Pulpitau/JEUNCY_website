import { useEffect, useState } from 'react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { ApplicationStatus, UserRole } from '@jeuncy/shared';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { useAuthStore } from '@/store/auth-store';
import { applyToOffer, listMyApplications } from '@/lib/api/applications';
import { getMyProfile, listGeneratedCvs } from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { cn } from '@/lib/utils';

interface ApplyToOfferSectionProps {
  jobOfferId: number;
}

type CvMode = 'GENERATED' | 'UPLOAD';

const MAX_CV_FILE_BYTES = 5 * 1024 * 1024;

const STATUS_LABELS: Record<string, string> = {
  [ApplicationStatus.SENT]: 'Envoyée',
  [ApplicationStatus.SEEN]: 'Vue',
  [ApplicationStatus.INTERVIEW]: 'Entretien',
  [ApplicationStatus.ACCEPTED]: 'Acceptée',
  [ApplicationStatus.REJECTED]: 'Refusée',
};

export function ApplyToOfferSection({ jobOfferId }: ApplyToOfferSectionProps) {
  const user = useAuthStore((state) => state.user);
  const [coverLetter, setCoverLetter] = useState('');
  const [phone, setPhone] = useState('');
  const [phoneTouched, setPhoneTouched] = useState(false);
  const [cvMode, setCvMode] = useState<CvMode>('GENERATED');
  const [cvModeTouched, setCvModeTouched] = useState(false);
  const [selectedCvId, setSelectedCvId] = useState<number | null>(null);
  const [cvFile, setCvFile] = useState<File | null>(null);
  const [cvFileError, setCvFileError] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const isCandidate = user?.role === UserRole.CANDIDATE;

  const myApplicationsQuery = useQuery({
    queryKey: ['applications', 'mine'],
    queryFn: listMyApplications,
    retry: false,
    enabled: isCandidate,
  });

  const profileQuery = useQuery({
    queryKey: ['candidate-profile'],
    queryFn: getMyProfile,
    retry: false,
    enabled: isCandidate,
  });

  const cvsQuery = useQuery({
    queryKey: ['candidate-profile', 'cv'],
    queryFn: listGeneratedCvs,
    retry: false,
    enabled: isCandidate,
  });

  // Un CV archive n'a plus de fichier sur le serveur (voir
  // CvService::archive) : le lien serait mort pour l'employeur.
  const usableCvs = (cvsQuery.data ?? []).filter((cv) => !cv.archived_at);

  useEffect(() => {
    if (!phoneTouched && profileQuery.data?.phone) {
      setPhone(profileQuery.data.phone);
    }
  }, [phoneTouched, profileQuery.data]);

  useEffect(() => {
    if (!cvModeTouched && !cvsQuery.isLoading && usableCvs.length === 0) {
      setCvMode('UPLOAD');
    }
  }, [cvModeTouched, cvsQuery.isLoading, usableCvs.length]);

  useEffect(() => {
    if (cvMode === 'GENERATED' && selectedCvId === null && usableCvs.length > 0) {
      setSelectedCvId(usableCvs[0].id);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cvMode, usableCvs.length]);

  function handleCvFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    if (file.type !== 'application/pdf') {
      setCvFileError('Seuls les fichiers PDF sont acceptés.');
      return;
    }
    if (file.size > MAX_CV_FILE_BYTES) {
      setCvFileError('Le fichier ne doit pas dépasser 5 Mo.');
      return;
    }

    setCvFileError(null);
    setCvFile(file);
  }

  const applyMutation = useMutation({
    mutationFn: () =>
      applyToOffer(jobOfferId, {
        coverLetter,
        contactPhone: phone,
        generatedCvId: cvMode === 'GENERATED' ? (selectedCvId ?? undefined) : undefined,
        cvFile: cvMode === 'UPLOAD' ? (cvFile ?? undefined) : undefined,
      }),
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

  if (!isCandidate) {
    return null;
  }

  if (myApplicationsQuery.isLoading) {
    return null;
  }

  const existingApplication = myApplicationsQuery.data?.find(
    (application) => application.job_offer_id === jobOfferId,
  );

  if (applyMutation.isSuccess || existingApplication) {
    const status = existingApplication?.status ?? ApplicationStatus.SENT;
    return (
      <div className="mt-6 rounded-md border border-jeuncy-orange bg-jeuncy-orange/10 p-4">
        <p className="font-inter text-sm text-foreground">
          {applyMutation.isSuccess
            ? 'Candidature envoyée !'
            : `Tu as déjà postulé à cette offre (statut : ${STATUS_LABELS[status]}).`}{' '}
          Tu peux suivre son statut depuis{' '}
          <Link to="/mes-candidatures" className="text-primary hover:underline">
            Mes candidatures
          </Link>
          .
        </p>
      </div>
    );
  }

  const canSubmit =
    phone.trim().length > 0 &&
    (cvMode === 'GENERATED' ? selectedCvId !== null : cvFile !== null);

  return (
    <div className="mt-6 flex flex-col gap-3 rounded-md border border-border p-4">
      <p className="font-poppins text-sm font-medium text-foreground">
        Postuler à cette offre
      </p>
      <p className="font-inter text-sm text-muted-foreground">
        Ces informations sont transmises au recruteur pour qu'il puisse te contacter
        directement.
      </p>

      <div className="flex flex-col gap-2">
        <Label htmlFor="contact-phone">Téléphone</Label>
        <Input
          id="contact-phone"
          type="tel"
          value={phone}
          onChange={(event) => {
            setPhoneTouched(true);
            setPhone(event.target.value);
          }}
          placeholder="Ex : 06 12 34 56 78"
        />
      </div>

      <div className="flex flex-col gap-2">
        <Label>CV joint</Label>
        <div className="flex flex-wrap gap-4 font-inter text-sm text-foreground">
          <label className="flex items-center gap-2">
            <input
              type="radio"
              name="cv-mode"
              checked={cvMode === 'GENERATED'}
              onChange={() => {
                setCvModeTouched(true);
                setCvMode('GENERATED');
              }}
              disabled={usableCvs.length === 0}
            />
            Utiliser un CV généré
          </label>
          <label className="flex items-center gap-2">
            <input
              type="radio"
              name="cv-mode"
              checked={cvMode === 'UPLOAD'}
              onChange={() => {
                setCvModeTouched(true);
                setCvMode('UPLOAD');
              }}
            />
            Importer un fichier PDF
          </label>
        </div>

        {cvMode === 'GENERATED' ? (
          cvsQuery.isLoading ? (
            <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
          ) : usableCvs.length === 0 ? (
            <p className="font-inter text-sm text-muted-foreground">
              Tu n'as pas encore de CV généré.{' '}
              <Link to="/profile" className="text-primary hover:underline">
                Génère ton CV
              </Link>{' '}
              ou importe un fichier PDF ci-dessus.
            </p>
          ) : (
            <select
              id="application-cv"
              className={cn(
                'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
              )}
              value={selectedCvId ?? ''}
              onChange={(event) => setSelectedCvId(Number(event.target.value))}
            >
              {usableCvs.map((cv) => (
                <option key={cv.id} value={cv.id}>
                  CV généré le {new Date(cv.generated_at).toLocaleDateString('fr-FR')}
                </option>
              ))}
            </select>
          )
        ) : (
          <div className="flex flex-col gap-1">
            <input
              type="file"
              accept="application/pdf,.pdf"
              onChange={handleCvFileChange}
              className="font-inter text-sm text-foreground"
            />
            <p className="font-inter text-xs text-muted-foreground">PDF, 5 Mo max.</p>
            {cvFile && (
              <p className="font-inter text-xs text-foreground">
                Fichier sélectionné : {cvFile.name}
              </p>
            )}
            {cvFileError && (
              <p role="alert" className="text-xs text-destructive">
                {cvFileError}
              </p>
            )}
          </div>
        )}
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="cover-letter">Lettre de motivation (facultatif)</Label>
        <Textarea
          id="cover-letter"
          rows={4}
          value={coverLetter}
          onChange={(event) => setCoverLetter(event.target.value)}
          placeholder="Explique en quelques lignes pourquoi cette offre t'intéresse…"
        />
      </div>

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
        disabled={!canSubmit || applyMutation.isPending}
      >
        {applyMutation.isPending ? 'Envoi…' : 'Postuler'}
      </Button>
    </div>
  );
}
