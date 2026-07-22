<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Exceptions\ApiException;
use App\Models\Application;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ApplicationService
{
    public function __construct(
        private readonly CandidateProfileService $candidateProfileService,
        private readonly JobOfferService $jobOfferService,
    ) {}

    public function applyForUser(User $user, JobOffer $jobOffer, ?string $coverLetter): Application
    {
        $profile = $this->candidateProfileService->requireProfile($user);

        if ($jobOffer->status !== JobOfferStatus::PUBLISHED) {
            throw new ApiException('JOB_OFFER_NOT_PUBLISHED', "Cette offre n'est plus disponible.", 409);
        }

        $alreadyApplied = Application::where('candidate_profile_id', $profile->id)
            ->where('job_offer_id', $jobOffer->id)
            ->exists();
        if ($alreadyApplied) {
            throw new ApiException('APPLICATION_ALREADY_EXISTS', 'Tu as déjà postulé à cette offre.', 409);
        }

        $application = Application::create([
            'candidate_profile_id' => $profile->id,
            'job_offer_id' => $jobOffer->id,
            'status' => ApplicationStatus::SENT,
            'cover_letter' => $coverLetter,
        ]);

        $owner = $this->jobOfferService->ownerUser($jobOffer);
        $owner?->notifications()->create([
            'type' => NotificationType::NEW_APPLICATION,
            'message' => "{$profile->first_name} {$profile->last_name} a postulé à ton offre \"{$jobOffer->title}\".",
            'link' => '/mes-offres',
        ]);

        return $application;
    }

    public function listForCandidate(User $user): Collection
    {
        $profile = $this->candidateProfileService->requireProfile($user);

        return $profile->applications()->with('jobOffer')->latest()->get();
    }

    public function listForOffer(User $user, JobOffer $jobOffer): Collection
    {
        $this->jobOfferService->requireOwnedOffer($user, $jobOffer);

        return $jobOffer->applications()->with('candidateProfile')->latest()->get();
    }

    public function updateStatus(User $user, Application $application, ApplicationStatus $status): Application
    {
        $jobOffer = $application->jobOffer;
        $this->jobOfferService->requireOwnedOffer($user, $jobOffer);

        $application->update(['status' => $status]);

        $candidateUser = $application->candidateProfile->user;
        $candidateUser->notifications()->create([
            'type' => NotificationType::APPLICATION_STATUS_CHANGED,
            'message' => "Le statut de ta candidature pour \"{$jobOffer->title}\" est maintenant : {$this->statusLabel($status)}.",
            'link' => '/mes-candidatures',
        ]);

        return $application;
    }

    private function statusLabel(ApplicationStatus $status): string
    {
        return match ($status) {
            ApplicationStatus::SENT => 'Envoyée',
            ApplicationStatus::SEEN => 'Vue',
            ApplicationStatus::INTERVIEW => 'Entretien',
            ApplicationStatus::ACCEPTED => 'Acceptée',
            ApplicationStatus::REJECTED => 'Refusée',
        };
    }
}
