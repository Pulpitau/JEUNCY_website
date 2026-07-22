import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { ProfileInfoForm } from '@/components/features/profile/ProfileInfoForm';
import { ProfilePhotoUpload } from '@/components/features/profile/ProfilePhotoUpload';
import { ExperienceSection } from '@/components/features/profile/ExperienceSection';
import { EducationSection } from '@/components/features/profile/EducationSection';
import { LanguagesSection } from '@/components/features/profile/LanguagesSection';
import { SkillsSection } from '@/components/features/profile/SkillsSection';
import { SoftwareSection } from '@/components/features/profile/SoftwareSection';
import { CvSection } from '@/components/features/profile/CvSection';
import { ImportCvSection } from '@/components/features/profile/ImportCvSection';
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
} from '@/lib/api/candidate-profile';
import { ApiError } from '@/lib/api/client';

const PROFILE_QUERY_KEY = ['candidate-profile'];
const CVS_QUERY_KEY = ['candidate-profile', 'cv'];

export function Profile() {
  const queryClient = useQueryClient();

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

  const createMutation = useMutation({
    mutationFn: createProfile,
    onSuccess: invalidateProfile,
  });
  const updateMutation = useMutation({
    mutationFn: updateProfile,
    onSuccess: invalidateProfile,
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
  const generateCvMutation = useMutation({
    mutationFn: generateCv,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CVS_QUERY_KEY }),
  });

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
          <ProfileInfoForm
            profile={profile}
            isSubmitting={createMutation.isPending || updateMutation.isPending}
            onSubmit={(values) =>
              profile
                ? updateMutation.mutateAsync(values)
                : createMutation.mutateAsync(values)
            }
          />
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Importer un CV existant</CardTitle>
        </CardHeader>
        <CardContent>
          <ImportCvSection onImport={(file) => importCv(file)} />
        </CardContent>
      </Card>

      {profile && (
        <>
          <Card>
            <CardHeader>
              <CardTitle>Expériences</CardTitle>
            </CardHeader>
            <CardContent>
              <ExperienceSection
                experiences={profile.experiences}
                isSubmitting={addExperienceMutation.isPending}
                onAdd={(values) => addExperienceMutation.mutateAsync(values)}
                onDelete={(id) => deleteExperienceMutation.mutateAsync(id)}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Formations</CardTitle>
            </CardHeader>
            <CardContent>
              <EducationSection
                educations={profile.educations}
                isSubmitting={addEducationMutation.isPending}
                onAdd={(values) => addEducationMutation.mutateAsync(values)}
                onDelete={(id) => deleteEducationMutation.mutateAsync(id)}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Langues</CardTitle>
            </CardHeader>
            <CardContent>
              <LanguagesSection
                languages={profile.languages}
                isSubmitting={addLanguageMutation.isPending}
                onAdd={(values) => addLanguageMutation.mutateAsync(values)}
                onDelete={(id) => deleteLanguageMutation.mutateAsync(id)}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Compétences</CardTitle>
            </CardHeader>
            <CardContent>
              <SkillsSection
                skills={profile.skills}
                isSubmitting={syncSkillsMutation.isPending}
                onSync={(names) => syncSkillsMutation.mutateAsync(names)}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Logiciels</CardTitle>
            </CardHeader>
            <CardContent>
              <SoftwareSection
                software={profile.software}
                isSubmitting={syncSoftwareMutation.isPending}
                onSync={(names) => syncSoftwareMutation.mutateAsync(names)}
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Mon CV</CardTitle>
            </CardHeader>
            <CardContent>
              <CvSection
                cvs={cvsQuery.data ?? []}
                isGenerating={generateCvMutation.isPending}
                onGenerate={() => generateCvMutation.mutateAsync()}
              />
            </CardContent>
          </Card>
        </>
      )}
    </main>
  );
}
