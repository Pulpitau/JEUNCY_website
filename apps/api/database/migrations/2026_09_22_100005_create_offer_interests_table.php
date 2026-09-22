<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Le coeur du modele match (MOBILE.md §5) : une ligne par couple
// candidat / offre Jeuncy, comme applications (unique sur le couple).
//
//  - candidate_decision / employer_decision : LIKE, PASS ou null (pas encore
//    decide). Match = les deux a LIKE, matched_at pose dans la meme
//    transaction, idempotent. Le dossier reste une Application : cette table
//    n'en remplace pas le sens (« je veux ce poste » reste un geste du
//    candidat).
//  - application_id : le dossier rattache au match, nullOnDelete parce que
//    le retrait d'un dossier ne detruit pas l'historique du match, il le
//    ferme (closed_reason APPLICATION_WITHDRAWN).
//  - closed_at / closed_reason (MatchClosedReason) : archivage, suppression
//    ou expiration de l'offre, retrait du dossier, suppression de compte.
//  - *_notified_at : moment ou chaque partie a ete prevenue du match. Sans
//    worker, c'est l'instant du match lui-meme ; la colonne existe pour une
//    future queue et pour la fenetre d'annulation.
//
// Cascades :
//  - candidate_profile_id : donnee personnelle rattachee au profil, droit a
//    l'effacement (meme raison que applications, 2026_07_17_000010).
//  - job_offer_id : un interet sans offre n'a pas de sens. La fermeture avec
//    notification a l'autre partie se fait AVANT la suppression de l'offre
//    (MatchClosingService), la cascade ne fait que nettoyer.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_offer_id')->constrained()->cascadeOnDelete();
            // Valeurs de InterestDecision (LIKE / PASS), string + enum PHP.
            $table->string('candidate_decision', 4)->nullable();
            $table->string('employer_decision', 4)->nullable();
            $table->timestamp('candidate_decided_at')->nullable();
            $table->timestamp('employer_decided_at')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason', 30)->nullable();
            $table->timestamp('candidate_notified_at')->nullable();
            $table->timestamp('employer_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['candidate_profile_id', 'job_offer_id']);
            // Deck employeur : « pas encore decide par cet employeur pour
            // cette offre » ; quota et pile candidat : « decide par ce
            // candidat, quand » ; Matchs : les lignes matchees.
            $table->index(['job_offer_id', 'employer_decision']);
            $table->index(['candidate_profile_id', 'candidate_decided_at']);
            $table->index('matched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_interests');
    }
};
