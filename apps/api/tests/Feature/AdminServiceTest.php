<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\UserRole;
use App\Enums\VideoRoomStatus;
use App\Exceptions\ApiException;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdminService;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use App\Services\VideoRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(AdminService::class);
    }

    private function makeAdmin(): User
    {
        return User::create(['email' => 'admin@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::ADMIN]);
    }

    private function makeCompanyOwner(): User
    {
        $user = User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        $this->app->make(CompanyService::class)->createForUser($user, ['name' => 'NexaTech']);

        return $user->fresh();
    }

    public function test_stats_counts_users_by_role(): void
    {
        $this->makeAdmin();
        User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        User::create(['email' => 'malik@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        $stats = $this->service->stats();

        $this->assertSame(3, $stats['users']['total']);
        $this->assertSame(2, $stats['users']['candidates']);
        $this->assertSame(0, $stats['users']['suspended']);
    }

    public function test_list_users_filters_by_role(): void
    {
        User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);

        $result = $this->service->listUsers(['role' => 'CANDIDATE']);

        $this->assertSame(1, $result->total());
    }

    public function test_suspend_user_sets_flag(): void
    {
        $admin = $this->makeAdmin();
        $target = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        $updated = $this->service->suspendUser($admin, $target);

        $this->assertTrue($updated->is_suspended);
    }

    public function test_suspend_user_rejects_self_suspension(): void
    {
        $admin = $this->makeAdmin();

        $this->expectException(ApiException::class);
        $this->service->suspendUser($admin, $admin);
    }

    public function test_reactivate_user_clears_flag(): void
    {
        $target = User::create([
            'email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE, 'is_suspended' => true,
        ]);

        $updated = $this->service->reactivateUser($target);

        $this->assertFalse($updated->is_suspended);
    }

    public function test_list_job_offers_filters_by_status(): void
    {
        $owner = $this->makeCompanyOwner();
        $this->app->make(JobOfferService::class)->createForUser($owner, [
            'title' => 'Développeur web en alternance',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);

        $draftResult = $this->service->listJobOffers(['status' => 'DRAFT']);
        $publishedResult = $this->service->listJobOffers(['status' => 'PUBLISHED']);

        $this->assertSame(1, $draftResult->total());
        $this->assertSame(0, $publishedResult->total());
    }

    public function test_force_archive_job_offer_ignores_ownership(): void
    {
        $owner = $this->makeCompanyOwner();
        $offer = $this->app->make(JobOfferService::class)->createForUser($owner, [
            'title' => 'Développeur web en alternance',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);

        $archived = $this->service->forceArchiveJobOffer($offer);

        $this->assertSame(JobOfferStatus::ARCHIVED, $archived->status);
    }

    public function test_list_payments_filters_by_status(): void
    {
        $owner = $this->makeCompanyOwner();
        $offer = $this->app->make(JobOfferService::class)->createForUser($owner, [
            'title' => 'Développeur web en alternance',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
        ]);
        Payment::create([
            'user_id' => $owner->id,
            'job_offer_id' => $offer->id,
            'amount_cents' => 4900,
            'currency' => 'EUR',
            'status' => 'PENDING',
            'stripe_session_id' => 'cs_test_demo123',
        ]);

        $pendingResult = $this->service->listPayments(['status' => 'PENDING']);
        $succeededResult = $this->service->listPayments(['status' => 'SUCCEEDED']);

        $this->assertSame(1, $pendingResult->total());
        $this->assertSame(0, $succeededResult->total());
    }

    public function test_list_video_rooms_filters_by_status(): void
    {
        $host = $this->makeCompanyOwner();
        $this->app->make(VideoRoomService::class)->createForUser($host, null, null);

        $scheduledResult = $this->service->listVideoRooms(['status' => 'SCHEDULED']);
        $liveResult = $this->service->listVideoRooms(['status' => 'LIVE']);

        $this->assertSame(1, $scheduledResult->total());
        $this->assertSame(0, $liveResult->total());
    }

    public function test_force_end_video_room_ignores_host(): void
    {
        $host = $this->makeCompanyOwner();
        $room = $this->app->make(VideoRoomService::class)->createForUser($host, null, null);

        $ended = $this->service->forceEndVideoRoom($room);

        $this->assertSame(VideoRoomStatus::ENDED, $ended->status);
        $this->assertNotNull($ended->ended_at);
    }
}
