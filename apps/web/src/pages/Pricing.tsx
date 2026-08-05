import { Link } from 'react-router-dom';
import { Check, Sparkles, Users, Video, Gauge } from 'lucide-react';
import { UserRole } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { FreeForCandidatesBadge } from '@/components/FreeForCandidatesBadge';
import {
  COMPANY_OFFER_PRICE_LABEL,
  CFA_OFFER_PRICE_LABEL,
  APPLICATIONS_UNLOCK_PRICE_LABEL,
} from '@/lib/api/job-offers';
import {
  COMPANY_SUBSCRIPTION_PRICE_LABEL,
  CFA_SUBSCRIPTION_PRICE_LABEL,
} from '@/lib/api/subscriptions';
import { useAuthStore } from '@/store/auth-store';

const SUBSCRIPTION_BENEFITS = [
  "Publication d'offres illimitée",
  'Accès aux candidatures de toutes vos offres, y compris les anciennes',
  'Tableau de bord centralisé pour gérer toutes vos candidatures',
  'Salle de visioconférence intégrée pour vos entretiens',
  'Sans engagement, résiliable à tout moment depuis votre espace',
];

// Prix du moins cher au plus cher (affichage : le moins cher toujours a
// gauche) — a reordonner ici si les tarifs changent un jour.
const SUBSCRIPTION_PLANS = [
  {
    audience: 'CFA',
    role: UserRole.CFA,
    price: CFA_SUBSCRIPTION_PRICE_LABEL,
    description: 'Pour gérer vos offres multi-filières au fil de l’année.',
    registerHref: '/register?role=CFA',
  },
  {
    audience: 'Entreprises',
    role: UserRole.COMPANY,
    price: COMPANY_SUBSCRIPTION_PRICE_LABEL,
    description: 'Pour recruter régulièrement, sans compter les offres.',
    registerHref: '/register?role=COMPANY',
  },
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
  const user = useAuthStore((state) => state.user);

  return (
    <main>
      <section className="mx-auto max-w-4xl px-4 py-20 text-center">
        <Badge variant="secondary" className="mb-4">
          Tarifs
        </Badge>
        <h1 className="font-poppins text-4xl font-bold tracking-tight text-foreground md:text-5xl">
          Des tarifs simples,{' '}
          <span className="bg-jeuncy-gradient bg-clip-text text-transparent">
            pensés pour recruter vite.
          </span>
        </h1>
        <p className="mx-auto mt-4 max-w-2xl font-inter text-lg text-muted-foreground">
          Un abonnement mensuel pour recruter sans limite, ou un paiement à l'offre si
          vous publiez plus rarement — à vous de choisir. Dans les deux cas, un premier
          essai est offert pour tester la plateforme.
        </p>
      </section>

      <section className="mx-auto max-w-3xl px-4 pb-10">
        <div className="rounded-md border border-jeuncy-orange/30 bg-jeuncy-orange/10 px-4 py-3 text-center font-inter text-sm text-foreground">
          <span className="font-poppins font-semibold">Essai gratuit</span> — publiez 1
          offre pendant 15 jours, avec accès aux candidatures inclus, sans carte bancaire.
        </div>
      </section>

      <section className="mx-auto max-w-5xl px-4 pb-6">
        <div className="mx-auto max-w-2xl text-center">
          <Badge variant="outline" className="mb-3">
            Recommandé
          </Badge>
          <h2 className="font-poppins text-2xl font-bold text-foreground md:text-3xl">
            Abonnement mensuel
          </h2>
          <p className="mt-2 font-inter text-sm text-muted-foreground">
            Publication illimitée et accès aux candidatures de toutes vos offres inclus.
          </p>
        </div>
        <div className="mt-8 grid gap-6 md:grid-cols-2">
          {SUBSCRIPTION_PLANS.map((plan) => {
            const isOwnAccount = user?.role === plan.role;
            const ctaHref = isOwnAccount ? '/mes-offres' : plan.registerHref;
            const ctaLabel = isOwnAccount ? 'Gérer mon abonnement' : `S'abonner`;

            return (
              <Card
                key={plan.audience}
                className="relative overflow-hidden border-2 border-primary/40 transition-all duration-300 hover:-translate-y-1 hover:border-primary hover:shadow-xl"
              >
                <div className="absolute inset-x-0 top-0 h-1 bg-jeuncy-gradient" />
                <CardHeader>
                  <Badge variant="outline" className="w-fit">
                    {plan.audience}
                  </Badge>
                  <CardTitle className="mt-2 text-3xl">
                    {plan.price}
                    <span className="ml-2 font-inter text-sm font-normal text-muted-foreground">
                      / mois
                    </span>
                  </CardTitle>
                  <p className="font-inter text-sm text-muted-foreground">
                    {plan.description}
                  </p>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                  <ul className="flex flex-col gap-2">
                    {SUBSCRIPTION_BENEFITS.map((benefit) => (
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
                    <Button variant="gradient" className="w-full">
                      {ctaLabel}
                    </Button>
                  </Link>
                </CardContent>
              </Card>
            );
          })}
        </div>
      </section>

      <section className="mx-auto max-w-5xl px-4 py-16">
        <div className="mx-auto max-w-2xl text-center">
          <Badge variant="outline" className="mb-3">
            Sans engagement
          </Badge>
          <h2 className="font-poppins text-2xl font-bold text-foreground md:text-3xl">
            Ou à la carte, offre par offre
          </h2>
          <p className="mt-2 font-inter text-sm text-muted-foreground">
            Vous publiez rarement ? Payez uniquement pour ce dont vous avez besoin :
            publier une offre, puis débloquer ses candidatures si vous le souhaitez.
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
                <div className="absolute inset-x-0 top-0 h-1 bg-jeuncy-gradient" />
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
                  <div className="rounded-md border border-border bg-muted/40 px-3 py-2 font-inter text-sm text-foreground">
                    Accès aux candidatures de cette offre :{' '}
                    <span className="font-poppins font-semibold">
                      {APPLICATIONS_UNLOCK_PRICE_LABEL}
                    </span>{' '}
                    (à renouveler à chaque offre, ou passez à l'abonnement pour tout
                    inclure)
                  </div>
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
              Ce que vous gagnez à publier chez nous
            </h2>
          </div>
          <div className="mt-10 grid gap-6 md:grid-cols-3">
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
          entièrement gratuit pour les alternants, saisonniers et bénévoles.
        </p>
      </section>
    </main>
  );
}
