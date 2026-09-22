<?php

namespace Tests\Feature;

use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Presenters\CandidateCardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La regle d'exposition unique (contrat lot 1 §4, MOBILE.md §4.3).
 *
 * Un seul presenteur sert le deck employeur de l'app ET la CVtheque du site :
 * si la liste blanche tient ici, elle tient partout. Ce fichier est donc le
 * test qui garde l'invariant « rien de nominatif ni de localisant avant que
 * le candidat ait postule » — le plus cher a perdre de tout le lot.
 *
 * Il teste volontairement la SORTIE, pas l'implementation : une colonne
 * ajoutee demain a candidate_profiles ne doit pas apparaitre dans la carte
 * sans qu'on l'ait decide, et c'est exactement ce que verifie
 * test_forbidden_keys_never_leak.
 */
class CandidateCardPresenterTest extends TestCase
{
    use RefreshDatabase;

    private CandidateCardPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->presenter = $this->app->make(CandidateCardPresenter::class);
        // Date fixe : une tranche d'age calculee ne doit pas faire echouer le
        // test le jour d'un anniversaire.
        Carbon::setTestNow('2026-09-22 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Un profil dont TOUTES les colonnes sensibles sont renseignees : un
     * champ laisse a null passerait le test sans rien prouver.
     */
    private function fullProfile(): CandidateProfile
    {
        $profile = CandidateProfile::factory()
            ->adult()
            ->withPreferences()
            ->create([
                'first_name' => 'Lea',
                'last_name' => 'Girard',
                'headline' => 'Vendeuse en alternance',
                'city' => 'Perpignan',
                'postal_code' => '66000',
                'address' => '12 rue des Tests',
                'phone' => '0600000000',
                'bio' => 'Passionnee de vente, joignable au 0600000000.',
                'hobbies' => 'Randonnee',
                'photo_url' => '/storage/photos/portrait.jpg',
                'linkedin_url' => 'https://linkedin.com/in/lea',
                'video_url' => 'https://youtu.be/abc',
                'portfolio_url' => 'https://lea.example.com',
                'cv_file_url' => '/storage/cv/lea.pdf',
                'cv_original_filename' => 'Mon CV Canva.pdf',
                'driving_license' => 'Permis B, vehicule',
            ]);

        $profile->latitude = 42.70;
        $profile->longitude = 2.90;
        $profile->device_latitude = 42.69;
        $profile->device_longitude = 2.89;
        $profile->device_located_at = now();
        $profile->saveQuietly();

        $profile->experiences()->create([
            'title' => 'Vendeuse',
            'company' => 'Zara',
            'location' => 'Perpignan',
            'description' => 'Accueil client, joignable au 0600000000.',
            'start_date' => '2025-06-01',
            'end_date' => '2025-08-31',
        ]);
        $profile->educations()->create([
            'degree' => 'Bac pro Commerce',
            'school' => 'Lycee Maillol',
            'field_of_study' => 'Commerce',
            'start_date' => '2024-09-01',
        ]);
        $profile->languages()->create(['name' => 'Anglais', 'level' => 'B1']);

        return $profile->fresh();
    }

    public function test_forbidden_keys_never_leak(): void
    {
        $card = $this->presenter->present($this->fullProfile());

        $interdits = [
            'last_name', 'city', 'postal_code', 'address', 'phone', 'email',
            'birth_date', 'age', 'latitude', 'longitude', 'device_latitude',
            'device_longitude', 'device_located_at', 'cv_file_url',
            'cv_original_filename', 'linkedin_url', 'video_url',
            'portfolio_url', 'bio', 'hobbies', 'user_id', 'updated_at',
            'driving_license', 'is_visible_in_cvtheque',
        ];

        foreach ($interdits as $cle) {
            $this->assertArrayNotHasKey($cle, $card, "La carte ne doit jamais porter {$cle}.");
        }

        // Le nom complet ne doit pas non plus reapparaitre par une valeur :
        // une initiale, pas « Girard » ni « Lea Girard ».
        $this->assertStringNotContainsString('Girard', json_encode($card, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('Perpignan', json_encode($card, JSON_THROW_ON_ERROR));

        // Ce qui doit y etre, en revanche, y est.
        $this->assertSame('Lea', $card['first_name']);
        $this->assertSame('G', $card['last_name_initial']);
        $this->assertSame('18-20', $card['age_band']);
        $this->assertSame('Vendeuse en alternance', $card['headline']);
        $this->assertTrue($card['has_uploaded_cv']);
    }

    // Les sous-objets sont la fuite la plus facile a oublier : une experience
    // porte un lieu et une description en texte libre.
    public function test_forbidden_keys_never_leak_from_experiences_and_educations(): void
    {
        $card = $this->presenter->present($this->fullProfile());

        $this->assertCount(1, $card['experiences']);
        foreach (['location', 'description', 'candidate_profile_id', 'id'] as $cle) {
            $this->assertArrayNotHasKey($cle, $card['experiences'][0], "Une experience ne doit pas porter {$cle}.");
        }
        $this->assertSame('Vendeuse', $card['experiences'][0]['title']);

        $this->assertCount(1, $card['educations']);
        foreach (['candidate_profile_id', 'id'] as $cle) {
            $this->assertArrayNotHasKey($cle, $card['educations'][0], "Une formation ne doit pas porter {$cle}.");
        }
        $this->assertSame('Bac pro Commerce', $card['educations'][0]['degree']);
    }

    public function test_photo_only_when_opted_in(): void
    {
        $refuse = CandidateProfile::factory()->adult()->create([
            'photo_url' => '/storage/photos/a.jpg',
            'show_photo_to_employers' => false,
        ]);
        $accepte = CandidateProfile::factory()->adult()->create([
            'photo_url' => '/storage/photos/b.jpg',
            'show_photo_to_employers' => true,
        ]);

        $this->assertNull($this->presenter->present($refuse)['photo_url']);
        $this->assertSame('/storage/photos/b.jpg', $this->presenter->present($accepte)['photo_url']);
    }

    // La mobilite ne se lit que face a une offre : hors contexte, « couvre ton
    // offre » n'aurait aucun sens, et dire « oui » ou « non » dans le vide
    // laisserait deviner un rayon, donc approximer une zone de residence.
    public function test_mobility_only_with_offer_context(): void
    {
        $profile = CandidateProfile::factory()->adult()->withPreferences()->create();
        $offer = JobOffer::factory()->published()->located()->create();

        $this->assertArrayNotHasKey('mobility', $this->presenter->present($profile));

        $avecOffre = $this->presenter->present($profile, $offer, true);
        $this->assertSame(['covers_offer' => true], $avecOffre['mobility']);

        $horsZone = $this->presenter->present($profile, $offer, false);
        $this->assertSame(['covers_offer' => false], $horsZone['mobility']);
    }

    public function test_skills_in_common_come_first(): void
    {
        $vente = Skill::create(['name' => 'Vente']);
        $caisse = Skill::create(['name' => 'Caisse']);

        $profile = CandidateProfile::factory()->adult()->create();
        // Attachee en premier a dessein : sans tri, « Caisse » sortirait en
        // tete et le recruteur lirait d'abord ce qui ne l'interesse pas.
        $profile->skills()->attach([$caisse->id, $vente->id]);

        $offer = JobOffer::factory()->published()->create();
        $offer->skills()->attach($vente->id);

        $card = $this->presenter->present($profile->fresh(), $offer);
        $noms = array_column($card['skills'], 'name');

        $this->assertSame(['Vente', 'Caisse'], $noms);
        $this->assertTrue($card['skills'][0]['in_common']);
        $this->assertFalse($card['skills'][1]['in_common']);
    }

    // Sans offre en contexte, aucune competence n'est « en commun » : rien a
    // comparer, et repondre true par defaut serait un mensonge.
    public function test_skills_have_no_common_flag_without_an_offer(): void
    {
        $profile = CandidateProfile::factory()->adult()->create();
        $profile->skills()->attach(Skill::create(['name' => 'Vente'])->id);

        $card = $this->presenter->present($profile->fresh());

        $this->assertFalse($card['skills'][0]['in_common']);
    }

    /**
     * Les bornes exactes de la tranche d'age. Elles remplacent l'age exact
     * montre jusqu'ici au recruteur : une tranche suffit a estimer le cout
     * d'un alternant, et elle ne permet pas de retrouver quelqu'un.
     */
    public function test_age_band_boundaries(): void
    {
        $attendu = [17 => '<18', 18 => '18-20', 20 => '18-20', 21 => '21-25', 25 => '21-25', 26 => '26+'];

        foreach ($attendu as $age => $tranche) {
            $profile = CandidateProfile::factory()->aged($age)->create();

            $this->assertSame(
                $tranche,
                $this->presenter->present($profile)['age_band'],
                "A {$age} ans la tranche doit etre {$tranche}.",
            );
        }

        // Anciens comptes : la colonne est nullable en base. Le profil reste
        // presentable, la tranche est simplement inconnue.
        $sansDate = CandidateProfile::factory()->create(['birth_date' => null]);
        $this->assertNull($this->presenter->present($sansDate)['age_band']);
    }
}
