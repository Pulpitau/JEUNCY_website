import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Video, Briefcase, Linkedin } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
  CandidateProfile,
  CandidateProfileInput,
} from '@/lib/api/candidate-profile';

const profileSchema = z.object({
  first_name: z.string().min(1, 'Le prénom est requis.'),
  last_name: z.string().min(1, 'Le nom est requis.'),
  headline: z.string().optional().or(z.literal('')),
  phone: z
    .string()
    .regex(/^[0-9 .+-]*$/, 'Le téléphone ne doit contenir que des chiffres.')
    .optional()
    .or(z.literal('')),
  // Obligatoire : l'age est un critere de selection pour les entreprises
  // (le cout d'un alternant en depend). Le serveur impose 15 ans minimum.
  birth_date: z.string().min(1, 'Indique ta date de naissance.'),
  address: z.string().optional().or(z.literal('')),
  city: z.string().optional().or(z.literal('')),
  postal_code: z
    .string()
    .regex(/^[0-9]*$/, 'Le code postal ne doit contenir que des chiffres.')
    .optional()
    .or(z.literal('')),
  bio: z.string().optional().or(z.literal('')),
  hobbies: z.string().optional().or(z.literal('')),
  driving_license: z.string().optional().or(z.literal('')),
  video_url: z.string().url('URL invalide.').optional().or(z.literal('')),
  portfolio_url: z.string().url('URL invalide.').optional().or(z.literal('')),
  linkedin_url: z.string().url('URL invalide.').optional().or(z.literal('')),
});

type ProfileFormValues = z.infer<typeof profileSchema>;

function valuesFromProfile(profile: CandidateProfile | null): ProfileFormValues {
  return {
    first_name: profile?.first_name ?? '',
    last_name: profile?.last_name ?? '',
    headline: profile?.headline ?? '',
    phone: profile?.phone ?? '',
    birth_date: profile?.birth_date?.slice(0, 10) ?? '',
    address: profile?.address ?? '',
    city: profile?.city ?? '',
    postal_code: profile?.postal_code ?? '',
    bio: profile?.bio ?? '',
    hobbies: profile?.hobbies ?? '',
    driving_license: profile?.driving_license ?? '',
    video_url: profile?.video_url ?? '',
    portfolio_url: profile?.portfolio_url ?? '',
    linkedin_url: profile?.linkedin_url ?? '',
  };
}

// Le brouillon est recopie champ par champ, et seulement sur les cles que le
// formulaire connait aujourd'hui : un brouillon ecrit par une version
// precedente du site ne doit pas injecter de champ fantome ni faire echouer la
// validation sur une valeur qui n'existe plus.
function mergeDraft(
  profile: CandidateProfile | null,
  draft: Record<string, string> | null | undefined,
): ProfileFormValues {
  const values = valuesFromProfile(profile);
  if (!draft) {
    return values;
  }

  for (const key of Object.keys(values) as (keyof ProfileFormValues)[]) {
    const saved = draft[key];
    if (typeof saved === 'string') {
      values[key] = saved;
    }
  }

  return values;
}

interface ProfileInfoFormProps {
  profile: CandidateProfile | null;
  onSubmit: (values: CandidateProfileInput) => Promise<unknown>;
  onCancel?: () => void;
  isSubmitting: boolean;
  // Saisie non enregistree retrouvee au retour sur la page (voir
  // lib/profile-draft.ts). Absente = comportement d'avant, le formulaire part
  // du profil enregistre.
  draft?: Record<string, string> | null;
  onDraftChange?: (values: Record<string, string>) => void;
  onDiscardDraft?: () => void;
}

export function ProfileInfoForm({
  profile,
  onSubmit,
  onCancel,
  isSubmitting,
  draft,
  onDraftChange,
  onDiscardDraft,
}: ProfileInfoFormProps) {
  const {
    register,
    handleSubmit,
    watch,
    reset,
    formState: { errors },
  } = useForm<ProfileFormValues>({
    resolver: zodResolver(profileSchema),
    defaultValues: mergeDraft(profile, draft),
  });

  // Fige l'etat d'ouverture : le bandeau annonce ce qui vient d'etre restaure,
  // il ne doit pas disparaitre des la premiere frappe ni reapparaitre ensuite.
  const [showRestored, setShowRestored] = useState(() =>
    Object.values(draft ?? {}).some(
      (value) => typeof value === 'string' && value.trim() !== '',
    ),
  );

  // Chaque frappe est ecrite tout de suite, sans temporisation : le geste qui
  // fait perdre la saisie (cliquer sur un lien, revenir en arriere) peut
  // arriver juste apres la derniere lettre, et un debounce la perdrait —
  // c'est exactement le bug qu'on corrige. L'ecriture est synchrone et porte
  // sur quelques kilo-octets.
  useEffect(() => {
    if (!onDraftChange) {
      return;
    }

    const subscription = watch((values) => {
      const info: Record<string, string> = {};
      for (const [key, value] of Object.entries(values)) {
        if (typeof value === 'string') {
          info[key] = value;
        }
      }

      onDraftChange(info);
    });

    return () => subscription.unsubscribe();
  }, [watch, onDraftChange]);

  function handleDiscardDraft() {
    // reset() notifie les abonnes de watch, donc reecrit un brouillon : on
    // efface APRES, sinon l'effacement serait aussitot annule.
    reset(valuesFromProfile(profile));
    onDiscardDraft?.();
    setShowRestored(false);
  }

  async function handleFormSubmit(values: ProfileFormValues) {
    await onSubmit({
      first_name: values.first_name,
      last_name: values.last_name,
      headline: values.headline || null,
      phone: values.phone || null,
      birth_date: values.birth_date || null,
      address: values.address || null,
      city: values.city || null,
      postal_code: values.postal_code || null,
      bio: values.bio || null,
      hobbies: values.hobbies || null,
      driving_license: values.driving_license || null,
      video_url: values.video_url || null,
      portfolio_url: values.portfolio_url || null,
      linkedin_url: values.linkedin_url || null,
    });
  }

  return (
    <form
      onSubmit={handleSubmit(handleFormSubmit)}
      noValidate
      className="flex flex-col gap-4"
    >
      {showRestored && (
        <div
          role="status"
          className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border bg-muted px-3 py-2"
        >
          <p className="font-inter text-sm text-muted-foreground">
            On a retrouvé ce que tu avais commencé à saisir sans l'enregistrer.
          </p>
          <Button type="button" variant="ghost" size="sm" onClick={handleDiscardDraft}>
            {profile ? 'Revenir aux infos enregistrées' : 'Vider le formulaire'}
          </Button>
        </div>
      )}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div className="flex flex-col gap-2">
          <Label htmlFor="first_name">Prénom</Label>
          <Input
            id="first_name"
            aria-invalid={!!errors.first_name}
            {...register('first_name')}
          />
          {errors.first_name && (
            <p role="alert" className="text-sm text-destructive">
              {errors.first_name.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="last_name">Nom</Label>
          <Input
            id="last_name"
            aria-invalid={!!errors.last_name}
            {...register('last_name')}
          />
          {errors.last_name && (
            <p role="alert" className="text-sm text-destructive">
              {errors.last_name.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2 sm:col-span-2">
          {/* "Titre professionnel" perturbait les candidats (retour terrain
              du 2026-09-02) : un jeune sans experience ne sait pas quel titre
              se donner et laissait le champ vide. Formule cote recherche, pas
              cote statut — la colonne en base reste headline. */}
          <Label htmlFor="headline">Ce que tu recherches</Label>
          <Input
            id="headline"
            placeholder="Ex : Alternance en communication digitale"
            {...register('headline')}
          />
          <p className="font-inter text-xs text-muted-foreground">
            C'est la première chose que voient les recruteurs. Un poste, un secteur, ou
            simplement le type de contrat que tu cherches.
          </p>
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="phone">Téléphone</Label>
          <Input
            id="phone"
            autoComplete="tel"
            inputMode="tel"
            aria-invalid={!!errors.phone}
            {...register('phone')}
          />
          {errors.phone && (
            <p role="alert" className="text-sm text-destructive">
              {errors.phone.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="birth_date">Date de naissance *</Label>
          <Input
            id="birth_date"
            type="date"
            autoComplete="bday"
            required
            aria-describedby="birth_date_help"
            {...register('birth_date')}
          />
          <p id="birth_date_help" className="font-inter text-xs text-muted-foreground">
            Seul ton âge est visible des recruteurs, jamais la date. Ils en ont besoin :
            la rémunération d'un alternant dépend de son âge.
          </p>
        </div>
        <div className="flex flex-col gap-2 sm:col-span-2">
          {/* « Adresse postale » et non « Adresse » : en francais, « ton
              adresse » designe couramment un email. Un premier candidat a
              effectivement saisi son adresse email ici. Le placeholder leve
              le doute meme pour qui ne lit pas le libelle en entier. */}
          <Label htmlFor="address">Adresse postale</Label>
          <Input
            id="address"
            placeholder="Ex : 12 rue des Écoles"
            autoComplete="street-address"
            {...register('address')}
          />
          <p className="font-inter text-xs text-muted-foreground">
            Facultatif. Ton email est celui de ton compte, tu n'as pas à le saisir ici.
          </p>
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="city">Ville</Label>
          <Input id="city" autoComplete="address-level2" {...register('city')} />
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="postal_code">Code postal</Label>
          <Input
            id="postal_code"
            autoComplete="postal-code"
            inputMode="numeric"
            aria-invalid={!!errors.postal_code}
            {...register('postal_code')}
          />
          {errors.postal_code && (
            <p role="alert" className="text-sm text-destructive">
              {errors.postal_code.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="driving_license">Permis de conduire</Label>
          <Input
            id="driving_license"
            placeholder="Ex : B"
            {...register('driving_license')}
          />
        </div>
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="bio">Bio</Label>
        <Textarea id="bio" rows={4} {...register('bio')} />
      </div>

      <div className="flex flex-col gap-2">
        <Label htmlFor="hobbies">Loisirs</Label>
        <Textarea
          id="hobbies"
          rows={2}
          placeholder="Ex : Photographie, football, lecture"
          {...register('hobbies')}
        />
      </div>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div className="flex flex-col gap-2">
          <Label htmlFor="video_url" className="flex items-center gap-1.5">
            <Video className="h-3.5 w-3.5" aria-hidden="true" />
            Vidéo de présentation
          </Label>
          <Input
            id="video_url"
            placeholder="Lien YouTube, Vimeo…"
            aria-invalid={!!errors.video_url}
            {...register('video_url')}
          />
          {errors.video_url && (
            <p role="alert" className="text-sm text-destructive">
              {errors.video_url.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="portfolio_url" className="flex items-center gap-1.5">
            <Briefcase className="h-3.5 w-3.5" aria-hidden="true" />
            Portfolio
          </Label>
          <Input
            id="portfolio_url"
            placeholder="https://…"
            aria-invalid={!!errors.portfolio_url}
            {...register('portfolio_url')}
          />
          {errors.portfolio_url && (
            <p role="alert" className="text-sm text-destructive">
              {errors.portfolio_url.message}
            </p>
          )}
        </div>
        <div className="flex flex-col gap-2">
          <Label htmlFor="linkedin_url" className="flex items-center gap-1.5">
            <Linkedin className="h-3.5 w-3.5" aria-hidden="true" />
            LinkedIn
          </Label>
          <Input
            id="linkedin_url"
            placeholder="https://linkedin.com/in/…"
            aria-invalid={!!errors.linkedin_url}
            {...register('linkedin_url')}
          />
          {errors.linkedin_url && (
            <p role="alert" className="text-sm text-destructive">
              {errors.linkedin_url.message}
            </p>
          )}
        </div>
      </div>

      <div className="flex gap-2">
        <Button
          type="submit"
          variant="gradient"
          disabled={isSubmitting}
          className="self-start"
        >
          {isSubmitting
            ? 'Enregistrement…'
            : profile
              ? 'Mettre à jour'
              : 'Créer mon profil'}
        </Button>
        {onCancel && (
          <Button type="button" variant="outline" onClick={onCancel}>
            Annuler
          </Button>
        )}
      </div>
    </form>
  );
}
