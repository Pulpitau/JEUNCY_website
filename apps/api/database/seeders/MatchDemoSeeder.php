<?php

namespace Database\Seeders;

use App\Enums\ContractType;
use App\Enums\InterestDecision;
use App\Enums\JobOfferStatus;
use App\Enums\OfferSector;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\VerificationStatus;
use App\Models\CandidateProfile;
use App\Models\JobOffer;
use App\Models\OfferInterest;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Jeu de demo du modele match, autour de Perpignan (base de DEV uniquement).
 *
 * Le relancer remet tout a zero : les comptes @demo-match.example.com sont
 * supprimes (cascades : profils, offres, interets, notifications) puis
 * recrees. C'est ce qui permet de tester les piles sans jamais toucher un
 * vrai candidat en production.
 *
 *   php artisan db:seed --class=MatchDemoSeeder
 *
 * Les URL des photos suivent APP_URL : pour un iPhone, lancer le seeder avec
 * APP_URL=http://<ip-du-pc>:3000, comme le serveur.
 */
class MatchDemoSeeder extends Seeder
{
    public const DOMAINE = '@demo-match.example.com';

    public const MOT_DE_PASSE = 'Password123!';

    private const PERPIGNAN = [42.6987, 2.8956];

    /** prenom, nom, age, decalage lat, decalage lng, code postal, ville, accroche, competences, loisirs */
    private const CANDIDATS = [
        ['Inès', 'Martin', 19, 0.010, 0.012, '66000', 'Perpignan', 'BTS MCO, je cherche une alternance en magasin.', ['Vente', 'Relation client'], 'Danse, cuisine'],
        ['Yanis', 'Bernard', 17, -0.020, 0.030, '66100', 'Perpignan', 'CAP boulanger, levé tôt et motivé.', ['Boulangerie', 'Hygiène alimentaire'], 'Rugby, vélo'],
        ['Chloé', 'Garcia', 21, 0.040, -0.020, '66530', 'Claira', 'Bachelor marketing, à l\'aise sur les réseaux sociaux.', ['Réseaux sociaux', 'Canva'], 'Photo, voyages'],
        ['Lucas', 'Roux', 18, -0.050, -0.040, '66200', 'Elne', 'Bac pro commerce, déjà deux étés en vente.', ['Vente', 'Encaissement'], 'Football'],
        ['Sarah', 'Benali', 20, 0.060, 0.050, '66600', 'Rivesaltes', 'BTS GPME, rigoureuse et organisée.', ['Excel', 'Accueil'], 'Lecture, randonnée'],
        ['Hugo', 'Lopez', 16, 0.005, -0.060, '66000', 'Perpignan', 'Je veux apprendre la pâtisserie en apprentissage.', ['Pâtisserie'], 'Jeux vidéo, basket'],
        ['Manon', 'Fabre', 22, -0.030, 0.070, '66140', 'Canet-en-Roussillon', 'Licence pro commerce, permis B et voiture.', ['Prospection', 'Négociation'], 'Surf, musique'],
        ['Nathan', 'Vidal', 19, 0.080, 0.000, '66250', 'Saint-Laurent-de-la-Salanque', 'BTS NDRC, j\'aime le contact client.', ['Relation client', 'Prospection'], 'Pêche'],
        ['Lina', 'Moreau', 18, -0.070, 0.010, '66670', 'Bages', 'Bac pro accueil, souriante et ponctuelle.', ['Accueil', 'Anglais'], 'Théâtre'],
        ['Théo', 'Pujol', 23, 0.020, -0.090, '66170', 'Millas', 'Titre pro vendeur conseil, disponible tout de suite.', ['Vente', 'Merchandising'], 'Moto, cuisine'],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Jeu de demo : jamais en production.');
        }

        Model::unguarded(function () {
            User::query()->where('email', 'like', '%'.self::DOMAINE)->delete();

            $cfa = $this->organisation('cfa.test', UserRole::CFA, 'CFA Démo Perpignan', '66000', 'Perpignan');
            $offreCfa = $this->offre(['cfa_organization_id' => $cfa->id], 'Vendeur·se en alternance', OfferSector::COMMERCE);

            $boulangerie = $this->organisation('entreprise.test', UserRole::COMPANY, 'Boulangerie Démo', '66100', 'Perpignan');
            $this->offre(['company_id' => $boulangerie->id], 'Apprenti·e boulanger·e', OfferSector::RESTAURATION_HOTELLERIE);

            foreach (self::CANDIDATS as $i => $c) {
                $profil = $this->candidat("candidat{$i}", $c, photo: true);

                // Les deux premiers ont deja dit oui a l'offre du CFA : un
                // swipe a droite cote CFA doit afficher « C'est un match ».
                if ($i < 2) {
                    OfferInterest::create([
                        'candidate_profile_id' => $profil->id,
                        'job_offer_id' => $offreCfa->id,
                        'candidate_decision' => InterestDecision::LIKE,
                        'candidate_decided_at' => now(),
                    ]);
                }
            }

            // Comptes pour tester le cote candidat : avec et sans photo.
            $candidatTest = $this->candidat('candidat.test', ['Pierre', 'Test', 20, 0.0, 0.0, '66000', 'Perpignan', 'Compte de test candidat.', ['Vente'], 'Tests'], photo: true);
            $this->candidat('sans.photo', ['Alex', 'Sansphoto', 20, 0.0, 0.0, '66000', 'Perpignan', 'Compte de test sans photo.', ['Vente'], ''], photo: false);

            // Match deja fait entre candidat.test et l'offre du CFA : le
            // dossier reste a envoyer, pour tester cette etape sans rejouer
            // tout le parcours (swipe cote CFA, swipe cote candidat).
            OfferInterest::create([
                'candidate_profile_id' => $candidatTest->id,
                'job_offer_id' => $offreCfa->id,
                'candidate_decision' => InterestDecision::LIKE,
                'candidate_decided_at' => now(),
                'employer_decision' => InterestDecision::LIKE,
                'employer_decided_at' => now(),
                'matched_at' => now(),
            ]);
        });

        $this->command?->info('Jeu de demo pret. Comptes '.self::DOMAINE.', mot de passe '.self::MOT_DE_PASSE);
        $this->command?->info('  cfa.test / entreprise.test / candidat.test / sans.photo');
    }

    private function organisation(string $login, UserRole $role, string $nom, string $cp, string $ville): Model
    {
        $user = User::create(['email' => $login.self::DOMAINE, 'password_hash' => self::MOT_DE_PASSE, 'role' => $role]);

        $donnees = ['name' => $nom, 'city' => $ville, 'postal_code' => $cp, 'description' => 'Organisation fictive du jeu de démo.'];
        $organisation = $role === UserRole::CFA
            ? $user->cfaOrganization()->create($donnees)
            : $user->company()->create($donnees + ['siret' => '00000000000000']);

        $organisation->forceFill([
            'verification_status' => VerificationStatus::VERIFIED,
            'verified_at' => now(),
            'latitude' => self::PERPIGNAN[0],
            'longitude' => self::PERPIGNAN[1],
        ])->saveQuietly();

        return $organisation;
    }

    private function offre(array $proprietaire, string $titre, OfferSector $secteur): JobOffer
    {
        $offre = JobOffer::create($proprietaire + [
            'title' => $titre,
            'description' => 'Offre fictive du jeu de démo.',
            'contract_type' => ContractType::ALTERNANCE,
            'city' => 'Perpignan',
            'postal_code' => '66000',
            'sector' => $secteur,
            'recruitment_radius_km' => 30,
            'minimum_age' => 16,
        ]);

        $offre->forceFill([
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::FREE,
            'published_at' => now(),
            'applications_unlocked_at' => now(),
            'latitude' => self::PERPIGNAN[0],
            'longitude' => self::PERPIGNAN[1],
        ])->saveQuietly();

        return $offre;
    }

    private function candidat(string $login, array $c, bool $photo): CandidateProfile
    {
        [$prenom, $nom, $age, $dLat, $dLng, $cp, $ville, $accroche, $competences, $loisirs] = $c;

        $user = User::create(['email' => $login.self::DOMAINE, 'password_hash' => self::MOT_DE_PASSE, 'role' => UserRole::CANDIDATE]);

        $profil = $user->candidateProfile()->create([
            'first_name' => $prenom,
            'last_name' => $nom,
            'birth_date' => now()->subYears($age)->subMonths(2)->toDateString(),
            'city' => $ville,
            'postal_code' => $cp,
            'headline' => $accroche,
            'pitch' => $accroche,
            'bio' => $accroche,
            'hobbies' => $loisirs ?: null,
            'is_visible_in_cvtheque' => true,
            'search_radius_km' => 30,
            'mobility_radius_km' => 30,
            'show_photo_to_employers' => $photo,
            'photo_url' => $photo ? $this->photo($login, $prenom, $nom) : null,
        ]);

        $profil->forceFill([
            'latitude' => round(self::PERPIGNAN[0] + $dLat, 4),
            'longitude' => round(self::PERPIGNAN[1] + $dLng, 4),
        ])->saveQuietly();

        $profil->skills()->sync(collect($competences)->map(fn (string $n) => Skill::firstOrCreate(['name' => $n])->id));
        $profil->experiences()->create([
            'title' => 'Job d\'été',
            'company' => 'Commerce fictif',
            'location' => $ville,
            'start_date' => now()->subYear()->startOfMonth()->toDateString(),
            'end_date' => now()->subYear()->addMonths(2)->toDateString(),
            'description' => 'Expérience fictive du jeu de démo.',
        ]);
        $profil->educations()->create([
            'degree' => 'Formation fictive',
            'school' => 'Lycée de démo',
            'start_date' => now()->subYears(2)->startOfYear()->toDateString(),
        ]);

        return $profil;
    }

    // Avatar a initiales, jamais un visage : ce sont des comptes fictifs.
    private function photo(string $login, string $prenom, string $nom): string
    {
        $image = imagecreatetruecolor(400, 400);
        [$r, $g, $b] = sscanf(substr(md5($login), 0, 6), '%02x%02x%02x');
        imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
        $initiales = mb_substr($prenom, 0, 1).mb_substr($nom, 0, 1);
        // Police GD integree (5 = la plus grande), agrandie par mise a l'echelle.
        $petit = imagecreatetruecolor(20, 16);
        imagefill($petit, 0, 0, imagecolorallocate($petit, $r, $g, $b));
        imagestring($petit, 5, 1, 0, $initiales, imagecolorallocate($petit, 255, 255, 255));
        imagecopyresized($image, $petit, 50, 80, 0, 0, 300, 240, 20, 16);

        ob_start();
        imagepng($image);
        $chemin = 'photos/demo-match-'.$login.'.png';
        Storage::disk('public')->put($chemin, ob_get_clean());

        return Storage::disk('public')->url($chemin);
    }
}
