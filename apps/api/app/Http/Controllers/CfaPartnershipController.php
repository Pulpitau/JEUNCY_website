<?php

namespace App\Http\Controllers;

use App\Http\Requests\CfaPartnership\AddPartnerCompanyRequest;
use App\Http\Requests\CfaPartnership\RecommendCandidateRequest;
use App\Http\Requests\CfaPartnership\SearchVerifiedCompaniesRequest;
use App\Services\CfaPartnershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CfaPartnershipController extends Controller
{
    public function __construct(private readonly CfaPartnershipService $service) {}

    public function searchCompanies(SearchVerifiedCompaniesRequest $request): JsonResponse
    {
        return response()->json($this->service->searchVerifiedCompanies($request->validated('q')));
    }

    public function partnerCompanies(Request $request): JsonResponse
    {
        return response()->json($this->service->listPartnerCompanies($request->user()));
    }

    public function addPartnerCompany(AddPartnerCompanyRequest $request): JsonResponse
    {
        $this->service->addPartnerCompany($request->user(), $request->validated('company_id'));

        return response()->json($this->service->listPartnerCompanies($request->user()), 201);
    }

    public function removePartnerCompany(Request $request, int $company): JsonResponse
    {
        $this->service->removePartnerCompany($request->user(), $company);

        return response()->json($this->service->listPartnerCompanies($request->user()));
    }

    public function partnerOffers(Request $request, int $company): JsonResponse
    {
        return response()->json($this->service->listPartnerOffers($request->user(), $company));
    }

    public function candidates(Request $request): JsonResponse
    {
        return response()->json($this->service->listMyCandidates($request->user()));
    }

    public function recommend(RecommendCandidateRequest $request): JsonResponse
    {
        $recommendation = $this->service->recommend(
            $request->user(),
            $request->validated('candidate_profile_id'),
            $request->validated('job_offer_id'),
        );

        return response()->json($recommendation, 201);
    }

    // Cote entreprise (role COMPANY) : les candidats qu'un CFA partenaire
    // lui a recommandes.
    public function received(Request $request): JsonResponse
    {
        return response()->json($this->service->listReceivedRecommendations($request->user()));
    }
}
