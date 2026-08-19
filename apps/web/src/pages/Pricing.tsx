import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { Check, Sparkles, Users, Video, Gauge, Search, Flame } from 'lucide-react';
import { UserRole } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FreeForCandidatesBadge } from '@/components/FreeForCandidatesBadge';
import { COMPANY_OFFER_PRICE_LABEL, CFA_OFFER_PRICE_LABEL } from '@/lib/api/job-offers';
import {
  getFounderOffer,
  SUBSCRIPTION_PRICE_LABEL,
  FOUNDER_SUBSCRIPTION_PRICE_LABEL,
} from '@/lib/api/subscriptions';
import { useAuthStore } from '@/store/auth-store';
import { usePageMetadata } from '@/hooks/use-page-metadata';

// Tout illimite : c'est le seul chemin vers les candidatures et la CVtheque
// depuis le 2026-08-17 (le deblocage a l'offre n'existe plus).
const SUBSCRIPTION_BENEFITS = [
  "Publication d'offres illimitée",
  'Accès à la CVthèque : cherchez et contactez les candidats directement',
  'Toutes les candidatures de toutes vos offres, y compris les anciennes',
  'Salle de visioconférence intégrée pour vos entretiens',
  'Sans engagement, résiliable à tout moment depuis votre espace',
];

const PAY_AS_YOU_GO_PLANS = [
  {
    audience: 'CFA',
    role: UserRole.CFA,
    publishPrice: CFA_OFFER_PRICE_LABEL,
    description: 'Faites connaître vos formations et trouvez vos futurs apprenants.',
    registerHref: '/register?role=CFA',
  },
  {
    audience: 'Entreprises',
    role: UserRole.COMPANY,
    publishPrice: COMPANY_OFFER_PRICE_LABEL,
    description: "Publiez vos offres d'alternance, de saisonnier ou de bénévolat.",
    registerHref: '/register?role=COMPANY',
  },
];

const PAY_AS_YOU_GO_BENEFITS = [
  "Visible immédiatement auprès d'un vivier de jeunes talents ciblé",
  'Salle de visioconférence intégrée pour organiser une démo ou un échange',
  'Sans abonnement : vous ne payez que ce que vous utilisez',
];

const WHY_JEUNCY = [
  {
    icon: Search,
    title: 'Une CVthèque, pas seulement des annonces',
    description:
      "N'attendez plus les candidatures : filtrez les profils par compétence, ville, langue ou permis, et contactez directement ceux qui vous correspondent.",
  },
  {
    icon: Users,
    title: 'Une audience jeune et engagée',
    description:
      'Jeuncy ne mélange pas tout : les candidats qui viennent ici cherchent spécifiquement une alternance, un job saisonnier ou étudiant, ou une mission bénévole.',
  },
  {
    icon: Gauge,
    title: 'Un tableau de bord pensé pour aller vite',
    description:
      'Créez, publiez et suivez vos offres et vos candidatures depuis un seul endroit, sans jongler entre plusieurs outils.',
  },
  {
    icon: Video,
    title: 'Une visio intégrée pour humaniser le recrutement',
    description:
      'Organisez une démonstration ou un premier échange directement depuis Jeuncy, sans lien externe à gérer.',
  },
];

export function Pricing() {
  usePageMetadata(
    'Tarifs entreprises et CFA',
    "Publication d'offres et accès à la CVthèque : les tarifs Jeuncy pour les entreprises et les CFA.",
    '/tarifs',
  );
  const user = useAuthStore((state) => state.user);
  const isOrganization = user?.role === UserRole.COMPANY || user?.role === UserRole.CFA;

  // Endpoint public : le compteur doit s'afficher pour un visiteur sans compte,
  // c'est precisement lui qu'on cherche a convaincre.
  const founderOfferQuery = useQuery({
    queryKey: ['founder-offer'],
    queryFn: getFounderOffer,
  });
  const founderOffer = founderOfferQuery.data ?? null;
  const founderAvailable = founderOffer?.available ?? false;

  const subscribeHref = isOrganization
    ? '/mes-offres'
    : `/register?role=${user?.role === UserRole.CFA ? 'CFA' : 'COMPANY'}`;

  return (
    <main>
      <section className="mx-auto max-w-4xl px-4 pb-10 pt-20 text-center">
        <Badge variant="secondary" className="mb-4">
          Tarifs
        </Badge>
        <h1 className="font-poppins text-4xl font-bold tracking-tight text-foreground md:text-5xl">
          Un seul abonnement,{' '}
          <span className="bg-jeuncy-gradient bg-clip-text text-transparent">
            tout le recrutement.
          </span>
        </h1>
        <p className="mx-auto mt-4 max-w-2xl font-inter text-lg text-muted-foreground">
          Offres illimitées, toutes vos candidatures et l'accès à la CVthèque pour
          chercher directement les profils qui vous intéressent. Ou payez à l'annonce si
          vous publiez rarement.
        </p>
      </section>

      {/* Banniere d'ouverture : premier element apres le titre, c'est l'argument
          qui doit declencher la souscription. Masquee des que les 50 places
          sont prises, plutot que d'afficher une offre morte. */}
      {founderAvailable && (
        <section className="mx-auto max-w-4xl px-4 pb-12">
          <div className="relative overflow-hidden rounded-xl border-2 border-jeuncy-orange/50 bg-jeuncy-orange/10 p-6 text-center shadow-lg">
            <div className="absolute inset-x-0 top-0 h-1 bg-jeuncy-gradient" />
            <div className="flex items-center justify-center gap-2">
              <Flame className="h-5 w-5 text-jeuncy-coral" aria-hidden="true" />
              <span className="font-poppins text-sm font-bold uppercase tracking-wide text-jeuncy-coral">
                Offre d'ouverture
              </span>
            </div>
            <p className="mt-3 font-poppins text-3xl font-bold text-foreground md:text-4xl">
              <span className="text-2xl text-muted-foreground line-through md:text-3xl">
                {SUBSCRIPTION_PRICE_LABEL}
              </span>{' '}
              <span className="bg-jeuncy-gradient bg-clip-text text-transparent">
                {FOUNDER_SUBSCRIPTION_PRICE_LABEL}
              </span>
              <span className="font-inter text-base font-normal text-muted-foreground">
                {' '}
                / mois
              </span>
            </p>
            <p className="mx-auto mt-3 max-w-xl font-inter text-sm text-foreground">
              Réservée aux <strong>50 premiers</strong> CFA et entreprises inscrits. Le
              tarif reste acquis <strong>tant que votre abonnement continue</strong> —
              même quand le prix public repassera à {SUBSCRIPTION_PRICE_LABEL}.
            </p>

            {/* Barre de progression : rendre la rarete visible, pas seulement
                enoncee. aria-* pour que le lecteur d'ecran ait l'information. */}
            <div className="mx-auto mt-5 max-w-md">
              <div
                className="h-2.5 w-full overflow-hidden rounded-full bg-background"
                role="progressbar"
                aria-valuenow={founderOffer!.seats_taken}
                aria-valuemin={0}
                aria-valuemax={founderOffer!.seats_total}
                aria-label="Places déjà prises sur l'offre d'ouverture"
              >
                <div
                  className="h-full rounded-full bg-jeuncy-gradient transition-all duration-500"
                  style={{
                    width: `${Math.max(4, (founderOffer!.seats_taken / founderOffer!.seats_total) * 100)}%`,
                  }}
                />
              </div>
              <p className="mt-2 font-poppins text-sm font-semibold text-foreground">
                Il reste {founderOffer!.seats_remaining} place
                {founderOffer!.seats_remaining > 1 ? 's' : ''} sur{' '}
                {founderOffer!.seats_total}
              </p>
            </div>

            <Link to={subscribeHref} className="mt-5 inline-block">
              <Button variant="gradient" size="lg">
                {isOrganization ? 'Bloquer mon tarif fondateur' : 'Créer mon compte'}
              </Button>
            </Link>
          </div>
        </section>
      )}

      <section className="mx-auto max-w-3xl px-4 pb-10">
        <div className="rounded-md border border-border bg-muted/40 px-4 py-3 text-center font-inter text-sm text-foreground">
          <span className="font-poppins font-semibold">Essai gratuit</span> — publiez 1
          offre pendant 15 jours, avec accès aux candidatures inclus, sans carte bancaire.
        </div>
      </section>

      <section className="mx-auto max-w-2xl px-4 pb-16">
        <Card className="relative overflow-hidden border-2 border-primary/40 transition-all duration-300 hover:-translate-y-1 hover:border-primary hover:shadow-xl">
          <div className="absolute inset-x-0 top-0 h-1 bg-jeuncy-gradient" />
          <CardHeader>
            <Badge variant="outline" className="w-fit">
              Entreprises et CFA
            </Badge>
            <CardTitle className="mt-2 text-4xl">
              {founderAvailable ? (
                <>
                  <span className="text-2xl text-muted-foreground line-through">
                    {SUBSCRIPTION_PRICE_LABEL}
                  </span>{' '}
                  {FOUNDER_SUBSCRIPTION_PRICE_LABEL}
                </>
              ) : (
                SUBSCRIPTION_PRICE_LABEL
              )}
              <span className="ml-2 font-inter text-sm font-normal text-muted-foreground">
                / mois
              </span>
            </CardTitle>
            <p className="font-inter text-sm text-muted-foreground">
              Tout le recrutement Jeuncy sans limite, pour recruter toute l'année.
            </p>
          </CardHeader>
          <CardContent className="flex flex-col gap-4">
            <ul className="flex flex-col gap-2">
              {SUBSCRIPTION_BENEFITS.map((benefit) => (
                <li key={benefit} className="flex items-start gap-2 font-inter text-sm">
                  <Check
                    className="mt-0.5 h-4 w-4 shrink-0 text-jeuncy-orange"
                    aria-hidden="true"
                  />
                  <span className="text-foreground">{benefit}</span>
                </li>
              ))}
            </ul>
            <Link to={subscribeHref}>
              <Button variant="gradient" className="w-full" size="lg">
                {isOrganization ? "S'abonner" : "Créer un compte et m'abonner"}
              </Button>
            </Link>
          </CardContent>
        </Card>
      </section>

      <section className="mx-auto max-w-5xl px-4 pb-16">
        <div className="mx-auto max-w-2xl text-center">
          <Badge variant="outline" className="mb-3">
            Sans engagement
          </Badge>
          <h2 className="font-poppins text-2xl font-bold text-foreground md:text-3xl">
            Ou à l'annonce, si vous publiez rarement
          </h2>
          <p className="mt-2 font-inter text-sm text-muted-foreground">
            Vous ne payez que la mise en ligne de l'offre. La CVthèque et l'accès aux
            candidatures restent réservés à l'abonnement.
          </p>
        </div>
        <div className="mt-8 grid gap-6 md:grid-cols-2">
          {PAY_AS_YOU_GO_PLANS.map((plan) => {
            const isOwnAccount = user?.role === plan.role;
            const ctaHref = isOwnAccount ? '/mes-offres' : plan.registerHref;
            const ctaLabel = isOwnAccount
              ? 'Publier une offre'
              : plan.audience === 'CFA'
                ? 'Créer un compte CFA'
                : 'Créer un compte entreprise';

            return (
              <Card
                key={plan.audience}
                className="relative overflow-hidden border-2 transition-all duration-300 hover:-translate-y-1 hover:border-primary hover:shadow-xl"
              >
                <CardHeader>
                  <Badge variant="outline" className="w-fit">
                    {plan.audience}
                  </Badge>
                  <CardTitle className="mt-2 text-3xl">
                    {plan.publishPrice}
                    <span className="ml-2 font-inter text-sm font-normal text-muted-foreground">
                      par offre publiée
                    </span>
                  </CardTitle>
                  <p className="font-inter text-sm text-muted-foreground">
                    {plan.description}
                  </p>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                  <ul className="flex flex-col gap-2">
                    {PAY_AS_YOU_GO_BENEFITS.map((benefit) => (
                      <li
                        key={benefit}
                        className="flex items-start gap-2 font-inter text-sm"
                      >
                        <Check
                          className="mt-0.5 h-4 w-4 shrink-0 text-jeuncy-orange"
                          aria-hidden="true"
                        />
                        <span className="text-foreground">{benefit}</span>
                      </li>
                    ))}
                  </ul>
                  <Link to={ctaHref}>
                    <Button variant="outline" className="w-full">
                      {ctaLabel}
                    </Button>
                  </Link>
                </CardContent>
              </Card>
            );
          })}
        </div>
      </section>

      <section className="bg-muted/30 px-4 py-16">
        <div className="mx-auto max-w-5xl">
          <div className="mx-auto max-w-2xl text-center">
            <Badge variant="outline" className="mb-4">
              Pourquoi Jeuncy
            </Badge>
            <h2 className="font-poppins text-3xl font-bold text-foreground">
              Ce que vous gagnez à recruter chez nous
            </h2>
          </div>
          <div className="mt-10 grid gap-6 sm:grid-cols-2">
            {WHY_JEUNCY.map((item) => (
              <div
                key={item.title}
                className="flex flex-col items-start gap-3 rounded-lg border border-border bg-card p-6"
              >
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-jeuncy-gradient text-white">
                  <item.icon className="h-5 w-5" aria-hidden="true" />
                </div>
                <p className="font-poppins font-semibold text-foreground">{item.title}</p>
                <p className="font-inter text-sm text-muted-foreground">
                  {item.description}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-3xl px-4 py-20 text-center">
        <Sparkles className="mx-auto h-8 w-8 text-jeuncy-orange" aria-hidden="true" />
        <h2 className="mt-4 font-poppins text-2xl font-bold text-foreground">
          Et pour les candidats ?
        </h2>
        <div className="mt-4 flex justify-center">
          <FreeForCandidatesBadge />
        </div>
        <p className="mx-auto mt-4 max-w-xl font-inter text-muted-foreground">
          Créer un profil, générer un CV et postuler à des offres reste et restera
          entièrement gratuit pour les alternants, saisonniers et bénévoles. Chaque
          candidat garde la main sur sa visibilité dans la CVthèque et peut s'en retirer à
          tout moment.
        </p>
      </section>
    </main>
  );
}
