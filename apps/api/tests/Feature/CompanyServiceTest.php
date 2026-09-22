<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Enums\WorkMode;
use App\Exceptions\ApiException;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SIRET_VALIDE = CompanyVerificationServiceTest::SIRET_VALIDE;

    private const SIRET_VALIDE_2 = CompanyVerificationServiceTest::SIRET_VALIDE_2;

    private CompanyService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(CompanyService::class);
    }

    private function makeUser(): User
    {
        return User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
    }

    public function test_get_for_user_throws_when_no_company_exists(): void
    {
        $this->expectException(ApiException::class);
        $this->service->getForUser($this->makeUser());
    }

    public function test_create_for_user_creates_company(): void
    {
        $company = $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech', 'city' => 'Rennes']);

        $this->assertSame('NexaTech', $company->name);
    }

    public function test_create_for_user_refuses_duplicate_company(): void
    {
        $user = $this->makeUser();
        $this->service->createForUser($user, ['name' => 'NexaTech']);

        $this->expectException(ApiException::class);
        $this->service->createForUser($user->fresh(), ['name' => 'NexaTech bis']);
    }

    public function test_update_for_user_updates_existing_company(): void
    {
        $user = $this->makeUser();
        // Le SIRET est desormais exige a la modification (voir
        // test_update_cannot_leave_siret_empty) : la fiche en porte un.
        $this->service->createForUser($user, ['name' => 'NexaTech', 'siret' => self::SIRET_VALIDE]);

        $updated = $this->service->updateForUser($user->fresh(), ['city' => 'Nantes']);

        $this->assertSame('Nantes', $updated->city);
    }

    // ------------------------------------------------------------------
    // SIRET, verification et geocodage (modele match, MOBILE.md §4.0 et §6)
    // ------------------------------------------------------------------

    public function test_siret_is_required_on_create(): void
    {
        $this->actingAs($this->makeUser(), 'api')
            ->postJson('/api/company', ['name' => 'NexaTech'])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');

        $this->assertDatabaseCount('companies', 0);
    }

    public function test_update_cannot_leave_siret_empty(): void
    {
        $user = $this->makeUser();
        $this->service->createForUser($user, ['name' => 'NexaTech', 'siret' => self::SIRET_VALIDE]);

        try {
            $this->service->updateForUser($user->fresh(), ['siret' => null]);
            $this->fail('Une fiche verifiee ne doit pas pouvoir redevenir anonyme.');
        } catch (ApiException $e) {
            $this->assertSame('SIRET_REQUIRED', $e->errorCode);
        }
    }

    public function test_siret_change_reverifies(): void
    {
        $user = $this->makeUser();
        $company = $this->service->createForUser($user, ['name' => 'NexaTech', 'siret' => self::SIRET_VALIDE]);
        // Le registre est muet au depart : la fiche reste PENDING.
        $this->assertSame(VerificationStatus::PENDING, $company->verification_status);

        $this->registreEntreprises = fn () => Http::response([
            'results' => [[
                'activite_principale' => '62.01Z',
                'etat_administratif' => 'A',
                'matching_etablissements' => [[
                    'siret' => self::SIRET_VALIDE_2,
                    'activite_principale' => '62.01Z',
                    'etat_administratif' => 'A',
                ]],
            ]],
        ]);

        $updated = $this->service->updateForUser($user->fresh(), ['siret' => self::SIRET_VALIDE_2]);

        $this->assertSame(VerificationStatus::VERIFIED, $updated->verification_status);
        $this->assertNotNull($updated->verified_at);
    }

    public function test_city_change_geocodes(): void
    {
        $user = $this->makeUser();
        $this->service->createForUser($user, ['name' => 'NexaTech', 'siret' => self::SIRET_VALIDE]);

        $this->geocodeur = fn () => Http::response([
            'features' => [['geometry' => ['coordinates' => [2.894833, 42.688700]]]],
        ]);

        $updated = $this->service->updateForUser($user->fresh(), ['city' => 'Perpignan', 'postal_code' => '66000']);

        $this->assertSame(42.6887, $updated->fresh()->latitude);
    }

    public function test_a_company_is_never_verified_by_default(): void
    {
        $company = $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech', 'siret' => self::SIRET_VALIDE]);

        $this->assertSame(VerificationStatus::PENDING, $company->verification_status);
    }

    public function test_search_public_lists_all_companies(): void
    {
        $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech', 'city' => 'Rennes']);
        $this->service->createForUser(
            User::create(['email' => 'contact@cafedeslices.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]),
            ['name' => 'Café des Lices', 'city' => 'Rennes'],
        );

        $result = $this->service->searchPublic();

        $this->assertSame(2, $result->total());
    }

    public function test_search_public_filters_by_city(): void
    {
        $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech', 'city' => 'Rennes']);
        $this->service->createForUser(
            User::create(['email' => 'contact@paris.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]),
            ['name' => 'ParisCo', 'city' => 'Paris'],
        );

        $result = $this->service->searchPublic(['city' => 'Rennes']);

        $this->assertSame(1, $result->total());
        $this->assertSame('NexaTech', $result->items()[0]->name);
    }

    public function test_search_public_filters_by_name(): void
    {
        $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech']);
        $this->service->createForUser(
            User::create(['email' => 'contact@paris.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]),
            ['name' => 'ParisCo'],
        );

        $result = $this->service->searchPublic(['name' => 'nexa']);

        $this->assertSame(1, $result->total());
        $this->assertSame('NexaTech', $result->items()[0]->name);
    }

    public function test_search_public_filters_by_work_mode(): void
    {
        $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech', 'work_mode' => WorkMode::DISTANCIEL->value]);
        $this->service->createForUser(
            User::create(['email' => 'contact@paris.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]),
            ['name' => 'ParisCo', 'work_mode' => WorkMode::PRESENTIEL->value],
        );

        $result = $this->service->searchPublic(['work_mode' => WorkMode::DISTANCIEL->value]);

        $this->assertSame(1, $result->total());
        $this->assertSame('NexaTech', $result->items()[0]->name);
    }

    public function test_search_public_filters_by_contract_type(): void
    {
        $company = $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech']);
        $otherCompany = $this->service->createForUser(
            User::create(['email' => 'contact@paris.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]),
            ['name' => 'ParisCo'],
        );
        JobOffer::create([
            'company_id' => $company->id,
            'title' => 'Alternant dev',
            'description' => 'x',
            'contract_type' => ContractType::ALTERNANCE,
            'status' => JobOfferStatus::PUBLISHED,
        ]);
        JobOffer::create([
            'company_id' => $otherCompany->id,
            'title' => 'Stagiaire marketing',
            'description' => 'x',
            'contract_type' => ContractType::STAGE,
            'status' => JobOfferStatus::PUBLISHED,
        ]);

        $result = $this->service->searchPublic(['contract_type' => ContractType::ALTERNANCE->value]);

        $this->assertSame(1, $result->total());
        $this->assertSame('NexaTech', $result->items()[0]->name);
    }

    public function test_find_public_throws_when_company_not_found(): void
    {
        $this->expectException(ApiException::class);
        $this->service->findPublic(999);
    }

    public function test_find_public_returns_company(): void
    {
        $company = $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech']);

        $found = $this->service->findPublic($company->id);

        $this->assertSame('NexaTech', $found->name);
    }

    // Une fiche est dans l'annuaire par defaut : ne rien changer pour les
    // inscriptions existantes ni les futures.
    public function test_company_is_listed_in_the_directory_by_default(): void
    {
        $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech']);

        $this->assertSame(1, $this->service->searchPublic()->total());
    }

    public function test_hidden_company_disappears_from_the_directory(): void
    {
        $company = $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech']);
        $company->update(['is_public' => false]);

        $this->assertSame(0, $this->service->searchPublic()->total());
    }

    // Masquer doit aussi fermer l'acces direct : une fiche retiree de
    // l'annuaire mais consultable en devinant son id ne serait pas masquee.
    public function test_hidden_company_is_not_reachable_by_direct_id(): void
    {
        $company = $this->service->createForUser($this->makeUser(), ['name' => 'NexaTech']);
        $company->update(['is_public' => false]);

        $this->expectException(ApiException::class);
        $this->service->findPublic($company->id);
    }
}
