<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// La remuneration etait un texte libre (« 1200 », « selon profil »...), affiche
// tel quel : pas de symbole, pas d'unite, pas de periode. Un candidat lisant
// « 1200 » ne sait pas s'il s'agit d'un montant mensuel, annuel ou horaire.
//
// Deux colonnes structurees a la place, pour que l'affichage soit fabrique par
// le code et non par ce que l'entreprise a bien voulu taper.
//
// L'ancienne colonne `compensation` est CONSERVEE : les valeurs qui ne se
// laissent pas interpreter (« selon profil », « SMIC + primes ») y restent
// lisibles, et un mauvais parsing ne detruit rien. Elle n'est simplement plus
// ni saisie ni affichee.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            // En euros entiers et non en centimes : un salaire ne s'exprime
            // pas au centime, et le formulaire reste lisible.
            $table->unsignedInteger('compensation_amount')->nullable()->after('compensation');
            $table->enum('compensation_period', ['HOURLY', 'MONTHLY', 'YEARLY'])
                ->nullable()
                ->after('compensation_amount');
        });

        // Reprise au mieux de l'existant : on ne recupere que le premier
        // nombre trouve, et on suppose le mensuel (cas de loin le plus
        // courant en alternance). Le texte d'origine reste en base pour
        // pouvoir corriger a la main si l'interpretation est fausse.
        foreach (DB::table('job_offers')->whereNotNull('compensation')->get(['id', 'compensation']) as $offer) {
            if (! preg_match('/(\d[\d\s.,]*)/', (string) $offer->compensation, $matches)) {
                continue;
            }

            $amount = (int) preg_replace('/\D/', '', $matches[1]);
            if ($amount <= 0) {
                continue;
            }

            $text = mb_strtolower((string) $offer->compensation);
            $period = match (true) {
                str_contains($text, 'heure'), str_contains($text, '/h'), str_contains($text, 'horaire') => 'HOURLY',
                str_contains($text, 'an'), str_contains($text, 'annuel') => 'YEARLY',
                default => 'MONTHLY',
            };

            DB::table('job_offers')->where('id', $offer->id)->update([
                'compensation_amount' => $amount,
                'compensation_period' => $period,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropColumn(['compensation_amount', 'compensation_period']);
        });
    }
};
