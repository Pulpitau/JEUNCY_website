import { useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const BRAND_VALUES = [
  {
    title: 'Mission',
    description:
      'Créer des rencontres professionnelles utiles et accélérer le recrutement d’alternants.',
  },
  {
    title: 'Promesse',
    description:
      'Simplifier la recherche d’une entreprise et présenter aux employeurs des profils pertinents.',
  },
  {
    title: 'Personnalité',
    description:
      'Jeune sans être adolescente. Professionnelle sans être froide. Dynamique sans être agressive. Directe, optimiste et humaine.',
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
