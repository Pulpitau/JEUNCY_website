import { Link } from 'react-router-dom';
import { Building2, ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  CardDescription,
} from '@/components/ui/card';
import { WORK_MODE_LABELS } from '@/lib/work-mode-labels';
import {
  EXTERNAL_SOURCE_LABEL,
  type ExternalJobOffer,
} from '@/lib/api/external-job-offers';

// Carte d'une offre importee de La bonne alternance. Meme gabarit que
// PublicJobOfferCard pour que les deux listes se lisent d'un seul oeil, avec
// une seule difference visible : la mention de la source (licence Etalab,
// et honnetete envers le candidat qui va quitter Jeuncy pour postuler).
export function ExternalJobOfferCard({ offer }: { offer: ExternalJobOffer }) {
  return (
    <Link to={`/offres/partenaire/${offer.id}`}>
      <Card className="h-full transition-all duration-200 hover:-translate-y-1 hover:border-primary hover:shadow-lg">
        <CardHeader>
          <div className="flex flex-wrap gap-1">
            <Badge variant="outline" className="w-fit">
              Alternance
            </Badge>
            {offer.target_diploma_label && (
              <Badge variant="outline" className="w-fit">
                {offer.target_diploma_label}
              </Badge>
            )}
            {offer.work_mode && (
              <Badge variant="outline" className="w-fit">
                {WORK_MODE_LABELS[offer.work_mode]}
              </Badge>
            )}
          </div>
          <CardTitle>{offer.title}</CardTitle>
          <div className="flex items-center gap-2">
            <div className="flex h-6 w-6 shrink-0 items-center justify-center rounded border border-border bg-muted">
              <Building2
                className="h-3.5 w-3.5 text-muted-foreground"
                aria-hidden="true"
              />
            </div>
            <CardDescription>
              {offer.company_name ?? 'Employeur non précisé'}
              {offer.city ? ` · ${offer.city}` : ''}
            </CardDescription>
          </div>
        </CardHeader>
        <CardContent>
          <p className="line-clamp-3 font-inter text-sm text-muted-foreground">
            {offer.description}
          </p>
          <p className="mt-3 flex items-center gap-1 font-inter text-xs text-muted-foreground">
            <ExternalLink className="h-3 w-3" aria-hidden="true" />
            via {EXTERNAL_SOURCE_LABEL}
          </p>
        </CardContent>
      </Card>
    </Link>
  );
}
