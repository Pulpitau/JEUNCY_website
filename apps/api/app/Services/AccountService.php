<?php

namespace App\Services;

use App\Enums\MatchClosedReason;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\Report;
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
        private readonly MatchClosingService $matchClosingService,
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
            // Blocages poses par le titulaire et signalements qu'il a emis :
            // ce sont des donnees le concernant, au meme titre que ses
            // candidatures. Les blocages SUBIS ne sont pas exportes — ils
            // sont la donnee de quelqu'un d'autre, et les rendre lisibles
            // reviendrait a dire a un utilisateur qui l'a bloque.
            'user_blocks' => $user->blocks()->get(),
            // Requete directe plutot qu'une relation : User n'en declare pas
            // pour les signalements emis (app/Models/User.php, lot F), et
            // l'export n'est pas une raison suffisante d'en ajouter une.
            'reports' => Report::query()->where('reporter_user_id', $user->id)->latest('id')->get(),
        ];

        $candidateProfile = $user->candidateProfile()
            ->with(['experiences', 'educations', 'languages', 'skills', 'software', 'generatedCvs', 'applications.jobOffer:id,title'])
            ->first();
        if ($candidateProfile) {
            // Sans ce makeVisible, $hidden retire du profil les deux paires de
            // coordonnees (geocodees et GPS) : un export « complet » qui tait
            // la position stockee sur le serveur n'est pas complet.
            $data['candidate_profile'] = $candidateProfile->makeVisible(CandidateProfile::OWNER_VISIBLE);

            // Gestes du modele match : intentions exprimees par le titulaire
            // sur des offres Jeuncy et sur des offres partenaires. Le titre de
            // l'offre suffit a rendre la ligne lisible.
            $data['offer_interests'] = $candidateProfile->offerInterests()
                ->with('jobOffer:id,title')
                ->latest('id')
                ->get();
            $data['external_interests'] = $candidateProfile->externalInterests()
                ->latest('id')
                ->get();
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

        $this->closeMatchesBeforeDeletion($user);

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

    /**
     * Fermer les interets ouverts AVANT de supprimer quoi que ce soit.
     *
     * Les deux chemins de suppression font partir les lignes offer_interests
     * en cascade : par candidate_profile_id pour un candidat, et par
     * job_offer_id pour un employeur (l'organisation supprimee emmene ses
     * offres). Sans cette passe, l'autre partie d'un match verrait sa carte
     * disparaitre sans un mot — et cote employeur, personne n'aurait meme
     * su qu'il y avait un match a fermer.
     *
     * Appelee hors transaction : les notifications sont un effet visible, on
     * ne les cree pas dans une transaction qui pourrait encore echouer sur
     * une contrainte de cle etrangere.
     */
    private function closeMatchesBeforeDeletion(User $user): void
    {
        $profile = $user->candidateProfile;
        if ($profile) {
            $this->matchClosingService->closeForCandidateProfile($profile, MatchClosedReason::ACCOUNT_DELETED);
        }

        $organization = $user->company ?? $user->cfaOrganization;
        if ($organization) {
            foreach ($organization->jobOffers()->get() as $offer) {
                $this->matchClosingService->closeForOffer($offer, MatchClosedReason::ACCOUNT_DELETED);
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
