import { Link } from 'react-router-dom';
import {
  Check,
  X,
  Sparkles,
  Users,
  Video,
  Gauge,
  Search,
  Bell,
  FileText,
  ShieldCheck,
  Rocket,
  type LucideIcon,
} from 'lucide-react';
import { UserRole } from '@jeuncy/shared';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAuthStore } from '@/store/auth-store';
import { usePageMetadata } from '@/hooks/use-page-metadata';

// Page « Gratuit » — remplace l'ancienne page Tarifs depuis que Jeuncy ne
// facture plus les entreprises (decision du 2026-09-15). Le business plan
// mise sur le volume : plus d'entreprises et de candidats d'abord, la
// monetisation ensuite. Cette page a un seul travail : que personne ne
// puisse douter que c'est gratuit, et que ca donne envie de s'inscrire.
//
// Adresse canonique /tarifs (c'est le mot que cherche une entreprise, et
// l'onglet du menu) ; /gratuit y mene aussi.

// Ce qu'une entreprise obtient. Chaque ligne repond a un doute qu'on entend
// en rendez-vous : « gratuit, mais limite ? », « gratuit, mais sans les
// candidatures ? », « gratuit, mais avec ma carte bancaire ? ».
const INCLUDED: { icon: LucideIcon; title: string; description: string }[] = [
  {
    icon: Rocket,
    title: "Publication d'offres illimitée",
    description:
      "Alternance, saisonnier, stage, job étudiant, bénévolat : publiez autant d'offres que vous voulez, en ligne en quelques minutes.",
  },
  {
    icon: FileText,
    title: 'Toutes vos candidatures, CV et lettres inclus',
    description:
      'Chaque candidature arrive avec le CV du candidat et son message. Vous changez son statut, il est prévenu.',
  },
  {
    icon: Search,
    title: 'La CVthèque en accès complet',
    description:
      "N'attendez pas les candidatures : filtrez les profils par métier, ville, âge, langue ou permis, et contactez-les directement.",
  },
  {
    icon: Bell,
    title: 'Vos offres poussées aux bons candidats',
    description:
      'Dès la publication, les candidats dont le profil correspond reçoivent une notification. Ils postulent en un clic.',
  },
  {
    icon: Video,
    title: 'Visioconférence intégrée',
    description:
      'Un premier échange ou un entretien directement depuis Jeuncy, sans lien externe à gérer.',
  },
  {
    icon: Gauge,
    title: 'Un tableau de bord qui va droit au but',
    description:
      'Offres, candidatures, profil entreprise : tout au même endroit, pensé pour être utilisé depuis un téléphone.',
  },
];

// Ce qu'il n'y a PAS. Dire ce qu'on ne fait pas est plus convaincant que
// repeter « gratuit » : c'est la liste des pieges que le lecteur redoute.
const NOT_INCLUDED = [
  'Pas de carte bancaire demandée',
  "Pas de période d'essai qui expire",
  "Pas de limite au nombre d'offres",
  'Pas de candidatures cachées derrière un abonnement',
  "Pas d'engagement, pas de frais cachés",
];

const HOW_IT_WORKS = [
  {
    step: '1',
    title: 'Créez votre compte entreprise',
    description: 'Un email, un mot de passe — ou votre compte Google. Deux minutes.',
  },
  {
    step: '2',
    title: 'Publiez votre offre',
    description:
      'Titre, missions, ville, type de contrat. Elle est en ligne immédiatement et visible de tous les candidats.',
  },
  {
    step: '3',
    title: 'Recevez et gérez vos candidatures',
    description:
      "Ou allez chercher vous-même les profils dans la CVthèque. Dans les deux cas, c'est inclus.",
  },
];

const FAQ = [
  {
    question: 'Pourquoi est-ce gratuit ?',
    answer:
      "Parce que nous construisons Jeuncy avec vous. Une plateforme de recrutement ne vaut que par le nombre d'entreprises et de candidats qui s'y rencontrent : notre priorité aujourd'hui, c'est que vous y trouviez les bons profils, pas de vous facturer.",
  },
  {
    question: 'Est-ce que ça va rester gratuit ?',
    answer:
      "Jeuncy est gratuit pour les entreprises, sans date de fin annoncée. Si un jour une offre payante voyait le jour, elle s'ajouterait à ce qui existe : vos offres publiées et vos candidatures ne seraient jamais reprises.",
  },
  {
    question: 'Et pour les candidats ?',
    answer:
      'Gratuit aussi, et ça ne changera pas. Créer un profil, générer un CV, postuler : rien ne sera jamais facturé à un jeune qui cherche du travail.',
  },
  {
    question: 'Je suis un CFA ou une école, puis-je publier ?',
    answer:
      "L'espace CFA n'est pas ouvert aux inscriptions pour le moment : Jeuncy travaille avec des écoles partenaires sélectionnées. Écrivez-nous depuis la page Contact pour en discuter.",
  },
];

export function FreePlatform() {
  usePageMetadata(
    'Gratuit pour les entreprises',
    "Publiez vos offres d'alternance, recevez les candidatures et accédez à la CVthèque : Jeuncy est entièrement gratuit pour les entreprises. Sans carte bancaire, sans limite.",
    '/tarifs',
  );
  const user = useAuthStore((state) => state.user);
  const isOrganization = user?.role === UserRole.COMPANY || user?.role === UserRole.CFA;
  const ctaHref = isOrganization ? '/mes-offres' : '/register?role=COMPANY';
  const ctaLabel = isOrganization ? 'Publier une offre' : 'Créer mon compte entreprise';

  return (
    <main>
      {/* Le « 0 € » est l'element le plus grand de la page : c'est la
          reponse a la seule question que le visiteur se pose. */}
      <section className="relative overflow-hidden">
        <div className="mx-auto max-w-5xl px-4 pb-14 pt-20 text-center">
          <Badge
            variant="secondary"
            className="animate-in fade-in slide-in-from-bottom-2 mb-5 duration-500"
          >
            Entreprises
          </Badge>
          <h1 className="animate-in fade-in slide-in-from-bottom-3 font-poppins text-4xl font-bold tracking-tight text-foreground duration-700 md:text-6xl">
            Recrutez en alternance.{' '}
            <span className="bg-jeuncy-gradient bg-clip-text text-transparent">
              Gratuitement. Vraiment.
            </span>
          </h1>
          <p className="animate-in fade-in slide-in-from-bottom-3 mx-auto mt-5 max-w-2xl font-inter text-lg text-muted-foreground duration-700 [animation-delay:100ms] [animation-fill-mode:backwards]">
            Publiez vos offres, recevez les candidatures, cherchez dans la CVthèque. Tout
            Jeuncy, pour toutes les entreprises, sans rien payer.
          </p>

          <div className="animate-in fade-in zoom-in-95 mx-auto mt-10 max-w-md duration-700 [animation-delay:200ms] [animation-fill-mode:backwards]">
            <div className="relative overflow-hidden rounded-2xl border-2 border-jeuncy-coral/40 bg-card p-8 shadow-xl">
              <div className="absolute inset-x-0 top-0 h-1.5 bg-jeuncy-gradient" />
              <p className="font-inter text-sm font-medium uppercase tracking-wider text-muted-foreground">
                Espace entreprise
              </p>
              <p className="mt-2 font-poppins text-7xl font-bold leading-none text-foreground md:text-8xl">
                0<span className="text-5xl md:text-6xl"> €</span>
              </p>
              <p className="mt-3 font-inter text-base text-muted-foreground">
                Offres illimitées · Candidatures · CVthèque
              </p>
              <Link to={ctaHref} className="mt-6 block">
                <Button
                  variant="gradient"
                  size="lg"
                  className="w-full transition-transform duration-200 hover:scale-[1.03]"
                >
                  {ctaLabel}
                </Button>
              </Link>
              <p className="mt-3 font-inter text-xs text-muted-foreground">
                Aucune carte bancaire demandée. Jamais.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-16">
        <div className="mx-auto max-w-2xl text-center">
          <Badge variant="outline" className="mb-4">
            Inclus
          </Badge>
          <h2 className="font-poppins text-3xl font-bold text-foreground">
            Tout ce qu'il faut pour recruter, sans exception
          </h2>
          <p className="mt-3 font-inter text-muted-foreground">
            Pas de version « limitée » : ce que voit une entreprise sur Jeuncy, c'est tout
            Jeuncy.
          </p>
        </div>
        <div className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {INCLUDED.map((item, index) => (
            <Card
              key={item.title}
              className="animate-in fade-in slide-in-from-bottom-3 transition-all duration-300 [animation-fill-mode:backwards] hover:-translate-y-1 hover:shadow-lg"
              style={{ animationDelay: `${index * 80}ms` }}
            >
              <CardContent className="flex flex-col items-start gap-3 p-6">
                <div className="flex h-11 w-11 items-center justify-center rounded-full bg-jeuncy-gradient text-white">
                  <item.icon className="h-5 w-5" aria-hidden="true" />
                </div>
                <p className="font-poppins font-semibold text-foreground">{item.title}</p>
                <p className="font-inter text-sm text-muted-foreground">
                  {item.description}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      </section>

      <section className="bg-muted/30 px-4 py-16">
        <div className="mx-auto grid max-w-5xl gap-10 md:grid-cols-2 md:items-center">
          <div>
            <Badge variant="outline" className="mb-4">
              Sans surprise
            </Badge>
            <h2 className="font-poppins text-3xl font-bold text-foreground">
              Ce que « gratuit » veut dire chez nous
            </h2>
            <p className="mt-3 font-inter text-muted-foreground">
              Beaucoup de plateformes sont « gratuites » jusqu'au moment où vous voulez
              lire une candidature. Pas ici.
            </p>
          </div>
          <ul className="flex flex-col gap-3">
            {NOT_INCLUDED.map((item) => (
              <li
                key={item}
                className="flex items-center gap-3 rounded-lg border border-border bg-card px-4 py-3 font-inter text-sm text-foreground"
              >
                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-jeuncy-coral/10">
                  <X className="h-4 w-4 text-jeuncy-coral" aria-hidden="true" />
                </span>
                {item}
              </li>
            ))}
          </ul>
        </div>
      </section>

      <section className="mx-auto max-w-5xl px-4 py-16">
        <div className="mx-auto max-w-2xl text-center">
          <Badge variant="outline" className="mb-4">
            Comment ça marche
          </Badge>
          <h2 className="font-poppins text-3xl font-bold text-foreground">
            En ligne en moins de dix minutes
          </h2>
        </div>
        <ol className="mt-10 grid gap-6 md:grid-cols-3">
          {HOW_IT_WORKS.map((item) => (
            <li
              key={item.step}
              className="relative flex flex-col gap-2 rounded-lg border border-border bg-card p-6"
            >
              <span className="font-poppins text-4xl font-bold text-jeuncy-orange">
                {item.step}
              </span>
              <p className="font-poppins font-semibold text-foreground">{item.title}</p>
              <p className="font-inter text-sm text-muted-foreground">
                {item.description}
              </p>
            </li>
          ))}
        </ol>
        <div className="mt-10 text-center">
          <Link to={ctaHref}>
            <Button variant="gradient" size="lg">
              {ctaLabel}
            </Button>
          </Link>
        </div>
      </section>

      <section className="bg-muted/30 px-4 py-16">
        <div className="mx-auto max-w-5xl">
          <div className="mx-auto max-w-2xl text-center">
            <Badge variant="outline" className="mb-4">
              Pourquoi Jeuncy
            </Badge>
            <h2 className="font-poppins text-3xl font-bold text-foreground">
              Gratuit, et surtout efficace
            </h2>
          </div>
          <div className="mt-10 grid gap-6 sm:grid-cols-2">
            <div className="flex flex-col items-start gap-3 rounded-lg border border-border bg-card p-6">
              <Users className="h-6 w-6 text-jeuncy-orange" aria-hidden="true" />
              <p className="font-poppins font-semibold text-foreground">
                Une audience qui cherche exactement ça
              </p>
              <p className="font-inter text-sm text-muted-foreground">
                Les candidats Jeuncy viennent pour une alternance, un job saisonnier ou
                étudiant, un stage ou une mission bénévole. Pas pour un CDI de cadre : vos
                offres tombent sur les bonnes personnes.
              </p>
            </div>
            <div className="flex flex-col items-start gap-3 rounded-lg border border-border bg-card p-6">
              <ShieldCheck className="h-6 w-6 text-jeuncy-orange" aria-hidden="true" />
              <p className="font-poppins font-semibold text-foreground">
                Des profils lisibles, avec l'essentiel
              </p>
              <p className="font-inter text-sm text-muted-foreground">
                Âge, ville, permis, langues, compétences, CV généré au format Jeuncy :
                vous savez en dix secondes si un profil vaut un appel — et le coût d'un
                alternant se lit dès son âge.
              </p>
            </div>
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-3xl px-4 py-16">
        <div className="text-center">
          <Badge variant="outline" className="mb-4">
            Questions fréquentes
          </Badge>
          <h2 className="font-poppins text-3xl font-bold text-foreground">
            Les questions qu'on nous pose
          </h2>
        </div>
        <dl className="mt-10 flex flex-col gap-6">
          {FAQ.map((item) => (
            <div
              key={item.question}
              className="rounded-lg border border-border bg-card p-6"
            >
              <dt className="flex items-start gap-2 font-poppins font-semibold text-foreground">
                <Check
                  className="mt-1 h-4 w-4 shrink-0 text-jeuncy-orange"
                  aria-hidden="true"
                />
                {item.question}
              </dt>
              <dd className="mt-2 font-inter text-sm text-muted-foreground">
                {item.answer}
              </dd>
            </div>
          ))}
        </dl>
      </section>

      <section className="px-4 pb-20 pt-4">
        <div className="mx-auto max-w-4xl overflow-hidden rounded-2xl bg-jeuncy-navy px-6 py-12 text-center text-white">
          <Sparkles className="mx-auto h-8 w-8 text-jeuncy-orange" aria-hidden="true" />
          <h2 className="mt-4 font-poppins text-3xl font-bold">
            Votre prochaine recrue est peut-être déjà inscrite.
          </h2>
          <p className="mx-auto mt-3 max-w-xl font-inter text-white/80">
            Publiez votre première offre aujourd'hui. C'est gratuit, et ça le reste.
          </p>
          <Link to={ctaHref} className="mt-6 inline-block">
            <Button variant="gradient" size="lg">
              {ctaLabel}
            </Button>
          </Link>
        </div>
      </section>
    </main>
  );
}
