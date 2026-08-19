<?php

namespace Tests\Feature;

use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\JobOffer;
use App\Models\User;
use App\Services\CfaOrganizationService;
use App\Services\CompanyService;
use App\Services\JobOfferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Minimisation des donnees exposees sans authentification.
//
// Les fiches entreprise/CFA sortent sur des routes publiques (listes,
// pages de detail) ET accrochees a chaque offre publique. Tout attribut
// serialisable y est donc lisible par n'importe qui, sans compte.
//
// Ce fichier verrouille ce qui ne doit PAS en sortir. Il vise en priorite
// trial_started_at / trial_offers_count : les exposer revelait a tout
// visiteur, concurrent compris, quelles entreprises sont en periode d'essai
// gratuite et combien d'offres il leur restait.
class PublicDataExposureTest extends TestCase
{
    use RefreshDatabase;

    private const NEVER_PUBLIC = [
        'user_id',
        'siret',
        'trial_started_at',
        'trial_offers_count',
    ];

    private function makeCompany(): Company
    {
        $user = User::create([
            'email' => 'rh@nexatech.example.com',
            'password_hash' => 'x',
            'role' => UserRole::COMPANY,
        ]);

        $company = $user->company()->create([
            'name' => 'NexaTech',
            'siret' => '12345678901234',
            'city' => 'Perpignan',
        ]);

        // Etat d'essai renseigne : sans ca, des champs nuls passeraient le
        // test sans rien prouver.
        $company->forceFill(['trial_started_at' => now(), 'trial_offers_count' => 1])->save();

        return $company->refresh();
    }

    private function makeCfa(): CfaOrganization
    {
        $user = User::create([
            'email' => 'contact@cfa.example.com',
            'password_hash' => 'x',
            'role' => UserRole::CFA,
        ]);

        $cfa = $user->cfaOrganization()->create([
            'name' => 'CFA Sup Alternance',
            'siret' => '98765432109876',
            'nda_number' => 'NDA-123',
            'qualiopi_number' => 'QUALIOPI-456',
            'city' => 'Montpellier',
        ]);

        $cfa->forceFill(['trial_started_at' => now(), 'trial_offers_count' => 1])->save();

        return $cfa->refresh();
    }

    private function assertNothingSensitive(array $payload, string $context): void
    {
        foreach (self::NEVER_PUBLIC as $field) {
            $this->assertArrayNotHasKey(
                $field,
                $payload,
                "{$context} expose « {$field} » sans authentification.",
            );
        }
    }

    public function test_public_company_listing_hides_sensitive_fields(): void
    {
        $this->makeCompany();

        $results = app(CompanyService::class)->searchPublic();

        $this->assertNothingSensitive(
            $results->items()[0]->toArray(),
            'La liste publique des entreprises',
        );
    }

    public function test_public_company_detail_hides_sensitive_fields(): void
    {
        $company = $this->makeCompany();

        $this->assertNothingSensitive(
            app(CompanyService::class)->findPublic($company->id)->toArray(),
            'La fiche publique d’une entreprise',
        );
    }

    public function test_public_cfa_listing_hides_sensitive_fields(): void
    {
        $this->makeCfa();

        $results = app(CfaOrganizationService::class)->searchPublic();

        $this->assertNothingSensitive(
            $results->items()[0]->toArray(),
            'La liste publique des CFA',
        );
    }

    // Les certifications restent visibles : un CFA les met en avant, elles
    // rassurent le candidat et figurent deja dans des registres officiels.
    public function test_public_cfa_still_exposes_its_certifications(): void
    {
        $cfa = $this->makeCfa();

        $payload = app(CfaOrganizationService::class)->findPublic($cfa->id)->toArray();

        $this->assertSame('NDA-123', $payload['nda_number']);
        $this->assertSame('QUALIOPI-456', $payload['qualiopi_number']);
        $this->assertSame('CFA Sup Alternance', $payload['name']);
    }

    // Le point d'exposition le plus facile a oublier : la fiche n'est pas
    // demandee directement, elle voyage accrochee a chaque offre publiee.
    public function test_company_attached_to_a_public_offer_hides_sensitive_fields(): void
    {
        $company = $this->makeCompany();

        JobOffer::create([
            'company_id' => $company->id,
            'title' => 'Alternance developpeur',
            'description' => 'Une offre',
            'contract_type' => ContractType::ALTERNANCE,
            'city' => 'Perpignan',
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::SUCCEEDED,
            'published_at' => now(),
        ]);

        $offers = app(JobOfferService::class)->searchPublished([]);
        $payload = $offers->items()[0]->toArray();

        $this->assertNothingSensitive(
            $payload['company'],
            'L’entreprise attachee a une offre publique',
        );
    }

    // Contrepartie indispensable du masquage : le proprietaire doit toujours
    // recuperer son SIRET (son formulaire le reaffiche) et son etat d'essai
    // (son tableau de bord propose la publication gratuite). Sans ce test, un
    // masquage trop large casserait les deux en silence.
    public function test_owner_still_receives_siret_and_trial_state(): void
    {
        $company = $this->makeCompany();

        $payload = app(CompanyService::class)->getForUser($company->user)->toArray();

        $this->assertSame('12345678901234', $payload['siret']);
        $this->assertArrayHasKey('trial_started_at', $payload);
        $this->assertSame(1, $payload['trial_offers_count']);
    }

    public function test_cfa_owner_still_receives_siret_and_trial_state(): void
    {
        $cfa = $this->makeCfa();

        $payload = app(CfaOrganizationService::class)->getForUser($cfa->user)->toArray();

        $this->assertSame('98765432109876', $payload['siret']);
        $this->assertArrayHasKey('trial_started_at', $payload);
        $this->assertSame(1, $payload['trial_offers_count']);
    }
}
