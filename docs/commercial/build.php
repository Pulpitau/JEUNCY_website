<?php

// Assemble un document (logos, icones, CSS injectes) et le rend en PDF via
// Chrome headless. Usage : php build.php <nom> [<nom>...]
//   <nom>.src.html  ->  dist/<nom>.html  ->  dist/<Nom>.pdf

$dir = __DIR__;
$chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe';
$css = file_get_contents("$dir/base.css");
$icons = file_get_contents("$dir/icons.html");
$logoLight = file_get_contents("$dir/logo-light.txt");
$logoDark = file_get_contents("$dir/logo-dark.txt");

@mkdir("$dir/dist");

foreach (array_slice($argv, 1) as $nom) {
    $src = "$dir/$nom.src.html";
    if (! is_file($src)) {
        fwrite(STDERR, "source absente : $src\n");
        exit(1);
    }

    $html = file_get_contents($src);
    $html = str_replace(
        ['{{CSS}}', '{{ICONS}}', '{{LOGO_LIGHT}}', '{{LOGO_DARK}}'],
        ["<style>\n$css\n</style>", $icons, $logoLight, $logoDark],
        $html,
    );

    $out = "$dir/dist/$nom.html";
    file_put_contents($out, $html);

    // Nom de fichier PDF lisible pour l'envoi : Plaquette-Entreprises.pdf
    $pdfNom = implode('-', array_map('ucfirst', explode('-', $nom))).'.pdf';
    $pdf = "$dir/dist/$pdfNom";
    @unlink($pdf);

    $cmd = sprintf(
        '"%s" --headless=new --disable-gpu --no-sandbox --virtual-time-budget=20000 --no-pdf-header-footer --print-to-pdf="%s" "file:///%s" 2>nul',
        $chrome, $pdf, str_replace('\\', '/', $out),
    );
    exec($cmd);

    if (! is_file($pdf)) {
        fwrite(STDERR, "PDF non produit pour $nom\n");
        exit(1);
    }

    $data = file_get_contents($pdf);
    $pages = preg_match_all('/\/Type\s*\/Page[^s]/', $data);
    printf("%-34s %2d page(s)  %4d Ko  -> %s\n", $nom, $pages, round(strlen($data) / 1024), $pdfNom);
}
