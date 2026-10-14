import { useState } from 'react';

import { downloadApplicationCv } from '@/lib/api/applications';
import { ApiError } from '@/lib/api/client';

// Telecharge le CV d'un dossier recu. Le serveur envoie le PDF en flux, jamais
// son adresse (meme principe que DownloadCvButton pour la CVtheque) : on
// reconstruit un lien ephemere cote navigateur, revoque aussitot.
export function useApplicationCvDownload(applicationId: number) {
  const [pending, setPending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function download() {
    setError(null);
    setPending(true);
    let objectUrl: string | null = null;
    try {
      const { blob, filename } = await downloadApplicationCv(applicationId);
      objectUrl = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = objectUrl;
      link.download = filename ?? 'CV.pdf';
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
      setPending(false);
    }
  }

  return { download, pending, error };
}
