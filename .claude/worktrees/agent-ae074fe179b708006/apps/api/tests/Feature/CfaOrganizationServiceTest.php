<?php

namespace Tests\Feature;

use App\Enums\UserRole;
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
}
