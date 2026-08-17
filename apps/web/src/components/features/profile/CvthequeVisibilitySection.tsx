import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Eye, EyeOff } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { updateProfile } from '@/lib/api/candidate-profile';

interface CvthequeVisibilitySectionProps {
  isVisible: boolean;
  queryKey: readonly unknown[];
}

// Droit d'opposition a la CVtheque (RGPD art. 21).
//
// Le profil est visible par defaut (decision produit du 2026-08-17, pour que la
// CVtheque soit peuplee des le lancement). Ce defaut n'est defendable QUE si le
// retrait est reellement facile et clairement explique — d'ou :
//   - une section a part entiere, pas une case perdue en bas d'un formulaire ;
//   - un texte qui dit qui voit quoi, sans euphemisme ;
//   - un effet immediat, sans bouton "Enregistrer" a trouver.
// Ne pas transformer ceci en simple case a cocher du formulaire de profil.
export function CvthequeVisibilitySection({
  isVisible,
  queryKey,
}: CvthequeVisibilitySectionProps) {
  const queryClient = useQueryClient();

  const mutation = useMutation({
    mutationFn: (nextValue: boolean) =>
      updateProfile({ is_visible_in_cvtheque: nextValue }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey }),
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-lg">
          {isVisible ? (
            <Eye className="h-5 w-5 text-jeuncy-orange" aria-hidden="true" />
          ) : (
            <EyeOff className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
          )}
          Visibilité auprès des recruteurs
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        <p className="font-inter text-sm text-muted-foreground">
          Les entreprises et CFA abonnés à Jeuncy peuvent rechercher des profils et
          consulter le tien : ton nom, ta ville, ton parcours, tes compétences, et tes
          coordonnées (email et téléphone) pour te contacter directement. Personne d'autre
          n'y a accès, et ton profil n'est jamais visible publiquement sur Internet.
        </p>

        <label className="flex cursor-pointer items-start gap-3 rounded-md border border-border bg-muted/30 p-3">
          <input
            type="checkbox"
            checked={isVisible}
            disabled={mutation.isPending}
            onChange={(event) => mutation.mutate(event.target.checked)}
            className="mt-0.5 h-4 w-4 shrink-0 rounded border-border accent-jeuncy-coral"
          />
          <span className="font-inter text-sm text-foreground">
            <span className="font-poppins font-medium">
              Apparaître dans la CVthèque des recruteurs
            </span>
            <span className="mt-0.5 block text-muted-foreground">
              Décoche pour te retirer immédiatement. Tu pourras toujours postuler aux
              offres normalement — seule la recherche par les recruteurs est désactivée.
            </span>
          </span>
        </label>

        {mutation.isPending && (
          <p className="font-inter text-sm text-muted-foreground">Enregistrement…</p>
        )}
        {mutation.isError && (
          <p role="alert" className="font-inter text-sm text-destructive">
            Impossible d'enregistrer ce choix pour le moment. Réessaie dans un instant.
          </p>
        )}
        {mutation.isSuccess && !mutation.isPending && (
          <p role="status" className="font-inter text-sm text-jeuncy-orange">
            {isVisible
              ? 'Ton profil est visible par les recruteurs abonnés.'
              : 'Ton profil est retiré de la CVthèque.'}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
