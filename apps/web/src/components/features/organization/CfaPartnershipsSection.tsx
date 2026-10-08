import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  addPartnerCompany,
  listMyCandidates,
  listPartnerCompanies,
  listPartnerOffers,
  recommendCandidate,
  removePartnerCompany,
  searchVerifiedCompanies,
} from '@/lib/api/cfa-partnership';
import { ApiError } from '@/lib/api/client';

// Espace CFA, chantier 2 de la feuille de route (2026-10-07) : déclarer ses
// entreprises partenaires (toujours un compte Jeuncy déjà vérifié — jamais
// un envoi à l'aveugle), puis recommander un des candidats rattachés à ce
// CFA (badge « JEUNCY x école ») pour une de leurs offres publiées.
//
// Une recommandation ne crée JAMAIS d'intérêt employeur à la place de
// l'entreprise : juste une notification + un lien vers la carte. Le
// « Ça m'intéresse » reste son geste à elle, inchangé.

const PARTNERS_KEY = ['cfa', 'partner-companies'];
const CANDIDATES_KEY = ['cfa', 'candidates'];

export function CfaPartnershipsSection() {
  const queryClient = useQueryClient();

  const partnersQuery = useQuery({
    queryKey: PARTNERS_KEY,
    queryFn: listPartnerCompanies,
  });
  const partners = partnersQuery.data ?? [];

  const [searchQuery, setSearchQuery] = useState('');
  const searchEnabled = searchQuery.trim().length >= 2;
  const searchResultsQuery = useQuery({
    queryKey: ['cfa', 'companies-search', searchQuery],
    queryFn: () => searchVerifiedCompanies(searchQuery),
    enabled: searchEnabled,
  });

  const addMutation = useMutation({
    mutationFn: addPartnerCompany,
    onSuccess: (data) => queryClient.setQueryData(PARTNERS_KEY, data),
  });
  const removeMutation = useMutation({
    mutationFn: removePartnerCompany,
    onSuccess: (data) => queryClient.setQueryData(PARTNERS_KEY, data),
  });

  const candidatesQuery = useQuery({
    queryKey: CANDIDATES_KEY,
    queryFn: listMyCandidates,
  });
  const candidates = candidatesQuery.data ?? [];

  const [selectedCandidateId, setSelectedCandidateId] = useState<number | ''>('');
  const [selectedCompanyId, setSelectedCompanyId] = useState<number | ''>('');
  const [selectedOfferId, setSelectedOfferId] = useState<number | ''>('');

  const offersQuery = useQuery({
    queryKey: ['cfa', 'partner-offers', selectedCompanyId],
    queryFn: () => listPartnerOffers(selectedCompanyId as number),
    enabled: selectedCompanyId !== '',
  });
  const offers = offersQuery.data ?? [];

  const recommendMutation = useMutation({
    mutationFn: () =>
      recommendCandidate(selectedCandidateId as number, selectedOfferId as number),
    onSuccess: () => {
      setSelectedCandidateId('');
      setSelectedCompanyId('');
      setSelectedOfferId('');
    },
  });

  const searchResults = (searchResultsQuery.data ?? []).filter(
    (company) => !partners.some((partner) => partner.id === company.id),
  );

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle>Entreprises partenaires</CardTitle>
          <CardDescription>
            Toujours un compte Jeuncy déjà vérifié — jamais d'envoi à une entreprise qui
            n'en a pas.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {partners.length === 0 ? (
            <p className="font-inter text-sm text-muted-foreground">
              Aucune entreprise partenaire pour l'instant.
            </p>
          ) : (
            <ul className="flex flex-col gap-2">
              {partners.map((company) => (
                <li
                  key={company.id}
                  className="flex items-center justify-between rounded-md border border-border p-3"
                >
                  <span className="font-inter text-sm">
                    {company.name}
                    {company.city ? ` — ${company.city}` : ''}
                  </span>
                  <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    onClick={() => removeMutation.mutate(company.id)}
                    disabled={removeMutation.isPending}
                  >
                    Retirer
                  </Button>
                </li>
              ))}
            </ul>
          )}

          <div className="flex flex-col gap-2">
            <Label htmlFor="cfa-company-search">Ajouter une entreprise partenaire</Label>
            <Input
              id="cfa-company-search"
              placeholder="Nom de l'entreprise (ex : NexaTech)"
              value={searchQuery}
              onChange={(event) => setSearchQuery(event.target.value)}
            />
            {searchEnabled &&
              (searchResultsQuery.isLoading ? (
                <p className="font-inter text-xs text-muted-foreground">Recherche…</p>
              ) : searchResults.length === 0 ? (
                <p className="font-inter text-xs text-muted-foreground">
                  Aucune entreprise vérifiée ne correspond. Elle doit d'abord créer son
                  compte Jeuncy.
                </p>
              ) : (
                <ul className="flex flex-col gap-2">
                  {searchResults.map((company) => (
                    <li
                      key={company.id}
                      className="flex items-center justify-between rounded-md border border-border p-3"
                    >
                      <span className="font-inter text-sm">
                        {company.name}
                        {company.city ? ` — ${company.city}` : ''}
                      </span>
                      <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={() => addMutation.mutate(company.id)}
                        disabled={addMutation.isPending}
                      >
                        Ajouter
                      </Button>
                    </li>
                  ))}
                </ul>
              ))}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Recommander un candidat</CardTitle>
          <CardDescription>
            Un de tes candidats, pour une offre publiée d'une entreprise partenaire. Elle
            est prévenue et voit sa carte ; elle reste libre de la liker ou non.
          </CardDescription>
        </CardHeader>
        <CardContent className="flex flex-col gap-4">
          {candidates.length === 0 ? (
            <p className="font-inter text-sm text-muted-foreground">
              Aucun candidat n'est encore rattaché à ton CFA.
            </p>
          ) : partners.length === 0 ? (
            <p className="font-inter text-sm text-muted-foreground">
              Ajoute d'abord une entreprise partenaire ci-dessus.
            </p>
          ) : (
            <>
              <div className="flex flex-col gap-1.5">
                <Label htmlFor="cfa-recommend-candidate">Candidat</Label>
                <select
                  id="cfa-recommend-candidate"
                  className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                  value={selectedCandidateId}
                  onChange={(event) =>
                    setSelectedCandidateId(
                      event.target.value ? Number(event.target.value) : '',
                    )
                  }
                >
                  <option value="">Choisir…</option>
                  {candidates.map((candidate) => (
                    <option key={candidate.id} value={candidate.id}>
                      {candidate.first_name} {candidate.last_name_initial}.
                      {candidate.headline ? ` — ${candidate.headline}` : ''}
                    </option>
                  ))}
                </select>
              </div>

              <div className="flex flex-col gap-1.5">
                <Label htmlFor="cfa-recommend-company">Entreprise partenaire</Label>
                <select
                  id="cfa-recommend-company"
                  className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                  value={selectedCompanyId}
                  onChange={(event) => {
                    setSelectedCompanyId(
                      event.target.value ? Number(event.target.value) : '',
                    );
                    setSelectedOfferId('');
                  }}
                >
                  <option value="">Choisir…</option>
                  {partners.map((partner) => (
                    <option key={partner.id} value={partner.id}>
                      {partner.name}
                    </option>
                  ))}
                </select>
              </div>

              {selectedCompanyId !== '' && (
                <div className="flex flex-col gap-1.5">
                  <Label htmlFor="cfa-recommend-offer">Offre</Label>
                  {offersQuery.isLoading ? (
                    <p className="font-inter text-xs text-muted-foreground">
                      Chargement…
                    </p>
                  ) : offers.length === 0 ? (
                    <p className="font-inter text-xs text-muted-foreground">
                      Cette entreprise n'a aucune offre publiée pour l'instant.
                    </p>
                  ) : (
                    <select
                      id="cfa-recommend-offer"
                      className="rounded-md border border-input bg-background px-3 py-2 text-sm"
                      value={selectedOfferId}
                      onChange={(event) =>
                        setSelectedOfferId(
                          event.target.value ? Number(event.target.value) : '',
                        )
                      }
                    >
                      <option value="">Choisir…</option>
                      {offers.map((offer) => (
                        <option key={offer.id} value={offer.id}>
                          {offer.title}
                        </option>
                      ))}
                    </select>
                  )}
                </div>
              )}

              {recommendMutation.isError && (
                <p role="alert" className="font-inter text-sm text-destructive">
                  {recommendMutation.error instanceof ApiError
                    ? recommendMutation.error.message
                    : "Impossible d'envoyer la recommandation."}
                </p>
              )}
              {recommendMutation.isSuccess && (
                <p className="font-inter text-sm text-jeuncy-coral">
                  Recommandation envoyée.
                </p>
              )}

              <Button
                type="button"
                variant="gradient"
                disabled={
                  selectedCandidateId === '' ||
                  selectedOfferId === '' ||
                  recommendMutation.isPending
                }
                onClick={() => recommendMutation.mutate()}
              >
                {recommendMutation.isPending ? 'Envoi…' : 'Recommander'}
              </Button>
            </>
          )}
        </CardContent>
      </Card>
    </>
  );
}
