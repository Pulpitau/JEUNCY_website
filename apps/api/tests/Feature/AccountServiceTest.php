<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\Payment;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountServiceTest extends TestCase
{
    use RefreshDatabase;

    private AccountService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(AccountService::class);
    }

    private function makeCandidate(): User
    {
        $user = User::create(['email' => 'lea@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        CandidateProfile::create(['user_id' => $user->id, 'first_name' => 'Léa', 'last_name' => 'Girard']);

        return $user;
    }

    private function makeCompany(): User
    {
        $user = User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        Company::create(['user_id' => $user->id, 'name' => 'NexaTech']);

        return $user;
    }

    public function test_export_data_includes_candidate_profile_and_relations(): void
    {
        $user = $this->makeCandidate();

        $data = $this->service->exportData($user);

        $this->assertSame('lea@example.com', $data['account']['email']);
        $this->assertArrayHasKey('candidate_profile', $data);
        $this->assertSame('Léa', $data['candidate_profile']->first_name);
        $this->assertArrayNotHasKey('company', $data);
    }

    public function test_export_data_includes_company_and_payments(): void
    {
        $user = $this->makeCompany();
        Payment::create([
            'user_id' => $user->id,
            'amount_cents' => 999,
            'currency' => 'eur',
            'status' => PaymentStatus::SUCCEEDED,
        ]);

        $data = $this->service->exportData($user);

        $this->assertArrayHasKey('company', $data);
        $this->assertSame('NexaTech', $data['company']->name);
        $this->assertCount(1, $data['payments']);
    }

    public function test_delete_account_hard_deletes_candidate_without_payments(): void
    {
        $user = $this->makeCandidate();
        $profileId = $user->candidateProfile->id;

        $this->service->deleteAccount($user, 'lea@example.com');

        $this->assertNull(User::find($user->id));
        $this->assertNull(CandidateProfile::find($profileId));
    }

    public function test_delete_account_rejects_mismatched_email(): void
    {
        $user = $this->makeCandidate();

        $this->expectException(ApiException::class);
        $this->service->deleteAccount($user, 'pas-le-bon-email@example.com');
    }

    public function test_delete_account_rejects_admin(): void
    {
        $user = User::create(['email' => 'admin@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::ADMIN]);

        $this->expectException(ApiException::class);
        $this->service->deleteAccount($user, 'admin@jeuncy.com');
    }

    public function test_delete_account_anonymizes_company_with_payments_instead_of_hard_delete(): void
    {
        $user = $this->makeCompany();
        $companyId = $user->company->id;
        $payment = Payment::create([
            'user_id' => $user->id,
            'amount_cents' => 999,
            'currency' => 'eur',
            'status' => PaymentStatus::SUCCEEDED,
        ]);

        $this->service->deleteAccount($user, 'rh@nexatech.example.com');

        $refreshed = User::find($user->id);
        $this->assertNotNull($refreshed, 'le User doit survivre : payments.user_id est en contrainte RESTRICT');
        $this->assertSame('compte-supprime-'.$user->id.'@jeuncy.invalid', $refreshed->email);
        $this->assertNull($refreshed->password_hash);
        $this->assertTrue($refreshed->is_suspended);
        $this->assertNull(Company::find($companyId), 'le profil entreprise, lui, doit etre supprime');
        $this->assertNotNull(Payment::find($payment->id), 'le paiement doit etre conserve intact');
    }

    public function test_delete_account_hard_deletes_company_without_payments(): void
    {
        $user = $this->makeCompany();
        $companyId = $user->company->id;

        $this->service->deleteAccount($user, 'rh@nexatech.example.com');

        $this->assertNull(User::find($user->id));
        $this->assertNull(Company::find($companyId));
    }

    public function test_delete_account_hard_deletes_cfa_without_payments(): void
    {
        $user = User::create(['email' => 'contact@cfa.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);
        $cfa = CfaOrganization::create(['user_id' => $user->id, 'name' => 'CFA Sup Alternance']);

        $this->service->deleteAccount($user, 'contact@cfa.example.com');

        $this->assertNull(User::find($user->id));
        $this->assertNull(CfaOrganization::find($cfa->id));
    }
}
