<?php

namespace App\Services;

use App\Models\Application;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Badge « Repond en N jours » (MOBILE.md §5).
 *
 * C'EST L'ARGUMENT DU PRODUIT, rendu verifiable. Jeuncy promet une reponse ;
 * ce badge dit au candidat, avant qu'il postule, si cet employeur-la tient
 * la promesse. Il n'est donc pas decoratif : c'est la seule information de la
 * carte qui parle du COMPORTEMENT du recruteur et pas de son offre.
 *
 * SEUIL DE CINQ CANDIDATURES TRAITEES. En dessous, on affiche « Nouvelle
 * entreprise » plutot qu'un chiffre. Une entreprise qui a repondu une fois en
 * deux heures n'est pas « repond en 0 jour » — c'est une entreprise dont on
 * ne sait rien, et un chiffre tire d'un seul cas serait faux dans les deux
 * sens : injuste pour celle qui a eu un accident, flatteur pour celle qui a
 * eu de la chance.
 *
 * MEDIANE ET NON MOYENNE. Une seule candidature repondue au bout de six mois
 * tirerait une moyenne a des semaines et effacerait vingt reponses en deux
 * jours. La mediane dit ce qu'un candidat peut raisonnablement attendre.
 */
class EmployerResponseStats
{
    public const SEUIL_CANDIDATURES = 5;

    /**
     * Statistiques pour un lot d'organisations, en deux requetes.
     *
     * Le lot plutot que l'offre : une pile de vingt offres declencherait
     * vingt requetes, alors que deux suffisent.
     *
     * @param  list<int>  $companyIds
     * @param  list<int>  $cfaIds
     * @return array{COMPANY: array<int, int>, CFA: array<int, int>}
     */
    public function medianesPour(array $companyIds, array $cfaIds): array
    {
        return [
            'COMPANY' => $this->medianes('company_id', $companyIds),
            'CFA' => $this->medianes('cfa_organization_id', $cfaIds),
        ];
    }

    /**
     * Ce qu'affiche la carte : un nombre de jours, ou null quand on ne sait
     * pas encore. Le libelle appartient au client — le serveur ne renvoie
     * pas de phrase.
     *
     * @param  array{COMPANY: array<int, int>, CFA: array<int, int>}  $medianes
     */
    public function pourOffre(array $medianes, ?int $companyId, ?int $cfaId): ?int
    {
        if ($companyId !== null) {
            return $medianes['COMPANY'][$companyId] ?? null;
        }

        return $cfaId === null ? null : ($medianes['CFA'][$cfaId] ?? null);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function medianes(string $colonne, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // Une ligne par candidature repondue, avec le delai en jours. Le
        // calcul de la mediane se fait en PHP : MySQL 8 n'a pas de fonction
        // d'agregat mediane, et l'emuler en SQL (variables de session, ou
        // fenetre + comptage) rendrait la requete illisible pour quelques
        // centaines de lignes tout au plus.
        $delais = Application::query()
            ->join('job_offers', 'job_offers.id', '=', 'applications.job_offer_id')
            ->whereIn("job_offers.{$colonne}", $ids)
            ->whereNotNull('applications.responded_at')
            ->selectRaw("job_offers.{$colonne} as organisation_id, applications.created_at, applications.responded_at")
            ->get()
            ->groupBy('organisation_id');

        $resultat = [];

        foreach ($delais as $organisationId => $lignes) {
            if ($lignes->count() < self::SEUIL_CANDIDATURES) {
                continue;
            }

            $resultat[(int) $organisationId] = $this->mediane(
                $lignes->map(fn ($ligne) => max(
                    0,
                    (int) Carbon::parse($ligne->created_at)
                        ->diffInDays(Carbon::parse($ligne->responded_at)),
                )),
            );
        }

        return $resultat;
    }

    /**
     * @param  Collection<int, int>  $valeurs
     */
    private function mediane(Collection $valeurs): int
    {
        $triees = $valeurs->sort()->values();
        $milieu = intdiv($triees->count(), 2);

        // Nombre pair : on prend la valeur haute des deux du milieu. Annoncer
        // le delai le plus favorable des deux serait une promesse que la
        // moitie des candidats verraient dementie.
        return (int) $triees[$milieu];
    }
}
