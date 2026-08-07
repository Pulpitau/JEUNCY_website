<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Exceptions\ApiException;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\GeneratedCv;
use App\Models\JobOffer;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ApplicationService
{
    public function __construct(
        private readonly CandidateProfileService $candidateProfileService,
        private readonly JobOfferService $jobOfferService,
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function applyForUser(
        User $user,
        JobOffer $jobOffer,
        ?string $coverLetter,
        ?string $contactPhone = null,
        ?int $generatedCvId = null,
        ?UploadedFile $cvFile = null,
    ): Application {
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

        // Un fichier importe prime sur un CV genere si les deux sont fournis (ne
        // devrait pas arriver via le frontend, qui n'envoie que l'un des deux) :
        // generated_cv_id et cv_file_url restent mutuellement exclusifs en base.
        // Aucun des deux n'est requis ici (params optionnels) : les tests appellent
        // parfois ce service directement sans ces champs.
        $generatedCvId = $cvFile !== null ? null : $generatedCvId;
        $generatedCv = $generatedCvId !== null ? $this->requireOwnedCv($profile, $generatedCvId) : null;
        $cvFileUrl = $cvFile !== null ? $this->storeUploadedCv($profile, $cvFile) : null;

        $application = Application::create([
            'candidate_profile_id' => $profile->id,
            'job_offer_id' => $jobOffer->id,
            'status' => ApplicationStatus::SENT,
            'cover_letter' => $coverLetter,
            'contact_phone' => $contactPhone,
            'generated_cv_id' => $generatedCv?->id,
            'cv_file_url' => $cvFileUrl,
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

        return $profile->applications()->with(['jobOffer', 'generatedCv'])->latest()->get();
    }

    // candidateProfile.user restreint a id/email : l'employeur n'a besoin que
    // d'un moyen de contact, pas du reste du compte (role, is_suspended...).
    // Acces payant depuis le 2026-08-05 : soit cette offre precise a ete
    // debloquee (essai gratuit ou paiement a l'offre, voir
    // applications_unlocked_at), soit l'utilisateur a un abonnement actif qui
    // couvre toutes ses offres. 402 (Payment Required) plutot que 403 : le
    // frontend distingue "pas le droit" de "il faut payer" pour afficher le
    // bon message.
    public function listForOffer(User $user, JobOffer $jobOffer): Collection
    {
        $this->jobOfferService->requireOwnedOffer($user, $jobOffer);

        $hasAccess = $jobOffer->applications_unlocked_at !== null
            || $this->subscriptionService->hasActiveSubscription($user);

        if (! $hasAccess) {
            throw new ApiException(
                'APPLICATIONS_ACCESS_REQUIRED',
                "L'accès aux candidatures de cette offre nécessite un déblocage ou un abonnement actif.",
                402,
            );
        }

        return $jobOffer->applications()
            ->with(['candidateProfile.user:id,email', 'generatedCv'])
            ->latest()
            ->get();
    }

    // Rejette un CV appartenant a un autre candidat (IDOR) ou deja archive
    // (fichier supprime du disque par CvService::archive, le lien serait mort
    // pour l'employeur).
    private function requireOwnedCv(CandidateProfile $profile, int $generatedCvId): GeneratedCv
    {
        $cv = GeneratedCv::whereNull('archived_at')->find($generatedCvId);
        if (! $cv || $cv->candidate_profile_id !== $profile->id) {
            throw new ApiException('CV_NOT_FOUND', "Ce CV n'existe pas ou n'est plus disponible.", 404);
        }

        return $cv;
    }

    // Fichier propre a cette candidature (pas rattache au profil comme les CV
    // generes) : meme convention de nommage que CandidateProfileService::uploadPhoto.
    private function storeUploadedCv(CandidateProfile $profile, UploadedFile $file): string
    {
        $filename = $profile->id.'-'.Str::uuid().'.pdf';
        $path = $file->storeAs('application-cvs', $filename, 'public');

        return Storage::disk('public')->url($path);
    }

    // Annulation cote candidat (contrairement a updateStatus, reserve a
    // l'entreprise/CFA proprietaire de l'offre) : suppression definitive
    // plutot qu'un statut "WITHDRAWN" — permet au candidat de repostuler
    // ensuite si besoin (la contrainte unique candidate_profile_id/
    // job_offer_id l'en empecherait sinon), et evite d'ajouter une valeur a
    // l'enum natif applications.status pour un cas d'usage qui n'a pas besoin
    // de conserver l'historique cote candidat. L'entreprise/CFA est
    // notifiee avant la suppression, un CV importe (pas genere) rattache a
    // cette candidature est aussi supprime du disque (meme convention que
    // CandidateProfileService::removePhoto).
    public function withdrawForUser(User $user, Application $application): void
    {
        $profile = $this->candidateProfileService->requireProfile($user);
        if ($application->candidate_profile_id !== $profile->id) {
            throw new ApiException('FORBIDDEN', "Cette candidature ne t'appartient pas.", 403);
        }

        $jobOffer = $application->jobOffer;
        $owner = $this->jobOfferService->ownerUser($jobOffer);
        $owner?->notifications()->create([
            'type' => NotificationType::APPLICATION_STATUS_CHANGED,
            'message' => "{$profile->first_name} {$profile->last_name} a retiré sa candidature pour \"{$jobOffer->title}\".",
            'link' => '/mes-offres',
        ]);

        if ($application->cv_file_url) {
            $this->deleteStoredCv($application->cv_file_url);
        }

        $application->delete();
    }

    private function deleteStoredCv(string $cvFileUrl): void
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';
        $relativePath = Str::startsWith($cvFileUrl, $base) ? substr($cvFileUrl, strlen($base)) : $cvFileUrl;

        Storage::disk('public')->delete($relativePath);
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
