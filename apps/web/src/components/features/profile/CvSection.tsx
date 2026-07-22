import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { GeneratedCv } from '@/lib/api/candidate-profile';

interface CvSectionProps {
  cvs: GeneratedCv[];
  onGenerate: () => Promise<unknown>;
  isGenerating: boolean;
}

export function CvSection({ cvs, onGenerate, isGenerating }: CvSectionProps) {
  return (
    <div className="flex flex-col gap-4">
      <p className="font-inter text-sm text-muted-foreground">
        Génère un CV PDF à partir de tes informations, tes expériences, tes formations et
        tes compétences.
      </p>
      <Button
        type="button"
        variant="gradient"
        className="self-start"
        onClick={() => void onGenerate()}
        disabled={isGenerating}
      >
        {isGenerating ? 'Génération…' : 'Générer mon CV (PDF)'}
      </Button>

      {cvs.length > 0 && (
        <div className="flex flex-col gap-2">
          <p className="font-poppins text-sm font-medium">CV générés</p>
          <ul className="flex flex-col gap-1">
            {cvs.map((cv) => (
              <li key={cv.id} className="flex items-center gap-2">
                {cv.archived_at ? (
                  <>
                    <span className="text-sm text-muted-foreground">
                      CV du {new Date(cv.generated_at).toLocaleString('fr-FR')}
                    </span>
                    <Badge variant="secondary">Archivé</Badge>
                  </>
                ) : (
                  <a
                    href={cv.file_url}
                    target="_blank"
                    rel="noreferrer"
                    className="text-sm text-primary hover:underline"
                  >
                    CV du {new Date(cv.generated_at).toLocaleString('fr-FR')}
                  </a>
                )}
              </li>
            ))}
          </ul>
          <p className="font-inter text-xs text-muted-foreground">
            Un CV inactif depuis 15 jours est archivé automatiquement (le fichier est
            supprimé, génère-en un nouveau si besoin).
          </p>
        </div>
      )}
    </div>
  );
}
