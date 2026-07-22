<?php

namespace App\Http\Controllers;

use App\Http\Requests\CandidateProfile\StoreLanguageRequest;
use App\Models\Language;
use App\Services\CandidateProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LanguageController extends Controller
{
    public function __construct(private readonly CandidateProfileService $service) {}

    public function store(StoreLanguageRequest $request): JsonResponse
    {
        $language = $this->service->addLanguage($request->user(), $request->validated());

        return response()->json($language, 201);
    }

    public function destroy(Request $request, Language $language): JsonResponse
    {
        $this->service->deleteLanguage($request->user(), $language);

        return response()->json(['deleted' => true]);
    }
}
