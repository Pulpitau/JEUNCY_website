<?php

namespace App\Services;

use App\Models\GeneratedCv;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CvService
{
    public function __construct(private readonly CandidateProfileService $profileService) {}

    public function generate(User $user): GeneratedCv
    {
        $profile = $this->profileService->requireProfile($user)
            ->load(['user', 'experiences', 'educations', 'skills']);

        $pdf = Pdf::loadView('cv.template', ['profile' => $profile]);
        $path = 'cvs/'.$profile->id.'/'.Str::uuid().'.pdf';
        Storage::disk('public')->put($path, $pdf->output());

        return $profile->generatedCvs()->create([
            'file_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function listForUser(User $user): Collection
    {
        return $this->profileService->requireProfile($user)
            ->generatedCvs()
            ->latest('generated_at')
            ->get();
    }
}
