<?php

namespace App\Support;

/**
 * Distance a vol d'oiseau en SQL, par la formule de haversine.
 *
 * Volontairement en SQL et non en PHP : la decouverte par distance filtre et
 * trie des milliers de lignes (7 779 offres partenaires en production), ce
 * que seule la base sait faire sans tout charger en memoire.
 *
 * Volontairement en haversine et non en ST_Distance_Sphere, pourtant
 * disponible sur le MySQL 8.4 de production : SQLite ne l'a pas, et une
 * requete que les tests ne peuvent pas executer est une requete que personne
 * ne verifie. Les fonctions trigonometriques manquantes de SQLite sont
 * pretees par Tests\TestCase::setUp.
 *
 * L'expression est celle de DeployController::autourDePerpignan, verifiee a
 * la main le 2026-09-22 (Perpignan -> Narbonne = 55,78 km).
 */
final class Haversine
{
    // Rayon moyen de la Terre, en kilometres.
    public const EARTH_RADIUS_KM = 6371;

    // 1 degre de latitude vaut ~111 km partout ; 1 degre de longitude vaut
    // 111 x cos(latitude) km, donc de moins en moins en montant vers le pole.
    private const KM_PER_DEGREE = 111.0;

    /**
     * Expression SQL rendant la distance en kilometres entre les colonnes
     * passees et un point. Elle porte TROIS marqueurs `?`, dans l'ordre
     * exact rendu par bindings() : latitude, latitude, longitude.
     *
     * La latitude apparait deux fois a dessein (une fois dans l'ecart, une
     * fois dans le cosinus) : c'est la formule, pas une redite.
     */
    public static function sql(string $latColumn, string $lngColumn): string
    {
        return self::EARTH_RADIUS_KM.' * 2 * ASIN(SQRT('
            ."POWER(SIN(RADIANS({$latColumn} - ?) / 2), 2)"
            ." + COS(RADIANS(?)) * COS(RADIANS({$latColumn}))"
            ." * POWER(SIN(RADIANS({$lngColumn} - ?) / 2), 2)))";
    }

    /**
     * Les trois valeurs a lier a sql(), dans l'ordre. Toujours utiliser
     * cette methode plutot que d'ecrire le tableau a la main : l'ordre
     * (lat, lat, lng) n'est pas celui qu'on ecrirait spontanement, et une
     * inversion donne une distance plausible mais fausse.
     *
     * @return list<float>
     */
    public static function bindings(float $lat, float $lng): array
    {
        return [$lat, $lat, $lng];
    }

    /**
     * Boite englobante d'un rayon, pour un pre-filtre indexable : la
     * haversine ne peut pas se servir d'un index, un BETWEEN sur latitude et
     * longitude si. On restreint d'abord au carre, la base n'evalue ensuite
     * la formule que sur les lignes survivantes.
     *
     * La boite est plus large que le cercle (ses coins depassent) : elle ne
     * peut donc jamais ecarter une ligne que le rayon aurait gardee. C'est
     * l'ordre qui compte — boite puis distance, jamais l'inverse.
     *
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    public static function boundingBox(float $lat, float $lng, int $km): array
    {
        $deltaLat = $km / self::KM_PER_DEGREE;

        // Pres des poles, cos(lat) tend vers zero et la boite s'ouvrirait a
        // l'infini. Un plancher evite la division par zero ; aucune commune
        // francaise n'en approche, mais une coordonnee aberrante en base ne
        // doit pas faire exploser la requete.
        $cos = max(cos(deg2rad($lat)), 0.01);
        $deltaLng = $km / (self::KM_PER_DEGREE * $cos);

        return [
            'minLat' => $lat - $deltaLat,
            'maxLat' => $lat + $deltaLat,
            'minLng' => $lng - $deltaLng,
            'maxLng' => $lng + $deltaLng,
        ];
    }

    /**
     * Meme calcul en PHP, pour les quelques cas ou il n'y a pas de requete
     * (comparer deux points deja charges). Meme formule, donc meme resultat
     * que la version SQL a l'arrondi flottant pres.
     */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * asin(min(1.0, sqrt($a)));
    }
}
