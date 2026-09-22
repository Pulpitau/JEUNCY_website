<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Exceptions\ApiException;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\User;
use App\Rules\ValidSiret;
use App\Services\CfaOrganizationService;
use App\Services\CompanyVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La porte d'entree du cote employeur (MOBILE.md §4.0).
 *
 * L'invariant teste ici tient en une phrase : on ne devient jamais VERIFIED
 * par defaut. Tout ce qui n'est pas une confirmation explicite du registre —
 * panne, timeout, SIRET inconnu — laisse la fiche PENDING.
 */
class CompanyVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    // SIRET a cle de Luhn correcte.
    public const SIRET_VALIDE = '73282932000074';

    public const SIRET_VALIDE_2 = '44306184100047';

    // Cle de Luhn fausse : une faute de frappe typique.
    public const SIRET_FAUX = '12345678900019';

    private CompanyVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(CompanyVerificationService::class);
    }

    private function registreRepond(string $siret, string $naf = '62.01Z', string $etat = 'A'): void
    {
        $this->registreEntreprises = fn () => Http::response([
            'results' => [[
                'siren' => substr($siret, 0, 9),
                'activite_principale' => $naf,
                'etat_administratif' => $etat,
                'matching_etablissements' => [[
                    'siret' => $siret,
                    'activite_principale' => $naf,
                    'etat_administratif' => $etat,
                ]],
            ]],
            'total_results' => 1,
        ]);
    }

    private function company(?string $siret): Company
    {
        return Company::factory()->create(['siret' => $siret]);
    }

    // ------------------------------------------------------------------
    // La cle de Luhn
    // ------------------------------------------------------------------

    public function test_luhn_rejects_invalid_siret(): void
    {
        $company = $this->company(self::SIRET_FAUX);

        $this->assertSame(VerificationStatus::REJECTED, $this->service->verify($company));
        $this->assertSame('SIRET invalide', $company->fresh()->verification_note);
        // Le registre n'a meme pas ete consulte : un numero impossible ne
        // merite pas un appel reseau.
        Http::assertNothingSent();
    }

    public function test_la_poste_siren_passes_luhn_exception(): void
    {
        // Les SIRET de La Poste (SIREN 356000000) ne respectent pas Luhn :
        // leur regle est que la somme des chiffres est un multiple de 5.
        // Sans exception, le premier employeur de France serait refuse.
        $this->assertTrue(ValidSiret::isValid('35600000000010'), 'La somme des chiffres est un multiple de 5 : accepte.');
        $this->assertFalse(ValidSiret::isValid('35600000000011'), "Le SIREN de La Poste n'ouvre pas la porte a n'importe quel numero.");
    }

    // ------------------------------------------------------------------
    // Ce que dit (ou ne dit pas) le registre
    // ------------------------------------------------------------------

    public function test_registry_down_gives_pending_never_verified(): void
    {
        $this->registreEntreprises = fn () => Http::response('', 500);
        $company = $this->company(self::SIRET_VALIDE);

        $this->assertSame(VerificationStatus::PENDING, $this->service->verify($company));
        $this->assertNull($company->fresh()->verified_at);
    }

    public function test_unknown_siret_gives_pending(): void
    {
        // Le stub par defaut de TestCase repond « aucun resultat ».
        $company = $this->company(self::SIRET_VALIDE);

        $this->assertSame(VerificationStatus::PENDING, $this->service->verify($company));
    }

    public function test_missing_siret_gives_pending(): void
    {
        $company = $this->company(null);

        $this->assertSame(VerificationStatus::PENDING, $this->service->verify($company));
        Http::assertNothingSent();
    }

    public function test_a_siret_absent_from_the_matching_establishments_stays_pending(): void
    {
        // Le registre repond a une recherche plein texte : un numero invente
        // dont les neuf premiers chiffres forment un SIREN reel rend l'unite
        // legale, sans l'etablissement demande. Se contenter de ce repli
        // suffisait a se faire verifier avec un SIRET qui n'existe pas.
        $this->registreEntreprises = fn () => Http::response([
            'results' => [[
                'siren' => substr(self::SIRET_VALIDE, 0, 9),
                'activite_principale' => '62.01Z',
                'etat_administratif' => 'A',
                'matching_etablissements' => [[
                    'siret' => '73282932099999',
                    'activite_principale' => '62.01Z',
                    'etat_administratif' => 'A',
                ]],
            ]],
            'total_results' => 1,
        ]);
        $company = $this->company(self::SIRET_VALIDE);

        $this->assertSame(VerificationStatus::PENDING, $this->service->verify($company));
        $this->assertNull($company->fresh()->verified_at);
    }

    public function test_an_unmatched_school_naf_is_still_rejected(): void
    {
        // Le NAF du siege suffit a refuser une ecole meme sans etablissement
        // apparie : on refuse large, on ouvre etroit.
        $this->registreEntreprises = fn () => Http::response([
            'results' => [[
                'siren' => substr(self::SIRET_VALIDE, 0, 9),
                'activite_principale' => '85.32Z',
                'etat_administratif' => 'A',
                'matching_etablissements' => [],
            ]],
            'total_results' => 1,
        ]);
        $company = $this->company(self::SIRET_VALIDE);

        $this->assertSame(VerificationStatus::REJECTED, $this->service->verify($company));
    }

    public function test_blocked_naf_gives_rejected(): void
    {
        $this->registreRepond(self::SIRET_VALIDE, '85.32Z');
        $company = $this->company(self::SIRET_VALIDE);

        $this->assertSame(VerificationStatus::REJECTED, $this->service->verify($company));
        $this->assertStringContainsString('enseignement', $company->fresh()->verification_note);
    }

    public function test_a_cfa_with_a_teaching_naf_is_still_verified(): void
    {
        // Ecart assume par rapport au contrat : un CFA a par definition un
        // code NAF d'enseignement. Lui appliquer la regle des entreprises
        // refuserait l'ecole partenaire, le seul CFA que Jeuncy accepte.
        $this->registreRepond(self::SIRET_VALIDE, '85.32Z');
        $cfa = CfaOrganization::factory()->create(['siret' => self::SIRET_VALIDE]);

        $this->assertSame(VerificationStatus::VERIFIED, $this->service->verify($cfa));
    }

    public function test_closed_establishment_gives_rejected(): void
    {
        $this->registreRepond(self::SIRET_VALIDE, '62.01Z', 'F');
        $company = $this->company(self::SIRET_VALIDE);

        $this->assertSame(VerificationStatus::REJECTED, $this->service->verify($company));
        $this->assertStringContainsString('fermé', $company->fresh()->verification_note);
    }

    public function test_active_establishment_gives_verified_automatically(): void
    {
        $this->registreRepond(self::SIRET_VALIDE);
        $company = $this->company(self::SIRET_VALIDE);

        $this->assertSame(VerificationStatus::VERIFIED, $this->service->verify($company));

        $company->refresh();
        $this->assertNotNull($company->verified_at);
        // null = automatique, pas un clic d'admin.
        $this->assertNull($company->verified_by);
        $this->assertNull($company->verification_note);
    }

    public function test_new_cfa_is_pending(): void
    {
        $user = User::create(['email' => 'contact@nouveau-cfa.example.com', 'password_hash' => 'x', 'role' => UserRole::CFA]);
        $cfa = $this->app->make(CfaOrganizationService::class)->createForUser($user, [
            'name' => 'Nouveau CFA',
            'siret' => self::SIRET_VALIDE,
        ]);

        $this->assertSame(VerificationStatus::PENDING, $cfa->verification_status);
    }

    // ------------------------------------------------------------------
    // La garde
    // ------------------------------------------------------------------

    public function test_require_verified_throws_403(): void
    {
        $company = $this->company(self::SIRET_VALIDE);

        try {
            $this->service->requireVerified($company->user);
            $this->fail('Une entreprise non verifiee ne doit voir aucun candidat.');
        } catch (ApiException $e) {
            $this->assertSame('COMPANY_NOT_VERIFIED', $e->errorCode);
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_require_verified_passes_for_a_verified_company_and_for_staff(): void
    {
        $company = Company::factory()->verified()->create(['siret' => self::SIRET_VALIDE]);
        $staff = User::create(['email' => 'pierre@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::STAFF]);

        $this->service->requireVerified($company->user->fresh());
        $this->service->requireVerified($staff);

        $this->assertTrue(true);
    }

    public function test_a_company_without_a_fiche_is_not_verified(): void
    {
        $user = User::create(['email' => 'seul@example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);

        $this->assertFalse($this->service->isVerified($user));
    }

    // ------------------------------------------------------------------
    // La saisie
    // ------------------------------------------------------------------

    public function test_form_request_refuses_invalid_siret(): void
    {
        $user = User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);

        $this->actingAs($user, 'api')
            ->postJson('/api/company', ['name' => 'NexaTech', 'siret' => self::SIRET_FAUX])
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');

        $this->assertDatabaseCount('companies', 0);
    }
}
