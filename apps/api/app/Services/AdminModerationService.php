<?php

namespace App\Services;

use App\Enums\MatchReminderStage;
use App\Enums\VerificationStatus;
use App\Models\Application;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\Report;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Les trois files de moderation du modele match (MOBILE.md §10, lot 4).
 *
 * A PART D'AdminService, qui fait deja 350 lignes sur six domaines sans
 * rapport entre eux. Celles-ci en ont un : ce sont les trois endroits ou
 * quelqu'un de l'equipe doit agir vite sur une situation impliquant un
 * candidat, souvent mineur.
 *
 *   - signalements : objectif 24 h (§7) ;
 *   - verifications en attente : combler le trou assume du lot 1 — une
 *     organisation passee PENDING parce que le registre public etait muet y
 *     restait jusqu'a sa prochaine modification de fiche, c'est-a-dire
 *     peut-etre jamais ;
 *   - employeurs silencieux : l'engagement de reponse du produit, rendu
 *     visible a l'equipe au moment ou il n'est plus tenu.
 */
class AdminModerationService
{
    public const PAR_PAGE = 20;

    // -----------------------------------------------------------------
    // Signalements
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Report>
     */
    public function listReports(array $filters): LengthAwarePaginator
    {
        return Report::query()
            ->with([
                'reporter:id,email,role',
                'reported:id,email,role',
                'jobOffer:id,title',
            ])
            // En attente d'abord, et les plus anciens en tete de file : c'est
            // l'ordre dans lequel on veut les traiter, pas l'inverse. Un
            // signalement de trois jours est plus urgent qu'un de ce matin.
            ->when(
                ($filters['status'] ?? 'PENDING') === 'PENDING',
                fn ($q) => $q->whereNull('handled_at')->oldest(),
                fn ($q) => $q->whereNotNull('handled_at')->latest('handled_at'),
            )
            ->paginate(self::PAR_PAGE);
    }

    /**
     * Marque un signalement traite. Ne suspend rien tout seul : suspendre un
     * compte reste un geste separe et explicite (`admin/users/{id}/suspend`),
     * parce qu'il coupe l'acces d'une personne.
     */
    public function handleReport(User $admin, Report $report): Report
    {
        $report->handled_by = $admin->id;
        $report->handled_at = now();
        $report->save();

        return $report->fresh(['reporter:id,email', 'reported:id,email']);
    }

    // -----------------------------------------------------------------
    // Verifications en attente
    // -----------------------------------------------------------------

    /**
     * Entreprises et CFA qui attendent une decision humaine.
     *
     * Les deux tables dans une seule liste, triee par anciennete : l'equipe
     * traite « qui attend depuis quand », pas « quelle table ». La
     * pagination est volontairement absente — s'il y a plus de vingt
     * organisations en attente, le probleme n'est pas l'ecran.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listPendingVerifications(): Collection
    {
        $entreprises = Company::query()
            ->where('verification_status', VerificationStatus::PENDING->value)
            ->with('user:id,email')
            ->get()
            ->map(fn (Company $c) => $this->ligneVerification('COMPANY', $c->id, $c->name, $c->siret, $c->verification_note, $c->user?->email, $c->created_at));

        $cfa = CfaOrganization::query()
            ->where('verification_status', VerificationStatus::PENDING->value)
            ->with('user:id,email')
            ->get()
            ->map(fn (CfaOrganization $c) => $this->ligneVerification('CFA', $c->id, $c->name, $c->siret, $c->verification_note, $c->user?->email, $c->created_at));

        return $entreprises->concat($cfa)->sortBy('created_at')->values();
    }

    /**
     * Decision humaine sur une organisation.
     *
     * POURQUOI CE BOUTON EXISTE. La verification automatique interroge le
     * registre public ; quand il ne repond pas, elle laisse PENDING — jamais
     * VERIFIED par defaut, et c'est la bonne prudence. Mais sans geste
     * manuel, une entreprise legitime restait bloquee indefiniment par une
     * panne qui n'etait pas la sienne.
     *
     * La note est OBLIGATOIRE : une verification accordee a la main sans
     * raison ecrite est indistinguable d'une erreur, six mois plus tard.
     */
    public function decideVerification(
        User $admin,
        Company|CfaOrganization $organisation,
        VerificationStatus $statut,
        string $note,
    ): Company|CfaOrganization {
        $organisation->verification_status = $statut;
        $organisation->verified_by = $admin->id;
        $organisation->verification_note = trim($note);
        $organisation->save();

        return $organisation->fresh();
    }

    // -----------------------------------------------------------------
    // Employeurs silencieux
    // -----------------------------------------------------------------

    /**
     * Candidatures laissees sans reponse depuis au moins sept jours.
     *
     * Se lit directement sur `applications.reminder_stage`, pose par
     * MatchReminderService : pas de table de plus, pas de calcul parallele
     * qui pourrait diverger de la cascade. Si l'ecran et les relances ne
     * disent pas la meme chose, c'est l'ecran qui a tort.
     *
     * @return LengthAwarePaginator<int, Application>
     */
    public function listSilentEmployers(): LengthAwarePaginator
    {
        return Application::query()
            ->whereNull('responded_at')
            ->whereIn('reminder_stage', [
                MatchReminderStage::APPLICATION_SILENT_D7->value,
                MatchReminderStage::APPLICATION_SILENT_D14->value,
                MatchReminderStage::APPLICATION_SILENT_CLOSED->value,
            ])
            ->with([
                'jobOffer:id,title,company_id,cfa_organization_id',
                'jobOffer.company:id,name,user_id',
                'jobOffer.cfaOrganization:id,name,user_id',
            ])
            // Le plus ancien silence en tete : c'est le candidat qui attend
            // depuis le plus longtemps.
            ->oldest('created_at')
            ->paginate(self::PAR_PAGE);
    }

    /**
     * @return array<string, mixed>
     */
    private function ligneVerification(
        string $type,
        int $id,
        string $nom,
        ?string $siret,
        ?string $note,
        ?string $email,
        mixed $creeLe,
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'name' => $nom,
            'siret' => $siret,
            'verification_note' => $note,
            'email' => $email,
            'created_at' => $creeLe,
        ];
    }
}
