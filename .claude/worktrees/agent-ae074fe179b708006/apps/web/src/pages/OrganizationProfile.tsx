import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
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
import { getMyCompany, createCompany, updateCompany } from '@/lib/api/company';
import {
  getMyCfaOrganization,
  createCfaOrganization,
  updateCfaOrganization,
} from '@/lib/api/cfa-organization';
import { useAuthStore } from '@/store/auth-store';
import { ApiError } from '@/lib/api/client';

const COMPANY_QUERY_KEY = ['company'];
const CFA_QUERY_KEY = ['cfa-organization'];

export function OrganizationProfile() {
  const user = useAuthStore((state) => state.user);
  const queryClient = useQueryClient();
  const isCompany = user?.role === UserRole.COMPANY;

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
    onSuccess: () => queryClient.invalidateQueries({ queryKey: COMPANY_QUERY_KEY }),
  });
  const updateCompanyMutation = useMutation({
    mutationFn: updateCompany,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: COMPANY_QUERY_KEY }),
  });
  const createCfaMutation = useMutation({
    mutationFn: createCfaOrganization,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CFA_QUERY_KEY }),
  });
  const updateCfaMutation = useMutation({
    mutationFn: updateCfaOrganization,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: CFA_QUERY_KEY }),
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
            <CompanyForm
              company={companyQuery.data ?? null}
              isSubmitting={
                createCompanyMutation.isPending || updateCompanyMutation.isPending
              }
              onSubmit={(values) =>
                companyQuery.data
                  ? updateCompanyMutation.mutateAsync(values)
                  : createCompanyMutation.mutateAsync(values)
              }
            />
          ) : (
            <CfaForm
              cfaOrganization={cfaQuery.data ?? null}
              isSubmitting={createCfaMutation.isPending || updateCfaMutation.isPending}
              onSubmit={(values) =>
                cfaQuery.data
                  ? updateCfaMutation.mutateAsync(values)
                  : createCfaMutation.mutateAsync(values)
              }
            />
          )}
        </CardContent>
      </Card>
    </main>
  );
}
