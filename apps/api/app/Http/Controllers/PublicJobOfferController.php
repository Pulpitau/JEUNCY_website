<?php

namespace App\Http\Controllers;

use App\Http\Requests\JobOffer\SearchJobOffersRequest;
use App\Services\JobOfferService;
use App\Support\OffersCount;
use Illuminate\Http\JsonResponse;

class PublicJobOfferController extends Controller
{
    public function __construct(private readonly JobOfferService $service) {}

    public function index(SearchJobOffersRequest $request): JsonResponse
    {
        return response()->json($this->service->searchPublished($request->validated()));
    }

    public function show(int $jobOffer): JsonResponse
    {
        return response()->json($this->service->findPublished($jobOffer));
    }

    // Nombre d'offres en ligne, offres Jeuncy et offres partenaires (La
    // bonne alternance) confondues : affiche en page d'accueil. Le VRAI
    // chiffre, en direct, jamais arrondi vers le haut — un visiteur qui
    // compte doit retrouver ce nombre sur /offres. En cache dix minutes :
    // c'est la page la plus vue du site, et le total ne bouge qu'une fois
    // par nuit.
    //
    // Le calcul vit dans App\Support\OffersCount depuis le 2026-10-01 : la
    // lettre hebdomadaire annonce ce meme chiffre, et deux requetes separees
    // auraient fini par se contredire dans le dos du candidat.
    public function count(): JsonResponse
    {
        return response()->json(OffersCount::current());
    }
}
