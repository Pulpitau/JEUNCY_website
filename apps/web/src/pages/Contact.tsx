import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Mail, Phone, MessageSquare, CheckCircle2, Clock } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { FreeForCandidatesBadge } from '@/components/FreeForCandidatesBadge';
import { getContactDetails, sendContactMessage } from '@/lib/api/contact';

// Objets proposes plutot qu'un champ libre : ils orientent le message vers ce
// que l'equipe sait traiter, et permettent de trier la boite de contact d'un
// coup d'oeil sur l'objet.
const SUBJECTS = [
  'Publier une offre',
  'Découvrir la CVthèque',
  'Demander une démonstration',
  'Connaître les tarifs',
  'Partenariat',
  'Autre demande',
] as const;

const contactSchema = z.object({
  name: z.string().min(2, 'Merci d’indiquer ton nom.').max(120),
  email: z.string().email('Adresse email invalide.').max(255),
  organization: z.string().max(160).optional(),
  subject: z.string().min(1, 'Choisis un objet.'),
  message: z
    .string()
    .min(10, 'Détaille un peu ta demande (10 caractères minimum).')
    .max(4000),
  website: z.string().max(0).optional(),
});

type ContactValues = z.infer<typeof contactSchema>;

export function Contact() {
  // Meme cle de cache que useContactEmail (utilise par les pages legales) :
  // une seule requete pour toute la session. Cette page a besoin du numero de
  // telephone en plus, d'ou l'appel direct plutot que le hook.
  const detailsQuery = useQuery({
    queryKey: ['contact-details'],
    queryFn: getContactDetails,
    staleTime: 5 * 60 * 1000,
  });
  const details = detailsQuery.data;

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<ContactValues>({
    resolver: zodResolver(contactSchema),
    defaultValues: { subject: SUBJECTS[0], website: '' },
  });

  const mutation = useMutation({
    mutationFn: sendContactMessage,
    onSuccess: () => reset({ subject: SUBJECTS[0], website: '' }),
  });

  return (
    <main>
      <section className="mx-auto max-w-3xl px-4 pb-10 pt-20 text-center">
        <Badge variant="secondary" className="mb-4">
          Contact
        </Badge>
        <h1 className="font-poppins text-4xl font-bold tracking-tight text-foreground md:text-5xl">
          Parlons de{' '}
          <span className="bg-jeuncy-gradient bg-clip-text text-transparent">
            votre recrutement.
          </span>
        </h1>
        <p className="mx-auto mt-4 max-w-2xl font-inter text-lg text-muted-foreground">
          Une question, un besoin précis, l'envie d'une démonstration ? Écrivez-nous, on
          vous répond avec une proposition adaptée à votre situation.
        </p>
      </section>

      <section className="mx-auto max-w-5xl px-4 pb-20">
        <div className="grid gap-8 md:grid-cols-[1fr_1.4fr]">
          {/* Coordonnees directes : certains preferent decrocher leur telephone
              ou ecrire depuis leur propre boite plutot que remplir un
              formulaire. Les deux chemins doivent exister. */}
          <div className="flex flex-col gap-4">
            <h2 className="font-poppins text-xl font-semibold text-foreground">
              Nous joindre directement
            </h2>

            {/* Pas d'adresse de repli en dur : tant que l'API n'a pas repondu,
                la carte s'affiche sans lien plutot qu'avec une adresse
                potentiellement perimee (l'adresse officielle a deja change une
                fois). href undefined = element non focusable, ce qui est le
                comportement voulu pendant le chargement. */}
            <a
              href={details?.email ? `mailto:${details.email}` : undefined}
              className="group flex min-h-[44px] items-center gap-3 rounded-lg border border-border bg-card p-4 transition-all hover:border-primary hover:shadow-md"
            >
              <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-jeuncy-gradient text-white">
                <Mail className="h-5 w-5" aria-hidden="true" />
              </span>
              <span className="min-w-0">
                <span className="block font-poppins text-sm font-medium text-foreground">
                  Par email
                </span>
                <span className="block truncate font-inter text-sm text-muted-foreground">
                  {details?.email ?? '…'}
                </span>
              </span>
            </a>

            {/* Bloc telephone affiche uniquement si un numero est configure
                (CONTACT_PHONE cote serveur) : mieux vaut pas de telephone
                qu'un numero factice sur une page de contact. */}
            {details?.phone && (
              <a
                href={`tel:${details.phone.replace(/\s/g, '')}`}
                className="group flex min-h-[44px] items-center gap-3 rounded-lg border border-border bg-card p-4 transition-all hover:border-primary hover:shadow-md"
              >
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-jeuncy-gradient text-white">
                  <Phone className="h-5 w-5" aria-hidden="true" />
                </span>
                <span className="min-w-0">
                  <span className="block font-poppins text-sm font-medium text-foreground">
                    Par téléphone
                  </span>
                  <span className="block font-inter text-sm text-muted-foreground">
                    {details.phone}
                  </span>
                </span>
              </a>
            )}

            <div className="flex items-start gap-2 rounded-lg border border-border bg-muted/40 p-4">
              <Clock
                className="mt-0.5 h-4 w-4 shrink-0 text-jeuncy-orange"
                aria-hidden="true"
              />
              <p className="font-inter text-sm text-muted-foreground">
                Nous répondons généralement sous 24 h ouvrées.
              </p>
            </div>

            <div className="rounded-lg border border-border bg-card p-4">
              <p className="font-poppins text-sm font-medium text-foreground">
                Vous êtes candidat ?
              </p>
              <div className="mt-2">
                <FreeForCandidatesBadge />
              </div>
              <p className="mt-2 font-inter text-sm text-muted-foreground">
                Créer un profil, générer son CV et postuler est entièrement gratuit. Pas
                besoin de nous contacter pour commencer.
              </p>
            </div>
          </div>

          <Card className="overflow-hidden">
            <div className="h-1 bg-jeuncy-gradient" />
            <CardContent className="p-6">
              <h2 className="flex items-center gap-2 font-poppins text-xl font-semibold text-foreground">
                <MessageSquare
                  className="h-5 w-5 text-jeuncy-orange"
                  aria-hidden="true"
                />
                Écrivez-nous
              </h2>

              {mutation.isSuccess ? (
                <div
                  role="status"
                  className="mt-6 flex flex-col items-center gap-3 rounded-lg border border-jeuncy-orange/40 bg-jeuncy-orange/10 p-6 text-center"
                >
                  <CheckCircle2
                    className="h-8 w-8 text-jeuncy-orange"
                    aria-hidden="true"
                  />
                  <p className="font-poppins font-semibold text-foreground">
                    Message envoyé, merci !
                  </p>
                  <p className="font-inter text-sm text-muted-foreground">
                    Nous revenons vers vous sous 24 h ouvrées.
                  </p>
                  <Button variant="outline" size="sm" onClick={() => mutation.reset()}>
                    Envoyer un autre message
                  </Button>
                </div>
              ) : (
                <form
                  onSubmit={handleSubmit((values) => mutation.mutateAsync(values))}
                  className="mt-6 flex flex-col gap-4"
                  noValidate
                >
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                      <label
                        htmlFor="contact-name"
                        className="font-inter text-sm font-medium"
                      >
                        Nom et prénom <span className="text-destructive">*</span>
                      </label>
                      <Input
                        id="contact-name"
                        {...register('name')}
                        autoComplete="name"
                      />
                      {errors.name && (
                        <p role="alert" className="font-inter text-sm text-destructive">
                          {errors.name.message}
                        </p>
                      )}
                    </div>

                    <div className="flex flex-col gap-1.5">
                      <label
                        htmlFor="contact-email"
                        className="font-inter text-sm font-medium"
                      >
                        Email <span className="text-destructive">*</span>
                      </label>
                      <Input
                        id="contact-email"
                        type="email"
                        {...register('email')}
                        autoComplete="email"
                      />
                      {errors.email && (
                        <p role="alert" className="font-inter text-sm text-destructive">
                          {errors.email.message}
                        </p>
                      )}
                    </div>
                  </div>

                  <div className="flex flex-col gap-1.5">
                    <label
                      htmlFor="contact-organization"
                      className="font-inter text-sm font-medium"
                    >
                      Entreprise ou CFA{' '}
                      <span className="text-muted-foreground">(facultatif)</span>
                    </label>
                    <Input
                      id="contact-organization"
                      {...register('organization')}
                      autoComplete="organization"
                    />
                  </div>

                  <div className="flex flex-col gap-1.5">
                    <label
                      htmlFor="contact-subject"
                      className="font-inter text-sm font-medium"
                    >
                      Objet de la demande <span className="text-destructive">*</span>
                    </label>
                    <select
                      id="contact-subject"
                      {...register('subject')}
                      className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 font-inter text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                      {SUBJECTS.map((subject) => (
                        <option key={subject} value={subject}>
                          {subject}
                        </option>
                      ))}
                    </select>
                    {errors.subject && (
                      <p role="alert" className="font-inter text-sm text-destructive">
                        {errors.subject.message}
                      </p>
                    )}
                  </div>

                  <div className="flex flex-col gap-1.5">
                    <label
                      htmlFor="contact-message"
                      className="font-inter text-sm font-medium"
                    >
                      Votre message <span className="text-destructive">*</span>
                    </label>
                    <Textarea id="contact-message" rows={6} {...register('message')} />
                    {errors.message && (
                      <p role="alert" className="font-inter text-sm text-destructive">
                        {errors.message.message}
                      </p>
                    )}
                  </div>

                  {/* Champ piege anti-robot : masque visuellement ET retire de
                      l'ordre de tabulation et des lecteurs d'ecran, pour qu'un
                      humain ne puisse pas le remplir par accident. */}
                  <div className="hidden" aria-hidden="true">
                    <label htmlFor="contact-website">Ne pas remplir</label>
                    <input id="contact-website" tabIndex={-1} {...register('website')} />
                  </div>

                  {mutation.isError && (
                    <p role="alert" className="font-inter text-sm text-destructive">
                      L'envoi a échoué. Réessayez dans un instant
                      {details?.email
                        ? `, ou écrivez-nous directement à ${details.email}`
                        : ''}
                      .
                    </p>
                  )}

                  <Button
                    type="submit"
                    variant="gradient"
                    size="lg"
                    disabled={isSubmitting || mutation.isPending}
                  >
                    {mutation.isPending ? 'Envoi…' : 'Envoyer le message'}
                  </Button>

                  <p className="font-inter text-xs text-muted-foreground">
                    Les informations transmises servent uniquement à traiter votre
                    demande.
                  </p>
                </form>
              )}
            </CardContent>
          </Card>
        </div>
      </section>
    </main>
  );
}
