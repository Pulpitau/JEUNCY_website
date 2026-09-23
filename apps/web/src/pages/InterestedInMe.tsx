import { useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ApiError } from '@/lib/api/client';
import { discoverOffers, type DeckJobOffer } from '@/lib/api/discover';
import { INTEREST_ERRORS, likeOffer, passOffers } from '@/lib/api/interests';
import { contractTypeLabel } from '@/lib/contract-type-labels';
import { formatCompensation } from '@/lib/format-compensation';
import { MATCHES_QUERY_KEY } from '@/pages/Matches';

// « Ils s'intéressent à toi » — la face candidat de l'intérêt employeur.
//
// POURQUOI CETTE PAGE EXISTE. Quand un recruteur dit oui le premier, le
// candidat reçoit une notification et l'offre remonte dans sa pile
// (MOBILE.md §5). Sur le site, il n'y a pas de pile : sans cet écran,
// l'invitation n'existe nulle part une fois la notification lue.
//
// L'INTÉRÊT N'EST PAS UNE LISTE CONSULTABLE. Aucune route ne renvoie « les
// employeurs qui s'intéressent à moi » : l'information vit dans le drapeau
// `employer_interested` des offres de `discover/offers`, et c'est tout. On
// filtre donc la pile ici plutôt que d'ajouter une route qui ferait de
// l'intérêt un annuaire.
//
// Le geste reste celui du candidat : deux boutons, jamais une candidature
// envoyée à sa place. C'est la décision de fond du produit, celle qui avait
// fait écarter la candidature automatique en septembre 2026.

const DISCOVER_QUERY_KEY = ['discover', 'offers'];

export function InterestedInMe() {
  const queryClient = useQueryClient();
  const [notice, setNotice] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  /** Offres traitées pendant cette visite : retirées sans attendre un rechargement. */
  const [handled, setHandled] = useState<number[]>([]);

  const query = useQuery({
    queryKey: DISCOVER_QUERY_KEY,
    queryFn: () => discoverOffers(),
    retry: false,
  });

  const like = useMutation({
    mutationFn: likeOffer,
    onSuccess: (result, offerId) => {
      setHandled((current) => [...current, offerId]);
      setActionError(null);
      if (result.matched) {
        void queryClient.invalidateQueries({ queryKey: MATCHES_QUERY_KEY });
        setNotice(
          'C’est un match ! Envoie ton dossier depuis « Mes matchs » pour que le recruteur puisse te répondre.',
        );
      } else {
        setNotice('C’est noté. On prévient le recruteur.');
      }
    },
    onError: (error: unknown) => setActionError(messageFor(error)),
  });

  const pass = useMutation({
    mutationFn: (offerId: number) => passOffers([offerId]),
    onSuccess: (_result, offerId) => {
      setHandled((current) => [...current, offerId]);
      setActionError(null);
      setNotice(null);
    },
    onError: (error: unknown) => setActionError(messageFor(error)),
  });

  const busy = like.isPending || pass.isPending;

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">Ils s’intéressent à toi</h1>
        <p className="mt-1 font-inter text-muted-foreground">
          Ces recruteurs ont vu ton profil et aimeraient en savoir plus. À toi de dire si
          ça t’intéresse.
        </p>
      </div>

      {notice && (
        <p
          role="status"
          className="rounded-md border border-primary/40 bg-primary/5 p-3 font-inter text-sm"
        >
          {notice}
        </p>
      )}
      {actionError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {actionError}
        </p>
      )}

      <Content
        query={query}
        handled={handled}
        busy={busy}
        onLike={(offerId) => like.mutate(offerId)}
        onPass={(offerId) => pass.mutate(offerId)}
      />
    </main>
  );
}

function Content({
  query,
  handled,
  busy,
  onLike,
  onPass,
}: {
  query: ReturnType<typeof useQuery<Awaited<ReturnType<typeof discoverOffers>>>>;
  handled: number[];
  busy: boolean;
  onLike: (offerId: number) => void;
  onPass: (offerId: number) => void;
}) {
  if (query.isPending) {
    return <p className="font-inter text-sm text-muted-foreground">Chargement…</p>;
  }

  if (query.error) return <Blocked error={query.error} />;

  const interested = (query.data?.jeuncy ?? []).filter(
    (offer) => offer.employer_interested && !handled.includes(offer.id),
  );

  if (interested.length === 0) {
    return (
      <Card>
        <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
          <h2 className="font-poppins text-xl font-bold">Personne pour l’instant</h2>
          <p className="max-w-md font-inter text-sm text-muted-foreground">
            Un profil complet est vu bien plus souvent : ajoute tes expériences, tes
            compétences et une phrase de présentation.
          </p>
          <Link to="/profile">
            <Button variant="outline">Compléter mon profil</Button>
          </Link>
        </CardContent>
      </Card>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {interested.map((offer) => (
        <OfferCard
          key={offer.id}
          offer={offer}
          busy={busy}
          onLike={() => onLike(offer.id)}
          onPass={() => onPass(offer.id)}
        />
      ))}
    </div>
  );
}

function OfferCard({
  offer,
  busy,
  onLike,
  onPass,
}: {
  offer: DeckJobOffer;
  busy: boolean;
  onLike: () => void;
  onPass: () => void;
}) {
  const publisher = offer.company ?? offer.cfa_organization;
  const remuneration = formatCompensation(
    offer.compensation_amount,
    offer.compensation_period,
    offer.compensation,
  );

  return (
    <Card>
      <CardHeader className="flex flex-row items-start justify-between gap-3">
        <div className="min-w-0">
          <CardTitle className="font-poppins text-lg">
            <Link to={`/offres/${offer.id}`} className="hover:text-primary">
              {offer.title}
            </Link>
          </CardTitle>
          <p className="mt-1 font-inter text-sm text-muted-foreground">
            {[publisher?.name, offer.city, remuneration].filter(Boolean).join(' · ')}
          </p>
        </div>
        <Badge variant="default">Intéressé par ton profil</Badge>
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        <div className="flex flex-wrap gap-2">
          <Badge variant="outline">{contractTypeLabel(offer.contract_type)}</Badge>
          {/* La distance n'apparaît QUE de ce côté-ci : le candidat a le droit
              de savoir où est le poste, l'employeur n'a pas le droit de savoir
              où habite le candidat (L1132-1). */}
          {offer.distance_km !== null && (
            <Badge variant="outline">à {offer.distance_km} km</Badge>
          )}
        </div>

        <p className="line-clamp-3 font-inter text-sm text-muted-foreground">
          {offer.description}
        </p>

        <div className="flex flex-wrap gap-2">
          {/* Le libellé change pendant l'attente, et pas seulement l'état
              désactivé : quand le oui crée un match, le serveur envoie deux
              notifications et deux emails DANS la requête (pas de file
              d'attente, cf. MOBILE.md §5). Mesuré au navigateur : une dizaine
              de secondes, pendant lesquelles un bouton simplement grisé
              ressemble à un clic perdu. */}
          <Button variant="gradient" disabled={busy} onClick={onLike}>
            {busy ? 'Un instant…' : 'Ça m’intéresse'}
          </Button>
          <Button variant="outline" disabled={busy} onClick={onPass}>
            Passer
          </Button>
          <Link to={`/offres/${offer.id}`}>
            <Button variant="ghost">Voir l’offre</Button>
          </Link>
        </div>

        <p className="font-inter text-xs text-muted-foreground">
          Dire « ça m’intéresse » ne transmet aucune coordonnée : ça ouvre la
          conversation, et c’est toi qui décides ensuite d’envoyer ton dossier.
        </p>
      </CardContent>
    </Card>
  );
}

/**
 * Les refus de la garde du match, chacun avec ce qu'il y a à faire.
 *
 * Trois d'entre eux se réparent en deux clics (profil, date de naissance), un
 * ne se répare pas du tout (moins de 16 ans) — et c'est justement celui-là
 * qu'un message générique rendrait incompréhensible, puisque le site accepte
 * les inscriptions dès 15 ans.
 */
function Blocked({ error }: { error: unknown }) {
  const code = error instanceof ApiError ? error.code : null;

  if (code === INTEREST_ERRORS.PROFILE_REQUIRED) {
    return (
      <Notice
        title="Crée ton profil"
        description="Les recruteurs ne peuvent s’intéresser qu’à un profil existant."
        action={{ to: '/profile', label: 'Créer mon profil' }}
      />
    );
  }
  if (code === INTEREST_ERRORS.BIRTH_DATE_REQUIRED) {
    return (
      <Notice
        title="Il manque ta date de naissance"
        description="Elle nous sert à vérifier l’âge minimum, et n’est jamais montrée à un recruteur — il ne voit qu’une tranche d’âge."
        action={{ to: '/profile', label: 'Compléter mon profil' }}
      />
    );
  }
  if (code === INTEREST_ERRORS.MIN_AGE) {
    return (
      <Notice
        title="Réservé aux 16 ans et plus"
        description="La mise en relation directe avec les recruteurs ouvre à 16 ans. En attendant, tu peux postuler normalement aux offres."
        action={{ to: '/offres', label: 'Voir les offres' }}
      />
    );
  }

  return (
    <p role="alert" className="font-inter text-sm text-destructive">
      {error instanceof Error ? error.message : 'Chargement impossible.'}
    </p>
  );
}

function Notice({
  title,
  description,
  action,
}: {
  title: string;
  description: string;
  action: { to: string; label: string };
}) {
  return (
    <Card>
      <CardContent className="flex flex-col items-center gap-3 py-10 text-center">
        <h2 className="font-poppins text-xl font-bold">{title}</h2>
        <p className="max-w-md font-inter text-sm text-muted-foreground">{description}</p>
        <Link to={action.to}>
          <Button variant="outline">{action.label}</Button>
        </Link>
      </CardContent>
    </Card>
  );
}

function messageFor(error: unknown): string {
  const code = error instanceof ApiError ? error.code : null;

  if (code === INTEREST_ERRORS.QUOTA) {
    return 'Tu as atteint tes « ça m’intéresse » du jour. Reviens demain.';
  }
  if (code === INTEREST_ERRORS.OFFER_UNPUBLISHED || code === INTEREST_ERRORS.CLOSED) {
    return 'Cette offre n’est plus disponible.';
  }
  if (code === INTEREST_ERRORS.ALREADY_DECIDED) {
    return 'Tu as déjà répondu sur cette offre.';
  }

  return error instanceof Error ? error.message : 'Réessaie dans un instant.';
}
