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
            'users' => [
                'total' => User::count(),
                'candidates' => User::where('role', UserRole::CANDIDATE)->count(),
                'companies' => User::where('role', UserRole::COMPANY)->count(),
                'cfa_organizations' => User::where('role', UserRole::CFA)->count(),
                'suspended' => User::where('is_suspended', true)->count(),
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
        $query = User::query()->latest();

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
