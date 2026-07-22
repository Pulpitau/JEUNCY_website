<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;

// Extraction "best effort" a partir d'un PDF existant : aucune IA disponible
// dans cet environnement pour une extraction fiable et structuree (meme
// limitation que Stripe/Google OAuth dans CLAUDE.md), donc uniquement des
// heuristiques regex simples (email, telephone) — fiables sur a peu pres tous
// les formats de CV. Une premiere version tentait aussi de deviner des blocs
// d'experience via des plages de dates, mais testee contre un vrai CV a mise
// en page multi-colonnes (tres courante, y compris le propre gabarit de
// Jeuncy), le texte extrait du PDF melange l'ordre de lecture des colonnes et
// produit des blocs meconnaissables — retiree plutot que d'afficher un
// resultat qui a l'air casse. Le texte brut integral reste renvoye pour que
// le candidat puisse toujours s'y referer et completer son profil a la main.
class CvImportService
{
    public function parse(UploadedFile $file): array
    {
        $parser = new Parser;
        $document = $parser->parseFile($file->getRealPath());
        $text = $document->getText();

        return [
            'email' => $this->extractEmail($text),
            'phone' => $this->extractPhone($text),
            'raw_text' => trim($text),
        ];
    }

    private function extractEmail(string $text): ?string
    {
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            return $matches[0];
        }

        return null;
    }

    private function extractPhone(string $text): ?string
    {
        if (preg_match('/(?:\+33[\s.-]?|0)[1-9](?:[\s.-]?\d{2}){4}/', $text, $matches)) {
            return trim($matches[0]);
        }

        return null;
    }
}
