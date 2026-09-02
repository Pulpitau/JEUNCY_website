import { useState } from 'react';
import { Download } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { downloadCvthequeCv } from '@/lib/api/cvtheque';
import { ApiError } from '@/lib/api/client';

interface DownloadCvButtonProps {
  candidateId: number;
  // Vrai quand le PDF est celui que le candidat a lui-meme depose. Change le
  // libelle : un recruteur n'accorde pas le meme poids au document choisi par
  // le candidat qu'a une fiche mise en page par Jeuncy.
  hasUploadedCv: boolean;
  fallbackFilename: string;
}

export function DownloadCvButton({
  candidateId,
  hasUploadedCv,
  fallbackFilename,
}: DownloadCvButtonProps) {
  const [isDownloading, setIsDownloading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleDownload() {
    setError(null);
    setIsDownloading(true);

    let objectUrl: string | null = null;
    try {
      const { blob, filename } = await downloadCvthequeCv(candidateId);

      // Le serveur envoie le PDF en flux, jamais son URL : on reconstruit donc
      // un lien de telechargement ephemere cote navigateur. Revoque juste
      // apres pour ne pas garder le fichier en memoire.
      objectUrl = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = objectUrl;
      link.download = filename ?? fallbackFilename;
      document.body.appendChild(link);
      link.click();
      link.remove();
    } catch (caught) {
      setError(
        caught instanceof ApiError
          ? caught.message
          : 'Le téléchargement a échoué. Réessaie dans un instant.',
      );
    } finally {
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      setIsDownloading(false);
    }
  }

  return (
    <div className="flex flex-col gap-2">
      <Button
        type="button"
        variant="gradient"
        className="self-start"
        onClick={() => void handleDownload()}
        disabled={isDownloading}
      >
        <Download className="h-4 w-4" aria-hidden="true" />
        {isDownloading
          ? 'Téléchargement…'
          : hasUploadedCv
            ? 'Télécharger son CV'
            : 'Télécharger le CV'}
      </Button>

      <p className="font-inter text-xs text-muted-foreground">
        {hasUploadedCv
          ? 'CV déposé par le candidat.'
          : 'CV généré à partir du profil du candidat.'}{' '}
        Chaque téléchargement est enregistré.
      </p>

      {error && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {error}
        </p>
      )}
    </div>
  );
}
