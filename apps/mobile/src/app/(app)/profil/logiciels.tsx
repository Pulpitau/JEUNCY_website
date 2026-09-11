import { TagListEditor } from '@/components/features/profile/tag-list-editor';
import { useCandidateProfile } from '@/hooks/use-candidate-profile';
import { syncSoftware } from '@/lib/api/candidate-profile';

export default function LogicielsScreen() {
  const profile = useCandidateProfile();

  return (
    <TagListEditor
      initial={profile.data?.software.map((s) => s.name) ?? []}
      onSave={syncSoftware}
      placeholder="Ex : Excel, Canva, Photoshop"
      hint="Les outils que tu maîtrises, même un peu : bureautique, logiciel de caisse, création graphique…"
    />
  );
}
