<?php

namespace Database\Seeders;

use App\Enums\ApplicationStatus;
use App\Enums\ContractType;
use App\Enums\JobOfferStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Enums\VideoRoomStatus;
use App\Models\JobOffer;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    // Mot de passe de demo commun a tous les comptes seedes (dev uniquement).
    private const DEMO_PASSWORD = 'Password123!';

    public function run(): void
    {
        [$reactSkill, $nodeSkill, $salesSkill, $serviceSkill, $prospectionSkill, $typescriptSkill] = collect([
            'React', 'Node.js', 'Vente', 'Relation client', 'Prospection', 'TypeScript',
        ])->map(fn (string $name) => Skill::firstOrCreate(['name' => $name]))->all();

        $lea = User::create([
            'email' => 'lea.girard@example.com',
            'password_hash' => self::DEMO_PASSWORD,
            'role' => UserRole::CANDIDATE,
        ]);
        $leaProfile = $lea->candidateProfile()->create([
            'first_name' => 'Léa',
            'last_name' => 'Girard',
            'headline' => 'Chargée de développement commercial en alternance',
            'phone' => '0612345678',
            'birth_date' => '2003-04-12',
            'address' => '12 rue de la Fosse',
            'city' => 'Nantes',
            'postal_code' => '44000',
            'bio' => "Étudiante en BTS NDRC, à la recherche d'une alternance en développement commercial. Dynamique et à l'écoute, j'aime comprendre les besoins clients et proposer des solutions adaptées.",
            'hobbies' => 'Photographie, football, lecture',
            'driving_license' => 'B',
        ]);
        $leaProfile->languages()->create(['name' => 'Anglais', 'level' => 'B2']);
        $leaProfile->educations()->create([
            'degree' => 'BTS Négociation et Digitalisation de la Relation Client',
            'school' => 'Lycée Livet',
            'start_date' => '2024-09-01',
        ]);
        $leaProfile->educations()->create([
            'degree' => 'Baccalauréat général, spécialité SES',
            'school' => 'Lycée Gabriel Guist\'hau',
            'start_date' => '2021-09-01',
            'end_date' => '2024-06-30',
        ]);
        $leaProfile->experiences()->create([
            'title' => 'Vendeuse saisonnière',
            'company' => 'Decathlon Nantes',
            'location' => 'Nantes',
            'start_date' => '2023-06-01',
            'end_date' => '2023-08-31',
            'description' => "Accueil et conseil personnalisé des clients en rayon\nGestion de l'encaissement et des retours produits\nMise en rayon et suivi des stocks",
        ]);
        $leaProfile->experiences()->create([
            'title' => "Hôtesse d'accueil",
            'company' => 'Salon Nantes Digital Week',
            'location' => 'Nantes',
            'start_date' => '2022-10-10',
            'end_date' => '2022-10-12',
            'description' => "Accueil et orientation des visiteurs\nDistribution de documentation commerciale\nRemontée des retours visiteurs à l'équipe organisatrice",
        ]);
        $leaProfile->skills()->attach([$salesSkill->id, $serviceSkill->id, $prospectionSkill->id]);

        $malik = User::create([
            'email' => 'malik.benali@example.com',
            'password_hash' => self::DEMO_PASSWORD,
            'role' => UserRole::CANDIDATE,
        ]);
        $malikProfile = $malik->candidateProfile()->create([
            'first_name' => 'Malik',
            'last_name' => 'Benali',
            'headline' => 'Développeur web full-stack en alternance',
            'phone' => '0698765432',
            'birth_date' => '2002-11-03',
            'address' => '5 rue Saint-Melaine',
            'city' => 'Rennes',
            'postal_code' => '35000',
            'bio' => "Développeur en formation, en alternance dans le développement web. Curieux et rigoureux, j'aime autant travailler le frontend que le backend et je continue à apprendre en dehors des cours.",
            'hobbies' => 'Jeux vidéo, musculation, veille tech',
            'driving_license' => 'B',
        ]);
        $malikProfile->languages()->create(['name' => 'Anglais', 'level' => 'Courant']);
        $malikProfile->educations()->create([
            'degree' => 'Bachelor Développeur Web Full-Stack',
            'school' => 'CFA Sup Alternance',
            'start_date' => '2024-09-01',
        ]);
        $malikProfile->educations()->create([
            'degree' => 'BUT Informatique, 1re année',
            'school' => 'IUT de Rennes',
            'start_date' => '2022-09-01',
            'end_date' => '2023-06-30',
        ]);
        $malikProfile->experiences()->create([
            'title' => 'Développeur web stagiaire',
            'company' => 'Studio Kaolin',
            'location' => 'Rennes',
            'start_date' => '2024-01-08',
            'end_date' => '2024-02-16',
            'description' => "Développement de composants React pour un site vitrine client\nIntégration d'une API REST Node.js/Express\nCorrection de bugs et écriture de tests unitaires",
        ]);
        $malikProfile->skills()->attach([$reactSkill->id, $nodeSkill->id, $typescriptSkill->id]);

        $nexatechUser = User::create([
            'email' => 'rh@nexatech.example.com',
            'password_hash' => self::DEMO_PASSWORD,
            'role' => UserRole::COMPANY,
        ]);
        $nexatech = $nexatechUser->company()->create([
            'name' => 'NexaTech',
            'siret' => '12345678900011',
            'description' => 'Agence de développement web basée à Rennes, spécialisée dans les applications métier.',
            'website' => 'https://nexatech.example.com',
            'city' => 'Rennes',
            'postal_code' => '35000',
        ]);

        $cafeDesLicesUser = User::create([
            'email' => 'contact@cafedeslices.example.com',
            'password_hash' => self::DEMO_PASSWORD,
            'role' => UserRole::COMPANY,
        ]);
        $cafeDesLices = $cafeDesLicesUser->company()->create([
            'name' => 'Café des Lices',
            'siret' => '98765432100022',
            'description' => 'Café-restaurant au coeur de Rennes, terrasse et cuisine maison.',
            'city' => 'Rennes',
            'postal_code' => '35000',
        ]);

        $cfaUser = User::create([
            'email' => 'contact@cfa-sup-alternance.example.com',
            'password_hash' => self::DEMO_PASSWORD,
            'role' => UserRole::CFA,
        ]);
        $cfaSupAlternance = $cfaUser->cfaOrganization()->create([
            'name' => 'CFA Sup Alternance',
            'description' => 'Centre de formation dédié aux métiers du numérique et du commerce.',
            'website' => 'https://cfa-sup-alternance.example.com',
            'city' => 'Rennes',
            'postal_code' => '35000',
        ]);

        $offreDev = JobOffer::create([
            'company_id' => $nexatech->id,
            'title' => 'Développeur web full-stack en alternance',
            'description' => 'Rejoins notre équipe pour développer des applications React/Laravel pour nos clients. Tutorat assuré par un développeur senior.',
            'contract_type' => ContractType::ALTERNANCE,
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::SUCCEEDED,
            'city' => 'Rennes',
            'published_at' => now(),
        ]);

        $offreServeur = JobOffer::create([
            'company_id' => $cafeDesLices->id,
            'title' => 'Serveur / serveuse saisonnier(ère)',
            'description' => 'Service en salle et en terrasse pour la saison estivale. Équipe jeune et dynamique.',
            'contract_type' => ContractType::SAISONNIER,
            'status' => JobOfferStatus::PUBLISHED,
            'payment_status' => PaymentStatus::SUCCEEDED,
            'city' => 'Rennes',
            'published_at' => now(),
        ]);

        JobOffer::create([
            'cfa_organization_id' => $cfaSupAlternance->id,
            'title' => 'Bénévole journée portes ouvertes',
            'description' => 'Accueil des visiteurs et présentation des filières lors de notre journée portes ouvertes.',
            'contract_type' => ContractType::BENEVOLAT,
            'status' => JobOfferStatus::DRAFT,
            'payment_status' => PaymentStatus::PENDING,
            'city' => 'Rennes',
        ]);

        $malikProfile->applications()->create([
            'job_offer_id' => $offreDev->id,
            'status' => ApplicationStatus::INTERVIEW,
            'cover_letter' => 'Motivé pour rejoindre NexaTech et progresser en développement full-stack.',
        ]);

        $leaProfile->applications()->create([
            'job_offer_id' => $offreServeur->id,
            'status' => ApplicationStatus::SENT,
        ]);

        $nexatechUser->payments()->create([
            'job_offer_id' => $offreDev->id,
            'amount_cents' => 900,
            'status' => PaymentStatus::SUCCEEDED,
            'stripe_payment_intent_id' => 'pi_demo_seed_001',
        ]);

        $nexatechUser->notifications()->create([
            'type' => NotificationType::NEW_APPLICATION,
            'message' => 'Malik Benali a postulé à votre offre "Développeur web full-stack en alternance".',
            'link' => "/applications/{$offreDev->id}",
        ]);

        $nexatechUser->hostedVideoRooms()->create([
            'participant_id' => $malik->id,
            'jitsi_room_name' => (string) Str::uuid(),
            'status' => VideoRoomStatus::SCHEDULED,
            'scheduled_at' => now()->addDay(),
        ]);

        User::create([
            'email' => 'admin@jeuncy.com',
            'password_hash' => self::DEMO_PASSWORD,
            'role' => UserRole::ADMIN,
        ]);

        $this->command->info('Seed terminé.');
        $this->command->info('Comptes de démo (mot de passe commun : '.self::DEMO_PASSWORD.') :');
        $this->command->info('- lea.girard@example.com (candidate)');
        $this->command->info('- malik.benali@example.com (candidate)');
        $this->command->info('- rh@nexatech.example.com (company)');
        $this->command->info('- contact@cafedeslices.example.com (company)');
        $this->command->info('- contact@cfa-sup-alternance.example.com (cfa)');
        $this->command->info('- admin@jeuncy.com (admin)');
    }
}
