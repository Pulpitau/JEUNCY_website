<?php

namespace Tests\Feature;

use App\Models\Skill;
use App\Models\Software;
use App\Services\CvImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CvImportServiceTest extends TestCase
{
    // Depuis que l'extraction reconnait les competences et logiciels du
    // referentiel, le service interroge la base : elle doit etre propre a
    // chaque test, sinon un referentiel residuel fausse les assertions.
    use RefreshDatabase;

    private CvImportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(CvImportService::class);
    }

    // Genere un vrai PDF (via dompdf, deja une dependance du projet) plutot que
    // de committer un fixture binaire : smalot/pdfparser a besoin d'une
    // structure PDF valide, un fichier "fake" de contenu aleatoire ne suffit pas.
    private function makePdfUpload(string $html): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cv_import_test_').'.pdf';
        file_put_contents($path, Pdf::loadHTML($html)->output());

        return new UploadedFile($path, 'cv.pdf', 'application/pdf', null, true);
    }

    public function test_parse_extracts_email_and_phone(): void
    {
        $file = $this->makePdfUpload('<p>Contact : jean.dupont@example.com — 06 12 34 56 78</p>');

        $result = $this->service->parse($file);

        $this->assertSame('jean.dupont@example.com', $result['email']);
        $this->assertSame('06 12 34 56 78', $result['phone']);
        $this->assertStringContainsString('Contact', $result['raw_text']);
    }

    public function test_parse_returns_null_fields_when_nothing_found(): void
    {
        $file = $this->makePdfUpload('<p>Un CV sans coordonnees.</p>');

        $result = $this->service->parse($file);

        $this->assertNull($result['email']);
        $this->assertNull($result['phone']);
    }

    public function test_parse_handles_international_phone_format(): void
    {
        $file = $this->makePdfUpload('<p>Tel : +33 6 12 34 56 78</p>');

        $result = $this->service->parse($file);

        $this->assertNotNull($result['phone']);
    }

    // --- Pre-remplissage du profil ---

    public function test_parse_extracts_postal_code_and_linkedin_and_licence(): void
    {
        $file = $this->makePdfUpload(
            '<p>66000 Perpignan — linkedin.com/in/lea-girard — Titulaire du permis B</p>',
        );

        $result = $this->service->parse($file);

        $this->assertSame('66000', $result['postal_code']);
        $this->assertSame('https://linkedin.com/in/lea-girard', $result['linkedin_url']);
        $this->assertSame('Permis B', $result['driving_license']);
    }

    // On ne devine pas une competence : on constate qu'un nom deja connu de
    // Jeuncy apparait dans le texte.
    public function test_parse_only_suggests_skills_from_the_referential(): void
    {
        Skill::create(['name' => 'React']);
        Skill::create(['name' => 'Comptabilite']);
        Software::create(['name' => 'Photoshop']);

        $file = $this->makePdfUpload('<p>Maitrise de React et de Photoshop. Notions de soudure.</p>');

        $result = $this->service->parse($file);

        $this->assertSame(['React'], $result['skills']);
        $this->assertSame(['Photoshop'], $result['software']);
    }

    // Un nom trop court produirait des faux positifs partout ("Go" dans
    // "Google", "R" dans n'importe quel mot).
    public function test_parse_ignores_referential_names_shorter_than_three_characters(): void
    {
        Skill::create(['name' => 'R']);
        Skill::create(['name' => 'Go']);

        $result = $this->service->parse($this->makePdfUpload('<p>Rigoureux et organise.</p>'));

        $this->assertSame([], $result['skills']);
    }

    public function test_parse_extracts_languages_with_their_level(): void
    {
        $file = $this->makePdfUpload('<p>Langues</p><p>Anglais B2</p><p>Espagnol courant</p>');

        $result = $this->service->parse($file);

        $this->assertContains(['name' => 'Anglais', 'level' => 'B2'], $result['languages']);
        // "courant" est traduit en C1 : les CV de jeunes candidats donnent
        // rarement le niveau CECRL, presque toujours un mot.
        $this->assertContains(['name' => 'Espagnol', 'level' => 'C1'], $result['languages']);
    }

    // --- Identite (permet de creer le profil depuis le CV) ---

    public function test_parse_extracts_first_and_last_name(): void
    {
        $result = $this->service->parse(
            $this->makePdfUpload('<p>ALEXANDRE LEYVA</p><p>Conseiller Commercial</p>'),
        );

        $this->assertSame('Alexandre', $result['first_name']);
        $this->assertSame('Leyva', $result['last_name']);
    }

    // Cas reel : beaucoup de gabarits coupent le nom sur deux lignes.
    public function test_parse_extracts_a_name_split_over_two_lines(): void
    {
        $result = $this->service->parse(
            $this->makePdfUpload('<p>ALEXANDRE</p><p>LEYVA</p><p>Conseiller Commercial</p>'),
        );

        $this->assertSame('Alexandre', $result['first_name']);
        $this->assertSame('Leyva', $result['last_name']);
    }

    public function test_parse_keeps_a_compound_last_name(): void
    {
        $result = $this->service->parse(
            $this->makePdfUpload('<p>INAYA BEN ABDESLEM</p><p>Bac Pro Vente</p>'),
        );

        $this->assertSame('Inaya', $result['first_name']);
        $this->assertSame('Ben Abdeslem', $result['last_name']);
    }

    // CV a deux colonnes : l'extraction rend d'abord la colonne etroite de
    // gauche (contact, competences, langues), et le nom n'arrive qu'ensuite.
    // La lecture s'arretait avant de l'atteindre.
    public function test_parse_finds_the_name_after_a_left_hand_column(): void
    {
        $result = $this->service->parse($this->makePdfUpload(
            '<p>CONTACT</p><p>06 12 34 56 78</p><p>lea@example.com</p>'
            .'<p>COMPÉTENCES</p><p>Vente</p><p>Accueil client</p>'
            .'<p>LÉA MOREAU</p><p>Alternance vente</p>',
        ));

        $this->assertSame('Léa', $result['first_name']);
        $this->assertSame('Moreau', $result['last_name']);
    }

    // Le nom est presque toujours en capitales sur un CV, et c'est ce qui le
    // distingue d'une competence restee dans la fenetre de recherche : sans
    // cette preference, ce CV donnait le nom "Accueil Client".
    public function test_parse_prefers_an_uppercase_line_over_a_skill(): void
    {
        $result = $this->service->parse($this->makePdfUpload(
            '<p>Accueil client</p><p>Gestion de stock</p><p>NOAH PETIT</p>',
        ));

        $this->assertSame('Noah', $result['first_name']);
        $this->assertSame('Petit', $result['last_name']);
    }

    // Sans nom lisible, on ne renvoie rien : le frontend demande alors au
    // candidat de le saisir plutot que de creer un profil bancal.
    public function test_parse_returns_no_name_when_the_header_is_not_one(): void
    {
        $result = $this->service->parse(
            $this->makePdfUpload('<p>contact@exemple.fr</p><p>06 00 00 00 00</p>'),
        );

        $this->assertNull($result['first_name']);
        $this->assertNull($result['last_name']);
    }

    // --- Trois profils reels crees sous un mauvais nom (2026-09-03) ---
    //
    // L'import cree le profil SANS que le candidat relise l'identite lue dans
    // son PDF (voir Profile.tsx). Un nom mal extrait ne se voit donc qu'apres
    // coup, dans la CVtheque, sous les yeux des recruteurs : ces trois cas
    // valent des tests permanents.

    // "Permis B" a la forme exacte d'un nom — deux mots, que des lettres, en
    // tete de document — et devenait l'identite du candidat.
    public function test_parse_does_not_take_a_personal_information_label_for_a_name(): void
    {
        $result = $this->service->parse($this->makePdfUpload(
            '<p>Permis B</p><p>Organisation</p><p>Wordpress</p>',
        ));

        $this->assertNull($result['first_name']);
        $this->assertNull($result['last_name']);
    }

    // CV a deux colonnes dont la colonne de gauche est longue : le vrai nom
    // tombe au-dela de la fenetre de lecture de l'en-tete. L'adresse email le
    // confirme quand meme, ou qu'il se trouve dans le document.
    public function test_parse_confirms_the_name_with_the_email_address(): void
    {
        $result = $this->service->parse($this->makePdfUpload(
            '<p>CONTACT</p><p>rostomghazli64@gmail.com</p><p>07 43 58 58 13</p>'
            .'<p>Permis B</p><p>COMPÉTENCES</p><p>Organisation</p>'
            .'<p>LOGICIELS</p><p>Wordpress</p><p>python</p>'
            .'<p>LANGUES</p><p>Anglais - B1</p><p>Arabe - A2</p>'
            .'<p>ROSTOM GHAZLI</p><p>Alternance en développement full stack</p>',
        ));

        $this->assertSame('Rostom', $result['first_name']);
        $this->assertSame('Ghazli', $result['last_name']);
    }

    // Une liste de competences est, elle aussi, une suite de lignes d'un seul
    // mot : la regle du nom coupe sur deux lignes assemblait "Prospection" et
    // "Encaissement" en identite de candidat.
    public function test_parse_does_not_glue_two_skills_into_a_name(): void
    {
        $result = $this->service->parse($this->makePdfUpload(
            '<p>Prospection</p><p>Encaissement</p><p>Organisation</p>',
        ));

        $this->assertNull($result['first_name']);
        $this->assertNull($result['last_name']);
    }

    // Un titre de CV n'est pas un nom, meme court ("Alternance Vente").
    public function test_parse_does_not_take_a_cv_title_for_a_name(): void
    {
        $result = $this->service->parse($this->makePdfUpload(
            '<p>Alternance Vente</p><p>Recherche Contrat</p>',
        ));

        $this->assertNull($result['first_name']);
        $this->assertNull($result['last_name']);
    }
    // --- Rubriques (experiences / formations) ---

    private function makeRealisticCv(): UploadedFile
    {
        return $this->makePdfUpload(<<<'HTML'
            <p>ALEXANDRE LEYVA</p>
            <p>Conseiller Commercial</p>
            <p>+33 6 30 15 43 12 - alexandre.leyva@example.com</p>
            <p>12 Rue Teresa Rebull, 66300 Saint Laurent</p>

            <p>EXPÉRIENCES</p>
            <p>Conseiller de vente</p>
            <p>Boulanger, Perpignan</p>
            <p>sept. 2023 - juin 2024</p>
            <p>Accueil et conseil des clients en rayon</p>
            <p>Gestion de l'encaissement</p>
            <p>Hôte de caisse</p>
            <p>Carrefour, Cabestany</p>
            <p>06/2022 - 08/2022</p>
            <p>Encaissement et fidélisation</p>

            <p>FORMATION</p>
            <p>BTS Négociation et Digitalisation de la Relation Client</p>
            <p>Lycée Aristide Maillol</p>
            <p>2022 - 2024</p>
            <p>Baccalauréat professionnel Commerce</p>
            <p>Lycée Jean Lurçat</p>
            <p>2019 - 2022</p>

            <p>LANGUES</p>
            <p>Anglais B1</p>
            HTML);
    }

    public function test_parse_extracts_experiences_with_dates(): void
    {
        $result = $this->service->parse($this->makeRealisticCv());

        $this->assertCount(2, $result['experiences']);

        $first = $result['experiences'][0];
        $this->assertSame('Conseiller de vente', $first['title']);
        // Le lieu est desormais separe du nom de l entreprise : "Boulanger,
        // Perpignan" donne bien "Boulanger", la ville n ayant rien a faire
        // dans le champ entreprise du profil.
        $this->assertSame('Boulanger', $first['company']);
        $this->assertSame('2023-09-01', $first['start_date']);
        $this->assertSame('2024-06-30', $first['end_date']);
        $this->assertStringContainsString('Accueil et conseil', $first['description']);

        $this->assertSame('Hôte de caisse', $result['experiences'][1]['title']);
        $this->assertSame('2022-06-01', $result['experiences'][1]['start_date']);
    }

    // Deuxieme disposition, tres repandue (gabarits Canva, CV mis en page a
    // l'etranger) : la date OUVRE l'entree, et l'extraction PDF colle le nom de
    // l'entreprise en capitales au poste qui suit. Reproduit ici tel que
    // smalot/pdfparser le restitue sur un vrai CV — sans ce traitement,
    // AUCUNE des trois experiences n'etait reconnue.
    public function test_parse_reads_entries_where_the_date_opens_and_the_company_is_glued(): void
    {
        $file = $this->makePdfUpload(
            '<p>PARCOURS PROFESSIONNEL</p>'
            .'<p>JUN 2025- Act</p>'
            .'<p>SURPRISE ROSESetter commercial/ Madrid freelance</p>'
            .'<p>Prise de contact et qualification des prospects</p>'
            .'<p>Suivi des clients et relance commerciale JUI 2024- NOV 2024</p>'
            .'<p>LOVISAResponsable de Magasin/ Quito Equateur</p>'
            .'<p>Gestion des stocks et des inventaires</p>',
        );

        $result = $this->service->parse($file);

        $this->assertCount(2, $result['experiences']);

        $this->assertSame('Setter commercial', $result['experiences'][0]['title']);
        $this->assertSame('SURPRISE ROSE', $result['experiences'][0]['company']);
        $this->assertSame('2025-06-01', $result['experiences'][0]['start_date']);
        $this->assertNull($result['experiences'][0]['end_date']);

        $this->assertSame('Responsable de Magasin', $result['experiences'][1]['title']);
        $this->assertSame('LOVISA', $result['experiences'][1]['company']);
        $this->assertSame('2024-11-30', $result['experiences'][1]['end_date']);
    }

    // "JUI" est ambigu (juin ou juillet). Juin par defaut : se tromper d'un
    // mois est sans consequence, retomber sur janvier fausse la chronologie.
    public function test_parse_reads_an_ambiguous_month_abbreviation(): void
    {
        $file = $this->makePdfUpload(
            '<p>EXPÉRIENCES</p><p>Vendeur</p><p>Decathlon</p><p>JUI 2023 - AOU 2023</p>',
        );

        $result = $this->service->parse($file);

        $this->assertCount(1, $result['experiences']);
        $this->assertSame('2023-06-01', $result['experiences'][0]['start_date']);
    }

    public function test_parse_extracts_educations_with_dates(): void
    {
        $result = $this->service->parse($this->makeRealisticCv());

        $this->assertCount(2, $result['educations']);
        $this->assertStringContainsString('BTS Négociation', $result['educations'][0]['degree']);
        $this->assertSame('Lycée Aristide Maillol', $result['educations'][0]['school']);
        $this->assertSame('2022-01-01', $result['educations'][0]['start_date']);
        $this->assertSame('2024-12-31', $result['educations'][0]['end_date']);
    }

    // Une entree en cours ("depuis", "aujourd'hui") laisse la date de fin
    // vide : c'est ce que le profil attend pour un poste toujours occupe.
    public function test_parse_leaves_end_date_empty_for_an_ongoing_entry(): void
    {
        $file = $this->makePdfUpload(
            '<p>EXPÉRIENCES</p><p>Alternance communication</p><p>Odyssée Studio</p>'
            ."<p>oct. 2025 - aujourd'hui</p>",
        );

        $result = $this->service->parse($file);

        $this->assertCount(1, $result['experiences']);
        $this->assertSame('2025-10-01', $result['experiences'][0]['start_date']);
        $this->assertNull($result['experiences'][0]['end_date']);
    }

    // --- Garde-fous : ce qui sort d'ici doit etre acceptable par le profil ---

    // Le profil refuse une description de plus de 2000 caracteres. Sur un CV a
    // colonnes, l'extraction agrege parfois plusieurs blocs : sans plafond,
    // l'API rejetait l'experience entiere et le candidat ne voyait qu'un echec
    // sans cause.
    public function test_parse_caps_a_description_at_the_profile_limit(): void
    {
        $longue = str_repeat('Ligne de description tres longue. ', 200);
        $file = $this->makePdfUpload(
            '<p>EXPÉRIENCES</p><p>Vendeur</p><p>Decathlon</p><p>2022 - 2023</p><p>'.$longue.'</p>',
        );

        $result = $this->service->parse($file);

        $this->assertCount(1, $result['experiences']);
        $this->assertLessThanOrEqual(2000, mb_strlen($result['experiences'][0]['description']));
    }

    // Le profil exige end_date >= start_date. Un texte melange peut apparier
    // deux dates qui n'appartiennent pas a la meme entree : on prefere une date
    // de fin inconnue a une entree refusee par l'API.
    public function test_parse_drops_an_end_date_that_precedes_the_start(): void
    {
        $file = $this->makePdfUpload(
            '<p>EXPÉRIENCES</p><p>Vendeur</p><p>Decathlon</p><p>nov. 2024 - juin 2024</p>',
        );

        $result = $this->service->parse($file);

        $this->assertCount(1, $result['experiences']);
        $this->assertSame('2024-11-01', $result['experiences'][0]['start_date']);
        $this->assertNull($result['experiences'][0]['end_date']);
    }

    // Les intitules sont bornes bien en dessous de la limite du profil (255).
    public function test_parse_keeps_titles_within_the_profile_limits(): void
    {
        $file = $this->makePdfUpload(
            '<p>EXPÉRIENCES</p><p>'.str_repeat('Intitule interminable ', 30).'</p>'
            .'<p>Decathlon</p><p>2022 - 2023</p>',
        );

        $result = $this->service->parse($file);

        foreach ($result['experiences'] as $experience) {
            $this->assertLessThanOrEqual(255, mb_strlen($experience['title']));
            $this->assertLessThanOrEqual(255, mb_strlen((string) $experience['company']));
        }
    }

    // Sans date reconnaissable, on ne propose rien plutot que d'inventer une
    // entree que le candidat devrait ensuite traquer et corriger.
    public function test_parse_ignores_entries_without_a_recognisable_date(): void
    {
        $file = $this->makePdfUpload('<p>EXPÉRIENCES</p><p>Un poste sans aucune date</p>');

        $result = $this->service->parse($file);

        $this->assertSame([], $result['experiences']);
    }

    public function test_parse_returns_empty_sections_for_a_cv_without_headings(): void
    {
        $result = $this->service->parse($this->makePdfUpload('<p>Juste un paragraphe libre.</p>'));

        $this->assertSame([], $result['experiences']);
        $this->assertSame([], $result['educations']);
    }
}
