import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { ApiError } from '@/lib/api/client';
import type { ImportedCvData } from '@/lib/api/candidate-profile';

const MAX_SIZE_BYTES = 5 * 1024 * 1024;

interface ImportCvSectionProps {
  onImport: (file: File) => Promise<ImportedCvData>;
}

function CopyField({ label, value }: { label: string; value: string }) {
  const [copied, setCopied] = useState(false);

  async function handleCopy() {
    await navigator.clipboard.writeText(value);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  }

  return (
    <div className="flex items-center justify-between gap-3 rounded-md border border-border p-3">
      <div>
        <p className="font-inter text-xs text-muted-foreground">{label}</p>
        <p className="font-inter text-sm">{value}</p>
      </div>
      <Button type="button" variant="outline" size="sm" onClick={() => void handleCopy()}>
        {copied ? 'Copié !' : 'Copier'}
      </Button>
    </div>
  );
}

export function ImportCvSection({ onImport }: ImportCvSectionProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [isImporting, setIsImporting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<ImportedCvData | null>(null);
  const [showRawText, setShowRawText] = useState(false);

  async function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    if (file.type !== 'application/pdf') {
      setError('Seuls les fichiers PDF sont acceptés.');
      return;
    }
    if (file.size > MAX_SIZE_BYTES) {
      setError('Le fichier ne doit pas dépasser 5 Mo.');
      return;
    }

    setError(null);
    setResult(null);
    setIsImporting(true);
    try {
      const data = await onImport(file);
      setResult(data);
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : "Impossible d'analyser ce fichier pour le moment.",
      );
    } finally {
      setIsImporting(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="font-inter text-sm text-muted-foreground">
        Tu as déjà un CV ? Importe-le au format PDF pour en extraire automatiquement ton
        email et ton téléphone. L'extraction est faite au mieux (pas d'IA côté serveur) —
        vérifie et complète ensuite ton profil toi-même à partir du texte retrouvé
        ci-dessous.
      </p>

      <Button
        type="button"
        variant="outline"
        className="self-start"
        onClick={() => inputRef.current?.click()}
        disabled={isImporting}
      >
        {isImporting ? 'Analyse en cours…' : 'Importer un CV (PDF)'}
      </Button>
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

      {result && (
        <div className="flex flex-col gap-3">
          {result.email && <CopyField label="Email détecté" value={result.email} />}
          {result.phone && <CopyField label="Téléphone détecté" value={result.phone} />}
          {!result.email && !result.phone && (
            <p className="font-inter text-sm text-muted-foreground">
              Aucun email ni téléphone détecté automatiquement — le texte complet reste
              disponible ci-dessous.
            </p>
          )}

          <div className="flex flex-col gap-2">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="self-start"
              onClick={() => setShowRawText((value) => !value)}
            >
              {showRawText ? 'Masquer le texte extrait' : 'Voir le texte extrait du PDF'}
            </Button>
            {showRawText && (
              <pre className="max-h-64 overflow-auto whitespace-pre-wrap rounded-md border border-border bg-muted/30 p-3 font-inter text-xs text-muted-foreground">
                {result.raw_text || 'Aucun texte extrait.'}
              </pre>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
