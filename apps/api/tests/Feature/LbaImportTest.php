<?php

namespace Tests\Feature;

use App\Enums\ExternalJobOfferStatus;
use App\Enums\UserRole;
use App\Models\ExternalEmployerBlock;
use App\Models\ExternalJobOffer;
use App\Models\User;
use App\Services\Lba\ExternalOfferFilter;
use App\Services\Lba\LbaImportService;
use App\Services\Lba\LbaOfferMapper;
use App\Support\JsonArrayStreamer;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Import des offres de La bonne alternance.
 *
 * L'exigence numero un (Pierre, 2026-09-15) : aucune ecole ne doit
 * apparaitre. Les tests du filtre pesent donc plus lourd que ceux de la
 * mecanique d'import — mais celle-ci doit aussi survivre a un vrai fichier
 * de plusieurs centaines de Mo, d'ou le lecteur en flux teste a part.
 */
class LbaImportTest extends TestCase
{
    use RefreshDatabase;

    private string $exportPath;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.lba.departements', ['66', '11']);
        Config::set('services.lba.siret_whitelist', ['11111111100011']);
        $this->exportPath = tempnam(sys_get_temp_dir(), 'lba-test-');
    }

    protected function tearDown(): void
    {
        @unlink($this->exportPath);
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Un export factice, fidele a la structure de /job/v1/search
    // ------------------------------------------------------------------

    private function job(array $overrides = []): array
    {
        static $n = 0;
        $n++;
        $base = [
            'identifier' => ['id' => "lba-{$n}", 'partner_job_id' => "job-{$n}", 'partner_label' => 'Hellowork'],
            'workplace' => [
                'siret' => '73282932000074',
                'brand' => null,
                'legal_name' => 'NEXATECH',
                'name' => 'NexaTech',
                'description' => 'PME du numerique.',
                'website' => 'https://nexatech.example.com',
                'size' => '20-49',
                'location' => [
                    'address' => '12 Rue des Lilas 66000 Perpignan',
                    'geopoint' => ['type' => 'Point', 'coordinates' => [2.8956, 42.6986]],
                ],
                'domain' => ['idcc' => 1486, 'opco' => 'ATLAS', 'naf' => ['code' => '62.01Z', 'label' => 'Programmation informatique']],
            ],
            'apply' => ['url' => "https://labonnealternance.apprentissage.beta.gouv.fr/emploi/job-{$n}", 'phone' => null, 'recipient_id' => null],
            'contract' => ['start' => '2026-09-01', 'duration' => 24, 'type' => ['Apprentissage'], 'remote' => 'onsite'],
            'offer' => [
                'title' => "Developpeur web en alternance {$n}",
                'description' => '<p>Rejoins notre equipe.<br>Missions : dev.</p>',
                'desired_skills' => [], 'to_be_acquired_skills' => [], 'access_conditions' => [],
                'opening_count' => 1,
                'publication' => ['creation' => '2026-09-10T08:00:00.000Z', 'expiration' => '2026-11-10T08:00:00.000Z'],
                'rome_codes' => ['M1805'],
                'target_diploma' => ['european' => '6', 'label' => 'Licence, Bachelor'],
                'status' => 'Active',
            ],
            'is_delegated' => false,
        ];

        return array_replace_recursive($base, $overrides);
    }

    // Meme forme que le vrai export (2026-09-16) : un tableau a la racine,
    // indente, offres et recruteurs meles.
    private function writeExport(array $jobs, array $recruiters = []): void
    {
        file_put_contents($this->exportPath, json_encode(array_merge($recruiters, $jobs), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private function import(bool $measureOnly = false): array
    {
        return $this->app->make(LbaImportService::class)->importFromFile($this->exportPath, $measureOnly);
    }

    // ------------------------------------------------------------------
    // Le lecteur en flux
    // ------------------------------------------------------------------

    public function test_the_streamer_reads_every_object_without_loading_the_file(): void
    {
        // Objets plus gros que le tampon de lecture, chaines contenant des
        // accolades et des guillemets echappes : tout ce qui casse un
        // decoupage naif.
        $jobs = [];
        for ($i = 0; $i < 30; $i++) {
            $jobs[] = $this->job(['offer' => ['description' => str_repeat('Texte { avec } des "guillemets" \\ et accolades ', 3000)]]);
        }
        $this->writeExport($jobs, [['identifier' => ['id' => 'r1', 'partner_label' => 'recruteurs_lba']]]);

        $read = iterator_to_array(JsonArrayStreamer::objects($this->exportPath), false);

        $this->assertCount(31, $read);
        $read = array_values(array_filter($read, fn ($o) => ($o['identifier']['partner_label'] ?? null) !== 'recruteurs_lba'));
        $this->assertCount(30, $read);
        $this->assertSame('Developpeur web en alternance '.($read[0]['identifier']['partner_job_id'] === 'job-1' ? 1 : (int) substr($read[0]['identifier']['partner_job_id'], 4)), $read[0]['offer']['title']);
        $this->assertStringContainsString('{ avec }', $read[29]['offer']['description']);
    }

    public function test_the_streamer_also_accepts_an_array_under_a_key(): void
    {
        file_put_contents($this->exportPath, json_encode(['meta' => ['x' => 1], 'jobs' => [['a' => 1], ['a' => 2]]]));

        $this->assertSame([['a' => 1], ['a' => 2]], iterator_to_array(JsonArrayStreamer::objects($this->exportPath, 'jobs'), false));
    }

    // ------------------------------------------------------------------
    // La traduction d'une offre
    // ------------------------------------------------------------------

    public function test_the_mapper_extracts_postal_code_city_and_department(): void
    {
        $row = $this->app->make(LbaOfferMapper::class)->map($this->job());

        $this->assertSame('66000', $row['postal_code']);
        $this->assertSame('Perpignan', $row['city']);
        $this->assertSame('66', $row['department']);
        $this->assertSame('PRESENTIEL', $row['work_mode']);
        $this->assertSame(['M1805'], $row['rome_codes']);
        $this->assertSame(42.6986, $row['latitude']);
        $this->assertSame("Rejoins notre equipe.\nMissions : dev.", $row['description'], 'Le HTML est reduit au texte.');
    }

    public function test_the_mapper_knows_corsica_and_overseas_departments(): void
    {
        $this->assertSame('2A', LbaOfferMapper::departmentFromPostalCode('20000'));
        $this->assertSame('2B', LbaOfferMapper::departmentFromPostalCode('20200'));
        $this->assertSame('974', LbaOfferMapper::departmentFromPostalCode('97400'));
        $this->assertSame('75', LbaOfferMapper::departmentFromPostalCode('75011'));
    }

    public function test_an_offer_without_postal_code_or_apply_url_is_unusable(): void
    {
        $mapper = $this->app->make(LbaOfferMapper::class);

        $this->assertNull($mapper->map($this->job(['workplace' => ['location' => ['address' => 'Perpignan']]])));
        $this->assertNull($mapper->map($this->job(['apply' => ['url' => '']])));
    }

    // ------------------------------------------------------------------
    // Le filtre des ecoles — la regle du projet
    // ------------------------------------------------------------------

    private function reason(array $offer): ?string
    {
        $filter = $this->app->make(ExternalOfferFilter::class);
        $filter->loadManualBlocks();

        return $filter->exclusionReason(array_merge([
            'company_name' => 'NexaTech', 'company_siret' => '73282932000074', 'company_naf' => '62.01Z',
            'description' => 'Rejoins notre equipe.', 'is_delegated' => false,
            'title' => 'Developpeur web en alternance', 'partner_label' => 'Hellowork',
        ], $offer));
    }

    public function test_a_plain_employer_passes(): void
    {
        $this->assertNull($this->reason([]));
    }

    public function test_a_delegated_offer_is_excluded(): void
    {
        $this->assertStringContainsString('deleguee', $this->reason(['is_delegated' => true]));
    }

    public function test_an_education_naf_code_is_excluded(): void
    {
        $this->assertStringContainsString('85.59A', $this->reason(['company_naf' => '85.59A']));
        $this->assertNull($this->reason(['company_naf' => '85.53Z']), 'Une auto-ecole est un employeur.');
    }

    public function test_a_name_from_the_lba_cfa_list_is_excluded(): void
    {
        $this->assertStringContainsString('liste des CFA', $this->reason(['company_name' => 'AFTEC Rennes']));
        $this->assertStringContainsString('liste des CFA', $this->reason(['company_name' => 'Groupe AFTEC RENNES - campus 2']));
    }

    public function test_a_short_name_from_the_list_only_matches_exactly(): void
    {
        // « ADG » est dans la liste : il ne doit pas condamner « Adgest Immobilier ».
        $this->assertNull($this->reason(['company_name' => 'Adgest Immobilier']));
        $this->assertNotNull($this->reason(['company_name' => 'ADG']));
    }

    public function test_a_school_like_name_is_excluded(): void
    {
        $this->assertNotNull($this->reason(['company_name' => 'Campus des Metiers du Roussillon']));
        $this->assertNotNull($this->reason(['company_name' => 'Ecole Superieure de Commerce de Perpignan']));
    }

    public function test_the_vocabulary_of_a_school_in_the_description_is_excluded(): void
    {
        $this->assertNotNull($this->reason(['description' => "Nous recherchons pour l'une de nos entreprises partenaires un alternant en BTS."]));
        // Frequent dans de vraies offres d'entreprise : ne suffit pas ici.
        $this->assertNull($this->reason(['description' => 'Poste a pourvoir pour la rentree 2026, titre RNCP niveau 5 prepare en ecole de commerce.']));
        // Vu sur le vrai export (Leclerc Voyages, Merimani...) : un employeur
        // qui precise que la formation est assuree par un organisme partenaire.
        $this->assertNull($this->reason(['description' => 'Formation assuree en alternance par un organisme de formation partenaire, centre de formation d\x27apprentis de la region.']));
    }

    // Regles ajoutees le 2026-09-17 apres relecture des 727 offres reelles.
    public function test_a_school_advertising_for_its_partner_company_is_excluded(): void
    {
        $this->assertNotNull($this->reason(['description' => 'Grand Sud Formation recherche pour son entreprise partenaire un alternant en BTS.']));
        $this->assertNotNull($this->reason(['description' => 'Le CFA H et C Conseil recherche un apprenti vendeur.']));
        $this->assertNotNull($this->reason(['description' => 'Pre-selection des dossiers par PRH360 Formation, puis entretien avec notre equipe pedagogique.']));
        $this->assertNotNull($this->reason(['description' => 'Rentree en formation le 15 septembre, aucun frais de formation.']));
        $this->assertNotNull($this->reason(['company_name' => 'ASSOCIATION REGIONALE DES ENTREPRISES ALIMENTAIRES - OCCITANIE']));
        $this->assertNotNull($this->reason(['company_name' => 'PRH 360']));
    }

    // Mesure sur le corpus : ces tournures sont courantes chez de vrais
    // employeurs (La Poste et son CFA, agences d'interim, GEIQ) et ne
    // doivent PAS exclure.
    public function test_a_real_employer_naming_its_own_cfa_or_partner_agency_passes(): void
    {
        $this->assertNull($this->reason(['description' => 'Vous preparez et distribuez le courrier aupres d\x27une clientele de particuliers et d\x27entreprises en respectant les standards de qualite de service. La Poste vous propose un contrat en alternance de 12 mois. La formation est assuree par son CFA Formaposte. Vous preparez un titre professionnel RNCP niveau 4.']));
        $this->assertNull($this->reason(['description' => 'Notre agence Manpower recherche pour l\x27un de ses partenaires un operateur CN. Conditions d\x27acces : niveau bac. Entreprise d\x27accueil en Occitanie.']));
        $this->assertNull($this->reason(['description' => 'GEIQ : mise a disposition au sein de notre entreprise partenaire, zero frais de formation, votre permis integralement finance.']));
    }

    // Gabarit des annonces anonymes de l'ISCOD diffusees via France Travail :
    // exclu seulement quand les trois signaux sont reunis.
    public function test_the_anonymous_online_school_template_is_excluded(): void
    {
        $base = ['company_name' => null, 'company_siret' => null, 'partner_label' => 'France Travail', 'description' => 'Vos missions : accueil, mise en rayon, encaissement.'];

        $this->assertStringContainsString('anonyme', $this->reason(['title' => 'Alternance Employe(e) polyvalent(e) - Fenouillet (F/H)'] + $base));
        // Un employeur nomme : c'est une vraie offre au meme titre.
        $this->assertNull($this->reason(['company_name' => 'Carrefour', 'partner_label' => 'France Travail', 'description' => 'Vos missions : accueil.', 'title' => 'Alternance Employe polyvalent - Fenouillet (F/H)']));
        // Sans les parentheses ni la ville : pas le gabarit.
        $this->assertNull($this->reason(['title' => 'Alternance - Conseiller service apres-vente F/H'] + $base));
        $this->assertNull($this->reason(['title' => 'Apprenti Cuisinier (H/F)'] + $base));
        // Autre source que France Travail : pas le gabarit.
        $this->assertNull($this->reason(['partner_label' => 'Hellowork', 'title' => 'Alternance Commercial - Montpellier (F/H)'] + $base));
    }

    public function test_the_partner_school_is_never_excluded_by_the_filter(): void
    {
        $this->assertNull($this->reason([
            'company_name' => 'IDA Formation', 'company_siret' => '11111111100011', 'company_naf' => '85.42Z', 'is_delegated' => true,
        ]));
    }

    public function test_a_manual_block_wins_over_everything_including_the_whitelist(): void
    {
        ExternalEmployerBlock::create(['siret' => '11111111100011', 'display_name' => 'IDA']);

        $this->assertStringContainsString('administrateur', $this->reason(['company_siret' => '11111111100011']));
    }

    public function test_a_manual_block_by_name_works_without_siret(): void
    {
        ExternalEmployerBlock::create(['normalized_name' => 'l ecole des talents', 'display_name' => "L'École des Talents"]);

        $this->assertStringContainsString('administrateur', $this->reason(['company_name' => "L'ÉCOLE DES TALENTS", 'company_siret' => null]));
    }

    // ------------------------------------------------------------------
    // L'import complet
    // ------------------------------------------------------------------

    public function test_the_import_keeps_the_perimeter_and_stores_exclusions_with_their_reason(): void
    {
        $this->writeExport([
            $this->job(),                                                                        // 66, visible
            $this->job(['workplace' => ['location' => ['address' => '3 Av. Foch 11000 Carcassonne']]]), // 11, visible
            $this->job(['workplace' => ['location' => ['address' => '1 Rue X 31000 Toulouse']]]),        // hors perimetre
            $this->job(['is_delegated' => true]),                                                        // exclue
            $this->job(['workplace' => ['name' => 'CFA du Batiment 66']]),                               // exclue
            $this->job(['offer' => ['status' => 'Filled']]),                                             // pourvue
            ['identifier' => ['partner_label' => 'recruteurs_lba', 'id' => 'r1']],                       // pas une offre
        ]);

        $report = $this->import();

        $this->assertSame(7, $report['lus']);
        $this->assertSame(1, $report['recruteurs_ignores']);
        $this->assertSame(1, $report['hors_perimetre']);
        $this->assertSame(1, $report['inactives']);
        $this->assertSame(4, $report['retenues']);
        $this->assertSame(2, $report['actives']);
        $this->assertSame(2, $report['exclues']);
        $this->assertSame(['11' => 1, '66' => 3], $report['par_departement']);

        $this->assertSame(2, ExternalJobOffer::where('status', ExternalJobOfferStatus::ACTIVE)->count());
        $excluded = ExternalJobOffer::where('status', ExternalJobOfferStatus::EXCLUDED)->pluck('exclusion_reason')->all();
        $this->assertCount(2, $excluded);
        $this->assertStringContainsString('deleguee', $excluded[0]);
        $this->assertStringContainsString('CFA', $excluded[1]);
        $this->assertNotNull(LbaImportService::lastReport());
    }

    public function test_the_import_is_idempotent_and_removes_what_disappeared(): void
    {
        $a = $this->job();
        $b = $this->job();
        $this->writeExport([$a, $b]);
        $this->import();
        $this->assertSame(2, ExternalJobOffer::count());
        $idA = ExternalJobOffer::where('partner_job_id', $a['identifier']['partner_job_id'])->value('id');

        // Meme offre A (titre change), B a disparu.
        $a['offer']['title'] = 'Titre mis a jour';
        $this->writeExport([$a]);
        $report = $this->import();

        $this->assertSame(1, $report['supprimees']);
        $this->assertSame(1, ExternalJobOffer::count());
        $this->assertSame($idA, ExternalJobOffer::first()->id, 'Une offre connue garde son identifiant (et donc son URL).');
        $this->assertSame('Titre mis a jour', ExternalJobOffer::first()->title);
    }

    public function test_measure_mode_writes_nothing(): void
    {
        $this->writeExport([$this->job(), $this->job(['is_delegated' => true])]);
        Cache::forget(LbaImportService::CACHE_KEY);

        $report = $this->import(measureOnly: true);

        $this->assertSame(2, $report['retenues']);
        $this->assertSame(0, ExternalJobOffer::count());
        $this->assertTrue(LbaImportService::lastReport()['mesure_seulement'], 'Le rapport d\x27une passe a blanc est conserve pour etre lu le lendemain.');
    }

    public function test_the_command_does_nothing_without_an_api_key(): void
    {
        Config::set('services.lba.api_key', null);
        Http::fake(['api.apprentissage.beta.gouv.fr/*' => fn () => throw new \RuntimeException('ne doit pas etre appele')]);

        $this->artisan('lba:import')->expectsOutputToContain('LBA_API_KEY absente')->assertSuccessful();
    }

    public function test_the_command_imports_from_a_local_file(): void
    {
        $this->writeExport([$this->job()]);

        $this->artisan('lba:import', ['--fichier' => $this->exportPath])->assertSuccessful();

        $this->assertSame(1, ExternalJobOffer::count());
    }

    // ------------------------------------------------------------------
    // Ce que voit le public
    // ------------------------------------------------------------------

    public function test_public_search_only_returns_active_offers_without_siret_or_naf(): void
    {
        $this->writeExport([$this->job(), $this->job(['is_delegated' => true])]);
        $this->import();

        $response = $this->getJson('/api/job-offers/external/search')->assertOk();

        $this->assertSame(1, $response->json('data.total'));
        $item = $response->json('data.data.0');
        $this->assertSame('NexaTech', $item['company_name']);
        $this->assertSame('lba', $item['source']);
        $this->assertArrayNotHasKey('company_siret', $item);
        $this->assertArrayNotHasKey('company_naf', $item);
        $this->assertArrayNotHasKey('exclusion_reason', $item);
        $this->assertStringStartsWith('https://', $item['apply_url']);
    }

    public function test_public_search_filters_and_ignores_non_alternance_contracts(): void
    {
        $this->writeExport([
            $this->job(),
            $this->job(['workplace' => ['location' => ['address' => '3 Av. Foch 11000 Carcassonne']], 'offer' => ['title' => 'Boulanger en alternance']]),
        ]);
        $this->import();

        $this->assertSame(1, $this->getJson('/api/job-offers/external/search?city=Carcassonne')->json('data.total'));
        $this->assertSame(1, $this->getJson('/api/job-offers/external/search?q=boulanger')->json('data.total'));
        $this->assertSame(2, $this->getJson('/api/job-offers/external/search?contract_type=ALTERNANCE')->json('data.total'));
        $this->assertSame(0, $this->getJson('/api/job-offers/external/search?contract_type=SAISONNIER')->json('data.total'));
    }

    public function test_the_public_counter_adds_jeuncy_and_partner_offers(): void
    {
        $this->writeExport([$this->job(), $this->job(), $this->job(['is_delegated' => true])]);
        $this->import();

        $this->getJson('/api/job-offers/count')
            ->assertOk()
            ->assertJsonPath('data.partenaires', 2)
            ->assertJsonPath('data.jeuncy', 0)
            ->assertJsonPath('data.total', 2);
    }

    public function test_an_excluded_offer_is_not_reachable_by_id(): void
    {
        $this->writeExport([$this->job(['is_delegated' => true])]);
        $this->import();
        $id = ExternalJobOffer::first()->id;

        $this->getJson("/api/job-offers/external/{$id}")->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // L'admin ferme les mailles
    // ------------------------------------------------------------------

    public function test_an_admin_blocks_an_employer_and_all_its_offers_disappear_at_once(): void
    {
        $this->writeExport([$this->job(), $this->job(), $this->job(['workplace' => ['siret' => '99999999900019', 'name' => 'Autre SAS']])]);
        $this->import();
        $admin = User::create(['email' => 'admin@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::ADMIN]);
        $offer = ExternalJobOffer::where('company_name', 'NexaTech')->first();

        $this->actingAs($admin, 'api')
            ->postJson("/api/admin/external-job-offers/{$offer->id}/block-employer", ['reason' => 'ecole deguisee'])
            ->assertStatus(201)
            ->assertJsonPath('data.offres_retirees', 2);

        $this->assertSame(1, ExternalJobOffer::where('status', ExternalJobOfferStatus::ACTIVE)->count());

        // Et l'import suivant ne les remet pas en ligne.
        $this->import();
        $this->assertSame(1, ExternalJobOffer::where('status', ExternalJobOfferStatus::ACTIVE)->count());
    }

    public function test_an_admin_removes_a_single_offer_and_the_next_import_keeps_it_removed(): void
    {
        $this->writeExport([$this->job(), $this->job()]);
        $this->import();
        $admin = User::create(['email' => 'admin@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::ADMIN]);
        $offer = ExternalJobOffer::first();

        $this->actingAs($admin, 'api')
            ->postJson("/api/admin/external-job-offers/{$offer->id}/exclude")
            ->assertOk()
            ->assertJsonPath('data.status', 'EXCLUDED');

        $this->assertSame(1, ExternalJobOffer::where('status', ExternalJobOfferStatus::ACTIVE)->count(), 'L\x27autre offre du meme employeur reste visible.');
        $this->getJson("/api/job-offers/external/{$offer->id}")->assertStatus(404);

        // La passe suivante reecrit le statut calcule... puis remet le retrait.
        $report = $this->import();
        $this->assertSame(1, $report['retirees_par_admin']);
        $this->assertSame(ExternalJobOfferStatus::EXCLUDED, $offer->fresh()->status);
        $this->assertSame(LbaImportService::ADMIN_EXCLUSION_REASON, $offer->fresh()->exclusion_reason);

        // Retablir : visible a nouveau, et la passe suivante n'y touche plus.
        $this->actingAs($admin, 'api')
            ->postJson("/api/admin/external-job-offers/{$offer->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');
        $this->import();
        $this->assertSame(ExternalJobOfferStatus::ACTIVE, $offer->fresh()->status);
    }

    public function test_only_an_admin_can_block(): void
    {
        $this->writeExport([$this->job()]);
        $this->import();
        $company = User::create(['email' => 'rh@example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);

        $this->actingAs($company, 'api')
            ->postJson('/api/admin/external-job-offers/'.ExternalJobOffer::first()->id.'/block-employer')
            ->assertStatus(403);
    }

    public function test_admin_stats_expose_the_filter_audit(): void
    {
        $this->writeExport([$this->job(), $this->job(['is_delegated' => true])]);
        $this->import();
        $admin = User::create(['email' => 'admin@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::ADMIN]);

        $this->actingAs($admin, 'api')
            ->getJson('/api/admin/external-job-offers/stats')
            ->assertOk()
            ->assertJsonPath('data.actives', 1)
            ->assertJsonPath('data.exclues', 1)
            ->assertJsonPath('data.dernier_import.retenues', 2);
    }
    // ------------------------------------------------------------------
    // Declenchement a la demande depuis /deploy
    // ------------------------------------------------------------------

    public function test_the_deploy_endpoint_requests_an_import_for_the_next_cron_pass(): void
    {
        Config::set('app.deploy_token', 'jeton-test');

        $this->get('/deploy/jeton-test/lba-import?maintenant=1')
            ->assertOk()
            ->assertJsonPath('import_demande', fn ($v) => is_string($v));

        $this->assertTrue(Cache::has('lba.import_demande'));

        $this->get('/deploy/jeton-test/lba-import?annuler=1')->assertOk();
        $this->assertFalse(Cache::has('lba.import_demande'));
    }

    public function test_the_deploy_endpoint_refuses_a_wrong_token(): void
    {
        Config::set('app.deploy_token', 'jeton-test');

        $this->get('/deploy/mauvais/lba-import?maintenant=1')->assertStatus(404);
        $this->assertFalse(Cache::has('lba.import_demande'));
    }

    // Le planificateur : due si demandee OU si la passe du jour n'a pas eu lieu.
    public function test_the_schedule_runs_the_import_on_demand_and_once_a_day_otherwise(): void
    {
        // Le planificateur se peuple au demarrage de la console (voir
        // ScheduleRunsAtAnyMinuteTest) : un appel Artisan quelconque suffit.
        Artisan::call('list', ['--raw' => true]);
        $schedule = $this->app->make(Schedule::class);
        $event = collect($schedule->events())->first(fn ($e) => str_contains((string) $e->command, 'lba:import'));
        $this->assertNotNull($event);

        Cache::forget('lba.import_demande');
        Cache::forever('planificateur.derniere_passe.lba:import', now('Europe/Paris')->subHours(4)->toDateString());
        $this->assertFalse($event->filtersPass($this->app), 'Deja passee aujourd\x27hui : pas due.');

        Cache::forever('lba.import_demande', now()->toDateTimeString());
        $this->assertTrue($event->filtersPass($this->app), 'Demandee : due meme si deja passee.');

        Cache::forget('lba.import_demande');
        Cache::forget('planificateur.derniere_passe.lba:import');
        $this->assertTrue($event->filtersPass($this->app), 'Jamais passee : due.');
    }
}
