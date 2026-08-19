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

    // Un paiement rend le compte ineffacable (conservation comptable) : c'est
    // la branche qui anonymise au lieu de supprimer.
    private function makeCompanyWithPayment(): User
    {
        $user = $this->makeCompany();
        Payment::create([
            'user_id' => $user->id,
            'amount_cents' => 999,
            'status' => PaymentStatus::SUCCEEDED,
            'stripe_session_id' => 'cs_test_'.$user->id,
        ]);

        return $user->fresh();
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
        // Prefixe et domaine verifies, mais PAS l'adresse exacte : elle porte
        // desormais un suffixe aleatoire, sans lequel elle etait previsible
        // et donc pre-enregistrable par un tiers (voir
        // test_deletion_survives_a_squatted_anonymisation_email).
        $this->assertStringStartsWith('compte-supprime-'.$user->id.'-', $refreshed->email);
        $this->assertStringEndsWith(User::DELETED_EMAIL_DOMAIN, $refreshed->email);
        $this->assertNull($refreshed->password_hash);
        $this->assertTrue($refreshed->is_suspended);
        $this->assertNotNull($refreshed->deleted_account_at);
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

    // L'adresse d'anonymisation etait entierement previsible
    // ('compte-supprime-{id}@jeuncy.invalid') alors que users.email est
    // UNIQUE : un tiers pouvait la pre-enregistrer et faire echouer la
    // suppression RGPD d'un compte precis, de facon definitive.
    public function test_deletion_survives_a_squatted_anonymisation_email(): void
    {
        $user = $this->makeCompanyWithPayment();
        User::create([
            'email' => 'compte-supprime-'.$user->id.User::DELETED_EMAIL_DOMAIN,
            'password_hash' => 'x',
            'role' => UserRole::CANDIDATE,
        ]);

        $this->service->deleteAccount($user, $user->email);

        $deleted = User::find($user->id);
        $this->assertNotNull($deleted->deleted_account_at);
        $this->assertStringEndsWith(User::DELETED_EMAIL_DOMAIN, $deleted->email);
        $this->assertNull($deleted->password_hash);
    }

    // L'etat de suppression vit dans une colonne, pas dans l'email : un
    // compte reel dont l'adresse finirait par le domaine reserve ne doit pas
    // etre pris pour un compte supprime (il ne peut plus s'inscrire ainsi,
    // mais le scope ne doit pas dependre de cette garde).
    public function test_deletion_state_does_not_depend_on_the_email(): void
    {
        $user = $this->makeCompanyWithPayment();
        $this->service->deleteAccount($user, $user->email);

        $this->assertFalse(User::query()->notDeleted()->whereKey($user->id)->exists());
        $this->assertTrue(User::find($user->id)->isDeletedAccount());
    }
}
