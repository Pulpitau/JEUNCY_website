import { useQuery } from '@tanstack/react-query';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { listReceivedRecommendations } from '@/lib/api/cfa-partnership';
import { ageBandLabel } from '@/lib/age-band-labels';

// « Recommandations reçues » — ce qu'un CFA partenaire pousse à l'entreprise.
//
// Lecture seule à dessein : décision de Pierre (2026-10-08), une
// recommandation ne crée jamais d'intérêt employeur à sa place, seulement
// une notification + un lien vers la carte. Le geste « Ça m'intéresse »
// reste strictement celui de l'entreprise, et aujourd'hui ce geste n'existe
// que côté application mobile (le site n'a pas de pile de cartes) — la page
// renvoie donc vers l'app plutôt que d'ajouter une seconde façon de liker.

const RECOMMENDATIONS_QUERY_KEY = ['recommendations', 'received'];

export function ReceivedRecommendations() {
  const query = useQuery({
    queryKey: RECOMMENDATIONS_QUERY_KEY,
    queryFn: listReceivedRecommendations,
  });

  const recommendations = query.data ?? [];

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Recommandations reçues</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Des candidats que tes CFA partenaires te recommandent pour tes offres publiées.
        </p>
      </div>

      {query.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : query.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les recommandations pour le moment, réessaie plus tard.
        </p>
      ) : recommendations.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          Aucune recommandation pour l'instant.
        </p>
      ) : (
        <div className="flex flex-col gap-4">
          {recommendations.map((recommendation) => {
            const candidate = recommendation.candidate;
            const ageLabel = ageBandLabel(candidate.age_band);

            return (
              <Card key={recommendation.id}>
                <CardHeader className="flex flex-col gap-1">
                  <Badge variant="secondary" className="w-fit text-xs">
                    JEUNCY x {recommendation.recommended_by}
                  </Badge>
                  <CardTitle className="text-lg">
                    {candidate.first_name} {candidate.last_name_initial}.
                    {ageLabel ? ` — ${ageLabel}` : ''}
                  </CardTitle>
                  <p className="font-inter text-sm text-muted-foreground">
                    Pour ton offre « {recommendation.job_offer.title} »
                  </p>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                  {candidate.headline && (
                    <p className="font-inter text-sm">{candidate.headline}</p>
                  )}
                  {candidate.pitch && (
                    <p className="font-inter text-sm italic text-muted-foreground">
                      « {candidate.pitch} »
                    </p>
                  )}
                  {candidate.skills.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                      {candidate.skills.map((skill) => (
                        <Badge
                          key={skill.id}
                          variant={skill.in_common ? 'default' : 'secondary'}
                          className="text-xs"
                        >
                          {skill.name}
                        </Badge>
                      ))}
                    </div>
                  )}
                  <p className="font-inter text-xs text-muted-foreground">
                    Ouvre l'application Jeuncy pour voir la fiche complète et dire si ce
                    candidat t'intéresse.
                  </p>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}
    </main>
  );
}
