import { useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

interface OwnCvSectionProps {
  fileUrl: string | null;
  originalFilename: string | null;
  uploadedAt: string | null;
  onUpload: (file: File) => Promise<unknown>;
  onRemove: () => Promise<unknown>;
}

const MAX_SIZE_BYTES = 5 * 1024 * 1024;

// Depot du CV que le candidat a deja (Canva, Word, ancien CV...). Distinct de
// la generation : les deux coexistent, et c'est le CV depose qui est propose
// en priorite aux recruteurs — c'est le document que le candidat a choisi de
// presenter.
export function OwnCvSection({
  fileUrl,
  originalFilename,
  uploadedAt,
  onUpload,
  onRemove,
}: OwnCvSectionProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [isBusy, setIsBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    // Remis a zero tout de suite : sans ca, redeposer le meme fichier apres
    // une erreur ne declenche aucun evenement change.
    event.target.value = '';
    if (!file) return;

    // Verifie avant l'envoi plutot que d'attendre le refus du serveur : sur un
    // reseau mobile, envoyer 20 Mo pour se voir repondre "trop lourd" est une
    // perte de temps reelle pour le candidat.
    if (file.type !== 'application/pdf') {
      setError('Ton CV doit être un fichier PDF.');
      return;
    }
    if (file.size > MAX_SIZE_BYTES) {
      setError('Ton CV ne doit pas dépasser 5 Mo.');
      return;
    }

    setError(null);
    setIsBusy(true);
    try {
      await onUpload(file);
    } catch {
      setError('Le dépôt a échoué. Vérifie ta connexion et réessaie.');
    } finally {
      setIsBusy(false);
    }
  }

  async function handleRemove() {
    setError(null);
    setIsBusy(true);
    try {
      await onRemove();
    } catch {
      setError('La suppression a échoué. Réessaie dans un instant.');
    } finally {
      setIsBusy(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="font-inter text-sm text-muted-foreground">
        Tu as déjà un CV qui te plaît ? Dépose-le ici en PDF (5 Mo maximum). C'est lui que
        les recruteurs verront en priorité, avant le CV généré par Jeuncy.
      </p>

      {fileUrl ? (
        <div className="flex flex-col gap-3">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="secondary">CV déposé</Badge>
            <a
              href={fileUrl}
              target="_blank"
              rel="noreferrer"
              className="font-inter text-sm text-primary hover:underline"
            >
              {originalFilename ?? 'Mon CV.pdf'}
            </a>
            {uploadedAt && (
              <span className="font-inter text-xs text-muted-foreground">
                déposé le {new Date(uploadedAt).toLocaleDateString('fr-FR')}
              </span>
            )}
          </div>
          <div className="flex flex-wrap gap-2">
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => inputRef.current?.click()}
              disabled={isBusy}
            >
              Remplacer
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => void handleRemove()}
              disabled={isBusy}
            >
              Supprimer
            </Button>
          </div>
        </div>
      ) : (
        <Button
          type="button"
          variant="outline"
          className="self-start"
          onClick={() => inputRef.current?.click()}
          disabled={isBusy}
        >
          {isBusy ? 'Envoi…' : 'Déposer mon CV (PDF)'}
        </Button>
      )}

      <input
        ref={inputRef}
        type="file"
        accept="application/pdf"
        onChange={(event) => void handleFileChange(event)}
        className="hidden"
      />

      {error && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {error}
        </p>
      )}
    </div>
  );
}
