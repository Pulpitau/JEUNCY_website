import { useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import type {
  EducationInput,
  ExperienceInput,
  ImportedCvData,
  LanguageInput,
} from '@/lib/api/candidate-profile';

export interface ImportedCvApplyPayload {
  // Nom et prenom lus dans le CV. Servent a CREER le profil quand il n'existe
  // pas encore : c'est le parcours d'entree d'un nouveau candidat.
  identity: { first_name: string; last_name: string };
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
  // Absent tant que le profil n'existe pas : il n'y a rien a completer avant
  // sa creation.
  onApply: (payload: ImportedCvApplyPayload) => Promise<unknown>;
  // Le profil existe-t-il deja ? Change le message de fin, pas le traitement.
  hasProfile: boolean;
  existingSkills?: string[];
  existingSoftware?: string[];
  existingLanguages?: string[];
}

// Niveau retenu quand le CV cite une langue sans preciser son niveau : le
// champ est obligatoire cote profil, et "A2" est une hypothese basse que le
// candidat corrigera plus facilement qu'une hypothese flatteuse.
const DEFAULT_LANGUAGE_LEVEL = 'A2';

interface Added {
  profilCree: boolean;
  experiences: number;
  educations: number;
  languages: number;
  skills: number;
  software: number;
  info: number;
}

function summarize(added: Added): string[] {
  const plural = (n: number, one: string, many: string) => `${n} ${n > 1 ? many : one}`;
  const parts: string[] = [];
  if (added.experiences)
    parts.push(plural(added.experiences, 'expérience', 'expériences'));
  if (added.educations) parts.push(plural(added.educations, 'formation', 'formations'));
  if (added.languages) parts.push(plural(added.languages, 'langue', 'langues'));
  if (added.skills) parts.push(plural(added.skills, 'compétence', 'compétences'));
  if (added.software) parts.push(plural(added.software, 'logiciel', 'logiciels'));
  if (added.info) parts.push('tes coordonnées');
  return parts;
}

export function ImportCvSection({
  onImport,
  onApply,
  hasProfile,
  existingSkills = [],
  existingSoftware = [],
  existingLanguages = [],
}: ImportCvSectionProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [isWorking, setIsWorking] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [added, setAdded] = useState<Added | null>(null);
  const [rawText, setRawText] = useState<string | null>(null);
  const [showRawText, setShowRawText] = useState(false);

  async function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    setError(null);
    setAdded(null);
    setRawText(null);
    setIsWorking(true);

    try {
      const result = await onImport(file);
      setRawText(result.raw_text);

      // L'API renvoie-t-elle bien la forme attendue ? Un serveur qui n'a pas
      // recu la derniere version repond sans ces tableaux, et le code plus bas
      // echouerait sur un "undefined" — l'utilisateur ne verrait qu'un echec
      // generique alors que le PDF a ete lu correctement. Cas rencontre en
      // production le 2026-09-02, ou l'ancien code tournait encore.
      if (!Array.isArray(result.experiences) || !Array.isArray(result.educations)) {
        setError(
          "Le serveur n'a pas renvoyé les rubriques attendues. Le PDF est bien lu, mais l'API doit être mise à jour côté serveur avant que le profil puisse être rempli automatiquement.",
        );
        return;
      }

      // Sans profil existant, le nom est indispensable : c'est le seul champ
      // que la creation d'un profil exige et qu'on ne peut pas deviner.
      if (!hasProfile && (!result.first_name || !result.last_name)) {
        setError(
          "Je n'ai pas réussi à lire ton nom dans ce PDF. Renseigne ton prénom et ton nom en haut de la page, enregistre, puis relance l'import : le reste sera repris automatiquement.",
        );
        return;
      }

      // Comparaison insensible a la casse : "photoshop" et "Photoshop" sont la
      // meme entree, l'ajouter en double serait du bruit.
      const isNew = (value: string, existing: string[]) =>
        !existing.some((e) => e.toLowerCase() === value.toLowerCase());

      const newSkills = (result.skills ?? []).filter((s) => isNew(s, existingSkills));
      const newSoftware = (result.software ?? []).filter((s) =>
        isNew(s, existingSoftware),
      );
      const newLanguages = (result.languages ?? []).filter((l) =>
        isNew(l.name, existingLanguages),
      );

      // Le profil exige un intitule, une organisation et une date de debut.
      // Une entree incomplete n'est pas creee : mieux vaut que le candidat la
      // saisisse lui-meme qu'une ligne bancale a corriger.
      const experiences = result.experiences.filter(
        (e) => e.title && e.company && e.start_date,
      );
      const educations = result.educations.filter(
        (e) => e.degree && e.school && e.start_date,
      );

      const info = {
        phone: result.phone ?? undefined,
        postal_code: result.postal_code ?? undefined,
        linkedin_url: result.linkedin_url ?? undefined,
        driving_license: result.driving_license ?? undefined,
      };

      await onApply({
        identity: {
          first_name: result.first_name ?? '',
          last_name: result.last_name ?? '',
        },
        info,
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

      setAdded({
        profilCree: !hasProfile,
        experiences: experiences.length,
        educations: educations.length,
        languages: newLanguages.length,
        skills: newSkills.length,
        software: newSoftware.length,
        info: Object.values(info).filter(Boolean).length,
      });
    } catch {
      setError("La lecture du CV a échoué. Vérifie que le fichier n'est pas protégé.");
    } finally {
      setIsWorking(false);
    }
  }

  const summary = added ? summarize(added) : [];

  return (
    <div className="flex flex-col gap-4">
      <p className="font-inter text-sm text-muted-foreground">
        {hasProfile
          ? "Importe ton CV en PDF : Jeuncy le lit et complète directement ton profil — expériences, formations, langues, compétences et coordonnées. Tu relis et tu corriges ensuite, rien n'est définitif."
          : "Importe ton CV en PDF et Jeuncy crée ton profil pour toi : nom, coordonnées, expériences, formations, langues et compétences. Tu relis et tu corriges ensuite, rien n'est définitif."}
      </p>

      <Button
        type="button"
        variant="outline"
        className="self-start"
        onClick={() => inputRef.current?.click()}
        disabled={isWorking}
      >
        {isWorking
          ? 'Lecture du CV…'
          : hasProfile
            ? 'Compléter depuis mon CV (PDF)'
            : 'Créer mon profil depuis mon CV (PDF)'}
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

      {added && (
        <div className="flex flex-col gap-3 rounded-lg border border-border p-4">
          {summary.length > 0 ? (
            <p className="font-inter text-sm">
              <span className="font-poppins font-semibold">
                {added.profilCree ? 'Profil créé :' : 'Profil complété :'}
              </span>{' '}
              {summary.join(', ')}. Relis les rubriques ci-dessous et corrige ce qui ne va
              pas.
            </p>
          ) : (
            <p className="font-inter text-sm text-muted-foreground">
              Rien de nouveau n'a pu être repéré dans ce PDF. C'est fréquent avec les CV
              sur deux colonnes, dont le texte se mélange à la lecture. Le texte complet
              est disponible ci-dessous pour compléter ton profil à la main.
            </p>
          )}

          {rawText && (
            <div className="flex flex-col gap-2">
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
                  {rawText}
                </pre>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
