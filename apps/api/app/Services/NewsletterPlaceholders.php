<?php

namespace App\Services;

use App\Support\OffersCount;

/**
 * Espaces reserves d'une edition, remplis AU MOMENT DE L'ENVOI.
 *
 * POURQUOI LES CHIFFRES NE SONT PAS ECRITS EN DUR DANS L'EDITION. Le stock
 * d'offres partenaires bouge a chaque import de nuit — il a perdu 956 offres
 * en neuf jours. Une edition redigee le lundi matin avec « 6 823 offres »
 * serait deja fausse a 10 h, et plus personne ne la relit : depuis le
 * 2026-10-01 l'envoi part tout seul. Le seul moment ou un chiffre est vrai est
 * celui ou le message est fabrique, donc c'est la qu'il est calcule.
 *
 * SYNTAXE [[nom]] ET NON {{ nom }} : le corps de l'edition est injecte dans la
 * coque Blade avec {!! !!}, donc Blade ne le relit pas — une accolade double
 * survivrait telle quelle et ressemblerait a un bug de Blade pour quiconque
 * lirait le resultat. Des crochets ne ressemblent a rien d'autre.
 *
 * UN ESPACE RESERVE INCONNU FAIT ECHOUER L'ENVOI. C'est le point important :
 * sans relecture humaine, la seule protection contre « [[ofres_total]] » recu
 * par 125 personnes est que le serveur refuse de partir. Mieux vaut une
 * semaine sans lettre qu'une lettre qui montre sa tuyauterie.
 */
class NewsletterPlaceholders
{
    /**
     * Espaces reserves reconnus, avec ce qu'ils disent. Sert aussi d'aide en
     * ligne sur la page de depot : une liste qu'on ne peut pas consulter est
     * une liste qu'on tape de memoire, donc de travers.
     */
    public const AIDE = [
        'offres_total' => 'Nombre total d\'offres en ligne (Jeuncy + partenaires)',
        'offres_jeuncy' => 'Offres publiees par les entreprises et CFA sur Jeuncy',
        'offres_partenaires' => 'Offres importees de La bonne alternance',
    ];

    /**
     * Separateur de milliers : espace INSECABLE (U+00A0), jamais un espace
     * ordinaire. Sans lui, « 6 823 » peut se couper en fin de ligne et
     * afficher « 6 » puis « 823 » sur deux lignes — ce qui se lit comme deux
     * nombres.
     */
    private const SEPARATEUR_MILLIERS = "\u{00A0}";

    /**
     * Les valeurs du moment. Rien n'est memorise entre deux appels : chaque
     * envoi recalcule.
     *
     * @return array<string, string>
     */
    public function valeurs(): array
    {
        $offres = OffersCount::current();

        return [
            'offres_total' => $this->nombre($offres['total']),
            'offres_jeuncy' => $this->nombre($offres['jeuncy']),
            'offres_partenaires' => $this->nombre($offres['partenaires']),
        ];
    }

    /**
     * Remplace les espaces reserves connus dans un contenu (HTML ou texte :
     * les deux passent ici, sans quoi la version texte annoncerait un autre
     * chiffre que la version HTML du meme message).
     *
     * @param  array<string, string>|null  $valeurs  Calculees une fois par envoi et passees ici, pour que les deux corps du MEME message portent exactement les memes nombres — les recalculer deux fois les ferait diverger si un import tombait entre les deux.
     */
    public function remplacer(string $contenu, ?array $valeurs = null): string
    {
        $valeurs ??= $this->valeurs();

        foreach ($valeurs as $nom => $valeur) {
            $contenu = str_replace('[['.$nom.']]', $valeur, $contenu);
        }

        return $contenu;
    }

    /**
     * Les espaces reserves qui subsistent apres remplacement, sans doublon.
     *
     * @return list<string>
     */
    public function nonResolus(string ...$contenus): array
    {
        $restants = [];

        foreach ($contenus as $contenu) {
            if (preg_match_all('/\[\[\s*([A-Za-z0-9_]+)\s*\]\]/', $contenu, $trouves)) {
                $restants = array_merge($restants, $trouves[1]);
            }
        }

        return array_values(array_unique($restants));
    }

    private function nombre(int $valeur): string
    {
        return number_format($valeur, 0, ',', self::SEPARATEUR_MILLIERS);
    }
}
