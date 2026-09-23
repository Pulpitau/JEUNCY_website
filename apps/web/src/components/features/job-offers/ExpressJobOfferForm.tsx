import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { ContractType, OfferSector } from '@jeuncy/shared';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import {
  OFFER_SECTOR_LABELS,
  RECRUITMENT_RADIUS_OPTIONS,
} from '@/lib/offer-sector-labels';
import type { ExpressJobOfferInput } from '@/lib/api/job-offers';

// L'offre express (MOBILE.md §4.1) : cinq champs, une minute, publiée dans
// la foulée.
//
// POURQUOI ELLE EXISTE À CÔTÉ DU FORMULAIRE COMPLET. Le formulaire long est
// le mur contre lequel bute un employeur venu essayer : intitulé,
// description, rémunération, niveau, avantages, compétences. Rien de tout
// cela n'est nécessaire pour proposer des candidats — il faut un poste, un
// contrat, un lieu et un secteur. Le reste se complète plus tard, quand
// l'employeur a vu que ça valait le coup.
//
// Le serveur écrit une description de départ et publie tout de suite : il
// n'y a donc aucune action à faire après, et c'est le point.

const schema = z.object({
  title: z
    .string()
    .trim()
    .min(1, "L'intitulé est requis.")
    .max(255, '255 caractères maximum.'),
  contract_type: z.enum([
    ContractType.ALTERNANCE,
    ContractType.SAISONNIER,
    ContractType.BENEVOLAT,
    ContractType.JOB_ETUDIANT,
    ContractType.STAGE,
  ]),
  city: z
    .string()
    .trim()
    .min(1, 'Indique la commune du poste.')
    .max(255, '255 caractères maximum.'),
  postal_code: z
    .string()
    .trim()
    .regex(/^\d{5}$/, 'Un code postal à 5 chiffres.'),
  sector: z.nativeEnum(OfferSector),
  recruitment_radius_km: z.string(),
});

type ExpressFormValues = z.infer<typeof schema>;

const CONTRACT_TYPE_LABELS: Record<string, string> = {
  [ContractType.ALTERNANCE]: 'Alternance',
  [ContractType.SAISONNIER]: 'Saisonnier',
  [ContractType.BENEVOLAT]: 'Bénévolat',
  [ContractType.JOB_ETUDIANT]: 'Job étudiant',
  [ContractType.STAGE]: 'Stage',
};

const selectClassName = cn(
  'flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm font-inter focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
);

export interface ExpressJobOfferFormProps {
  variant: 'COMPANY' | 'CFA';
  onSubmit: (values: ExpressJobOfferInput) => Promise<unknown>;
  onCancel: () => void;
  isSubmitting: boolean;
  submitError?: string | null;
}

export function ExpressJobOfferForm({
  variant,
  onSubmit,
  onCancel,
  isSubmitting,
  submitError,
}: ExpressJobOfferFormProps) {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<ExpressFormValues>({
    resolver: zodResolver(schema),
    defaultValues: {
      title: '',
      contract_type: ContractType.ALTERNANCE,
      city: '',
      postal_code: '',
      sector: OfferSector.AUTRE,
      // 30 km comme la colonne en base, qui a un défaut et refuse NULL.
      recruitment_radius_km: '30',
    },
  });

  return (
    <form
      onSubmit={handleSubmit((values) =>
        onSubmit({
          title: values.title,
          contract_type: values.contract_type,
          city: values.city,
          postal_code: values.postal_code,
          sector: values.sector,
          recruitment_radius_km: Number(values.recruitment_radius_km) || 30,
        }),
      )}
      noValidate
      className="flex flex-col gap-4"
    >
      {submitError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          {submitError}
        </p>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-2 sm:col-span-2">
          <Label htmlFor="express-title">
            {variant === 'CFA' ? 'Intitulé de la formation' : 'Intitulé du poste'}
          </Label>
          <Input
            id="express-title"
            placeholder="Vendeur conseil en alternance"
            aria-invalid={!!errors.title}
            {...register('title')}
          />
          {errors.title && (
            <p role="alert" className="text-sm text-destructive">
              {errors.title.message}
            </p>
          )}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="express-contract">Type de contrat</Label>
          <select
            id="express-contract"
            className={selectClassName}
            {...register('contract_type')}
          >
            {Object.entries(CONTRACT_TYPE_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="express-sector">Secteur</Label>
          <select id="express-sector" className={selectClassName} {...register('sector')}>
            {Object.entries(OFFER_SECTOR_LABELS).map(([value, label]) => (
              <option key={value} value={value}>
                {label}
              </option>
            ))}
          </select>
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="express-city">Commune du poste</Label>
          <Input
            id="express-city"
            placeholder="Perpignan"
            aria-invalid={!!errors.city}
            {...register('city')}
          />
          {errors.city && (
            <p role="alert" className="text-sm text-destructive">
              {errors.city.message}
            </p>
          )}
        </div>

        <div className="flex flex-col gap-2">
          <Label htmlFor="express-postal-code">Code postal</Label>
          <Input
            id="express-postal-code"
            inputMode="numeric"
            maxLength={5}
            placeholder="66000"
            aria-invalid={!!errors.postal_code}
            {...register('postal_code')}
          />
          {errors.postal_code && (
            <p role="alert" className="text-sm text-destructive">
              {errors.postal_code.message}
            </p>
          )}
        </div>

        <div className="flex flex-col gap-2 sm:col-span-2">
          <Label htmlFor="express-radius">Rayon de recrutement</Label>
          <select
            id="express-radius"
            className={selectClassName}
            aria-describedby="express-radius-aide"
            {...register('recruitment_radius_km')}
          >
            {RECRUITMENT_RADIUS_OPTIONS.map((km) => (
              <option key={km} value={km}>
                {km} km
              </option>
            ))}
          </select>
          <p
            id="express-radius-aide"
            className="font-inter text-xs text-muted-foreground"
          >
            Jusqu&apos;où tu acceptes qu&apos;un candidat vienne.
          </p>
        </div>
      </div>

      <p className="rounded-md border border-border bg-muted/40 p-3 font-inter text-sm text-muted-foreground">
        L&apos;offre est publiée aussitôt et visible des candidats. Tu compléteras la
        description, la rémunération et les missions quand tu veux, depuis cette page.
      </p>

      <div className="flex flex-wrap gap-2">
        <Button type="submit" variant="gradient" disabled={isSubmitting}>
          {isSubmitting ? 'Création…' : 'Créer et publier'}
        </Button>
        <Button type="button" variant="ghost" onClick={onCancel}>
          Annuler
        </Button>
      </div>
    </form>
  );
}
