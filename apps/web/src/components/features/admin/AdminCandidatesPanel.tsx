import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  listAdminCandidateProfiles,
  listAdminCfaOrganizations,
  updateCandidateCfaOrganization,
  updateCandidateName,
  type AdminCandidateProfile,
} from '@/lib/api/admin';
import { AdminPager } from './AdminPager';

// Correction des noms de candidats mal lus par l'import de CV.
//
// L'import cree le profil sans que le candidat relise l'identite tiree de son
// PDF : un nom rate ne se voit qu'apres coup, dans la CVtheque, sous les yeux
// des recruteurs. Le filtre "noms douteux" existe pour retrouver ces profils
// sans avoir a parcourir toute la liste — c'est lui qui repond a la question
// "en reste-t-il d'autres ?".
export function AdminCandidatesPanel() {
  const queryClient = useQueryClient();
  const [suspiciousOnly, setSuspiciousOnly] = useState(true);
  const [page, setPage] = useState(1);
  const [editingId, setEditingId] = useState<number | null>(null);

  const profilesQuery = useQuery({
    queryKey: ['admin', 'candidate-profiles', { suspiciousOnly, page }],
    queryFn: () => listAdminCandidateProfiles({ suspicious: suspiciousOnly, page }),
  });

  // Rarement modifiee (une poignee de CFA partenaires) : pas besoin de la
  // reinvalider avec la liste des candidats.
  const cfaOrganizationsQuery = useQuery({
    queryKey: ['admin', 'cfa-organizations'],
    queryFn: listAdminCfaOrganizations,
  });

  const renameMutation = useMutation({
    mutationFn: ({
      id,
      ...payload
    }: {
      id: number;
      first_name: string;
      last_name: string;
    }) => updateCandidateName(id, payload),
    onSuccess: async () => {
      setEditingId(null);
      await queryClient.invalidateQueries({ queryKey: ['admin', 'candidate-profiles'] });
    },
  });

  const cfaOrganizationMutation = useMutation({
    mutationFn: ({
      id,
      cfaOrganizationId,
    }: {
      id: number;
      cfaOrganizationId: number | null;
    }) => updateCandidateCfaOrganization(id, cfaOrganizationId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['admin', 'candidate-profiles'] });
    },
  });

  const profiles = profilesQuery.data?.data ?? [];
  const lastPage = profilesQuery.data?.last_page ?? 1;

  return (
    <div className="flex flex-col gap-4">
      <label className="flex max-w-xl items-start gap-2 font-inter text-sm">
        <input
          type="checkbox"
          className="mt-1 h-4 w-4 rounded border-input"
          checked={suspiciousOnly}
          onChange={(event) => {
            setSuspiciousOnly(event.target.checked);
            setPage(1);
          }}
        />
        <span>
          N'afficher que les noms douteux
          <span className="block text-xs text-muted-foreground">
            Étiquettes de CV, compétences, phrases : des noms que l'import ne produirait
            plus aujourd'hui. Un nom inhabituel reste un nom — à toi de trancher.
          </span>
        </span>
      </label>

      {profilesQuery.isLoading ? (
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      ) : profilesQuery.isError ? (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger les candidats pour le moment, réessaie plus tard.
        </p>
      ) : profiles.length === 0 ? (
        <p className="font-inter text-sm text-muted-foreground">
          {suspiciousOnly
            ? 'Aucun nom douteux — tous les profils ont un nom correct.'
            : 'Aucun candidat.'}
        </p>
      ) : (
        <div className="flex flex-col gap-3">
          {profiles.map((profile) => (
            <CandidateRow
              key={profile.id}
              profile={profile}
              isEditing={editingId === profile.id}
              isSaving={renameMutation.isPending}
              onEdit={() => setEditingId(profile.id)}
              onCancel={() => setEditingId(null)}
              onSave={(first_name, last_name) =>
                renameMutation.mutate({ id: profile.id, first_name, last_name })
              }
              cfaOrganizations={cfaOrganizationsQuery.data ?? []}
              onCfaOrganizationChange={(cfaOrganizationId) =>
                cfaOrganizationMutation.mutate({ id: profile.id, cfaOrganizationId })
              }
            />
          ))}
        </div>
      )}

      <AdminPager page={page} lastPage={lastPage} onChange={setPage} />
    </div>
  );
}

function CandidateRow({
  profile,
  isEditing,
  isSaving,
  onEdit,
  onCancel,
  onSave,
  cfaOrganizations,
  onCfaOrganizationChange,
}: {
  profile: AdminCandidateProfile;
  isEditing: boolean;
  isSaving: boolean;
  onEdit: () => void;
  onCancel: () => void;
  onSave: (firstName: string, lastName: string) => void;
  cfaOrganizations: { id: number; name: string }[];
  onCfaOrganizationChange: (cfaOrganizationId: number | null) => void;
}) {
  const [firstName, setFirstName] = useState(profile.first_name);
  const [lastName, setLastName] = useState(profile.last_name);

  const canSave = firstName.trim().length > 0 && lastName.trim().length > 0;

  // Badge « JEUNCY x <école> » : rassure une entreprise partenaire du CFA
  // que ce candidat en fait bien partie.
  const cfaSelector = (
    <label className="flex items-center gap-2 text-xs text-muted-foreground">
      JEUNCY x
      <select
        className="rounded-md border border-input bg-background px-2 py-1 text-xs"
        value={profile.cfa_organization_id ?? ''}
        onChange={(event) =>
          onCfaOrganizationChange(event.target.value ? Number(event.target.value) : null)
        }
      >
        <option value="">Aucun CFA</option>
        {cfaOrganizations.map((cfa) => (
          <option key={cfa.id} value={cfa.id}>
            {cfa.name}
          </option>
        ))}
      </select>
    </label>
  );

  if (!isEditing) {
    return (
      <div className="flex flex-wrap items-center justify-between gap-3 rounded-md border border-border p-4">
        <div>
          <p className="font-poppins font-medium">
            {profile.first_name} {profile.last_name}
          </p>
          <p className="text-xs text-muted-foreground">
            {profile.user?.email ?? 'compte introuvable'}
            {profile.city ? ` — ${profile.city}` : ''}
          </p>
        </div>
        <div className="flex items-center gap-3">
          {cfaSelector}
          <Button type="button" size="sm" variant="outline" onClick={onEdit}>
            Corriger le nom
          </Button>
        </div>
      </div>
    );
  }

  return (
    <form
      className="flex flex-col gap-3 rounded-md border border-jeuncy-coral p-4"
      onSubmit={(event) => {
        event.preventDefault();
        if (canSave) onSave(firstName, lastName);
      }}
    >
      <p className="text-xs text-muted-foreground">
        {profile.user?.email ?? 'compte introuvable'}
      </p>
      <div className="flex flex-wrap gap-3">
        <div className="flex-1">
          <Label htmlFor={`first-${profile.id}`}>Prénom</Label>
          <Input
            id={`first-${profile.id}`}
            value={firstName}
            onChange={(event) => setFirstName(event.target.value)}
          />
        </div>
        <div className="flex-1">
          <Label htmlFor={`last-${profile.id}`}>Nom</Label>
          <Input
            id={`last-${profile.id}`}
            value={lastName}
            onChange={(event) => setLastName(event.target.value)}
          />
        </div>
      </div>
      <div className="flex gap-2">
        <Button
          type="submit"
          size="sm"
          variant="gradient"
          disabled={!canSave || isSaving}
        >
          {isSaving ? 'Enregistrement…' : 'Enregistrer'}
        </Button>
        <Button type="button" size="sm" variant="ghost" onClick={onCancel}>
          Annuler
        </Button>
      </div>
    </form>
  );
}
