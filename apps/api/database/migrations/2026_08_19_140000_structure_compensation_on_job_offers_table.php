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

        // Reprise VOLONTAIREMENT stricte : on ne convertit que ce qu'on lit
        // sans ambiguite, et on laisse tout le reste tel quel.
        //
        // Une premiere version "au mieux" prenait le premier nombre venu et
        // devinait la periode par sous-chaine. Elle produisait des montants
        // faux sur des offres publiees et payees :
        //   « 800 € / mois + tickets restaurant » -> 800 / an  ('an' dans
        //      « restaurant », comme dans « avantages » ou « alternance »)
        //   « 11,88 € / heure »                   -> 1188 / heure
        //      (la virgule etait supprimee, pas tronquee)
        //   « 35h/semaine, 800 € / mois »         -> 35 / mois
        //      (premier nombre du texte, pas le salaire)
        // Afficher « 800 € brut / an » a la place d'un mensuel, c'est
        // exactement l'information trompeuse que ce changement veut
        // supprimer. Mieux vaut ne pas convertir que convertir faux : les
        // offres non reprises continuent d'afficher leur texte d'origine
        // (voir formatCompensation cote frontend, qui retombe dessus).
        //
        // Forme acceptee : un montant seul, eventuellement suivi d'une devise
        // et d'une periode explicite. Rien d'autre autour.
        $pattern = '/^\s*'
            .'(\d{1,3}(?:[ \x{00A0}\x{202F}]\d{3})*|\d+)'   // 1200, 1 200
            .'(?:[.,]\d{1,2})?'                              // decimales, tronquees
            .'\s*(?:€|eur|euros?)?'                          // devise facultative
            .'\s*(?:'
            .'(?:\/|par)\s*(mois|an(?:nee)?|heure|h)'        // « / mois », « par an »
            .'|(mensuel|annuel|horaire)'                     // ou l\'adjectif seul
            .')?\s*$/iu';

        foreach (DB::table('job_offers')->whereNotNull('compensation')->get(['id', 'compensation']) as $offer) {
            if (! preg_match($pattern, (string) $offer->compensation, $matches)) {
                continue;
            }

            // Separateurs de milliers retires ; les decimales ne sont jamais
            // capturees dans ce groupe, donc jamais concatenees au montant.
            $amount = (int) preg_replace('/\D/', '', $matches[1]);
            if ($amount <= 0) {
                continue;
            }

            $unit = mb_strtolower($matches[2] ?? $matches[3] ?? '');
            $period = match (true) {
                str_starts_with($unit, 'heure'), $unit === 'h', $unit === 'horaire' => 'HOURLY',
                str_starts_with($unit, 'an'), $unit === 'annuel' => 'YEARLY',
                // Periode absente : mensuel, de loin le cas le plus courant
                // en alternance, et la seule hypothese defendable pour un
                // montant a trois ou quatre chiffres.
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
