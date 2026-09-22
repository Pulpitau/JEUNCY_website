<?php

namespace App\Enums;

// Geste du candidat sur une offre partenaire (external_interests, MOBILE.md
// §3.2) : « Je garde » ou « Passer ». Pas de LIKE ici, a dessein : aucun
// match n'est possible sur une offre dont la candidature se fait ailleurs,
// et le mot ne doit pas apparaitre. Pas de pendant TS : seule l'app mobile
// (lot 2) le consomme, avec ses propres libelles.
enum ExternalInterestDecision: string
{
    case KEEP = 'KEEP';
    case PASS = 'PASS';
}
