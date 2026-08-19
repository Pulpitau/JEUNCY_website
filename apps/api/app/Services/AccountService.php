<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        // Chemins releves AVANT la transaction : ils vivent sur les lignes
        // qu'elle va supprimer. Les fichiers, eux, ne seront effaces qu'une
        // fois la transaction validee (voir plus bas).
        $storedFiles = $this->storedFilePaths($user);

        DB::transaction(function () use ($user, $hasPayments) {
            if ($hasPayments) {
                $user->company?->delete();
                $user->cfaOrganization?->delete();
                $user->candidateProfile?->delete();

                $user->update([
                    // Suffixe aleatoire : l'adresse etait auparavant
                    // entierement previsible ('compte-supprime-{id}@...') et
                    // users.email est UNIQUE. Un tiers pouvait donc
                    // pre-enregistrer l'adresse d'une victime et faire
                    // echouer sa suppression RGPD, definitivement.
                    'email' => 'compte-supprime-'.$user->id.'-'.Str::lower(Str::random(12)).User::DELETED_EMAIL_DOMAIN,
                    'password_hash' => null,
                    'google_id' => null,
                    'is_suspended' => true,
                    'deleted_account_at' => now(),
                ]);
                $user->increment('token_version');
            } else {
                $user->delete();
            }
        });

        // APRES la transaction, et volontairement : supprimer un fichier
        // n'est pas annulable par un rollback SQL. En le faisant a
        // l'interieur, un echec de la transaction laissait le compte intact
        // mais ses fichiers detruits — photo et logo disparus, urls pointant
        // dans le vide. On n'efface donc que ce qui est deja acte en base.
        foreach ($storedFiles as $path) {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    // Chemins relatifs, sur le disque "public", de tous les fichiers
    // rattaches a ce compte. Releves avant toute suppression en base : une
    // fois les lignes parties, les urls ne sont plus lisibles nulle part et
    // les fichiers resteraient orphelins sur le disque.
    private function storedFilePaths(User $user): array
    {
        $urls = [];

        $candidateProfile = $user->candidateProfile;
        if ($candidateProfile) {
            $urls[] = $candidateProfile->photo_url;
            foreach ($candidateProfile->generatedCvs as $cv) {
                $urls[] = $cv->file_url;
            }
        }

        $urls[] = $user->company?->logo_url;
        $urls[] = $user->cfaOrganization?->logo_url;

        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        return collect($urls)
            ->filter()
            ->map(fn (string $url) => Str::startsWith($url, $base) ? substr($url, strlen($base)) : $url)
            ->values()
            ->all();
    }
}
