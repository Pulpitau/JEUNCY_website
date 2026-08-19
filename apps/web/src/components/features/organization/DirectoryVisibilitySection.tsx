import { useMutation, useQueryClient } from '@tanstack/react-query';
import { Eye, EyeOff } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { updateCompany } from '@/lib/api/company';
import { updateCfaOrganization } from '@/lib/api/cfa-organization';

interface DirectoryVisibilitySectionProps {
  variant: 'COMPANY' | 'CFA';
  isPublic: boolean;
  queryKey: readonly unknown[];
}

// Presence dans l'annuaire public (/entreprises ou /cfa).
//
// Pendant tout ou partie: etre inscrit et vouloir figurer dans un annuaire
// consulte par tous les visiteurs sont deux choses distinctes. Cas concret a
// l'origine : la fiche de demonstration de l'equipe Jeuncy, necessaire pour
// tester le rendu des offres mais qui n'a rien a faire dans l'annuaire vu par
// les candidats.
//
// Meme parti-pris que CvthequeVisibilitySection : une section a part, un texte
// qui dit exactement ce que le choix change, et un effet immediat sans bouton
// "Enregistrer" a aller chercher.
export function DirectoryVisibilitySection({
  variant,
  isPublic,
  queryKey,
}: DirectoryVisibilitySectionProps) {
  const queryClient = useQueryClient();
  const label = variant === 'COMPANY' ? 'entreprises' : 'CFA';

  // Type de retour explicite : les deux appels renvoient des formes
  // differentes (Company vs CfaOrganization) et TanStack Query infererait
  // sinon une union que la mutation refuse. On n'exploite pas la reponse — la
  // fraicheur vient de l'invalidation — d'ou le void.
  const mutation = useMutation<void, Error, boolean>({
    mutationFn: async (nextValue) => {
      if (variant === 'COMPANY') {
        await updateCompany({ is_public: nextValue });
        return;
      }
      await updateCfaOrganization({ is_public: nextValue });
    },
    // await sur l'invalidation : sans lui, la mutation est "terminee" avant
    // que la fiche rechargee n'arrive, la case reprend brievement son ancien
    // etat, et un second clic pendant cette fenetre renvoie la MEME valeur au
    // serveur — le choix semble alors ne pas s'appliquer. Rester en "pending"
    // jusqu'au rafraichissement effectif ferme cette fenetre.
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey });
    },
  });

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-lg">
          {isPublic ? (
            <Eye className="h-5 w-5 text-jeuncy-orange" aria-hidden="true" />
          ) : (
            <EyeOff className="h-5 w-5 text-muted-foreground" aria-hidden="true" />
          )}
          Présence dans l'annuaire public
        </CardTitle>
      </CardHeader>
      <CardContent className="flex flex-col gap-3">
        <p className="font-inter text-sm text-muted-foreground">
          Ta fiche apparaît dans l'annuaire des {label}, consultable par tous les
          visiteurs du site, avec ton nom, ta description, ta ville et ton logo.
        </p>

        <label className="flex cursor-pointer items-start gap-3 rounded-md border border-border bg-muted/30 p-3">
          <input
            type="checkbox"
            checked={isPublic}
            disabled={mutation.isPending}
            onChange={(event) => mutation.mutate(event.target.checked)}
            className="mt-0.5 h-4 w-4 shrink-0 rounded border-border accent-jeuncy-coral"
          />
          <span className="font-inter text-sm text-foreground">
            <span className="font-poppins font-medium">
              Figurer dans l'annuaire des {label}
            </span>
            {/* Precision essentielle : sans elle, une entreprise qui vient de
                payer une publication peut croire qu'elle rend son offre
                invisible en decochant, et se priver de candidatures. */}
            <span className="mt-0.5 block text-muted-foreground">
              Décoche pour en être retiré immédiatement. Tes offres publiées restent
              visibles et continuent de recevoir des candidatures — seule ta fiche
              disparaît de l'annuaire.
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
            {isPublic
              ? `Ta fiche apparaît dans l'annuaire des ${label}.`
              : `Ta fiche est retirée de l'annuaire des ${label}.`}
          </p>
        )}
      </CardContent>
    </Card>
  );
}
