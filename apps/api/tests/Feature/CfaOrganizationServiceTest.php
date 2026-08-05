<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkMode;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\CfaOrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CfaOrganizationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CfaOrganizationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(CfaOrganizationService::class);
    }

    private function makeUser(): User
    {
        return User::create(['email' => 'contact@cfa-sup-alternance.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);
    }

    public function test_get_for_user_throws_when_no_cfa_organization_exists(): void
    {
        $this->expectException(ApiException::class);
        $this->service->getForUser($this->makeUser());
    }

    public function test_create_for_user_creates_cfa_organization(): void
    {
        $cfa = $this->service->createForUser($this->makeUser(), ['name' => 'CFA Sup Alternance']);

        $this->assertSame('CFA Sup Alternance', $cfa->name);
    }

    public function test_create_for_user_refuses_duplicate_cfa_organization(): void
    {
        $user = $this->makeUser();
        $this->service->createForUser($user, ['name' => 'CFA Sup Alternance']);

        $this->expectException(ApiException::class);
        $this->service->createForUser($user->fresh(), ['name' => 'CFA bis']);
    }

    public function test_search_public_lists_all_cfa_organizations(): void
    {
        $this->service->createForUser($this->makeUser(), ['name' => 'CFA Sup Alternance', 'city' => 'Rennes']);

        $result = $this->service->searchPublic();

        $this->assertSame(1, $result->total());
    }

    public function test_search_public_filters_by_name(): void
    {
        $this->service->createForUser($this->makeUser(), ['name' => 'CFA Sup Alternance']);
        $this->service->createForUser(
            User::create(['email' => 'contact@autre-cfa.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]),
            ['name' => 'Autre CFA'],
        );

        $result = $this->service->searchPublic(['name' => 'sup altern']);

        $this->assertSame(1, $result->total());
        $this->assertSame('CFA Sup Alternance', $result->items()[0]->name);
    }

    public function test_search_public_filters_by_diploma_level(): void
    {
        $this->service->createForUser($this->makeUser(), ['name' => 'CFA Sup Alternance', 'diploma_level' => 'Bac+2 (BTS, DUT)']);
        $this->service->createForUser(
            User::create(['email' => 'contact@autre-cfa.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]),
            ['name' => 'Autre CFA', 'diploma_level' => 'CAP / BEP'],
        );

        $result = $this->service->searchPublic(['diploma_level' => 'Bac+2 (BTS, DUT)']);

        $this->assertSame(1, $result->total());
        $this->assertSame('CFA Sup Alternance', $result->items()[0]->name);
    }

    public function test_search_public_filters_by_training_mode(): void
    {
        $this->service->createForUser($this->makeUser(), ['name' => 'CFA Sup Alternance', 'training_mode' => WorkMode::HYBRIDE->value]);
        $this->service->createForUser(
            User::create(['email' => 'contact@autre-cfa.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]),
            ['name' => 'Autre CFA', 'training_mode' => WorkMode::PRESENTIEL->value],
        );

        $result = $this->service->searchPublic(['training_mode' => WorkMode::HYBRIDE->value]);

        $this->assertSame(1, $result->total());
        $this->assertSame('CFA Sup Alternance', $result->items()[0]->name);
    }

    public function test_find_public_throws_when_cfa_organization_not_found(): void
    {
        $this->expectException(ApiException::class);
        $this->service->findPublic(999);
    }

    public function test_find_public_returns_cfa_organization(): void
    {
        $cfa = $this->service->createForUser($this->makeUser(), ['name' => 'CFA Sup Alternance']);

        $found = $this->service->findPublic($cfa->id);

        $this->assertSame('CFA Sup Alternance', $found->name);
    }
}
