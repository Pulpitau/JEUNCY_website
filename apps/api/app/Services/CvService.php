<?php

namespace App\Services;

use App\Models\CandidateProfile;
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

        // Format fixe (plutot que le "letter" par defaut) : le template a besoin de
        // connaitre la hauteur de page a l'avance pour que le bandeau colore de la
        // sidebar aille jusqu'en bas (voir min-height dans cv/template.blade.php).
        $pdf = Pdf::loadView('cv.template', [
            'profile' => $profile,
            'photoDataUri' => $this->photoDataUri($profile),
        ])->setPaper('a4');
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

    // dompdf lit une image locale bien plus simplement (et sans dependre du
    // reseau ni d'un aller-retour HTTP vers le serveur lui-meme) via une data
    // URI base64 incorporee directement dans le HTML plutot que via son URL publique.
    private function photoDataUri(CandidateProfile $profile): ?string
    {
        $path = $this->profileService->photoAbsolutePath($profile);
        if (! $path || ! is_file($path)) {
            return null;
        }

        $mimeType = mime_content_type($path) ?: 'image/jpeg';
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}
