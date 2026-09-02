import { useRef, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type {
  EducationInput,
  ExperienceInput,
  ImportedCvData,
  LanguageInput,
} from '@/lib/api/candidate-profile';

export interface ImportedCvApplyPayload {
  info: {
    phone?: string;
    postal_code?: string;
    linkedin_url?: string;
    driving_license?: string;
  };
  skills?: string[];
  software?: string[];
  experiences: ExperienceInput[];
  educations: EducationInput[];
  languages: LanguageInput[];
}

interface ImportCvSectionProps {
  onImport: (file: File) => Promise<ImportedCvData>;
  // Applique les suggestions retenues. Absent tant que le profil n'existe
  // pas : il n'y a rien a completer avant sa creation.
  onApply?: (payload: ImportedCvApplyPayload) => Promise<unknown>;
  existingSkills?: string[];
  existingSoftware?: string[];
  existingLanguages?: string[];
}

// Niveau retenu quand le CV cite une langue sans preciser son niveau : le
// champ est obligatoire cote profil, et "A2" est une hypothese basse que le
// candidat corrigera plus facilement qu'une hypothese flatteuse.
const DEFAULT_LANGUAGE_LEVEL = 'A2';

function formatPeriod(start: string | null, end: string | null): string {
  const fmt = (d: string) =>
    new Date(d).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
  if (!start) return '';
  return `${fmt(start)} — ${end ? fmt(end) : "aujourd'hui"}`;
}

export function ImportCvSection({
  onImport,
  onApply,
  existingSkills = [],
  existingSoftware = [],
  existingLanguages = [],
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
  const isNew = (value: string, existing: string[]) =>
    !existing.some((e) => e.toLowerCase() === value.toLowerCase());

  const newSkills = (result?.skills ?? []).filter((s) => isNew(s, existingSkills));
  const newSoftware = (result?.software ?? []).filter((s) => isNew(s, existingSoftware));
  const newLanguages = (result?.languages ?? []).filter((l) =>
    isNew(l.name, existingLanguages),
  );

  // Le profil exige un intitule, une organisation et une date de debut. Une
  // entree incomplete est affichee mais pas appliquee : mieux vaut que le
  // candidat la saisisse lui-meme que de creer une ligne bancale.
  const experiences = (result?.experiences ?? []).filter(
    (e) => e.title && e.company && e.start_date,
  );
  const educations = (result?.educations ?? []).filter(
    (e) => e.degree && e.school && e.start_date,
  );

  const hasSomethingToApply =
    !!result &&
    (!!result.phone ||
      !!result.postal_code ||
      !!result.linkedin_url ||
      !!result.driving_license ||
      newSkills.length > 0 ||
      newSoftware.length > 0 ||
      newLanguages.length > 0 ||
      experiences.length > 0 ||
      educations.length > 0);

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
        info: {
          phone: result.phone ?? undefined,
          postal_code: result.postal_code ?? undefined,
          linkedin_url: result.linkedin_url ?? undefined,
          driving_license: result.driving_license ?? undefined,
        },
        // Fusion, jamais remplacement : ce que le candidat a saisi lui-meme
        // prime toujours sur ce qu'on a lu dans son PDF.
        skills: newSkills.length ? [...existingSkills, ...newSkills] : undefined,
        software: newSoftware.length ? [...existingSoftware, ...newSoftware] : undefined,
        experiences: experiences.map((e) => ({
          title: e.title,
          company: e.company!,
          start_date: e.start_date!,
          end_date: e.end_date,
          description: e.description,
        })),
        educations: educations.map((e) => ({
          degree: e.degree,
          school: e.school!,
          start_date: e.start_date!,
          end_date: e.end_date,
        })),
        languages: newLanguages.map((l) => ({
          name: l.name,
          level: l.level ?? DEFAULT_LANGUAGE_LEVEL,
        })),
      });
      setApplied(true);
    } catch {
      setError("L'ajout à ton profil a échoué. Réessaie dans un instant.");
    } finally {
      setIsApplying(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      <p className="font-inter text-sm text-muted-foreground">
        Tu as déjà un CV ? Importe-le en PDF : Jeuncy y repère tes expériences, tes
        formations, tes langues et tes coordonnées, et remplit ton profil d'un coup. Tu
        relis avant que ce soit ajouté, et tu peux tout modifier ensuite.
      </p>

      <Button
        type="button"
        variant="outline"
        className="self-start"
        onClick={() => inputRef.current?.click()}
        disabled={isImporting}
      >
        {isImporting ? 'Lecture du CV…' : 'Remplir mon profil depuis un CV (PDF)'}
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
        <div className="flex flex-col gap-5 rounded-lg border border-border p-4">
          {hasSomethingToApply ? (
            <>
              {experiences.length > 0 && (
                <section className="flex flex-col gap-2">
                  <p className="font-poppins text-sm font-semibold">
                    Expériences trouvées ({experiences.length})
                  </p>
                  <ul className="flex flex-col gap-2">
                    {experiences.map((e, i) => (
                      <li key={i} className="border-l-2 border-jeuncy-coral pl-3">
                        <p className="font-inter text-sm font-medium">{e.title}</p>
                        <p className="font-inter text-xs text-muted-foreground">
                          {e.company} · {formatPeriod(e.start_date, e.end_date)}
                        </p>
                      </li>
                    ))}
                  </ul>
                </section>
              )}

              {educations.length > 0 && (
                <section className="flex flex-col gap-2">
                  <p className="font-poppins text-sm font-semibold">
                    Formations trouvées ({educations.length})
                  </p>
                  <ul className="flex flex-col gap-2">
                    {educations.map((e, i) => (
                      <li key={i} className="border-l-2 border-jeuncy-orange pl-3">
                        <p className="font-inter text-sm font-medium">{e.degree}</p>
                        <p className="font-inter text-xs text-muted-foreground">
                          {e.school} · {formatPeriod(e.start_date, e.end_date)}
                        </p>
                      </li>
                    ))}
                  </ul>
                </section>
              )}

              {newLanguages.length > 0 && (
                <section className="flex flex-col gap-2">
                  <p className="font-poppins text-sm font-semibold">Langues trouvées</p>
                  <div className="flex flex-wrap gap-1.5">
                    {newLanguages.map((l) => (
                      <Badge key={l.name} variant="outline">
                        {l.name}
                        {l.level && ` — ${l.level}`}
                      </Badge>
                    ))}
                  </div>
                </section>
              )}

              {newSkills.length > 0 && (
                <section className="flex flex-col gap-2">
                  <p className="font-poppins text-sm font-semibold">
                    Compétences trouvées
                  </p>
                  <div className="flex flex-wrap gap-1.5">
                    {newSkills.map((skill) => (
                      <Badge key={skill} variant="secondary">
                        {skill}
                      </Badge>
                    ))}
                  </div>
                </section>
              )}

              {newSoftware.length > 0 && (
                <section className="flex flex-col gap-2">
                  <p className="font-poppins text-sm font-semibold">Logiciels trouvés</p>
                  <div className="flex flex-wrap gap-1.5">
                    {newSoftware.map((software) => (
                      <Badge key={software} variant="secondary">
                        {software}
                      </Badge>
                    ))}
                  </div>
                </section>
              )}

              {(result.phone ||
                result.postal_code ||
                result.linkedin_url ||
                result.driving_license) && (
                <section className="flex flex-col gap-2">
                  <p className="font-poppins text-sm font-semibold">Coordonnées</p>
                  <div className="grid gap-2 sm:grid-cols-2">
                    {result.phone && (
                      <p className="font-inter text-sm">Téléphone : {result.phone}</p>
                    )}
                    {result.postal_code && (
                      <p className="font-inter text-sm">
                        Code postal : {result.postal_code}
                      </p>
                    )}
                    {result.driving_license && (
                      <p className="font-inter text-sm">{result.driving_license}</p>
                    )}
                    {result.linkedin_url && (
                      <p className="truncate font-inter text-sm">
                        LinkedIn : {result.linkedin_url}
                      </p>
                    )}
                  </div>
                </section>
              )}

              {onApply &&
                (applied ? (
                  <p className="font-inter text-sm text-muted-foreground">
                    Ajouté à ton profil. Relis les rubriques ci-dessus et corrige ce qui
                    ne va pas.
                  </p>
                ) : (
                  <Button
                    type="button"
                    variant="gradient"
                    className="self-start"
                    onClick={() => void handleApply()}
                    disabled={isApplying}
                  >
                    {isApplying ? 'Ajout…' : 'Ajouter tout ça à mon profil'}
                  </Button>
                ))}
            </>
          ) : (
            <p className="font-inter text-sm text-muted-foreground">
              Rien de nouveau n'a pu être repéré dans ce PDF. C'est fréquent avec les CV
              sur deux colonnes, dont le texte se mélange à la lecture. Le texte complet
              reste disponible ci-dessous pour compléter ton profil à la main.
            </p>
          )}

          <div className="flex flex-col gap-2 border-t border-border pt-3">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="self-start"
              onClick={() => setShowRawText((value) => !value)}
            >
              {showRawText ? 'Masquer le texte du PDF' : 'Voir le texte du PDF'}
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
