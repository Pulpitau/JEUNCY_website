<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\CandidateProfile;
use App\Models\Skill;
use App\Models\Subscription;
use App\Models\User;
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

        Subscription::create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::ACTIVE,
            'amount_cents' => 49900,
            'stripe_subscription_id' => 'sub_'.$user->id,
            'stripe_customer_id' => 'cus_'.$user->id,
        ]);

        return $user;
    }

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

    // La liste sert a juger la pertinence d'un profil, pas a moissonner des
    // coordonnees : ni telephone, ni adresse, ni date de naissance, ni email.
    public function test_search_results_expose_no_direct_contact_details(): void
    {
        $this->makeCandidate();

        $first = $this->service->search($this->makeSubscriber(), [])->items()[0];
        $payload = $first->toArray();

        foreach (['phone', 'address', 'birth_date', 'postal_code'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $payload, "La liste ne doit pas exposer {$forbidden}.");
        }
        $this->assertArrayNotHasKey('user', $payload, 'La liste ne doit pas exposer le compte (email).');
        // Ce dont le recruteur a besoin pour filtrer, en revanche, est bien la.
        $this->assertSame('Perpignan', $payload['city']);
        $this->assertSame('Developpeuse web en alternance', $payload['headline']);
    }

    // La fiche, elle, porte les coordonnees : c'est ce que le recruteur vient
    // chercher une fois le profil juge pertinent.
    public function test_detail_exposes_contact_details(): void
    {
        $profile = $this->makeCandidate();

        $found = $this->service->find($this->makeSubscriber(), $profile->id);

        $this->assertSame('0600000000', $found->phone);
        $this->assertNotNull($found->user->email);
    }

    // --- Filtres ---

    public function test_city_filter_narrows_results(): void
    {
        $this->makeCandidate(['city' => 'Perpignan']);
        $this->makeCandidate(['city' => 'Montpellier']);
        $subscriber = $this->makeSubscriber();

        $this->assertSame(1, $this->service->search($subscriber, ['city' => 'Perpi'])->total());
        $this->assertSame(2, $this->service->search($subscriber, [])->total());
    }

    public function test_keyword_filter_matches_headline_and_bio(): void
    {
        $this->makeCandidate(['headline' => 'Cuisinier saisonnier', 'bio' => 'Restauration']);
        $this->makeCandidate(['headline' => 'Developpeuse web', 'bio' => 'React et TypeScript']);
        $subscriber = $this->makeSubscriber();

        $this->assertSame(1, $this->service->search($subscriber, ['q' => 'Cuisinier'])->total());
        $this->assertSame(1, $this->service->search($subscriber, ['q' => 'TypeScript'])->total());
    }

    public function test_driving_license_filter_excludes_empty_values(): void
    {
        $this->makeCandidate(['driving_license' => 'Permis B']);
        $this->makeCandidate(['driving_license' => null]);
        $this->makeCandidate(['driving_license' => '']);

        $results = $this->service->search($this->makeSubscriber(), ['driving_license' => true]);

        $this->assertSame(1, $results->total());
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
}
