import type { CandidateCard } from './cvtheque';
import { apiRequest } from './client';

// Espace CFA, chantier 2 de la feuille de route (2026-10-07) : entreprises
// partenaires (toujours un compte Jeuncy vérifié) et recommandation d'un
// candidat du CFA pour une de leurs offres publiées. Ne crée jamais
// d'intérêt employeur à la place de l'entreprise — seulement une
// notification + un lien vers la carte (CandidateCard, même liste blanche
// que la CVthèque).

export interface PartnerCompany {
  id: number;
  name: string;
  city: string | null;
}

export interface PartnerJobOffer {
  id: number;
  title: string;
}

export interface ReceivedRecommendation {
  id: number;
  job_offer: { id: number; title: string };
  recommended_by: string;
  candidate: CandidateCard;
}

export function searchVerifiedCompanies(query: string) {
  return apiRequest<PartnerCompany[]>(
    `/cfa/companies/search?q=${encodeURIComponent(query)}`,
  );
}

export function listPartnerCompanies() {
  return apiRequest<PartnerCompany[]>('/cfa/partner-companies');
}

export function addPartnerCompany(companyId: number) {
  return apiRequest<PartnerCompany[]>('/cfa/partner-companies', {
    method: 'POST',
    body: { company_id: companyId },
  });
}

export function removePartnerCompany(companyId: number) {
  return apiRequest<PartnerCompany[]>(`/cfa/partner-companies/${companyId}`, {
    method: 'DELETE',
  });
}

export function listPartnerOffers(companyId: number) {
  return apiRequest<PartnerJobOffer[]>(`/cfa/partner-companies/${companyId}/job-offers`);
}

export function listMyCandidates() {
  return apiRequest<CandidateCard[]>('/cfa/candidates');
}

export function recommendCandidate(candidateProfileId: number, jobOfferId: number) {
  return apiRequest<{ id: number }>('/cfa/recommendations', {
    method: 'POST',
    body: { candidate_profile_id: candidateProfileId, job_offer_id: jobOfferId },
  });
}

export function listReceivedRecommendations() {
  return apiRequest<ReceivedRecommendation[]>('/recommendations');
}
