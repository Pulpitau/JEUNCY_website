<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DecideVerificationRequest;
use App\Models\CfaOrganization;
use App\Models\Company;
use App\Models\Report;
use App\Services\AdminModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Les trois files de moderation, en un clic chacune (MOBILE.md §10).
 *
 * Aucune logique ici : le controleur passe le plat. Ce qui compte est dans
 * AdminModerationService, testable sans traverser HTTP.
 */
class ModerationController extends Controller
{
    public function __construct(private readonly AdminModerationService $service) {}

    public function reports(Request $request): JsonResponse
    {
        return response()->json(
            $this->service->listReports(['status' => $request->query('status', 'PENDING')]),
        );
    }

    public function handleReport(Request $request, Report $report): JsonResponse
    {
        return response()->json($this->service->handleReport($request->user(), $report));
    }

    public function verifications(): JsonResponse
    {
        return response()->json($this->service->listPendingVerifications());
    }

    /**
     * Decision humaine sur une organisation en attente.
     *
     * Le type vient de l'URL et non d'un champ : deux tables distinctes, deux
     * espaces d'identifiants distincts, et une entreprise n° 3 n'est pas le
     * CFA n° 3. Le deviner aurait fini par verifier la mauvaise organisation.
     */
    public function decideVerification(
        DecideVerificationRequest $request,
        string $type,
        int $id,
    ): JsonResponse {
        $organisation = match (strtoupper($type)) {
            'COMPANY' => Company::find($id),
            'CFA' => CfaOrganization::find($id),
            default => null,
        };

        if ($organisation === null) {
            throw new ApiException('ORGANIZATION_NOT_FOUND', 'Organisation introuvable.', 404);
        }

        return response()->json($this->service->decideVerification(
            $request->user(),
            $organisation,
            VerificationStatus::from($request->validated('status')),
            (string) $request->validated('note'),
        ));
    }

    public function silentEmployers(): JsonResponse
    {
        return response()->json($this->service->listSilentEmployers());
    }
}
