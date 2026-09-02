<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Language;
use App\Models\Skill;
use App\Models\Software;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CandidateProfileService
{
    public function getForUser(User $user): CandidateProfile
    {
        return $this->requireProfile($user)->load(['experiences', 'educations', 'skills', 'languages', 'software']);
    }

    public function createForUser(User $user, array $data): CandidateProfile
    {
        if ($user->candidateProfile) {
            throw new ApiException('PROFILE_ALREADY_EXISTS', 'Un profil existe déjà pour ce compte.', 409);
        }

        $profile = $user->candidateProfile()->create($data);

        return $profile->load(['experiences', 'educations', 'skills', 'languages', 'software']);
    }

    public function updateForUser(User $user, array $data): CandidateProfile
    {
        $profile = $this->requireProfile($user);
        $profile->update($data);

        return $profile->load(['experiences', 'educations', 'skills', 'languages', 'software']);
    }

    public function addExperience(User $user, array $data): Experience
    {
        return $this->requireProfile($user)->experiences()->create($data);
    }

    public function updateExperience(User $user, Experience $experience, array $data): Experience
    {
        $this->authorizeOwnership($user, $experience->candidate_profile_id);
        $experience->update($data);

        return $experience;
    }

    public function deleteExperience(User $user, Experience $experience): void
    {
        $this->authorizeOwnership($user, $experience->candidate_profile_id);
        $experience->delete();
    }

    public function addEducation(User $user, array $data): Education
    {
        return $this->requireProfile($user)->educations()->create($data);
    }

    public function updateEducation(User $user, Education $education, array $data): Education
    {
        $this->authorizeOwnership($user, $education->candidate_profile_id);
        $education->update($data);

        return $education;
    }

    public function deleteEducation(User $user, Education $education): void
    {
        $this->authorizeOwnership($user, $education->candidate_profile_id);
        $education->delete();
    }

    public function addLanguage(User $user, array $data): Language
    {
        return $this->requireProfile($user)->languages()->create($data);
    }

    public function deleteLanguage(User $user, Language $language): void
    {
        $this->authorizeOwnership($user, $language->candidate_profile_id);
        $language->delete();
    }

    public function syncSkills(User $user, array $names): CandidateProfile
    {
        $profile = $this->requireProfile($user);

        $skillIds = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Skill::firstOrCreate(['name' => $name])->id);

        $profile->skills()->sync($skillIds);

        return $profile->load('skills');
    }

    public function syncSoftware(User $user, array $names): CandidateProfile
    {
        $profile = $this->requireProfile($user);

        $softwareIds = collect($names)
            ->map(fn (string $name) => trim($name))
            ->filter()
            ->unique()
            ->map(fn (string $name) => Software::firstOrCreate(['name' => $name])->id);

        $profile->software()->sync($softwareIds);

        return $profile->load('software');
    }

    public function updatePhoto(User $user, UploadedFile $file): CandidateProfile
    {
        $profile = $this->requireProfile($user);

        if ($profile->photo_url) {
            $this->deleteStoredFile($profile->photo_url);
        }

        $filename = $profile->id.'-'.Str::uuid().'.'.$file->extension();
        $path = $file->storeAs('photos', $filename, 'public');
        $profile->update(['photo_url' => Storage::disk('public')->url($path)]);

        return $profile->load(['experiences', 'educations', 'skills', 'languages', 'software']);
    }

    public function removePhoto(User $user): CandidateProfile
    {
        $profile = $this->requireProfile($user);

        if ($profile->photo_url) {
            $this->deleteStoredFile($profile->photo_url);
            $profile->update(['photo_url' => null]);
        }

        return $profile->load(['experiences', 'educations', 'skills', 'languages', 'software']);
    }

    // CV depose par le candidat lui-meme, conserve tel quel : c'est le
    // document qu'il a choisi de presenter aux recruteurs, il passe donc avant
    // tout CV genere par Jeuncy (voir CvthequeService::resolveCvFor).
    // Un seul CV depose a la fois : deposer remplace, comme pour la photo.
    public function uploadCv(User $user, UploadedFile $file): CandidateProfile
    {
        $profile = $this->requireProfile($user);

        if ($profile->cv_file_url) {
            $this->deleteStoredFile($profile->cv_file_url);
        }

        // Meme precaution de nommage que pour les CV generes : un dossier
        // "cvs/" est bloque par defaut par Apache sur le mutualise OVH (voir
        // le commentaire detaille dans CvService::generate).
        $filename = $profile->id.'-'.Str::uuid().'.pdf';
        $path = $file->storeAs('uploaded-cvs', $filename, 'public');

        $profile->update([
            'cv_file_url' => Storage::disk('public')->url($path),
            // Nom d'origine assaini : il est reaffiche au candidat et sert de
            // nom de telechargement cote recruteur, donc il ne doit jamais
            // pouvoir porter de separateur de chemin ni d'en-tete HTTP.
            'cv_original_filename' => $this->sanitizeFilename($file->getClientOriginalName()),
            'cv_uploaded_at' => now(),
        ]);

        return $profile->load(['experiences', 'educations', 'skills', 'languages', 'software']);
    }

    public function removeCv(User $user): CandidateProfile
    {
        $profile = $this->requireProfile($user);

        if ($profile->cv_file_url) {
            $this->deleteStoredFile($profile->cv_file_url);
            $profile->update([
                'cv_file_url' => null,
                'cv_original_filename' => null,
                'cv_uploaded_at' => null,
            ]);
        }

        return $profile->load(['experiences', 'educations', 'skills', 'languages', 'software']);
    }

    // Chemin absolu sur disque du CV depose, pour le servir en telechargement
    // sans exposer l'URL publique du fichier au recruteur (voir CvthequeService).
    public function uploadedCvAbsolutePath(CandidateProfile $profile): ?string
    {
        if (! $profile->cv_file_url) {
            return null;
        }

        return Storage::disk('public')->path($this->relativeStoragePath($profile->cv_file_url));
    }

    // Garde le nom lisible par un humain mais neutralise tout ce qui pourrait
    // sortir du nom de fichier : separateurs de chemin, retours a la ligne
    // (injection d'en-tete Content-Disposition), et longueur excessive.
    private function sanitizeFilename(string $original): string
    {
        $base = basename(str_replace('\\', '/', $original));
        $base = preg_replace('/[\r\n"]+/', '', $base) ?? '';
        $base = trim($base);

        if ($base === '' || ! Str::endsWith(Str::lower($base), '.pdf')) {
            $base = 'cv.pdf';
        }

        return Str::limit($base, 120, '');
    }

    // Chemin absolu sur disque de la photo de profil, pour l'incorporer en base64
    // dans le PDF du CV (voir CvService) sans dependre d'un aller-retour HTTP.
    public function photoAbsolutePath(CandidateProfile $profile): ?string
    {
        if (! $profile->photo_url) {
            return null;
        }

        return Storage::disk('public')->path($this->relativeStoragePath($profile->photo_url));
    }

    // Utilise pour la photo comme pour le CV depose : les deux sont stockes
    // sur le meme disque public et referencés par leur URL complete en base.
    private function relativeStoragePath(string $url): string
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        return Str::startsWith($url, $base) ? substr($url, strlen($base)) : $url;
    }

    private function deleteStoredFile(string $url): void
    {
        Storage::disk('public')->delete($this->relativeStoragePath($url));
    }

    public function requireProfile(User $user): CandidateProfile
    {
        $profile = $user->candidateProfile;
        if (! $profile) {
            throw new ApiException('PROFILE_NOT_FOUND', "Aucun profil candidat n'existe encore pour ce compte.", 404);
        }

        return $profile;
    }

    // "L'appartenance" est verifiee via l'id du profil plutot que via une requete
    // scopee, car les modeles Experience/Education sont deja resolus par route
    // model binding (Laravel) avant d'atteindre le service.
    private function authorizeOwnership(User $user, int $candidateProfileId): void
    {
        if ($this->requireProfile($user)->id !== $candidateProfileId) {
            throw new ApiException('FORBIDDEN', "Cette ressource ne t'appartient pas.", 403);
        }
    }
}
