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
}
