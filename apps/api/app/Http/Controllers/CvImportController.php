<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\ImportCvRequest;
use App\Services\CvImportService;
use Illuminate\Http\JsonResponse;

class CvImportController extends Controller
{
    public function __construct(private readonly CvImportService $service) {}

    // Ne persiste rien : renvoie uniquement des suggestions que le candidat
    // relit et applique lui-meme (voir CvImportService pour le detail des
    // limites de l'extraction heuristique).
    public function store(ImportCvRequest $request): JsonResponse
    {
        return response()->json($this->service->parse($request->file('cv')));
    }
}
