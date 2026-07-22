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
            $this->deleteStoredPhoto($profile->photo_url);
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
            $this->deleteStoredPhoto($profile->photo_url);
            $profile->update(['photo_url' => null]);
        }

        return $profile->load(['experiences', 'educations', 'skills', 'languages', 'software']);
    }

    // Chemin absolu sur disque de la photo de profil, pour l'incorporer en base64
    // dans le PDF du CV (voir CvService) sans dependre d'un aller-retour HTTP.
    public function photoAbsolutePath(CandidateProfile $profile): ?string
    {
        if (! $profile->photo_url) {
            return null;
        }

        return Storage::disk('public')->path($this->relativePhotoPath($profile->photo_url));
    }

    private function relativePhotoPath(string $photoUrl): string
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        return Str::startsWith($photoUrl, $base) ? substr($photoUrl, strlen($base)) : $photoUrl;
    }

    private function deleteStoredPhoto(string $photoUrl): void
    {
        Storage::disk('public')->delete($this->relativePhotoPath($photoUrl));
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
