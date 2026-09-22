<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Cle de controle d'un SIRET (14 chiffres, algorithme de Luhn).
 *
 * Pourquoi ici ET dans CompanyVerificationService : une faute de frappe doit
 * etre refusee a la saisie (INVALID_INPUT, 400, avec un message clair)
 * plutot qu'ecrite en base puis marquee REJECTED — l'entreprise corrigerait
 * alors un refus de verification sans comprendre qu'elle a tape un chiffre
 * de travers. Le controle dans verify() reste la ceinture des lignes
 * ecrites hors formulaire (seeder, tinker, backfill).
 *
 * Exception connue : les SIRET de La Poste (SIREN 356000000) ne respectent
 * pas Luhn. Leur regle propre est que la somme des chiffres est un multiple
 * de 5. Sans cette exception, le premier employeur de France ne pourrait
 * pas s'inscrire.
 */
class ValidSiret implements ValidationRule
{
    public const LA_POSTE_SIREN = '356000000';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Un tableau ou un objet est deja refuse par la regle "string" qui
        // precede ; le caster ici ne ferait qu'emettre un avertissement PHP
        // (« Array to string conversion ») au milieu d'une validation.
        if ($value !== null && ! is_scalar($value)) {
            return;
        }

        if (! self::isValid((string) $value)) {
            $fail("Ce numéro SIRET n'est pas valide.");
        }
    }

    public static function isValid(?string $siret): bool
    {
        $digits = preg_replace('/\D/', '', (string) $siret) ?? '';
        if (strlen($digits) !== 14) {
            return false;
        }

        if (str_starts_with($digits, self::LA_POSTE_SIREN)) {
            return array_sum(str_split($digits)) % 5 === 0;
        }

        return self::luhnSum($digits) % 10 === 0;
    }

    // Longueur paire : doubler un chiffre sur deux en partant de la droite
    // revient a doubler les positions paires en partant de la gauche, la
    // convention usuelle du SIRET.
    private static function luhnSum(string $digits): int
    {
        $sum = 0;
        $length = strlen($digits);

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $digits[$length - 1 - $i];
            if ($i % 2 === 1) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
        }

        return $sum;
    }
}
