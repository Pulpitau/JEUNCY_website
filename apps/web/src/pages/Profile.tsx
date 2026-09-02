import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { ProfileInfoForm } from '@/components/features/profile/ProfileInfoForm';
import { ProfileInfoSummary } from '@/components/features/profile/ProfileInfoSummary';
import { ProfilePhotoUpload } from '@/components/features/profile/ProfilePhotoUpload';
import { ExperienceSection } from '@/components/features/profile/ExperienceSection';
import { EducationSection } from '@/components/features/profile/EducationSection';
import { LanguagesSection } from '@/components/features/profile/LanguagesSection';
import { SkillsSection } from '@/components/features/profile/SkillsSection';
import { SoftwareSection } from '@/components/features/profile/SoftwareSection';
import { CvSection } from '@/components/features/profile/CvSection';
import { OwnCvSection } from '@/components/features/profile/OwnCvSection';
import { ImportCvSection } from '@/components/features/profile/ImportCvSection';
import type { ImportedCvApplyPayload } from '@/components/features/profile/ImportCvSection';
import { CvthequeVisibilitySection } from '@/components/features/profile/CvthequeVisibilitySection';
import {
  getMyProfile,
  createProfile,
  updateProfile,
  addExperience,
  deleteExperience,
  addEducation,
  deleteEducation,
  addLanguage,
  deleteLanguage,
  syncSkills,
  syncSoftware,
  uploadProfilePhoto,
  removeProfilePhoto,
  generateCv,
  listGeneratedCvs,
  importCv,
  uploadOwnCv,
  removeOwnCv,
} from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';
import { useStagedProfileSections } from '@/hooks/use-staged-profile-sections';

const PROFILE_QUERY_KEY = ['candidate-profile'];
const CVS_QUERY_KEY = ['candidate-profile', 'cv'];

export function Profile() {
  const queryClient = useQueryClient();
  const [isEditingInfo, setIsEditingInfo] = useState(false);
  const staged = useStagedProfileSections();

  const profileQuery = useQuery({
    queryKey: PROFILE_QUERY_KEY,
    queryFn: getMyProfile,
    retry: false,
  });

  const profile = profileQuery.data ?? null;
  // Un candidat sans profil recoit un 404 PROFILE_NOT_FOUND : pas une erreur a
  // afficher, juste le signal qu'il faut proposer le formulaire de creation.
  const unexpectedError =
    profileQuery.isError &&
    !(
      profileQuery.error instanceof ApiError &&
      profileQuery.error.code === 'PROFILE_NOT_FOUND'
    )
      ? profileQuery.error
      : null;

  const cvsQuery = useQuery({
    queryKey: CVS_QUERY_KEY,
    queryFn: listGeneratedCvs,
    enabled: !!profile,
  });

  function invalidateProfile() {
    return queryClient.invalidateQueries({ queryKey: PROFILE_QUERY_KEY });
  }

  // Le profil est cree d'abord, puis les elements saisis avant lui sont
  // envoyes : ils ont besoin de son id. L'invalidation vient apres le flush,
  // sinon le rechargement renverrait un profil sans ses experiences et
  // l'ecran clignoterait entre les deux etats.
  const createMutation = useMutation({
    mutationFn: async (values: Parameters<typeof createProfile>[0]) => {
      const created = await createProfile(values);
      await staged.flush();
      staged.clear();
      return created;
    },
    onSuccess: () => {
      invalidateProfile();
      setIsEditingInfo(false);
    },
  });
  const updateMutation = useMutation({
    mutationFn: updateProfile,
    onSuccess: () => {
      invalidateProfile();
      setIsEditingInfo(false);
    },
  });
  const addExperienceMutation = useMutation({
    mutationFn: addExperience,
    onSuccess: invalidateProfile,
  });
  const deleteExperienceMutation = useMutation({
    mutationFn: deleteExperience,
    onSuccess: invalidateProfile,
  });
  const addEducationMutation = useMutation({
    mutationFn: addEducation,
    onSuccess: invalidateProfile,
  });
  const deleteEducationMutation = useMutation({
    mutationFn: deleteEducation,
    onSuccess: invalidateProfile,
  });
  const addLanguageMutation = useMutation({
    mutationFn: addLanguage,
    onSuccess: invalidateProfile,
  });
  const deleteLanguageMutation = useMutation({
    mutationFn: deleteLanguage,
    onSuccess: invalidateProfile,
  });
  const syncSkillsMutation = useMutation({
    mutationFn: syncSkills,
    onSuccess: invalidateProfile,
  });
  const syncSoftwareMutation = useMutation({
    mutationFn: syncSoftware,
    onSuccess: invalidateProfile,
  });
  const uploadPhotoMutation = useMutation({
    mutationFn: uploadProfilePhoto,
    onSuccess: invalidateProfile,
  });
  const removePhotoMutation = useMutation({
    mutationFn: removeProfilePhoto,
    onSuccess: invalidateProfile,
  });
  const uploadOwnCvMutation = useMutation({
    mutationFn: uploadOwnCv,
    onSuccess: invalidateProfile,
  });
  const removeOwnCvMutation = useMutation({
    mutationFn: removeOwnCv,
    onSuccess: invalidateProfile,
  });
  const generateCvMutation = useMutation({
    mutationFn: generateCv,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CVS_QUERY_KEY }),
  });
  const generateCvError =
    generateCvMutation.error instanceof ApiError
      ? generateCvMutation.error.message
      : generateCvMutation.isError
        ? 'Impossible de générer le CV pour le moment.'
        : null;

  // Applique les suggestions issues d'un CV importe. Les champs de base et les
  // referentiels passent par des endpoints differents, d'ou les appels
  // separes ; ils sont sequentiels et non paralleles pour que le profil ne
  // soit invalide qu'une fois, a la fin.
  async function applyImportedCv(payload: ImportedCvApplyPayload) {
    const { skills, software, ...info } = payload;

    if (Object.keys(info).length > 0) {
      await updateProfile(info);
    }
    if (skills) {
      await syncSkills(skills);
    }
    if (software) {
      await syncSoftware(software);
    }

    await invalidateProfile();
  }

  if (profileQuery.isLoading) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-12">
        <p className="font-inter text-sm text-muted-foreground">Chargement du profil…</p>
      </main>
    );
  }

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Mon profil</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Complète ton profil pour générer ton CV et postuler aux offres.
        </p>
      </div>

      {unexpectedError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger ton profil pour le moment, réessaie plus tard.
        </p>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Informations personnelles</CardTitle>
          <CardDescription>
            {profile
              ? 'Modifie tes informations à tout moment.'
              : 'Crée ton profil pour commencer.'}
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-6">
          {profile && (
            <ProfilePhotoUpload
              photoUrl={profile.photo_url}
              firstName={profile.first_name}
              lastName={profile.last_name}
              isUploading={uploadPhotoMutation.isPending}
              isRemoving={removePhotoMutation.isPending}
              onUpload={(file) => uploadPhotoMutation.mutateAsync(file)}
              onRemove={() => removePhotoMutation.mutateAsync()}
            />
          )}
          {profile && !isEditingInfo ? (
            <ProfileInfoSummary profile={profile} onEdit={() => setIsEditingInfo(true)} />
          ) : (
            <ProfileInfoForm
              profile={profile}
              isSubmitting={createMutation.isPending || updateMutation.isPending}
              onCancel={profile ? () => setIsEditingInfo(false) : undefined}
              onSubmit={(values) =>
                profile
                  ? updateMutation.mutateAsync(values)
                  : createMutation.mutateAsync(values)
              }
            />
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Importer un CV existant</CardTitle>
        </CardHeader>
        <CardContent>
          <ImportCvSection
            onImport={(file) => importCv(file)}
            existingSkills={profile?.skills.map((s) => s.name) ?? []}
            existingSoftware={profile?.software.map((s) => s.name) ?? []}
            // Absent tant que le profil n'existe pas : il n'y a rien a mettre
            // a jour, le candidat cree d'abord ses informations de base.
            onApply={profile ? applyImportedCv : undefined}
          />
        </CardContent>
      </Card>

      {/* Sections toujours affichees, profil enregistre ou non. Avant
          enregistrement, ce qui est saisi ici est garde cote client puis
          envoye d'un bloc a la creation du profil (voir
          useStagedProfileSections) : le candidat remplit sa page dans
          l'ordre qu'il veut, en un seul passage, au lieu de devoir d'abord
          valider ses infos de base pour voir le reste apparaitre. */}
      <Card>
        <CardHeader>
          <CardTitle>Expériences</CardTitle>
        </CardHeader>
        <CardContent>
          <ExperienceSection
            experiences={profile ? profile.experiences : staged.experiences}
            isSubmitting={addExperienceMutation.isPending}
            onAdd={(values) =>
              profile
                ? addExperienceMutation.mutateAsync(values)
                : staged.addExperience(values)
            }
            onDelete={(id) =>
              profile
                ? deleteExperienceMutation.mutateAsync(id)
                : staged.removeExperience(id)
            }
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Formations</CardTitle>
        </CardHeader>
        <CardContent>
          <EducationSection
            educations={profile ? profile.educations : staged.educations}
            isSubmitting={addEducationMutation.isPending}
            onAdd={(values) =>
              profile
                ? addEducationMutation.mutateAsync(values)
                : staged.addEducation(values)
            }
            onDelete={(id) =>
              profile
                ? deleteEducationMutation.mutateAsync(id)
                : staged.removeEducation(id)
            }
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Langues</CardTitle>
        </CardHeader>
        <CardContent>
          <LanguagesSection
            languages={profile ? profile.languages : staged.languages}
            isSubmitting={addLanguageMutation.isPending}
            onAdd={(values) =>
              profile
                ? addLanguageMutation.mutateAsync(values)
                : staged.addLanguage(values)
            }
            onDelete={(id) =>
              profile ? deleteLanguageMutation.mutateAsync(id) : staged.removeLanguage(id)
            }
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Compétences</CardTitle>
        </CardHeader>
        <CardContent>
          <SkillsSection
            skills={profile ? profile.skills : staged.skills}
            isSubmitting={syncSkillsMutation.isPending}
            onSync={(names) =>
              profile ? syncSkillsMutation.mutateAsync(names) : staged.setSkills(names)
            }
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Logiciels</CardTitle>
        </CardHeader>
        <CardContent>
          <SoftwareSection
            software={profile ? profile.software : staged.software}
            isSubmitting={syncSoftwareMutation.isPending}
            onSync={(names) =>
              profile
                ? syncSoftwareMutation.mutateAsync(names)
                : staged.setSoftware(names)
            }
          />
        </CardContent>
      </Card>

      {/* Ces deux-la restent conditionnees a un profil enregistre, et pour
          une bonne raison : le PDF est fabrique par le serveur a partir du
          profil, et il n'y a rien a rendre visible dans la CVtheque tant
          qu'il n'existe pas. Un encart explicite le dit plutot que de les
          faire apparaitre sans prevenir. */}
      {profile ? (
        <>
          {/* Deux chemins volontairement distincts vers le meme besoin :
              deposer le CV qu'on a deja, ou en faire fabriquer un. Le CV
              depose est propose en premier parce que c'est celui que les
              recruteurs verront en priorite. */}
          <Card>
            <CardHeader>
              <CardTitle>Mon CV</CardTitle>
              <CardDescription>
                Dépose le tien, ou laisse Jeuncy en générer un à partir de ton profil.
              </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-8">
              <OwnCvSection
                fileUrl={profile.cv_file_url}
                originalFilename={profile.cv_original_filename}
                uploadedAt={profile.cv_uploaded_at}
                onUpload={(file) => uploadOwnCvMutation.mutateAsync(file)}
                onRemove={() => removeOwnCvMutation.mutateAsync()}
              />

              <div className="border-t border-border pt-8">
                <CvSection
                  cvs={cvsQuery.data ?? []}
                  isGenerating={generateCvMutation.isPending}
                  error={generateCvError}
                  onGenerate={() => generateCvMutation.mutateAsync()}
                />
              </div>
            </CardContent>
          </Card>

          <CvthequeVisibilitySection
            isVisible={profile.is_visible_in_cvtheque}
            queryKey={PROFILE_QUERY_KEY}
          />
        </>
      ) : (
        <Card>
          <CardHeader>
            <CardTitle>Mon CV</CardTitle>
            <CardDescription>
              La génération de ton CV et ta visibilité dans la CVthèque s'activent dès que
              tu enregistres tes informations personnelles.
            </CardDescription>
          </CardHeader>
        </Card>
      )}
    </main>
  );
}
