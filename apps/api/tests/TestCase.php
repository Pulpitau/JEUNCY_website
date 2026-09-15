<?php

namespace Tests;

use Closure;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    /**
     * Reponse du registre des entreprises pour le test en cours. Null =
     * registre vide (aucun SIRET connu). Un test qui veut un NAF precis
     * assigne ici une closure recevant la requete et renvoyant une reponse
     * Http::response(...) — un second Http::fake sur la meme URL ne
     * suffirait pas, le premier stub enregistre l'emporte toujours.
     */
    protected ?Closure $registreEntreprises = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Le registre des entreprises (TrainingOrganizationDetector::nafFor)
        // ne doit jamais etre appele pour de vrai depuis un test : lent,
        // dependant du reseau, et une fiche creee avec un SIRET quelconque
        // ne doit pas se retrouver bloquee parce que ce numero existe.
        $this->registreEntreprises = null;
        Http::fake([
            'recherche-entreprises.api.gouv.fr/*' => function ($request) {
                return $this->registreEntreprises
                    ? ($this->registreEntreprises)($request)
                    : Http::response(['results' => [], 'total_results' => 0]);
            },
        ]);
    }
}
