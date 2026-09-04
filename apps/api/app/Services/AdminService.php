<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\VideoRoomStatus;
use App\Exceptions\ApiException;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\Payment;
use App\Models\Skill;
use App\Models\Software;
use App\Models\User;
use App\Models\VideoRoom;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class AdminService
{
    public function stats(): array
    {
        return [
            // notDeleted() partout : les vestiges comptables de comptes
            // supprimes ne sont plus des utilisateurs. Sans ce filtre, les
            // effectifs affiches ne correspondaient pas a la liste juste a
            // cote, et "suspendus" gonflait a chaque suppression (un compte
            // anonymise est marque suspendu pour couper ses sessions).
            'users' => [
                'total' => User::query()->notDeleted()->count(),
                'candidates' => User::query()->notDeleted()->where('role', UserRole::CANDIDATE)->count(),
                'companies' => User::query()->notDeleted()->where('role', UserRole::COMPANY)->count(),
                'cfa_organizations' => User::query()->notDeleted()->where('role', UserRole::CFA)->count(),
                'suspended' => User::query()->notDeleted()->where('is_suspended', true)->count(),
            ],
            'job_offers' => [
                'total' => JobOffer::count(),
                'published' => JobOffer::where('status', JobOfferStatus::PUBLISHED)->count(),
                'draft' => JobOffer::where('status', JobOfferStatus::DRAFT)->count(),
                'expired' => JobOffer::where('status', JobOfferStatus::EXPIRED)->count(),
                'archived' => JobOffer::where('status', JobOfferStatus::ARCHIVED)->count(),
            ],
            'applications' => [
                'total' => Application::count(),
            ],
            'payments' => [
                'succeeded_count' => Payment::where('status', PaymentStatus::SUCCEEDED)->count(),
                'revenue_cents' => (int) Payment::where('status', PaymentStatus::SUCCEEDED)->sum('amount_cents'),
            ],
            'video_rooms' => [
                'total' => VideoRoom::count(),
                'live' => VideoRoom::where('status', VideoRoomStatus::LIVE)->count(),
            ],
        ];
    }

    public function listUsers(array $filters): LengthAwarePaginator
    {
        // Les comptes supprimes par leur titulaire sont exclus. Quand un compte
        // a des paiements, l'obligation de conservation comptable interdit de
        // supprimer la ligne : AccountService l'anonymise a la place
        // (email @jeuncy.invalid, mot de passe efface, suspendu). Ce vestige
        // comptable n'est plus un utilisateur, et l'afficher dans une liste
        // proposant "Suspendre"/"Reactiver" laissait croire a l'admin que la
        // suppression avait echoue — alors que toutes les donnees
        // personnelles avaient bien ete effacees.
        $query = User::query()->notDeleted()->latest();

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        return $query->paginate(20);
    }

    /**
     * Bascule un compte entre CANDIDATE et STAFF.
     *
     * Volontairement limite a ces deux roles. Promouvoir vers ADMIN depuis
     * l'interface donnerait a un administrateur le pouvoir d'en creer d'autres
     * sans trace ; et transformer une entreprise en membre de l'equipe
     * detacherait son profil, ses offres et ses paiements de leur titulaire.
     */
    public function setStaffRole(User $admin, User $target, bool $isStaff): User
    {
        if ($admin->is($target)) {
            throw new ApiException('CANNOT_CHANGE_OWN_ROLE', 'Tu ne peux pas changer ton propre rôle.', 400);
        }

        if (! in_array($target->role, [UserRole::CANDIDATE, UserRole::STAFF], true)) {
            throw new ApiException(
                'ROLE_NOT_SWITCHABLE',
                "Seul un compte candidat peut devenir membre de l'équipe, et inversement.",
                400,
            );
        }

        $target->update(['role' => $isStaff ? UserRole::STAFF : UserRole::CANDIDATE]);

        return $target->fresh();
    }

    public function suspendUser(User $admin, User $target): User
    {
        if ($admin->is($target)) {
            throw new ApiException('CANNOT_SUSPEND_SELF', 'Tu ne peux pas suspendre ton propre compte.', 400);
        }

        $target->update(['is_suspended' => true]);

        return $target;
    }

    public function reactivateUser(User $target): User
    {
        $target->update(['is_suspended' => false]);

        return $target;
    }

    // Etiquettes de gabarit de CV qui ne sont jamais un nom de personne.
    // "Permis B" a la forme exacte d'une identite — deux mots, que des
    // lettres — et un vrai profil a ete cree sous ce nom.
    //
    // Volontairement duplique depuis CvImportService plutot que partage : lire
    // sa constante obligeait a televerser les deux fichiers ensemble, et un
    // envoi depareille cassait cette liste. Les deux listes ne servent pas la
    // meme decision (ne pas extraire / signaler) et peuvent divenger.
    private const LABEL_NOT_A_NAME = 'curriculum vitae|curriculum|resume|cv|profil|profile|candidature'
        .'|contact|coordonn[ée]es|informations?|a propos|about( me)?'
        .'|permis(?:\s.{0,20})?|nationalit[ée]|[âa]ge|date de naissance|n[ée](?: le)?'
        .'|adresse|t[ée]l[ée]phone|t[ée]l|mobile|portable|e?-?mail|courriel'
        .'|situation familiale|v[ée]hicul[ée]e?|disponibilit[ée]';

    // Mots qui ne figurent jamais dans un nom de personne mais souvent dans un
    // titre de CV ("Alternance Vente").
    private const WORD_NEVER_IN_A_NAME = 'alternance|alternant|apprentissage|stage|stagiaire'
        .'|recherche|cherche|contrat|poste|emploi|job|etudiant|etudiante|motivation'
        .'|objectif|licence|master|bts|but|dut|cap|bac|diplome|formation|experience';

    // Profils candidats, avec un filtre sur les noms douteux.
    //
    // Le filtre s'evalue en PHP et non en SQL : reconnaitre "Permis B" ou
    // "Prospection Encaissement" demande le meme vocabulaire que l'extracteur
    // que l'extracteur, qu'aucune clause WHERE ne sait exprimer. Les
    // identifiants sont d'abord collectes sur deux colonnes seulement, puis la
    // pagination reste faite par la base — le cout tient a une requete legere,
    // pas au chargement de tous les profils.
    public function listCandidateProfiles(array $filters): LengthAwarePaginator
    {
        $query = CandidateProfile::query()
            ->with('user:id,email,is_suspended')
            ->whereHas('user', fn ($q) => $q->notDeleted())
            ->latest();

        if (! empty($filters['suspicious'])) {
            $query->whereIn('id', $this->implausiblyNamedProfileIds());
        }

        return $query->paginate(20);
    }

    /** @return int[] */
    private function implausiblyNamedProfileIds(): array
    {
        return CandidateProfile::query()
            ->whereHas('user', fn ($q) => $q->notDeleted())
            ->get(['id', 'first_name', 'last_name'])
            ->filter(fn (CandidateProfile $p) => $this->nameLooksImplausible(
                (string) $p->first_name,
                (string) $p->last_name,
            ))
            ->pluck('id')
            ->all();
    }

    // Un nom que l'import n'aurait plus le droit de produire aujourd'hui.
    // Signale, jamais corrige tout seul : un nom inhabituel reste un nom, et
    // c'est a un humain de trancher.
    public function nameLooksImplausible(string $first, string $last): bool
    {
        if (trim($first) === '' || trim($last) === '') {
            return true;
        }

        $complet = trim($first.' '.$last);

        // Un chiffre dans un nom vient toujours d'une ligne mal lue.
        if (preg_match('/\d/u', $complet)) {
            return true;
        }

        $mots = array_values(array_filter(preg_split('/\s+/u', $complet) ?: []));

        // Au-dela de quatre mots, c'est une phrase, pas une identite.
        if (count($mots) > 4) {
            return true;
        }

        foreach ([$first, $last] as $partie) {
            if (preg_match('/^(?:'.self::LABEL_NOT_A_NAME.')$/iu', Str::ascii(trim($partie)))) {
                return true;
            }
        }

        foreach ($mots as $mot) {
            if (preg_match('/^(?:'.self::WORD_NEVER_IN_A_NAME.')$/iu', Str::ascii($mot))) {
                return true;
            }
        }

        // Une competence ou un logiciel du referentiel n'est pas un nom de
        // personne. C'est ce qui reconnait "Prospection Encaissement" : deux
        // lignes d'une liste de competences assemblees en identite, que rien
        // dans leur forme ne distingue d'un vrai nom.
        foreach ([$first, $last] as $partie) {
            if (isset($this->referentiel()[Str::lower(Str::ascii(trim($partie)))])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Competences et logiciels connus, en minuscules sans accents.
     *
     * Charge une seule fois : la detection s'applique a tous les profils, et
     * une requete par profil rendrait la liste inutilisable.
     *
     * @var array<string, true>|null
     */
    private ?array $referentiel = null;

    /** @return array<string, true> */
    private function referentiel(): array
    {
        if ($this->referentiel === null) {
            $noms = array_merge(
                Skill::query()->pluck('name')->all(),
                Software::query()->pluck('name')->all(),
            );

            $this->referentiel = array_fill_keys(
                array_map(fn (string $n) => Str::lower(Str::ascii($n)), $noms),
                true,
            );
        }

        return $this->referentiel;
    }

    public function updateCandidateName(CandidateProfile $profile, string $first, string $last): CandidateProfile
    {
        $profile->update(['first_name' => trim($first), 'last_name' => trim($last)]);

        return $profile->fresh(['user']);
    }

    public function listJobOffers(array $filters): LengthAwarePaginator
    {
        $query = JobOffer::query()->with(['company', 'cfaOrganization'])->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(20);
    }

    // Apercu back-office d'une offre QUEL QUE SOIT son statut (brouillon
    // compris) : permet a l'equipe de controler le rendu public exact d'une
    // offre avant/sans publication. Volontairement separe de
    // JobOfferService::findPublished, dont le filtre PUBLISHED + 404 est un
    // invariant anti-fuite verrouille par un test — on ne l'assouplit pas, on
    // ajoute un chemin admin distinct derriere role:ADMIN. Meme eager-load
    // que findPublished (skills compris, contrairement a listJobOffers
    // ci-dessus) pour que le payload ait exactement la forme que le composant
    // de rendu public attend.
    public function previewJobOffer(JobOffer $jobOffer): JobOffer
    {
        return $jobOffer->load(['company', 'cfaOrganization', 'skills']);
    }

    // Pouvoir de moderation : archive n'importe quelle offre, sans verifier le
    // proprietaire (contrairement a JobOfferService::archiveForUser, reserve au
    // proprietaire lui-meme).
    public function forceArchiveJobOffer(JobOffer $jobOffer): JobOffer
    {
        $jobOffer->update(['status' => JobOfferStatus::ARCHIVED]);

        return $jobOffer;
    }

    public function listPayments(array $filters): LengthAwarePaginator
    {
        $query = Payment::query()->with(['user', 'jobOffer'])->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(20);
    }

    public function listVideoRooms(array $filters): LengthAwarePaginator
    {
        $query = VideoRoom::query()->with(['host', 'participant'])->latest('created_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate(20);
    }

    // Pouvoir de supervision : termine n'importe quelle salle, sans verifier
    // l'hote (contrairement a VideoRoomService::markEnded, reserve a l'hote).
    public function forceEndVideoRoom(VideoRoom $videoRoom): VideoRoom
    {
        $videoRoom->update(['status' => VideoRoomStatus::ENDED, 'ended_at' => $videoRoom->ended_at ?? now()]);

        return $videoRoom;
    }
}
