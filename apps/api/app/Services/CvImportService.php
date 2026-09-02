<?php

namespace App\Services;

use App\Models\Skill;
use App\Models\Software;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;

// Extraction "best effort" a partir d'un PDF existant : aucune IA disponible
// dans cet environnement pour une extraction fiable et structuree (meme
// limitation que Stripe/Google OAuth dans CLAUDE.md).
//
// Le principe qui guide ce service : ne proposer QUE ce qu'on sait reconnaitre
// sans deviner. Deux familles de reperes tiennent cette promesse :
//
//  - des formats non ambigus (email, telephone francais, code postal, URL
//    LinkedIn, mention de permis) reconnus par expression reguliere ;
//  - des mots deja connus de Jeuncy (competences, logiciels, langues du
//    referentiel en base) simplement RECHERCHES dans le texte — on ne devine
//    pas une competence, on constate qu'un nom du referentiel apparait.
//
// Ce qui reste hors de portee, et qu'on n'essaie plus : reconstituer les blocs
// d'experience ou de formation. Une premiere version tentait de les deviner via
// des plages de dates ; testee contre un vrai CV a mise en page multi-colonnes
// (tres courante, y compris le propre gabarit de Jeuncy), le texte extrait
// melange l'ordre de lecture des colonnes et produit des blocs meconnaissables.
// Retiree plutot que d'afficher un resultat qui a l'air casse. Le texte brut
// integral reste renvoye pour que le candidat complete a la main.
class CvImportService
{
    // En dessous de 3 caracteres, un nom du referentiel produit trop de faux
    // positifs meme avec des frontieres de mot ("R", "C", "Go"...). On prefere
    // rater une competence que d'en inventer une.
    private const MIN_REFERENTIAL_LENGTH = 3;

    // Plafond de suggestions par famille : au-dela, la liste cesse d'etre
    // relisible par le candidat et l'invite a tout accepter en bloc.
    private const MAX_SUGGESTIONS = 25;

    public function parse(UploadedFile $file): array
    {
        $parser = new Parser;
        $document = $parser->parseFile($file->getRealPath());
        $text = $document->getText();

        return [
            'email' => $this->extractEmail($text),
            'phone' => $this->extractPhone($text),
            'postal_code' => $this->extractPostalCode($text),
            'linkedin_url' => $this->extractLinkedinUrl($text),
            'driving_license' => $this->extractDrivingLicense($text),
            'skills' => $this->matchReferential($text, Skill::query()->pluck('name')->all()),
            'software' => $this->matchReferential($text, Software::query()->pluck('name')->all()),
            'languages' => $this->extractLanguages($text),
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

    // Code postal francais : 5 chiffres isoles, en excluant 00000 et les
    // suites qui font partie d'un nombre plus long (montant, numero de rue).
    private function extractPostalCode(string $text): ?string
    {
        if (preg_match('/(?<!\d)((?:0[1-9]|[1-9]\d)\d{3})(?!\d)/', $text, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractLinkedinUrl(string $text): ?string
    {
        if (preg_match('#(?:https?://)?(?:www\.)?linkedin\.com/in/[A-Za-z0-9\-_%]+#i', $text, $matches)) {
            $url = $matches[0];

            return Str::startsWith($url, ['http://', 'https://']) ? $url : 'https://'.$url;
        }

        return null;
    }

    // "Permis B", "permis de conduire B", "Titulaire du permis B". On ne
    // renvoie que la categorie, c'est ce que stocke le profil.
    private function extractDrivingLicense(string $text): ?string
    {
        if (preg_match('/permis\s*(?:de\s*conduire\s*)?:?\s*([A-E]\d?)\b/i', $text, $matches)) {
            return 'Permis '.Str::upper($matches[1]);
        }

        if (preg_match('/\bpermis\s*(?:de\s*conduire)?\b/i', $text)) {
            return 'Permis B';
        }

        return null;
    }

    // Cherche dans le texte les noms deja connus de Jeuncy. Aucune invention :
    // si le mot n'est pas dans le referentiel, il n'est pas propose. Le
    // candidat reste libre d'ajouter les siens a la main ensuite.
    private function matchReferential(string $text, array $names): array
    {
        $found = [];

        foreach ($names as $name) {
            $needle = trim((string) $name);
            if (mb_strlen($needle) < self::MIN_REFERENTIAL_LENGTH) {
                continue;
            }

            // Frontieres de mot uniquement quand le nom commence et finit par
            // un caractere de mot : "Excel/Word" ou "C++" cassent \b.
            $quoted = preg_quote($needle, '/');
            $pattern = preg_match('/^\w/', $needle) && preg_match('/\w$/', $needle)
                ? '/\b'.$quoted.'\b/iu'
                : '/'.$quoted.'/iu';

            if (preg_match($pattern, $text)) {
                $found[] = $needle;
            }

            if (count($found) >= self::MAX_SUGGESTIONS) {
                break;
            }
        }

        return $found;
    }

    // Langues courantes accompagnees, quand il est present, du niveau CECRL
    // ecrit juste apres. Le niveau n'est retenu que s'il apparait dans les
    // ~40 caracteres qui suivent la langue : au-dela, il appartient
    // vraisemblablement a une autre ligne du CV.
    private const COMMON_LANGUAGES = [
        'Anglais', 'Espagnol', 'Allemand', 'Italien', 'Portugais', 'Catalan',
        'Arabe', 'Chinois', 'Russe', 'Neerlandais', 'Japonais', 'Francais',
    ];

    private function extractLanguages(string $text): array
    {
        $found = [];

        foreach (self::COMMON_LANGUAGES as $language) {
            // Sans accents des deux cotes : "Neerlandais" doit reconnaitre
            // "Néerlandais", "Francais" doit reconnaitre "Français".
            $haystack = Str::ascii($text);
            $needle = Str::ascii($language);

            if (! preg_match('/\b'.preg_quote($needle, '/').'\b/i', $haystack, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $after = substr($haystack, $matches[0][1] + strlen($needle), 40);
            $level = preg_match('/\b([ABC][12])\b/i', $after, $levelMatch)
                ? Str::upper($levelMatch[1])
                : null;

            $found[] = ['name' => $language, 'level' => $level];
        }

        return $found;
    }
}
