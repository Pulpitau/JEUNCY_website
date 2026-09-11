import { TagListEditor } from '@/components/features/profile/tag-list-editor';
import { useCandidateProfile } from '@/hooks/use-candidate-profile';
import { syncSkills } from '@/lib/api/candidate-profile';

export default function CompetencesScreen() {
  const profile = useCandidateProfile();

  return (
    <TagListEditor
      initial={profile.data?.skills.map((s) => s.name) ?? []}
      onSave={syncSkills}
      placeholder="Ex : Vente, accueil, comptabilité"
      hint="Ce que tu sais faire. Les entreprises cherchent aussi par compétence, et l'app te prévient quand une offre en demande une que tu as."
    />
  );
}
