<?php

namespace App\Http\Controllers;

use App\Http\Requests\Application\StoreApplicationRequest;
use App\Models\Application;
use App\Models\JobOffer;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    // Meme en-tete que AuthController : c'est ainsi qu'un client natif se
    // declare. Sert ici a renseigner applications.source (SITE / APP), pas a
    // changer le comportement.
    private const MOBILE_CLIENT_HEADER = 'X-Jeuncy-Client';

    private const MOBILE_CLIENT_VALUE = 'mobile';

    public function __construct(private readonly ApplicationService $service) {}

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $jobOffer = JobOffer::findOrFail($validated['job_offer_id']);
        $application = $this->service->applyForUser(
            $request->user(),
            $jobOffer,
            $validated['cover_letter'] ?? null,
            $validated['contact_phone'],
            $validated['generated_cv_id'] ?? null,
            $request->file('cv_file'),
            $this->isMobileClient($request),
        );

        return response()->json($application, 201);
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->service->listForCandidate($request->user()));
    }

    public function destroy(Request $request, Application $application): JsonResponse
    {
        $this->service->withdrawForUser($request->user(), $application);

        return response()->json(['withdrawn' => true]);
    }

    private function isMobileClient(Request $request): bool
    {
        return strtolower(trim((string) $request->header(self::MOBILE_CLIENT_HEADER, '')))
            === self::MOBILE_CLIENT_VALUE;
    }
}
