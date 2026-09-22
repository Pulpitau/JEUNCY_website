<?php

namespace App\Support;

/**
 * Lecture du departement dans un code postal francais.
 *
 * Reprise de DeployController::departementDuCodePostal, a une difference
 * pres : ici un code inexploitable rend null et non 'inconnu'. La sonde
 * compte des lignes et a besoin d'une case ou les ranger ; le produit, lui,
 * doit pouvoir dire « je ne sais pas » sans qu'un departement fictif nomme
 * « inconnu » circule dans une requete SQL.
 */
final class PostalCodes
{
    /**
     * Cas traites : Corse (2A jusqu'a 20199, 2B au-dela), outre-mer (trois
     * chiffres), saisies sales (espaces, prefixe « F- », ville tapee dans le
     * champ). Tout ce qui ne donne pas exactement cinq chiffres rend null :
     * mieux vaut ne rien conclure que d'attribuer un departement au hasard —
     * un candidat mal range n'est pas montre au mauvais employeur, il n'est
     * pas montre du tout.
     */
    public static function department(?string $postalCode): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $postalCode);

        if (strlen((string) $chiffres) !== 5) {
            return null;
        }

        if (str_starts_with($chiffres, '20')) {
            return (int) $chiffres < 20200 ? '2A' : '2B';
        }

        if (str_starts_with($chiffres, '97') || str_starts_with($chiffres, '98')) {
            return substr($chiffres, 0, 3);
        }

        return substr($chiffres, 0, 2);
    }

    /**
     * Prefixe postal d'un departement, pour un `like 'xx%'`.
     *
     * Les deux Corse partagent le meme prefixe postal (20) : filtrer sur
     * '2A%' ne remonterait aucune ligne.
     */
    public static function prefix(string $department): string
    {
        $normalise = strtoupper(trim($department));

        return in_array($normalise, ['2A', '2B'], true) ? '20' : $normalise;
    }
}
