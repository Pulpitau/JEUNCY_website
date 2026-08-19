import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { GraduationCap } from 'lucide-react';
import { UserRole } from '@jeuncy/shared';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import { CompanyForm } from '@/components/features/organization/CompanyForm';
import { CfaForm } from '@/components/features/organization/CfaForm';
import { DirectoryVisibilitySection } from '@/components/features/organization/DirectoryVisibilitySection';
import { OrganizationSummary } from '@/components/features/organization/OrganizationSummary';
import { LogoUpload } from '@/components/features/organization/LogoUpload';
import {
  getMyCompany,
  createCompany,
  updateCompany,
  uploadCompanyLogo,
  removeCompanyLogo,
  type Company,
} from '@/lib/api/company';
import {
  getMyCfaOrganization,
  createCfaOrganization,
  updateCfaOrganization,
  uploadCfaOrganizationLogo,
  removeCfaOrganizationLogo,
  type CfaOrganization,
} from '@/lib/api/cfa-organization';
import { useAuthStore } from '@/store/auth-store';
import { ApiError } from '@/lib/api/client';

const COMPANY_QUERY_KEY = ['company'];
const CFA_QUERY_KEY = ['cfa-organization'];

export function OrganizationProfile() {
  const user = useAuthStore((state) => state.user);
  const queryClient = useQueryClient();
  const isCompany = user?.role === UserRole.COMPANY;
  const [isEditing, setIsEditing] = useState(false);

  const companyQuery = useQuery({
    queryKey: COMPANY_QUERY_KEY,
    queryFn: getMyCompany,
    retry: false,
    enabled: isCompany,
  });
  const cfaQuery = useQuery({
    queryKey: CFA_QUERY_KEY,
    queryFn: getMyCfaOrganization,
    retry: false,
    enabled: !isCompany,
  });

  const isLoading = isCompany ? companyQuery.isLoading : cfaQuery.isLoading;
  const activeError = isCompany ? companyQuery.error : cfaQuery.error;
  const isError = isCompany ? companyQuery.isError : cfaQuery.isError;
  const unexpectedError =
    isError &&
    !(
      activeError instanceof ApiError &&
      (activeError.code === 'COMPANY_NOT_FOUND' ||
        activeError.code === 'CFA_ORGANIZATION_NOT_FOUND')
    )
      ? activeError
      : null;

  const createCompanyMutation = useMutation({
    mutationFn: createCompany,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: COMPANY_QUERY_KEY });
      setIsEditing(false);
    },
  });
  const updateCompanyMutation = useMutation({
    mutationFn: updateCompany,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: COMPANY_QUERY_KEY });
      setIsEditing(false);
    },
  });
  const createCfaMutation = useMutation({
    mutationFn: createCfaOrganization,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CFA_QUERY_KEY });
      setIsEditing(false);
    },
  });
  const updateCfaMutation = useMutation({
    mutationFn: updateCfaOrganization,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: CFA_QUERY_KEY });
      setIsEditing(false);
    },
  });

  const uploadLogoMutation = useMutation<Company | CfaOrganization, ApiError, File>({
    mutationFn: (file: File) =>
      isCompany ? uploadCompanyLogo(file) : uploadCfaOrganizationLogo(file),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: isCompany ? COMPANY_QUERY_KEY : CFA_QUERY_KEY,
      });
    },
  });
  const removeLogoMutation = useMutation<Company | CfaOrganization, ApiError, void>({
    mutationFn: () => (isCompany ? removeCompanyLogo() : removeCfaOrganizationLogo()),
    onSuccess: () => {
      queryClient.invalidateQueries({
        queryKey: isCompany ? COMPANY_QUERY_KEY : CFA_QUERY_KEY,
      });
    },
  });

  if (isLoading) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-12">
        <p className="font-inter text-sm text-muted-foreground">Chargement…</p>
      </main>
    );
  }

  return (
    <main className="mx-auto flex max-w-3xl flex-col gap-6 px-4 py-12">
      <div>
        <h1 className="font-poppins text-3xl font-bold">
          {isCompany ? 'Mon entreprise' : 'Mon CFA'}
        </h1>
        <p className="mt-1 font-inter text-muted-foreground">
          {isCompany
            ? 'Complète le profil de ton entreprise pour publier des offres.'
            : 'Complète le profil de ton CFA pour publier des offres.'}
        </p>
      </div>

      {unexpectedError && (
        <p role="alert" className="font-inter text-sm text-destructive">
          Impossible de charger ce profil pour le moment, réessaie plus tard.
        </p>
      )}

      {(isCompany ? companyQuery.data : cfaQuery.data) && (
        <Card>
          <CardHeader>
            <CardTitle>Logo</CardTitle>
            <CardDescription>
              Affiché sur tes offres publiées pour rassurer les candidats.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <LogoUpload
              logoUrl={(isCompany ? companyQuery.data : cfaQuery.data)?.logo_url ?? null}
              fallbackIcon={isCompany ? undefined : GraduationCap}
              onUpload={(file) => uploadLogoMutation.mutateAsync(file)}
              onRemove={() => removeLogoMutation.mutateAsync()}
              isUploading={uploadLogoMutation.isPending}
              isRemoving={removeLogoMutation.isPending}
            />
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Informations</CardTitle>
          <CardDescription>
            {(isCompany ? companyQuery.data : cfaQuery.data)
              ? 'Modifie ces informations à tout moment.'
              : 'Crée le profil pour commencer.'}
          </CardDescription>
        </CardHeader>
        <CardContent>
          {isCompany ? (
            companyQuery.data && !isEditing ? (
              <OrganizationSummary
                name={companyQuery.data.name}
                logoUrl={companyQuery.data.logo_url}
                siret={companyQuery.data.siret}
                city={companyQuery.data.city}
                website={companyQuery.data.website}
                description={companyQuery.data.description}
                workMode={companyQuery.data.work_mode}
                onEdit={() => setIsEditing(true)}
              />
            ) : (
              <CompanyForm
                company={companyQuery.data ?? null}
                isSubmitting={
                  createCompanyMutation.isPending || updateCompanyMutation.isPending
                }
                onCancel={companyQuery.data ? () => setIsEditing(false) : undefined}
                onSubmit={(values) =>
                  companyQuery.data
                    ? updateCompanyMutation.mutateAsync(values)
                    : createCompanyMutation.mutateAsync(values)
                }
              />
            )
          ) : cfaQuery.data && !isEditing ? (
            <OrganizationSummary
              name={cfaQuery.data.name}
              logoUrl={cfaQuery.data.logo_url}
              siret={cfaQuery.data.siret}
              ndaNumber={cfaQuery.data.nda_number}
              qualiopiNumber={cfaQuery.data.qualiopi_number}
              city={cfaQuery.data.city}
              website={cfaQuery.data.website}
              description={cfaQuery.data.description}
              diplomasOffered={cfaQuery.data.diplomas_offered}
              diplomaLevel={cfaQuery.data.diploma_level}
              workMode={cfaQuery.data.training_mode}
              onEdit={() => setIsEditing(true)}
            />
          ) : (
            <CfaForm
              cfaOrganization={cfaQuery.data ?? null}
              isSubmitting={createCfaMutation.isPending || updateCfaMutation.isPending}
              onCancel={cfaQuery.data ? () => setIsEditing(false) : undefined}
              onSubmit={(values) =>
                cfaQuery.data
                  ? updateCfaMutation.mutateAsync(values)
                  : createCfaMutation.mutateAsync(values)
              }
            />
          )}
        </CardContent>
      </Card>

      {/* Apres le formulaire, et seulement une fois la fiche creee : il n'y a
          rien a montrer ou masquer dans un annuaire tant qu'elle n'existe
          pas. */}
      {isCompany && companyQuery.data && (
        <DirectoryVisibilitySection
          variant="COMPANY"
          isPublic={companyQuery.data.is_public}
          queryKey={COMPANY_QUERY_KEY}
        />
      )}
      {!isCompany && cfaQuery.data && (
        <DirectoryVisibilitySection
          variant="CFA"
          isPublic={cfaQuery.data.is_public}
          queryKey={CFA_QUERY_KEY}
        />
      )}
    </main>
  );
}
