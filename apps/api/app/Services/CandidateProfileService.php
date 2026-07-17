<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Skill;
use App\Models\User;

class CandidateProfileService
{
    public function getForUser(User $user): CandidateProfile
    {
        return $this->requireProfile($user)->load(['experiences', 'educations', 'skills']);
    }

    public function createForUser(User $user, array $data): CandidateProfile
    {
        if ($user->candidateProfile) {
            throw new ApiException('PROFILE_ALREADY_EXISTS', 'Un profil existe déjà pour ce compte.', 409);
        }

        $profile = $user->candidateProfile()->create($data);

        return $profile->load(['experiences', 'educations', 'skills']);
    }

    public function updateForUser(User $user, array $data): CandidateProfile
    {
        $profile = $this->requireProfile($user);
        $profile->update($data);

        return $profile->load(['experiences', 'educations', 'skills']);
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
