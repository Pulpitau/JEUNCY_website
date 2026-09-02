<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\UploadCvRequest;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// CV que le candidat depose lui-meme (son PDF Canva, Word...), a ne pas
// confondre avec GeneratedCvController (CV fabrique par Jeuncy depuis les
// donnees du profil) ni avec CvImportController (lecture d'un PDF pour
// pre-remplir le profil, sans conservation du fichier).
class UploadedCvController extends Controller
{
    public function __construct(private readonly CandidateProfileService $service) {}

    public function store(UploadCvRequest $request): JsonResponse
    {
        return response()->json(
            $this->service->uploadCv($request->user(), $request->file('cv_file')),
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        return response()->json($this->service->removeCv($request->user()));
    }
}
