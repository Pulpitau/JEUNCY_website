<?php

use App\Services\CvImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;
use Smalot\PdfParser\Parser;

/**
 * Banc d'essai de l'import de CV.
 *
 * Rend un CV HTML en PDF (comme le ferait un candidat exportant depuis Canva,
 * Word ou LinkedIn), le fait relire par CvImportService, et affiche ce qui a
 * ete reconnu. Sert a mesurer le RAPPEL de l'extraction sur des mises en page
 * variees : c'est le seul moyen honnete de savoir si l'import fonctionne
 * ailleurs que sur les CV dont on connait deja la structure.
 *
 * Passe par la reflexion plutot que par parse() pour ne pas dependre de la
 * base de donnees (parse() interroge le referentiel de competences).
 *
 * Usage :  php tools/cv-import-bench.php chemin/vers/cv.html
 *          php tools/cv-import-bench.php --pdf chemin/vers/cv.pdf
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$isPdf = in_array('--pdf', $args, true);
$path = end($args);

if (! $path || ! is_file($path)) {
    fwrite(STDERR, "Fichier introuvable : {$path}\n");
    exit(1);
}

if ($isPdf) {
    $pdfPath = $path;
} else {
    $pdfPath = sys_get_temp_dir().'/cv-bench-'.substr(sha1($path), 0, 8).'.pdf';
    file_put_contents(
        $pdfPath,
        Pdf::loadHTML(file_get_contents($path))->output(),
    );
}

$text = (new Parser)->parseFile($pdfPath)->getText();

$service = app(CvImportService::class);
$reflection = new ReflectionClass($service);
$call = function (string $method, ...$arguments) use ($reflection, $service) {
    $m = $reflection->getMethod($method);
    $m->setAccessible(true);

    return $m->invoke($service, ...$arguments);
};

$sections = $call('splitIntoSections', $text);
$name = $call('extractName', $text);

echo 'RUBRIQUES RECONNUES : '.(implode(', ', array_keys($sections)) ?: '(aucune)').PHP_EOL;
echo 'NOM                 : '.($name['first'] ?? '?').' '.($name['last'] ?? '?').PHP_EOL;

foreach (['experience' => 'EXPERIENCES', 'education' => 'FORMATIONS'] as $type => $libelle) {
    $entries = $call('collectEntries', $sections, $text, $type);
    echo $libelle.' : '.count($entries).PHP_EOL;

    foreach ($entries as $entry) {
        printf(
            "  - %-42s | %-24s | %s -> %s\n",
            mb_substr($entry['title'] ?? $entry['degree'], 0, 42),
            mb_substr((string) ($entry['company'] ?? $entry['school'] ?? '?'), 0, 24),
            $entry['start_date'] ?? '?',
            $entry['end_date'] ?? 'en cours',
        );
    }
}

$languages = $call('extractLanguages', $text);
echo 'LANGUES : '.implode(', ', array_map(
    fn ($l) => $l['name'].' '.($l['level'] ?? '-'),
    $languages,
)).PHP_EOL;
