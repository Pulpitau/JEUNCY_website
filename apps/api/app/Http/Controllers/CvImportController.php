<?php

namespace App\Http\Controllers;

use App\Exceptions\ApiException;
use App\Http\Requests\CandidateProfile\ImportCvRequest;
use App\Services\CvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class CvImportController extends Controller
{
    public function __construct(private readonly CvImportService $service) {}

    // Ne persiste rien : renvoie des suggestions que le frontend applique
    // ensuite au profil (voir CvImportService pour les limites de l'extraction).
    //
    // La lecture d'un PDF est la seule operation de l'API qui depend
    // entierement d'un fichier fourni par l'utilisateur : PDF protege par mot
    // de passe, scanne (donc sans couche texte), corrompu, ou simplement trop
    // lourd pour la memoire allouee par l'hebergeur. Sans ce filet, chacun de
    // ces cas remontait en 500 anonyme, et le candidat ne voyait qu'un "la
    // lecture a echoue" qui ne disait ni quoi faire, ni pourquoi. On distingue
    // donc explicitement l'echec de LECTURE du reste, et on journalise la vraie
    // cause cote serveur pour pouvoir la diagnostiquer sans faire tatonner
    // l'utilisateur.
    public function store(ImportCvRequest $request): JsonResponse
    {
        $file = $request->file('cv');

        try {
            return response()->json($this->service->parse($file));
        } catch (Throwable $exception) {
            Log::warning('Echec de lecture d\'un CV importe', [
                'nom_fichier' => $file->getClientOriginalName(),
                'octets' => $file->getSize(),
                'erreur' => $exception->getMessage(),
                'classe' => $exception::class,
            ]);

            throw new ApiException(
                'CV_UNREADABLE',
                "Ce PDF n'a pas pu être lu. Les CV protégés par mot de passe et les CV scannés (une image, sans texte sélectionnable) ne peuvent pas être analysés. Essaie de le réenregistrer en PDF depuis l'outil qui l'a créé, ou remplis ton profil à la main.",
                422,
            );
        }
    }
}
