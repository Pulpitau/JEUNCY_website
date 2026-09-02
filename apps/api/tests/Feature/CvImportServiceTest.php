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
        $file = $this->makePdfUpload('<p>Langues : Anglais B2, Espagnol courant</p>');

        $result = $this->service->parse($file);

        $this->assertContains(['name' => 'Anglais', 'level' => 'B2'], $result['languages']);
        $this->assertContains(['name' => 'Espagnol', 'level' => null], $result['languages']);
    }
}
