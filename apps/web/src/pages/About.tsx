import { useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import {
  Target,
  Handshake,
  Sparkles,
  MapPin,
  UserPlus,
  FileText,
  Send,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const BRAND_VALUES = [
  {
    title: 'Mission',
    description:
      'Créer des rencontres professionnelles utiles et accélérer le recrutement d’alternants.',
    icon: Target,
  },
  {
    title: 'Promesse',
    description:
      'Simplifier la recherche d’une entreprise et présenter aux employeurs des profils pertinents.',
    icon: Handshake,
  },
  {
    title: 'Personnalité',
    description:
      'Jeune sans être adolescente. Professionnelle sans être froide. Dynamique sans être agressive. Directe, optimiste et humaine.',
    icon: Sparkles,
  },
];

const AGENCIES = [
  {
    city: 'Perpignan',
    address: '46 boulevard Clémenceau, 66000 Perpignan',
    region: 'Sud de la France',
  },
];

const CANDIDATE_STEPS = [
  {
    title: 'Crée ton profil',
    description:
      'Renseigne tes informations, tes expériences et tes formations en quelques minutes.',
    icon: UserPlus,
  },
  {
    title: 'Génère ton CV',
    description:
      'Jeuncy met en forme automatiquement un CV professionnel, prêt à être téléchargé.',
    icon: FileText,
  },
  {
    title: 'Postule en un clic',
    description:
      'Trouve les offres d’alternance, de saisonnier ou de bénévolat qui te correspondent et candidate directement.',
    icon: Send,
  },
];

const AUDIENCE_SECTIONS = [
  {
    id: 'candidats',
    eyebrow: 'Candidats',
    quote: 'On a un employeur pour toi.',
    description:
      'Crée ton profil, génère ton CV et postule aux offres d’alternance, de saisonnier ou de bénévolat qui te correspondent. Envoie ton CV, on s’occupe du reste.',
    ctaLabel: 'Voir les offres',
    ctaTo: '/offres',
  },
  {
    id: 'entreprises',
    eyebrow: 'Entreprises',
    quote: 'Trouvez votre prochain alternant.',
    description:
      'Publiez vos offres et gérez vos candidatures depuis un seul tableau de bord. Nous recrutons, vous choisissez : des profils sélectionnés pour vous.',
    ctaLabel: 'Créer un compte entreprise',
    ctaTo: '/register',
  },
  {
    id: 'cfa',
    eyebrow: 'CFA',
    quote: 'Le bon alternant. La bonne entreprise.',
    description:
      'Gérez vos offres multi-filières et suivez le placement de vos apprenants, en les connectant directement aux entreprises qui recrutent en alternance.',
    ctaLabel: 'Créer un compte CFA',
    ctaTo: '/register',
  },
] as const;

export function About() {
  const location = useLocation();

  useEffect(() => {
    if (!location.hash) return;
    const element = document.querySelector(location.hash);
    element?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }, [location.hash]);

  return (
    <main>
      <section className="mx-auto max-w-4xl px-4 py-20 text-center">
        <Badge variant="secondary" className="mb-4">
          Talents · Opportunités · Avenir
        </Badge>
        <h1 className="font-poppins text-4xl font-bold tracking-tight text-foreground md:text-5xl">
          L&apos;alternance, rendue plus simple.
        </h1>
        <p className="mx-auto mt-4 max-w-2xl font-inter text-lg text-muted-foreground">
          Jeuncy rapproche les jeunes talents et les entreprises. Nous nous positionnons
          comme une agence de recrutement nouvelle génération : visible, accessible,
          humaine et efficace.
        </p>
      </section>

      <section className="mx-auto max-w-6xl px-4 pb-20">
        <div className="grid gap-6 md:grid-cols-3">
          {BRAND_VALUES.map((value) => (
            <Card key={value.title}>
              <CardHeader>
                <value.icon className="h-6 w-6 text-primary" aria-hidden="true" />
                <CardTitle>{value.title}</CardTitle>
              </CardHeader>
              <CardContent>
                <p className="font-inter text-sm text-muted-foreground">
                  {value.description}
                </p>
              </CardContent>
            </Card>
          ))}
        </div>
      </section>

      <section className="bg-muted/30 px-4 py-16">
        <div className="mx-auto max-w-5xl">
          <div className="mx-auto max-w-2xl text-center">
            <Badge variant="outline" className="mb-4">
              Comment ça marche
            </Badge>
            <h2 className="font-poppins text-3xl font-bold text-foreground">
              Trois étapes pour trouver ton alternance
            </h2>
          </div>
          <div className="mt-10 grid gap-6 md:grid-cols-3">
            {CANDIDATE_STEPS.map((step, index) => (
              <div
                key={step.title}
                className="flex flex-col items-start gap-3 rounded-lg border border-border bg-card p-6"
              >
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-jeuncy-gradient text-white">
                  <step.icon className="h-5 w-5" aria-hidden="true" />
                </div>
                <p className="font-inter text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                  Étape {index + 1}
                </p>
                <p className="font-poppins font-semibold text-foreground">{step.title}</p>
                <p className="font-inter text-sm text-muted-foreground">
                  {step.description}
                </p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {AUDIENCE_SECTIONS.map((section, index) => (
        <section
          key={section.id}
          id={section.id}
          className={`scroll-mt-20 px-4 py-16 ${index % 2 === 1 ? 'bg-muted/30' : ''}`}
        >
          <div className="mx-auto flex max-w-3xl flex-col items-start gap-4 text-left">
            <Badge variant="outline">{section.eyebrow}</Badge>
            <h2 className="font-poppins text-3xl font-bold text-foreground">
              {section.quote}
            </h2>
            <p className="font-inter text-muted-foreground">{section.description}</p>
            <Link to={section.ctaTo}>
              <Button variant="gradient">{section.ctaLabel}</Button>
            </Link>
          </div>
        </section>
      ))}

      <section id="agences" className="scroll-mt-20 px-4 py-16">
        <div className="mx-auto max-w-5xl">
          <div className="mx-auto max-w-2xl text-center">
            <Badge variant="outline" className="mb-4">
              Nos agences
            </Badge>
            <h2 className="font-poppins text-3xl font-bold text-foreground">
              Là où on te retrouve
            </h2>
            <p className="mt-3 font-inter text-muted-foreground">
              Une équipe présente sur le terrain, proche des candidats et des entreprises.
            </p>
          </div>
          <div className="mt-10 flex flex-wrap justify-center gap-6">
            {AGENCIES.map((agency) => (
              <Card key={agency.city} className="w-full max-w-xs">
                <CardHeader>
                  <MapPin className="h-6 w-6 text-primary" aria-hidden="true" />
                  <CardTitle>{agency.city}</CardTitle>
                </CardHeader>
                <CardContent>
                  <p className="font-inter text-sm text-muted-foreground">
                    {agency.address}
                  </p>
                  <p className="mt-1 font-inter text-xs text-muted-foreground">
                    {agency.region}
                  </p>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-4xl px-4 py-20 text-center">
        <h2 className="font-poppins text-3xl font-bold text-foreground">
          Ton alternance commence ici.
        </h2>
        <p className="mx-auto mt-3 max-w-xl font-inter text-muted-foreground">
          On a peut-être déjà ton futur employeur.
        </p>
        <div className="mt-6 flex justify-center gap-3">
          <Link to="/offres">
            <Button variant="gradient">Chercher une offre</Button>
          </Link>
          <Link to="/register">
            <Button variant="outline">Créer un compte</Button>
          </Link>
        </div>
      </section>
    </main>
  );
}
