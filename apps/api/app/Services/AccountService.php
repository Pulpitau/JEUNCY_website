<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Regroupe les actions RGPD "libre-service" (export + suppression) communes
// aux trois roles auto-gerables (CANDIDATE, COMPANY, CFA) : elles portent sur
// User et ses relations directes, pas sur un domaine metier en particulier,
// d'ou un service dedie plutot qu'un ajout a CandidateProfileService/
// CompanyService/CfaOrganizationService.
class AccountService
{
    public function __construct(
        private readonly CandidateProfileService $candidateProfileService,
        private readonly CompanyService $companyService,
        private readonly CfaOrganizationService $cfaOrganizationService,
        private readonly CvService $cvService,
    ) {}

    // Export "portabilite" (RGPD art. 20) : toutes les donnees rattachees au
    // compte, dans un format structure. Volontairement un tableau brut plutot
    // qu'une Resource formattee : c'est un instantane complet pour l'usager,
    // pas une reponse d'API a stabiliser dans le temps.
    public function exportData(User $user): array
    {
        $data = [
            'account' => [
                'email' => $user->email,
                'role' => $user->role->value,
                'created_at' => $user->created_at,
                'last_login_at' => $user->last_login_at,
            ],
            'notifications' => $user->notifications()->latest()->get(),
            'video_rooms' => $user->hostedVideoRooms()->get()
                ->merge($user->participatedVideoRooms()->get()),
        ];

        $candidateProfile = $user->candidateProfile()
            ->with(['experiences', 'educations', 'languages', 'skills', 'software', 'generatedCvs', 'applications.jobOffer:id,title'])
            ->first();
        if ($candidateProfile) {
            $data['candidate_profile'] = $candidateProfile;
        }

        $company = $user->company()->with(['jobOffers.skills'])->first();
        if ($company) {
            $data['company'] = $company;
            $data['payments'] = $user->payments()->get();
            $data['subscriptions'] = $user->subscriptions()->get();
        }

        $cfaOrganization = $user->cfaOrganization()->with(['jobOffers.skills'])->first();
        if ($cfaOrganization) {
            $data['cfa_organization'] = $cfaOrganization;
            $data['payments'] = $user->payments()->get();
            $data['subscriptions'] = $user->subscriptions()->get();
        }

        return $data;
    }

    // Suppression (RGPD art. 17, droit a l'effacement). Deux chemins selon
    // qu'il existe des Payment lies : la migration payments (voir
    // create_payments_table) attache volontairement user_id sans cascade
    // ("pieces comptables a conserver, obligation legale prioritaire sur
    // l'effacement RGPD") -> un hard delete de User echouerait sur la
    // contrainte de cle etrangere tant qu'un paiement existe. Dans ce cas on
    // supprime tout ce qui est effacable (profil, entreprise/CFA, offres,
    // fichiers stockes) et on anonymise le compte plutot que de le supprimer,
    // ce qui satisfait a la fois le droit a l'effacement des donnees
    // personnelles et l'obligation de conservation comptable.
    public function deleteAccount(User $user, string $confirmEmail): void
    {
        if ($user->role === UserRole::ADMIN) {
            throw new ApiException(
                'ADMIN_SELF_DELETE_FORBIDDEN',
                'Un compte administrateur ne peut pas être supprimé depuis cette interface. Contacte un autre administrateur.',
                403,
            );
        }

        if (mb_strtolower(trim($confirmEmail)) !== mb_strtolower($user->email)) {
            throw new ApiException('EMAIL_MISMATCH', "L'email saisi ne correspond pas à celui de ton compte.", 422);
        }

        // Meme obligation de conservation comptable pour les abonnements que
        // pour les paiements ponctuels (voir create_subscriptions_table) :
        // un compte ayant souscrit un abonnement, meme resilie depuis, est
        // anonymise plutot que supprime, comme pour hasPayments.
        $hasPayments = $user->payments()->exists() || $user->subscriptions()->exists();

        DB::transaction(function () use ($user, $hasPayments) {
            $this->deleteStoredFiles($user);

            if ($hasPayments) {
                $user->company?->delete();
                $user->cfaOrganization?->delete();
                $user->candidateProfile?->delete();

                $user->update([
                    'email' => 'compte-supprime-'.$user->id.'@jeuncy.invalid',
                    'password_hash' => null,
                    'google_id' => null,
                    'is_suspended' => true,
                ]);
                $user->increment('token_version');
            } else {
                $user->delete();
            }
        });
    }

    private function deleteStoredFiles(User $user): void
    {
        $candidateProfile = $user->candidateProfile;
        if ($candidateProfile) {
            if ($candidateProfile->photo_url) {
                $this->candidateProfileService->removePhoto($user);
            }
            foreach ($candidateProfile->generatedCvs as $cv) {
                $this->cvService->archive($cv);
            }
        }

        if ($user->company?->logo_url) {
            $this->companyService->removeLogo($user);
        }

        if ($user->cfaOrganization?->logo_url) {
            $this->cfaOrganizationService->removeLogo($user);
        }
    }
}
