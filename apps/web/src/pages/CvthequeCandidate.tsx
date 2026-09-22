import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
  ArrowLeft,
  Car,
  Cake,
  Briefcase,
  GraduationCap,
  CalendarClock,
  Target,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { DownloadCvButton } from '@/components/features/cvtheque/DownloadCvButton';
import { getCvthequeCandidate } from '@/lib/api/cvtheque';
import { ApiError } from '@/lib/api/client';
import { ageBandLabel } from '@/lib/age-band-labels';
import { contractTypeLabel } from '@/lib/contract-type-labels';
import { offerSectorLabel } from '@/lib/offer-sector-labels';

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
      !(error instanceof ApiError && [402, 403, 404].includes(error.status)) &&
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
    const error = query.error instanceof ApiError ? query.error : null;
    return (
      <main className="mx-auto max-w-4xl px-4 py-10">
        <p role="alert" className="font-inter text-foreground">
          {error && [402, 403].includes(error.status)
            ? error.message
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
  // Prenom + initiale : le nom complet n'est pas transmis avant candidature.
  const displayName = c.last_name_initial
    ? `${c.first_name} ${c.last_name_initial}.`
    : c.first_name;
  const initials = `${c.first_name.charAt(0)}${c.last_name_initial}`.toUpperCase();
  const ageLabel = ageBandLabel(c.age_band);
  const wanted = [
    ...c.wanted_contract_types.map(contractTypeLabel),
    ...c.wanted_sectors.map(offerSectorLabel),
  ];

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
          {/* Portrait seulement si le candidat a autorisé son affichage : le
              serveur renvoie null sinon. */}
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
            <CardTitle className="text-2xl">{displayName}</CardTitle>
            {c.headline && (
              <p className="font-inter text-muted-foreground">{c.headline}</p>
            )}
            {/* Pas de ville : le lieu de résidence n'est pas un critère de
                recrutement (L1132-1), et il n'est pas montré avant que le
                candidat ait postulé. */}
            <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 font-inter text-sm text-muted-foreground">
              {ageLabel && (
                <span className="inline-flex items-center gap-1.5">
                  <Cake className="h-4 w-4" aria-hidden="true" />
                  {ageLabel}
                </span>
              )}
              {c.has_driving_license && (
                <span className="inline-flex items-center gap-1.5">
                  <Car className="h-4 w-4" aria-hidden="true" />
                  {c.driving_license_categories.length > 0
                    ? `Permis ${c.driving_license_categories.join(', ')}`
                    : 'Permis'}
                  {c.has_vehicle ? ' · véhicule' : ''}
                </span>
              )}
              {c.mobility && (
                <span className="inline-flex items-center gap-1.5">
                  <Target className="h-4 w-4" aria-hidden="true" />
                  {c.mobility.covers_offer
                    ? 'Sa zone de mobilité couvre votre offre'
                    : 'Hors de sa zone de mobilité'}
                </span>
              )}
            </div>
          </div>
        </CardHeader>

        <CardContent className="flex flex-col gap-6">
          {c.pitch && (
            <p className="rounded-md border border-border bg-muted/40 p-4 font-inter text-sm text-foreground">
              « {c.pitch} »
            </p>
          )}

          {/* Le CV part quand le candidat fait le geste de postuler, pas quand
              le recruteur décide de le prendre. Sans candidature, le serveur
              répond CV_NOT_SHARED : on n'affiche même pas le bouton. */}
          {c.cv_available ? (
            <DownloadCvButton
              candidateId={c.id}
              hasUploadedCv={c.has_uploaded_cv}
              fallbackFilename={`CV-${c.first_name}-${c.last_name_initial}.pdf`}
            />
          ) : (
            <p className="rounded-md border border-border bg-muted/30 p-4 font-inter text-sm text-muted-foreground">
              Le CV est partagé dès que le candidat postule à l'une de vos offres.
            </p>
          )}

          {(wanted.length > 0 || c.available_from) && (
            <section>
              <h2 className="mb-2 font-poppins font-semibold text-foreground">
                Ce qu'il cherche
              </h2>
              <div className="flex flex-wrap gap-1.5">
                {wanted.map((label) => (
                  <Badge key={label} variant="secondary">
                    {label}
                  </Badge>
                ))}
              </div>
              {c.available_from && (
                <p className="mt-2 inline-flex items-center gap-1.5 font-inter text-sm text-muted-foreground">
                  <CalendarClock className="h-4 w-4" aria-hidden="true" />
                  Disponible à partir du{' '}
                  {new Date(c.available_from).toLocaleDateString('fr-FR')}
                </p>
              )}
            </section>
          )}

          {c.skills.length > 0 && (
            <section>
              <h2 className="mb-2 font-poppins font-semibold text-foreground">
                Compétences
              </h2>
              <div className="flex flex-wrap gap-1.5">
                {c.skills.map((s) => (
                  <Badge key={s.id} variant={s.in_common ? 'default' : 'secondary'}>
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
                  <Badge key={l.name} variant="outline">
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
                {c.experiences.map((exp, index) => (
                  <li
                    key={`${exp.title}-${exp.start_date}-${index}`}
                    className="border-l-2 border-border pl-4"
                  >
                    <p className="font-poppins text-sm font-semibold text-foreground">
                      {exp.title}
                    </p>
                    {exp.company && (
                      <p className="font-inter text-sm text-muted-foreground">
                        {exp.company}
                      </p>
                    )}
                    <p className="font-inter text-xs text-muted-foreground">
                      {formatPeriod(exp.start_date, exp.end_date)}
                    </p>
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
                {c.educations.map((edu, index) => (
                  <li
                    key={`${edu.degree}-${edu.start_date}-${index}`}
                    className="border-l-2 border-border pl-4"
                  >
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
        </CardContent>
      </Card>
    </main>
  );
}
