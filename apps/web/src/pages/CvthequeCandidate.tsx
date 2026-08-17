import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  ArrowLeft,
  Mail,
  Phone,
  MapPin,
  Car,
  Briefcase,
  GraduationCap,
  Linkedin,
  Video,
  Globe,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { getCvthequeCandidate } from '@/lib/api/cvtheque';
import { ApiError } from '@/lib/api/client';

function formatPeriod(start: string | null, end: string | null): string {
  const fmt = (d: string) =>
    new Date(d).toLocaleDateString('fr-FR', { month: 'short', year: 'numeric' });
  if (!start) return '';
  return `${fmt(start)} — ${end ? fmt(end) : "aujourd'hui"}`;
}

export function CvthequeCandidate() {
  const { id } = useParams<{ id: string }>();
  const candidateId = Number(id);

  const query = useQuery({
    queryKey: ['cvtheque-candidate', candidateId],
    queryFn: () => getCvthequeCandidate(candidateId),
    enabled: Number.isFinite(candidateId),
    retry: (failureCount, error) =>
      !(error instanceof ApiError && [402, 404].includes(error.status)) &&
      failureCount < 3,
  });

  if (query.isLoading) {
    return (
      <main className="mx-auto max-w-4xl px-4 py-10">
        <p className="font-inter text-muted-foreground">Chargement du profil…</p>
      </main>
    );
  }

  if (query.isError || !query.data) {
    const status = query.error instanceof ApiError ? query.error.status : 0;
    return (
      <main className="mx-auto max-w-4xl px-4 py-10">
        <p role="alert" className="font-inter text-foreground">
          {status === 402
            ? "L'accès à la CVthèque est réservé aux abonnés."
            : "Ce profil n'est plus disponible. Le candidat a peut-être choisi de se retirer de la CVthèque."}
        </p>
        <Link to="/candidats" className="mt-4 inline-block">
          <Button variant="outline" size="sm">
            Retour à la CVthèque
          </Button>
        </Link>
      </main>
    );
  }

  const c = query.data;
  const initials = `${c.first_name.charAt(0)}${c.last_name.charAt(0)}`.toUpperCase();

  return (
    <main className="mx-auto max-w-4xl px-4 py-10">
      <Link
        to="/candidats"
        className="mb-6 inline-flex items-center gap-1.5 font-inter text-sm text-muted-foreground transition-colors hover:text-foreground"
      >
        <ArrowLeft className="h-4 w-4" aria-hidden="true" />
        Retour à la CVthèque
      </Link>

      <Card className="overflow-hidden">
        <div className="h-1.5 bg-jeuncy-gradient" />
        <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center">
          {c.photo_url ? (
            <img
              src={c.photo_url}
              alt=""
              className="h-20 w-20 shrink-0 rounded-full object-cover"
            />
          ) : (
            <span
              aria-hidden="true"
              className="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-jeuncy-gradient font-poppins text-xl font-bold text-white"
            >
              {initials}
            </span>
          )}
          <div className="min-w-0">
            <CardTitle className="text-2xl">
              {c.first_name} {c.last_name}
            </CardTitle>
            {c.headline && (
              <p className="font-inter text-muted-foreground">{c.headline}</p>
            )}
            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 font-inter text-sm text-muted-foreground">
              {c.city && (
                <span className="inline-flex items-center gap-1.5">
                  <MapPin className="h-4 w-4" aria-hidden="true" />
                  {c.city}
                </span>
              )}
              {c.driving_license && (
                <span className="inline-flex items-center gap-1.5">
                  <Car className="h-4 w-4" aria-hidden="true" />
                  {c.driving_license}
                </span>
              )}
            </div>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6">
          {/* Coordonnees : visibles uniquement ici, jamais dans la liste (voir
              CvthequeService::LIST_COLUMNS). */}
          <div className="flex flex-wrap gap-3 rounded-md border border-border bg-muted/40 p-4">
            <a
              href={`mailto:${c.user.email}`}
              className="inline-flex items-center gap-1.5 font-inter text-sm text-foreground hover:underline"
            >
              <Mail className="h-4 w-4 text-jeuncy-coral" aria-hidden="true" />
              {c.user.email}
            </a>
            {c.phone && (
              <a
                href={`tel:${c.phone}`}
                className="inline-flex items-center gap-1.5 font-inter text-sm text-foreground hover:underline"
              >
                <Phone className="h-4 w-4 text-jeuncy-coral" aria-hidden="true" />
                {c.phone}
              </a>
            )}
            {c.linkedin_url && (
              <a
                href={c.linkedin_url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 font-inter text-sm text-foreground hover:underline"
              >
                <Linkedin className="h-4 w-4 text-jeuncy-coral" aria-hidden="true" />
                LinkedIn
              </a>
            )}
            {c.portfolio_url && (
              <a
                href={c.portfolio_url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 font-inter text-sm text-foreground hover:underline"
              >
                <Globe className="h-4 w-4 text-jeuncy-coral" aria-hidden="true" />
                Portfolio
              </a>
            )}
            {c.video_url && (
              <a
                href={c.video_url}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-1.5 font-inter text-sm text-foreground hover:underline"
              >
                <Video className="h-4 w-4 text-jeuncy-coral" aria-hidden="true" />
                Vidéo de présentation
              </a>
            )}
          </div>

          {c.bio && (
            <section>
              <h2 className="mb-2 font-poppins font-semibold text-foreground">
                À propos
              </h2>
              <p className="whitespace-pre-line font-inter text-sm text-muted-foreground">
                {c.bio}
              </p>
            </section>
          )}

          {c.skills.length > 0 && (
            <section>
              <h2 className="mb-2 font-poppins font-semibold text-foreground">
                Compétences
              </h2>
              <div className="flex flex-wrap gap-1.5">
                {c.skills.map((s) => (
                  <Badge key={s.id} variant="secondary">
                    {s.name}
                  </Badge>
                ))}
              </div>
            </section>
          )}

          {c.software.length > 0 && (
            <section>
              <h2 className="mb-2 font-poppins font-semibold text-foreground">
                Logiciels
              </h2>
              <div className="flex flex-wrap gap-1.5">
                {c.software.map((s) => (
                  <Badge key={s.id} variant="outline">
                    {s.name}
                  </Badge>
                ))}
              </div>
            </section>
          )}

          {c.languages.length > 0 && (
            <section>
              <h2 className="mb-2 font-poppins font-semibold text-foreground">Langues</h2>
              <div className="flex flex-wrap gap-1.5">
                {c.languages.map((l) => (
                  <Badge key={l.id} variant="outline">
                    {l.name}
                    {l.level ? ` — ${l.level}` : ''}
                  </Badge>
                ))}
              </div>
            </section>
          )}

          {c.experiences.length > 0 && (
            <section>
              <h2 className="mb-3 flex items-center gap-2 font-poppins font-semibold text-foreground">
                <Briefcase className="h-4 w-4 text-jeuncy-orange" aria-hidden="true" />
                Expériences
              </h2>
              <ul className="flex flex-col gap-4">
                {c.experiences.map((exp) => (
                  <li key={exp.id} className="border-l-2 border-border pl-4">
                    <p className="font-poppins text-sm font-semibold text-foreground">
                      {exp.title}
                    </p>
                    <p className="font-inter text-sm text-muted-foreground">
                      {[exp.company, exp.location].filter(Boolean).join(' · ')}
                    </p>
                    <p className="font-inter text-xs text-muted-foreground">
                      {formatPeriod(exp.start_date, exp.end_date)}
                    </p>
                    {exp.description && (
                      <p className="mt-1 whitespace-pre-line font-inter text-sm text-muted-foreground">
                        {exp.description}
                      </p>
                    )}
                  </li>
                ))}
              </ul>
            </section>
          )}

          {c.educations.length > 0 && (
            <section>
              <h2 className="mb-3 flex items-center gap-2 font-poppins font-semibold text-foreground">
                <GraduationCap
                  className="h-4 w-4 text-jeuncy-orange"
                  aria-hidden="true"
                />
                Formations
              </h2>
              <ul className="flex flex-col gap-4">
                {c.educations.map((edu) => (
                  <li key={edu.id} className="border-l-2 border-border pl-4">
                    <p className="font-poppins text-sm font-semibold text-foreground">
                      {edu.degree}
                    </p>
                    <p className="font-inter text-sm text-muted-foreground">
                      {[edu.school, edu.field_of_study].filter(Boolean).join(' · ')}
                    </p>
                    <p className="font-inter text-xs text-muted-foreground">
                      {formatPeriod(edu.start_date, edu.end_date)}
                    </p>
                  </li>
                ))}
              </ul>
            </section>
          )}

          {c.hobbies && (
            <section>
              <h2 className="mb-2 font-poppins font-semibold text-foreground">Loisirs</h2>
              <p className="font-inter text-sm text-muted-foreground">{c.hobbies}</p>
            </section>
          )}
        </CardContent>
      </Card>
    </main>
  );
}
