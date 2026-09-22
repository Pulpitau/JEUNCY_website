<?php

namespace App\Support;

/**
 * Perimetre geographique d'ouverture du modele match (MOBILE.md §6).
 *
 * Il ne borne QUE le cote employeur : « Decouvrir des candidats » et le LIKE
 * d'un employeur exigent que l'offre soit dans un departement ouvert. Un
 * candidat, lui, peut marquer son interet sur n'importe quelle offre Jeuncy
 * publiee ou qu'elle soit — sa pile n'est jamais bornee par ce reglage.
 * La raison est asymetrique : montrer des cartes de mineurs a un employeur
 * que personne n'a vu est le risque du lot ; un jeune qui repere une offre
 * a Lyon ne met personne en danger.
 *
 * VIDE = FERME. C'est l'inverse de LBA_DEPARTEMENTS (ou vide = tous), et
 * c'est volontaire : une variable d'environnement oubliee au deploiement
 * doit fermer la porte, pas l'ouvrir a toute la France sans que personne ne
 * s'en apercoive. Un test dedie garde cette inversion
 * (MatchPerimeterTest::test_empty_value_means_closed).
 */
final class MatchPerimeter
{
    public const ALL = '*';

    /**
     * Departements ouverts, normalises.
     *
     * Accepte indifferemment la chaine brute du .env et le tableau que
     * config/services.php en tire : la config est normalisee au chargement,
     * mais un test pose une chaine par Config::set et doit obtenir le meme
     * resultat. Lire les deux formes evite de faire dependre une garde de
     * securite de la maniere dont la valeur est arrivee.
     *
     * @return list<string>  [] = ferme, ['*'] = tous
     */
    public static function departments(): array
    {
        $valeur = config('services.jeuncy.match_departements');

        $liste = is_array($valeur)
            ? $valeur
            : explode(',', (string) $valeur);

        $normalises = array_values(array_filter(
            array_map(fn ($item) => strtoupper(trim((string) $item)), $liste),
            fn (string $item) => $item !== '',
        ));

        return in_array(self::ALL, $normalises, true) ? [self::ALL] : $normalises;
    }

    /**
     * Le departement de ce code postal est-il ouvert ?
     *
     * Un code postal absent ou inexploitable rend false : on n'ouvre pas par
     * defaut. C'est pour cela que DiscoverService exige le code postal de
     * l'offre AVANT de consulter le perimetre — « pas encore ouvert ici »
     * serait faux et inexploitable la ou « indique le code postal » dit quoi
     * faire.
     */
    public static function isOpen(?string $postalCode): bool
    {
        $ouverts = self::departments();

        if ($ouverts === []) {
            return false;
        }

        if ($ouverts === [self::ALL]) {
            return true;
        }

        $departement = PostalCodes::department($postalCode);

        return $departement !== null && in_array(strtoupper($departement), $ouverts, true);
    }
}
