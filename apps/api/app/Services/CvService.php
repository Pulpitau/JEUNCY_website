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
            ->load(['user', 'experiences', 'educations', 'skills', 'languages']);

        $pdf = Pdf::loadView('cv.template', [
            'profile' => $profile,
            'photoDataUri' => $this->photoDataUri($profile),
            'logoDataUri' => $this->logoDataUri(),
            'age' => $profile->birth_date?->age,
            'scales' => $this->contentScales($profile),
            'palette' => $this->palette($profile),
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

    // Supprime le fichier PDF stocke (recuperation d'espace disque, voir
    // app/Console/Commands/ArchiveInactiveCvs.php) et marque la ligne comme
    // archivee plutot que de la supprimer : garde une trace de l'historique
    // de generation sans garder le fichier lui-meme.
    public function archive(GeneratedCv $cv): void
    {
        $relativePath = $this->relativeStoragePath($cv->file_url);
        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }

        $cv->update(['archived_at' => now()]);
    }

    private function relativeStoragePath(string $url): string
    {
        $base = rtrim(Storage::disk('public')->url(''), '/').'/';

        return Str::startsWith($url, $base) ? substr($url, strlen($base)) : $url;
    }

    // Garde la charte graphique Jeuncy (bleu nuit corporate + dégradé
    // signature corail -> orange, voir CLAUDE.md §2) plutot que d'en sortir,
    // mais varie l'accent par candidat pour que deux CV generes cote a cote
    // n'aient pas l'air de sortir du meme moule ("CV d'usine") : chaque
    // accent est un point echantillonne le long du degrade signature lui-meme
    // (jamais une couleur hors-charte), le bleu nuit reste constant en
    // identite (titres, en-tete) comme l'exige la charte. Choix deterministe
    // sur l'id du profil (stable d'une generation a l'autre pour un meme
    // candidat) et une permutation (plutot que l'ordre du degrade) pour que
    // deux candidats d'affilee retombent rarement sur deux points voisins
    // du degrade, donc visuellement plus distincts.
    private const NAVY = '#061D4F';

    private const GRADIENT_STOPS = [
        '#FF2D55', '#FF3A50', '#FF484B', '#FF5546',
        '#FF6241', '#FF6F3C', '#FF7D37', '#FF8A32',
    ];

    private const STOP_ORDER = [0, 4, 1, 5, 2, 6, 3, 7];

    private function palette(CandidateProfile $profile): array
    {
        $stopIndex = self::STOP_ORDER[$profile->id % count(self::STOP_ORDER)];

        return [
            'primary' => self::NAVY,
            'accent' => self::GRADIENT_STOPS[$stopIndex],
        ];
    }

    // dompdf n'a aucun mecanisme pour reduire (ou grossir) dynamiquement du
    // contenu selon la place disponible, mais rien n'empeche de calculer cote
    // serveur, avant le rendu, a quel point un profil est "rempli" et de
    // deriver trois facteurs d'echelle a partir de ce score, jamais un seul :
    // - 'section' : les GRANDS espaces (padding de page, en-tete, marge entre
    //   sections) — peuvent grossir enormement, ca se lit comme une mise en
    //   page aeree et volontaire, jamais comme une erreur.
    // - 'item'    : les PETITS espaces entre elements d'une meme liste
    //   (puces, langues, formations) — doivent rester moderes, un enorme vide
    //   entre deux puces de la meme experience aurait l'air casse.
    // - 'font'    : les tailles de texte du corps (jamais l'identite du
    //   header) — un peu plus grand aide aussi a occuper la page.
    // Un profil dense (score >= denseScoreCeil) garde les trois a 1.0 (mise
    // en page compacte de reference, jamais de debordement sur une 2e page).
    // Bornes calibrees empiriquement (rendu + rasterisation) sur plusieurs
    // profils de reference (dense, normal, leger, tres sparse).
    private function contentScales(CandidateProfile $profile): array
    {
        $descriptionLineCount = $profile->experiences->sum(
            fn ($experience) => $experience->description
                ? count(array_filter(preg_split('/\r\n|\r|\n/', $experience->description)))
                : 0,
        );

        $score = ($profile->experiences->count() * 3)
            + $descriptionLineCount
            + ($profile->educations->count() * 1.5)
            + ($profile->skills->count() * 0.3)
            + ($profile->languages->count() * 0.3)
            + ($profile->bio ? 1.5 : 0)
            + ($profile->hobbies ? 1 : 0);

        $denseScoreFloor = 6;
        $denseScoreCeil = 34;
        $maxSectionScale = 4.5;
        $maxItemScale = 1.7;
        $maxFontScale = 1.35;

        $ratio = ($score - $denseScoreFloor) / ($denseScoreCeil - $denseScoreFloor);
        $ratio = max(0.0, min(1.0, $ratio));
        // Cube plutot que lineaire : un profil "normal" (ratio moyen) ne doit
        // recevoir qu'un boost modeste (garantir 1 page), seul un profil
        // vraiment tres peu fourni (ratio proche de 0) doit s'approcher du
        // boost maximal — sinon un profil normal deborde sur une 2e page
        // (regression constatee et corrigee empiriquement).
        $emptiness = (1 - $ratio) ** 3;

        return [
            'section' => round(1.0 + $emptiness * ($maxSectionScale - 1.0), 2),
            'item' => round(1.0 + $emptiness * ($maxItemScale - 1.0), 2),
            'font' => round(1.0 + $emptiness * ($maxFontScale - 1.0), 2),
        ];
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

    // Copie dans apps/api/resources (plutot que reference vers apps/web/public) :
    // le projet Laravel doit rester un package Composer autonome, sans dependre
    // de l'arborescence du frontend au moment du deploiement (voir CLAUDE.md §3).
    private function logoDataUri(): ?string
    {
        $path = resource_path('images/logo-jeuncy.png');
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }
}
