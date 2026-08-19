<?php

namespace App\Services;

use App\Enums\JobOfferStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\VideoRoomStatus;
use App\Exceptions\ApiException;
use App\Models\Application;
use App\Models\JobOffer;
use App\Models\Payment;
use App\Models\User;
use App\Models\VideoRoom;
use Illuminate\Pagination\LengthAwarePaginator;

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
