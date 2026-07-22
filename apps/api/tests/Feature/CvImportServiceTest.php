<?php

namespace Tests\Feature;

use App\Services\CvImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CvImportServiceTest extends TestCase
{
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
}
