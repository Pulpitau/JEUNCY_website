<?php

namespace App\Console\Commands;

use App\Enums\DrivingLicenseCategory;
use App\Models\CandidateProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Reprise du champ texte driving_license vers has_driving_license +
 * driving_license_categories (89 profils concernes au 2026-09-22).
 *
 * Deux precautions, toutes deux payees comptant par l'experience du
 * 2026-09-04 (des profils reels nommes « Permis B ») :
 *
 *  1. Rien n'est ecrit sans --apply. Le rapport se relit a l'oeil avant
 *     d'ecrire quoi que ce soit dans les profils de vraies personnes.
 *  2. Jamais de categorie sur une lettre isolee A, C ou D. Apres
 *     normalisation, « vehicule a disposition » donne un « a » isole et
 *     « d'un vehicule » un « d » isole : deux categories inventees sur des
 *     textes qui n'en parlent pas. Seul le B isole est admis, parce que
 *     « B » tout court veut dire le permis B pour tout le monde.
 *
 * La colonne texte est CONSERVEE : elle alimente encore le gabarit de CV.
 */
class MigrateDrivingLicense extends Command
{
    protected $signature = 'candidates:migrate-driving-license {--apply}';

    protected $description = 'Deduit has_driving_license et driving_license_categories du texte driving_license (rapport seul sans --apply)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $lignes = [];
        $nonReconnus = [];
        $ecrits = 0;

        CandidateProfile::query()
            ->whereNotNull('driving_license')
            ->where('driving_license', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($profils) use (&$lignes, &$nonReconnus, &$ecrits, $apply) {
                foreach ($profils as $profil) {
                    $categories = self::categoriesFor($profil->driving_license);

                    // Non reconnu : liste, jamais ecrit. Un texte qu'on ne
                    // comprend pas ne doit pas devenir un « pas de permis ».
                    if ($categories === null) {
                        $nonReconnus[] = [$profil->id, $profil->driving_license];

                        continue;
                    }

                    $lignes[] = [
                        $profil->id,
                        $profil->driving_license,
                        $categories === [] ? '—' : implode(', ', array_map(fn ($c) => $c->value, $categories)),
                    ];

                    if ($apply) {
                        $profil->has_driving_license = $categories !== [];
                        $profil->driving_license_categories = array_map(fn ($c) => $c->value, $categories);
                        // updated_at n'est pas touche : le deck employeur
                        // classe les candidats par « profil mis a jour
                        // recemment » (DiscoverService). Une reprise
                        // technique qui repasse sur 89 profils les ferait
                        // tous passer pour actifs le meme jour, et effacerait
                        // le seul signal de fraicheur dont dispose le
                        // classement.
                        $profil->timestamps = false;
                        $profil->save();
                        $profil->timestamps = true;
                        $ecrits++;
                    }
                }
            });

        // Aucun nom dans le rapport : il n'y a rien a y faire, et un rapport
        // sans donnee personnelle se colle dans n'importe quelle conversation.
        $this->table(['id', 'texte', 'categories deduites'], $lignes);

        if ($nonReconnus !== []) {
            $this->warn('Textes non reconnus (aucune ecriture) :');
            $this->table(['id', 'texte'], $nonReconnus);
        }

        $this->info($apply
            ? "{$ecrits} profil(s) mis a jour, colonne texte conservee."
            : count($lignes).' profil(s) seraient mis a jour. Relis le tableau, puis relance avec --apply.');

        return self::SUCCESS;
    }

    /**
     * Categories deduites d'un texte libre.
     *
     * - null  : texte non reconnu (ne rien ecrire) ;
     * - []    : le candidat dit explicitement qu'il n'a pas le permis ;
     * - liste : categories deduites.
     *
     * @return list<DrivingLicenseCategory>|null
     */
    public static function categoriesFor(?string $text): ?array
    {
        $normalized = trim(mb_strtolower(Str::ascii((string) $text)));
        if ($normalized === '') {
            return null;
        }

        // Les negations d'abord : « permis B en cours » ne vaut pas un permis B.
        foreach (['pas de permis', 'sans permis', 'aucun permis', 'en cours', 'pas encore'] as $negation) {
            if (str_contains($normalized, $negation)) {
                return [];
            }
        }

        // « non », « aucun », « sans » ne valent negation que SEULS : dans
        // « permis B, non vehicule », le mot « non » porte sur le vehicule, et
        // conclure « pas de permis » retirerait un permis a quelqu'un qui
        // vient de le declarer.
        if (in_array($normalized, ['non', 'aucun', 'sans', 'nc', '-'], true)) {
            return [];
        }

        $categories = [];

        // BE avant B : la frontiere de mot suffit a les distinguer
        // (« permis be » ne correspond pas a « permis b »).
        if (preg_match('/\bpermis\s+be\b/', $normalized) || preg_match('/\bb\s?96\b/', $normalized)) {
            $categories[] = DrivingLicenseCategory::BE;
        }
        if (preg_match('/\bpermis\s+b\b/', $normalized)
            || preg_match('/\bb\b/', $normalized)
            || preg_match('/\bpermis de conduire\b/', $normalized)
            || $normalized === 'oui') {
            $categories[] = DrivingLicenseCategory::B;
        }
        if (preg_match('/\ba\s?1\b/', $normalized)) {
            $categories[] = DrivingLicenseCategory::A1;
        }
        if (preg_match('/\ba\s?2\b/', $normalized)) {
            $categories[] = DrivingLicenseCategory::A2;
        }
        // « permis a » ou « moto », jamais un « a » isole.
        if (preg_match('/\bpermis\s+a\b/', $normalized) || preg_match('/\bmotos?\b/', $normalized)) {
            $categories[] = DrivingLicenseCategory::A;
        }
        if (preg_match('/\bam\b/', $normalized) || preg_match('/\bbsr\b/', $normalized)) {
            $categories[] = DrivingLicenseCategory::AM;
        }
        // « permis c » ou « poids lourd », jamais un « c » isole.
        if (preg_match('/\bpermis\s+c\b/', $normalized) || preg_match('/\bpoids\s+lourds?\b/', $normalized)) {
            $categories[] = DrivingLicenseCategory::C;
        }
        // « permis d », jamais un « d » isole (« d'un vehicule »).
        if (preg_match('/\bpermis\s+d\b/', $normalized)) {
            $categories[] = DrivingLicenseCategory::D;
        }

        if ($categories === []) {
            return null;
        }

        return $categories;
    }
}
