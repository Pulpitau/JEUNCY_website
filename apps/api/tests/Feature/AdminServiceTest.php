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

    // L'apercu admin doit rendre un BROUILLON avec exactement les relations
    // que le rendu public attend (company/cfaOrganization/skills), la ou le
    // chemin public renvoie 404 pour la meme offre — les deux assertions
    // ensemble verifient qu'on a ouvert un chemin admin sans elargir le
    // chemin public.
    public function test_preview_returns_draft_with_public_payload_relations(): void
    {
        $owner = $this->makeCompanyOwner();
        $offer = $this->app->make(JobOfferService::class)->createForUser($owner, [
            'title' => 'Développeur web en alternance',
            'description' => 'Rejoins notre équipe.',
            'contract_type' => ContractType::ALTERNANCE->value,
            'skills' => ['React', 'SQL'],
        ]);

        $preview = $this->service->previewJobOffer($offer->fresh());

        $this->assertSame(JobOfferStatus::DRAFT, $preview->status);
        $this->assertTrue($preview->relationLoaded('company'));
        $this->assertTrue($preview->relationLoaded('cfaOrganization'));
        $this->assertTrue($preview->relationLoaded('skills'));
        $this->assertSame(['React', 'SQL'], $preview->skills->pluck('name')->sort()->values()->all());

        $this->expectException(ApiException::class);
        $this->app->make(JobOfferService::class)->findPublished($offer->id);
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

    // Un compte ayant des paiements ne peut pas etre supprime (conservation
    // comptable) : AccountService l'anonymise. Ce vestige n'est plus un
    // utilisateur et n'a rien a faire dans une liste qui propose de le
    // suspendre — l'admin croyait la suppression ratee alors que toutes les
    // donnees personnelles avaient bien ete effacees.
    public function test_deleted_accounts_are_hidden_from_the_users_list(): void
    {
        $this->makeCompanyOwner();
        User::create([
            'email' => 'compte-supprime-42'.User::DELETED_EMAIL_DOMAIN,
            'password_hash' => 'x',
            'role' => UserRole::COMPANY,
            'is_suspended' => true,
            'deleted_account_at' => now(),
        ]);

        $emails = collect($this->service->listUsers([])->items())->pluck('email');

        $this->assertContains('rh@nexatech.example.com', $emails);
        $this->assertNotContains('compte-supprime-42'.User::DELETED_EMAIL_DOMAIN, $emails);
    }

    // Les compteurs doivent coller a la liste juste a cote. Sans ce filtre,
    // "suspendus" gonflait a chaque suppression de compte, puisque
    // l'anonymisation marque le compte suspendu pour couper ses sessions.
    public function test_stats_ignore_deleted_accounts(): void
    {
        $this->makeCompanyOwner();
        User::create([
            'email' => 'compte-supprime-43'.User::DELETED_EMAIL_DOMAIN,
            'password_hash' => 'x',
            'role' => UserRole::COMPANY,
            'is_suspended' => true,
            'deleted_account_at' => now(),
        ]);

        $stats = $this->service->stats();

        $this->assertSame(1, $stats['users']['companies']);
        $this->assertSame(0, $stats['users']['suspended']);
    }
}
