import { useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ImportedCvData } from '@/lib/api/candidate-profile';

export interface ImportedCvApplyPayload {
  phone?: string;
  postal_code?: string;
  linkedin_url?: string;
  driving_license?: string;
  skills?: string[];
  software?: string[];
}

interface ImportCvSectionProps {
  onImport: (file: File) => Promise<ImportedCvData>;
  // Applique les suggestions retenues au profil. Absent tant que le profil
  // n'existe pas : il n'y a rien a mettre a jour avant sa creation.
  onApply?: (payload: ImportedCvApplyPayload) => Promise<unknown>;
  // Competences et logiciels deja presents, pour ne proposer que du nouveau et
  // ne jamais ecraser ce que le candidat a saisi a la main.
  existingSkills?: string[];
  existingSoftware?: string[];
}

function Suggestion({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex flex-col gap-0.5">
      <p className="font-inter text-xs text-muted-foreground">{label}</p>
      <p className="font-inter text-sm">{value}</p>
    </div>
  );
}

export function ImportCvSection({
  onImport,
  onApply,
  existingSkills = [],
  existingSoftware = [],
}: ImportCvSectionProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [isImporting, setIsImporting] = useState(false);
  const [isApplying, setIsApplying] = useState(false);
  const [applied, setApplied] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [result, setResult] = useState<ImportedCvData | null>(null);
  const [showRawText, setShowRawText] = useState(false);

  // Comparaison insensible a la casse : "photoshop" et "Photoshop" sont la
  // meme entree, la proposer en double serait du bruit.
  const newSkills = (result?.skills ?? []).filter(
    (s) => !existingSkills.some((e) => e.toLowerCase() === s.toLowerCase()),
  );
  const newSoftware = (result?.software ?? []).filter(
    (s) => !existingSoftware.some((e) => e.toLowerCase() === s.toLowerCase()),
  );

  const hasSomethingToApply =
    !!result &&
    (!!result.phone ||
      !!result.postal_code ||
      !!result.linkedin_url ||
      !!result.driving_license ||
      newSkills.length > 0 ||
      newSoftware.length > 0);

  async function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    setError(null);
    setApplied(false);
    setResult(null);
    setIsImporting(true);
    try {
      setResult(await onImport(file));
    } catch {
      setError("La lecture du PDF a échoué. Vérifie que le fichier n'est pas protégé.");
    } finally {
      setIsImporting(false);
    }
  }

  async function handleApply() {
    if (!result || !onApply) return;

    setError(null);
    setIsApplying(true);
    try {
      await onApply({
        phone: result.phone ?? undefined,
        postal_code: result.postal_code ?? undefined,
        linkedin_url: result.linkedin_url ?? undefined,
        driving_license: result.driving_license ?? undefined,
        // Fusion, jamais remplacement : ce que le candidat a saisi lui-meme
        // prime toujours sur ce qu'on a cru lire dans son PDF.
        skills: newSkills.length ? [...existingSkills, ...newSkills] : undefined,
        software: newSoftware.length ? [...existingSoftware, ...newSoftware] : undefined,
      });
      setApplied(true);
    } catch {
      setError("L'application des informations a échoué. Réessaie dans un instant.");
    } finally {
      setIsApplying(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="font-inter text-sm text-muted-foreground">
        Tu as déjà un CV ? Importe-le en PDF pour remplir ton profil plus vite. Jeuncy y
        cherche tes coordonnées, tes compétences et tes logiciels, puis te propose de les
        ajouter — tu gardes toujours la main sur ce qui est retenu.
      </p>

      <Button
        type="button"
        variant="outline"
        className="self-start"
        onClick={() => inputRef.current?.click()}
        disabled={isImporting}
      >
        {isImporting ? 'Analyse en cours…' : 'Analyser un CV (PDF)'}
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
        <div className="flex flex-col gap-4 rounded-lg border border-border p-4">
          {hasSomethingToApply ? (
            <>
              <div className="grid gap-3 sm:grid-cols-2">
                {result.phone && <Suggestion label="Téléphone" value={result.phone} />}
                {result.postal_code && (
                  <Suggestion label="Code postal" value={result.postal_code} />
                )}
                {result.linkedin_url && (
                  <Suggestion label="LinkedIn" value={result.linkedin_url} />
                )}
                {result.driving_license && (
                  <Suggestion label="Permis" value={result.driving_license} />
                )}
              </div>

              {newSkills.length > 0 && (
                <div className="flex flex-col gap-2">
                  <p className="font-inter text-xs text-muted-foreground">
                    Compétences trouvées
                  </p>
                  <div className="flex flex-wrap gap-1.5">
                    {newSkills.map((skill) => (
                      <Badge key={skill} variant="secondary">
                        {skill}
                      </Badge>
                    ))}
                  </div>
                </div>
              )}

              {newSoftware.length > 0 && (
                <div className="flex flex-col gap-2">
                  <p className="font-inter text-xs text-muted-foreground">
                    Logiciels trouvés
                  </p>
                  <div className="flex flex-wrap gap-1.5">
                    {newSoftware.map((software) => (
                      <Badge key={software} variant="secondary">
                        {software}
                      </Badge>
                    ))}
                  </div>
                </div>
              )}

              {onApply &&
                (applied ? (
                  <p className="font-inter text-sm text-muted-foreground">
                    Informations ajoutées à ton profil. Relis-les et corrige si besoin.
                  </p>
                ) : (
                  <Button
                    type="button"
                    variant="gradient"
                    className="self-start"
                    onClick={() => void handleApply()}
                    disabled={isApplying}
                  >
                    {isApplying ? 'Ajout…' : 'Ajouter à mon profil'}
                  </Button>
                ))}
            </>
          ) : (
            <p className="font-inter text-sm text-muted-foreground">
              Rien de nouveau à ajouter automatiquement depuis ce PDF. Le texte complet
              reste disponible ci-dessous pour compléter ton profil à la main.
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
