<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reconnait une ecole ou un organisme de formation qui se presente comme
 * une entreprise.
 *
 * Depuis que l'espace entreprise est gratuit (2026-09-15) et que
 * l'inscription CFA est fermee, la porte evidente pour un CFA est de
 * s'inscrire comme entreprise : un SIRET de societe, un nom d'enseigne, et
 * le voila devant les memes candidats que l'ecole partenaire. Ce detecteur
 * ferme cette porte au moment ou la fiche entreprise est creee ou modifiee.
 *
 * Trois indices, du plus sur au moins sur :
 *  1. le code NAF de l'etablissement (registre public, via l'API
 *     recherche-entreprises.api.gouv.fr) — la loi dit ce que fait la
 *     societe, pas son formulaire ;
 *  2. des mots dans le NOM (« CFA », « ecole », « campus », « formation »…)
 *     — un nom ne ment presque jamais sur ce point ;
 *  3. des tournures dans la DESCRIPTION (« nos entreprises partenaires »,
 *     « titre RNCP », « nos apprenants »…) — le vocabulaire d'un vendeur
 *     de formations, pas d'un employeur.
 *
 * Volontairement conservateur : un faux positif ferme la porte a un vrai
 * employeur, qui doit alors nous ecrire. « institut » est absent du
 * detecteur pour cette raison (un institut de beaute embauche en
 * alternance), et les auto-ecoles sont explicitement admises. Le NAF n'est
 * jamais bloquant quand l'API publique ne repond pas : une panne chez eux
 * ne doit pas empecher une inscription chez nous.
 */
class TrainingOrganizationDetector
{
    // Codes NAF de l'enseignement qui designent une ecole au sens ou Jeuncy
    // l'entend : secondaire, superieur, formation d'adultes, soutien a
    // l'enseignement. Les autres 85.xx sont laisses passer a dessein :
    // creches et maternelles (85.10Z) recrutent des CAP petite enfance,
    // auto-ecoles (85.53Z), clubs sportifs (85.51Z) et ecoles de musique
    // (85.52Z) sont des employeurs, pas des concurrents.
    private const BLOCKED_NAF = ['85.31Z', '85.32Z', '85.41Z', '85.42Z', '85.59A', '85.59B', '85.60Z'];

    // Sur le nom, apres suppression des accents et passage en minuscules.
    private const NAME_PATTERNS = [
        '/\bcfa\b/' => 'CFA',
        '/\becoles?\b/' => 'ecole',
        '/\bcampus\b/' => 'campus',
        '/\bacademies?\b/' => 'academie',
        '/\bformations?\b/' => 'formation',
        '/\blycees?\b/' => 'lycee',
        '/\buniversites?\b/' => 'universite',
        '/\biut\b/' => 'IUT',
        '/\bapprentissage\b/' => 'apprentissage',
        '/\b(bts|bachelor|mba)\b/' => 'diplome dans le nom',
    ];

    // Sur la description : des expressions entieres, pas des mots isoles.
    // « formation » seul y est trop frequent chez de vrais employeurs
    // (« formation assuree en interne »).
    private const DESCRIPTION_PATTERNS = [
        'centre de formation',
        'organisme de formation',
        "centre de formation d'apprentis",
        'ecole de commerce',
        'ecole superieure',
        'nos entreprises partenaires',
        "l'une de nos entreprises partenaires",
        'entreprise partenaire recherche',
        'notre ecole',
        'notre campus',
        'nos formations',
        'nos apprenants',
        'nos etudiants',
        'titre rncp',
        'frais de scolarite',
        'rentree 20',
    ];

    public function assertNotTrainingOrganization(?string $name, ?string $description, ?string $siret): void
    {
        $reason = $this->reasonFor($name, $description, $siret);
        if ($reason === null) {
            return;
        }

        $contact = config('services.contact.email');
        throw new ApiException(
            'TRAINING_ORGANIZATION_NOT_ALLOWED',
            "Les ecoles et organismes de formation ne peuvent pas ouvrir un espace entreprise pour le moment ({$reason}). Si tu es bien une entreprise qui recrute, ecris-nous a {$contact} et nous debloquerons ton compte.",
            403,
        );
    }

    // La raison, lisible, ou null si rien ne designe une ecole. Publique pour
    // que l'outil de deploiement et les tests puissent l'interroger sans
    // passer par l'exception.
    public function reasonFor(?string $name, ?string $description, ?string $siret): ?string
    {
        $nameReason = $this->nameReason($name);
        if ($nameReason !== null) {
            return $nameReason;
        }

        $descriptionReason = $this->descriptionReason($description);
        if ($descriptionReason !== null) {
            return $descriptionReason;
        }

        return $this->nafReason($siret);
    }

    public function nameReason(?string $name): ?string
    {
        $text = $this->normalize($name);
        if ($text === '') {
            return null;
        }

        // Une auto-ecole est un employeur (moniteurs en alternance) : le mot
        // « ecole » qu'elle contient ne doit pas la condamner.
        $text = preg_replace('/\bauto[\s-]?ecoles?\b/', '', $text) ?? $text;

        foreach (self::NAME_PATTERNS as $pattern => $label) {
            if (preg_match($pattern, $text)) {
                return "le nom evoque un organisme de formation : {$label}";
            }
        }

        return null;
    }

    public function descriptionReason(?string $description): ?string
    {
        $text = $this->normalize($description);
        if ($text === '') {
            return null;
        }

        foreach (self::DESCRIPTION_PATTERNS as $needle) {
            if (str_contains($text, $needle)) {
                return "la description evoque un organisme de formation : « {$needle} »";
            }
        }

        return null;
    }

    private function nafReason(?string $siret): ?string
    {
        $naf = $this->nafFor($siret);
        if ($naf === null) {
            return null;
        }

        return self::isBlockedNaf($naf)
            ? "activite principale « enseignement » au registre des entreprises (NAF {$naf})"
            : null;
    }

    // Reutilise par l'import des offres externes, qui recoit le NAF tout
    // fait et n'a pas besoin du registre.
    public static function isBlockedNaf(?string $naf): bool
    {
        return $naf !== null && in_array(strtoupper(trim($naf)), self::BLOCKED_NAF, true);
    }

    // Code NAF de l'ETABLISSEMENT (pas de l'unite legale : un groupe peut
    // avoir un siege « holding » et un etablissement « ecole », ou l'inverse).
    // Null si le SIRET est absent, inconnu, ou si l'API ne repond pas.
    public function nafFor(?string $siret): ?string
    {
        $siret = preg_replace('/\D/', '', (string) $siret) ?? '';
        if (strlen($siret) !== 14) {
            return null;
        }

        try {
            $response = Http::timeout(4)
                ->acceptJson()
                ->get('https://recherche-entreprises.api.gouv.fr/search', ['q' => $siret, 'per_page' => 1]);
            if (! $response->successful()) {
                return null;
            }

            $unit = $response->json('results.0');
            if (! is_array($unit)) {
                return null;
            }

            foreach ($unit['matching_etablissements'] ?? [] as $etablissement) {
                if (($etablissement['siret'] ?? null) === $siret && ! empty($etablissement['activite_principale'])) {
                    return (string) $etablissement['activite_principale'];
                }
            }

            return isset($unit['activite_principale']) ? (string) $unit['activite_principale'] : null;
        } catch (\Throwable $e) {
            Log::warning("Consultation du NAF impossible pour le SIRET {$siret} : {$e->getMessage()}");

            return null;
        }
    }

    private function normalize(?string $text): string
    {
        return trim(mb_strtolower(Str::ascii((string) $text)));
    }
}
