<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyServiceTest extends TestCase
{
    use RefreshDatabase;

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
        $this->service->createForUser($user, ['name' => 'NexaTech']);

        $updated = $this->service->updateForUser($user->fresh(), ['city' => 'Nantes']);

        $this->assertSame('Nantes', $updated->city);
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

        $result = $this->service->searchPublic('Rennes');

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
}
