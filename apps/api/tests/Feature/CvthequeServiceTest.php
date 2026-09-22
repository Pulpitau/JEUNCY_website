<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\Company;
use App\Models\Skill;
use App\Models\Subscription;
use App\Models\User;
use App\Services\BlockService;
use App\Services\CvthequeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CvthequeServiceTest extends TestCase
{
    use RefreshDatabase;

    private CvthequeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(CvthequeService::class);
    }

    private function makeSubscriber(string $email = 'rh@nexatech.example.com'): User
    {
        $user = User::create(['email' => $email, 'password_hash' => 'x', 'role' => UserRole::COMPANY]);

        // Fiche entreprise VERIFIED : depuis le lot 1, la CVtheque exige que
        // l'employeur soit verifie en plus d'avoir un acces. Un compte sans
        // Company du tout n'aurait aucune organisation a verifier et serait
        // refuse, ce qui masquerait ce que ces tests veulent prouver.
        Company::factory()->verified()->create(['user_id' => $user->id, 'name' => 'NexaTech']);

        Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 49900,
            'stripe_subscription_id' => 'sub_'.$user->id,
            'stripe_customer_id' => 'cus_'.$user->id,
        ]);

        return $user;
    }

    // Volontairement sans Company : c'est l'absence d'abonnement qui doit
    // etre signalee (402), pas l'absence de verification (403).
    private function makeNonSubscriber(): User
    {
        return User::create(['email' => 'sans-abo@example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
    }

    private function makeCandidate(array $overrides = []): CandidateProfile
    {
        static $n = 0;
        $n++;
        $user = User::create(['email' => "candidat{$n}@example.com", 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        return CandidateProfile::create(array_merge([
            'user_id' => $user->id,
            'first_name' => 'Lea',
            'last_name' => 'Girard',
            'headline' => 'Developpeuse web en alternance',
            'city' => 'Perpignan',
            'phone' => '0600000000',
            'address' => '12 rue des Tests',
            'birth_date' => '2004-05-01',
            'bio' => 'Passionnee de React.',
        ], $overrides));
    }

    // --- Garde d'abonnement ---

    public function test_search_is_refused_without_active_subscription(): void
    {
        $this->makeCandidate();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage("L'accès à la CVthèque est réservé aux abonnés.");

        $this->service->search($this->makeNonSubscriber(), []);
    }

    public function test_show_is_refused_without_active_subscription(): void
    {
        $profile = $this->makeCandidate();

        $this->expectException(ApiException::class);

        $this->service->find($this->makeNonSubscriber(), $profile->id);
    }

    public function test_subscriber_can_search(): void
    {
        $this->makeCandidate();

        $results = $this->service->search($this->makeSubscriber(), []);

        $this->assertSame(1, $results->total());
    }

    // --- Garde de verification (MOBILE.md §4.0) ---

    // Une entreprise qui a l'acces mais dont la fiche n'est pas verifiee ne
    // doit voir aucun candidat : l'invariant « jamais d'employeur non
    // VERIFIED face a un candidat » ne souffre pas d'exception, pas meme la
    // CVtheque du site.
    public function test_unverified_company_is_refused(): void
    {
        $this->makeCandidate();
        $user = User::create(['email' => 'pending@example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
        Company::factory()->create(['user_id' => $user->id]);
        Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 49900,
            'stripe_subscription_id' => 'sub_pending',
            'stripe_customer_id' => 'cus_pending',
        ]);

        try {
            $this->service->search($user, []);
            $this->fail('Une entreprise non verifiee ne doit pas lire la CVtheque.');
        } catch (ApiException $e) {
            $this->assertSame('COMPANY_NOT_VERIFIED', $e->errorCode);
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    // La garde ne vaut pas que pour la liste : une fiche ouverte par son
    // identifiant, ou un CV telecharge directement, la contournerait sinon.
    public function test_unverified_company_is_refused_on_detail_and_download(): void
    {
        $profile = $this->makeCandidate();
        $user = $this->makeSubscriber('pending2@example.com');
        $user->company->verification_status = VerificationStatus::PENDING;
        $user->company->verified_at = null;
        $user->company->save();
        $user = $user->fresh();

        foreach ([fn () => $this->service->find($user, $profile->id), fn () => $this->service->downloadCv($user, $profile->id)] as $appel) {
            try {
                $appel();
                $this->fail('Une entreprise non verifiee ne doit ni lire une fiche ni telecharger un CV.');
            } catch (ApiException $e) {
                $this->assertSame('COMPANY_NOT_VERIFIED', $e->errorCode);
            }
        }
    }

    // --- Blocages (user_blocks) ---

    // Le blocage vaut dans les DEUX sens et sur les trois sorties. Silencieux
    // a dessein : un profil absent doit etre indiscernable d'un profil qui
    // n'existe pas, et la fiche rend le meme 404 qu'un profil retire.
    public function test_blocked_candidate_is_invisible_in_list_detail_and_download(): void
    {
        $profile = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();

        // Sens candidat -> employeur : c'est le candidat qui bloque, et c'est
        // pourtant l'employeur qui doit cesser de le voir.
        $this->app->make(BlockService::class)->block($profile->user, $recruiter->id);

        $this->assertSame(0, $this->service->search($recruiter, [])->total());

        foreach ([fn () => $this->service->find($recruiter, $profile->id), fn () => $this->service->downloadCv($recruiter, $profile->id)] as $appel) {
            try {
                $appel();
                $this->fail('Un profil bloque ne doit etre accessible par aucune des deux routes.');
            } catch (ApiException $e) {
                $this->assertSame('CANDIDATE_PROFILE_NOT_FOUND', $e->errorCode);
                $this->assertSame(404, $e->getStatusCode());
            }
        }
    }

    public function test_block_in_the_other_direction_hides_too(): void
    {
        $profile = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();

        $this->app->make(BlockService::class)->block($recruiter, $profile->user_id);

        $this->assertSame(0, $this->service->search($recruiter, [])->total());
    }

    public function test_a_block_between_others_hides_nobody(): void
    {
        $profile = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();
        $autre = $this->makeSubscriber('rh@autre.example.com');

        $this->app->make(BlockService::class)->block($profile->user, $autre->id);

        $this->assertSame(1, $this->service->search($recruiter, [])->total());
    }

    // --- Droit d'opposition (RGPD art. 21) ---

    public function test_candidate_who_opted_out_is_absent_from_search(): void
    {
        $this->makeCandidate(['is_visible_in_cvtheque' => false]);

        $results = $this->service->search($this->makeSubscriber(), []);

        $this->assertSame(0, $results->total());
    }

    // Le retrait doit tenir meme si le recruteur connait deja l'identifiant du
    // profil : sinon un lien mis en favori continuerait de fonctionner apres
    // que le candidat s'est retire.
    public function test_candidate_who_opted_out_is_not_reachable_by_id(): void
    {
        $profile = $this->makeCandidate(['is_visible_in_cvtheque' => false]);
        $subscriber = $this->makeSubscriber();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Profil introuvable.');

        $this->service->find($subscriber, $profile->id);
    }

    public function test_profiles_are_visible_by_default(): void
    {
        $profile = $this->makeCandidate();

        $this->assertTrue($profile->fresh()->is_visible_in_cvtheque);
    }

    // --- Minimisation des donnees ---

    // Ni la liste ni la fiche ne servent a moissonner des coordonnees : la
    // regle d'exposition unique s'applique aux deux (MOBILE.md §4.3).
    public function test_search_results_expose_no_direct_contact_details(): void
    {
        $this->makeCandidate();

        $card = $this->service->search($this->makeSubscriber(), [])->items()[0];

        foreach (['phone', 'address', 'birth_date', 'postal_code', 'last_name', 'bio', 'user'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $card, "La liste ne doit pas exposer {$forbidden}.");
        }
        // La ville a disparu avec le filtre qui la cherchait : un employeur
        // n'a pas a savoir ou vit un candidat avant qu'il postule.
        $this->assertArrayNotHasKey('city', $card);

        // Ce dont le recruteur a besoin pour juger la pertinence, en revanche,
        // est bien la.
        $this->assertSame('Lea', $card['first_name']);
        $this->assertSame('G', $card['last_name_initial']);
        $this->assertSame('Developpeuse web en alternance', $card['headline']);
    }

    // La fiche ne rajoute plus les coordonnees : elle rajoute le detail du
    // parcours, et le seul fait de savoir si le CV est partageable.
    public function test_detail_exposes_no_contact_details(): void
    {
        $profile = $this->makeCandidate();

        $card = $this->service->find($this->makeSubscriber(), $profile->id);

        foreach (['phone', 'address', 'city', 'last_name', 'birth_date', 'user', 'cv_file_url'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $card, "La fiche ne doit pas exposer {$forbidden}.");
        }
        $this->assertArrayHasKey('cv_available', $card);
        $this->assertFalse($card['cv_available']);
    }

    // --- Filtres ---

    public function test_keyword_filter_matches_headline_but_not_bio(): void
    {
        $this->makeCandidate(['headline' => 'Cuisinier saisonnier', 'bio' => 'Restauration']);
        $this->makeCandidate(['headline' => 'Developpeuse web', 'bio' => 'React et TypeScript']);
        $subscriber = $this->makeSubscriber();

        $this->assertSame(1, $this->service->search($subscriber, ['q' => 'Cuisinier'])->total());
        // La bio n'est plus montree : la chercher la revelerait par inference
        // (compter les resultats vaut lecture).
        $this->assertSame(0, $this->service->search($subscriber, ['q' => 'TypeScript'])->total());
    }

    // Le nom n'est plus montre, il n'est donc plus cherchable — sauf par
    // l'equipe Jeuncy, qui est deja responsable de traitement de ces donnees
    // et dont c'est le besoin quotidien (retrouver un candidat au telephone).
    public function test_last_name_search_only_for_staff(): void
    {
        $this->makeCandidate(['first_name' => 'Rostom', 'last_name' => 'Ghazli']);

        $this->assertSame(0, $this->service->search($this->makeSubscriber(), ['q' => 'Ghazli'])->total());

        $staff = User::create(['email' => 'collegue@jeuncy.com', 'password_hash' => 'x', 'role' => UserRole::STAFF]);
        $this->assertSame(1, $this->service->search($staff, ['q' => 'Ghazli'])->total());
    }

    public function test_driving_license_filter_uses_the_structured_column(): void
    {
        $this->makeCandidate(['has_driving_license' => true]);
        $this->makeCandidate(['has_driving_license' => false]);
        // Ancien profil : le texte libre subsiste mais ne vaut plus reponse.
        // La reprise (candidates:migrate-driving-license, lot A) le traduit.
        $this->makeCandidate(['driving_license' => 'Permis B']);

        $results = $this->service->search($this->makeSubscriber(), ['has_driving_license' => true]);

        $this->assertSame(1, $results->total());
    }

    // Le filtre par ville a disparu des DEUX cotes : la Form Request ne le
    // valide plus (donc validated() ne le transmet pas) et le service ne le
    // lit plus. Le second point se teste seul : un appel direct au service
    // avec une ville ne doit rien restreindre, sinon un futur controleur qui
    // passerait $request->all() la reintroduirait sans qu'on le voie.
    public function test_a_city_filter_narrows_nothing_anymore(): void
    {
        $this->makeCandidate(['city' => 'Perpignan']);
        $this->makeCandidate(['city' => 'Montpellier']);

        $this->assertSame(2, $this->service->search($this->makeSubscriber(), ['city' => 'Perpi'])->total());
    }

    // La liste blanche tenue jusqu'au JSON reellement envoye. Les tests
    // au-dessus interrogent le service ; celui-ci traverse le controleur, la
    // pagination et l'enveloppe { success, data } — c'est la seule facon de
    // prouver qu'aucune de ces trois couches ne rajoute quelque chose.
    public function test_the_http_payload_carries_no_forbidden_key(): void
    {
        $profile = $this->makeCandidate();
        $recruiter = $this->makeSubscriber();

        $interdits = [
            'last_name', 'city', 'postal_code', 'address', 'phone', 'email',
            'birth_date', 'age', 'latitude', 'longitude', 'device_latitude',
            'device_longitude', 'cv_file_url', 'linkedin_url', 'video_url',
            'portfolio_url', 'bio', 'hobbies', 'user_id', 'user',
        ];

        $liste = $this->actingAs($recruiter, 'api')->getJson('/api/cvtheque')
            ->assertOk()->json('data.data.0');
        $fiche = $this->actingAs($recruiter, 'api')->getJson('/api/cvtheque/'.$profile->id)
            ->assertOk()->json('data');

        foreach ($interdits as $cle) {
            $this->assertArrayNotHasKey($cle, $liste, "La liste JSON ne doit pas porter {$cle}.");
            $this->assertArrayNotHasKey($cle, $fiche, "La fiche JSON ne doit pas porter {$cle}.");
        }

        // Et pas davantage par une valeur : ni le nom, ni la ville, ni le
        // telephone ne doivent se retrouver ailleurs dans le corps.
        foreach (['Girard', 'Perpignan', '0600000000', '12 rue des Tests'] as $valeur) {
            $this->assertStringNotContainsString($valeur, json_encode($liste, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString($valeur, json_encode($fiche, JSON_THROW_ON_ERROR));
        }
    }

    // --- Bornes d'age, cote Form Request (requete HTTP) ---

    // « Jusqu'a 25 ans » sans borne basse : le formulaire de la CVtheque le
    // propose tel quel. Applique sans condition, gte:age_min comparait
    // age_max a la chaine « age_min » et refusait la recherche.
    public function test_age_max_alone_is_accepted(): void
    {
        $this->makeCandidate(['birth_date' => now()->subYears(20)->toDateString()]);
        $this->makeCandidate(['birth_date' => now()->subYears(30)->toDateString()]);

        $this->actingAs($this->makeSubscriber(), 'api')
            ->getJson('/api/cvtheque?age_max=25')
            ->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_age_max_below_age_min_is_refused(): void
    {
        $this->actingAs($this->makeSubscriber(), 'api')
            ->getJson('/api/cvtheque?age_min=25&age_max=18')
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'INVALID_INPUT');
    }

    // 16 ans, borne basse du modele match. En dessous, la requete est refusee
    // plutot que silencieusement elargie.
    public function test_age_min_below_16_is_refused(): void
    {
        $this->actingAs($this->makeSubscriber(), 'api')
            ->getJson('/api/cvtheque?age_min=15')
            ->assertStatus(400);
    }

    // Une URL forgee avec ?city= ne doit pas ressusciter le filtre : la Form
    // Request ne le valide plus, donc validated() ne le transmet pas.
    public function test_a_forged_city_parameter_is_dropped_by_the_form_request(): void
    {
        $this->makeCandidate(['city' => 'Perpignan']);
        $this->makeCandidate(['city' => 'Montpellier']);

        $this->actingAs($this->makeSubscriber(), 'api')
            ->getJson('/api/cvtheque?city=Perpignan')
            ->assertOk()
            ->assertJsonPath('data.total', 2);
    }

    // Plusieurs competences cochees = ET, pas OU : un recruteur qui en coche
    // trois veut les profils qui les ont toutes.
    public function test_multiple_skills_are_combined_with_and(): void
    {
        $withBoth = $this->makeCandidate();
        $withBoth->skills()->attach([
            Skill::create(['name' => 'React'])->id,
            Skill::create(['name' => 'SQL'])->id,
        ]);

        $withOne = $this->makeCandidate();
        $withOne->skills()->attach(Skill::where('name', 'React')->first()->id);

        $subscriber = $this->makeSubscriber();

        $this->assertSame(2, $this->service->search($subscriber, ['skills' => ['React']])->total());
        $this->assertSame(1, $this->service->search($subscriber, ['skills' => ['React', 'SQL']])->total());
    }

    // L'equipe Jeuncy doit pouvoir consulter ce qu'elle vend sans souscrire un
    // abonnement de complaisance, qui gonflerait les revenus du back-office.
    public function test_admin_accesses_cvtheque_without_any_subscription(): void
    {
        $candidate = $this->makeCandidate();
        $admin = User::create([
            'email' => 'admin@jeuncy.com',
            'password_hash' => 'x',
            'role' => UserRole::ADMIN,
        ]);

        $this->assertTrue($this->service->hasAccess($admin));
        $this->assertSame(1, $this->service->search($admin, [])->total());
        $this->assertSame($candidate->id, $this->service->find($admin, $candidate->id)['id']);
    }

    // L'admin voit la CVtheque, mais ne contourne pas le choix RGPD du
    // candidat : un profil retire de la CVtheque reste invisible pour lui.
    public function test_admin_does_not_bypass_candidate_opt_out(): void
    {
        $hidden = $this->makeCandidate();
        $hidden->update(['is_visible_in_cvtheque' => false]);

        $admin = User::create([
            'email' => 'admin@jeuncy.com',
            'password_hash' => 'x',
            'role' => UserRole::ADMIN,
        ]);

        $this->assertSame(0, $this->service->search($admin, [])->total());
        $this->expectException(ApiException::class);
        $this->service->find($admin, $hidden->id);
    }
}
