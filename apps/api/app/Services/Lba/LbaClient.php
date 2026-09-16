<?php

namespace App\Services\Lba;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Acces a l'API La bonne alternance. Une seule chose aujourd'hui : obtenir
 * l'export complet des offres et le poser sur disque.
 *
 * L'export est un fichier JSON de plusieurs centaines de Mo derriere une
 * URL signee valable deux minutes : on la demande, puis on telecharge en
 * flux directement dans un fichier temporaire — jamais en memoire.
 */
class LbaClient
{
    public function configured(): bool
    {
        return (string) config('services.lba.api_key') !== '';
    }

    /**
     * Telecharge l'export et renvoie le chemin du fichier local, a supprimer
     * par l'appelant.
     */
    public function downloadExport(): string
    {
        $url = $this->exportUrl();

        $path = tempnam(sys_get_temp_dir(), 'lba-export-');
        if ($path === false) {
            throw new RuntimeException('Impossible de creer un fichier temporaire.');
        }

        // sink() ecrit la reponse au fil de l'eau sur le disque. Une heure de
        // delai : un gros fichier sur une liaison lente doit pouvoir passer.
        $download = Http::timeout(3600)->sink($path)->get($url);
        if (! $download->successful()) {
            @unlink($path);
            throw new RuntimeException("Telechargement de l'export LBA refuse : {$download->status()}");
        }

        return $path;
    }

    // Les premiers octets de l'export, pour en voir la structure sans le
    // telecharger en entier (requete Range). Outil de diagnostic.
    public function exportPreview(int $bytes = 4000): string
    {
        // Sur disque, pas en memoire : si le serveur ignore l'en-tete Range,
        // c'est tout le fichier qui arrive.
        $path = tempnam(sys_get_temp_dir(), 'lba-apercu-');
        try {
            Http::timeout(120)
                ->withHeaders(['Range' => 'bytes=0-'.($bytes - 1)])
                ->sink($path)
                ->get($this->exportUrl());

            return (string) file_get_contents($path, false, null, 0, $bytes);
        } finally {
            @unlink($path);
        }
    }

    // URL signee de l'export (valable deux minutes).
    public function exportUrl(): string
    {
        if (! $this->configured()) {
            throw new RuntimeException('LBA_API_KEY absente : import impossible.');
        }

        $base = rtrim((string) config('services.lba.base_url'), '/');
        $response = Http::withToken((string) config('services.lba.api_key'))
            ->acceptJson()
            ->timeout(30)
            ->get($base.'/job/v1/export');

        if (! $response->successful()) {
            throw new RuntimeException("LBA /job/v1/export a repondu {$response->status()} : ".mb_substr($response->body(), 0, 300));
        }

        $url = (string) $response->json('url');
        if ($url === '') {
            throw new RuntimeException('LBA /job/v1/export : aucune URL dans la reponse.');
        }

        return $url;
    }
}
