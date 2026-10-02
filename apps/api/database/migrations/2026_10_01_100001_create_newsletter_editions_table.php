<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le contenu d'une edition de la lettre hebdomadaire vit EN BASE.
 *
 * POURQUOI PAS UN FICHIER BLADE PAR SEMAINE. L'envoi est recurrent — chaque
 * lundi. Un texte en fichier aurait voulu dire un envoi FTP par semaine, sur
 * un hebergement mutualise sans SSH, avec le risque d'oubli que ce depot
 * connait deja par coeur (cinq allers-retours en septembre pour des fichiers
 * jamais arrives). Une edition en base se depose depuis le navigateur et
 * l'automatisation tient toute seule.
 *
 * CE QUI RESTE EN BLADE : la coque seulement (en-tete, logo, degrade, pied de
 * page et lien de desinscription). Le pied n'est donc jamais la responsabilite
 * de celui qui ecrit l'edition — on ne peut pas oublier le lien de
 * desinscription, il n'est pas dans le texte qu'on redige.
 *
 * `html` et `text` en longText : une lettre mise en page pour les clients mail
 * est verbeuse (tout le style est inline, voir la coque), et un TEXT MySQL
 * classique plafonne a 64 Ko — assez aujourd'hui, pas assez le jour ou une
 * edition porte trois blocs d'offres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_editions', function (Blueprint $table) {
            $table->id();
            // Identifiant lisible et stable (ex: 2026-10-01-lettre-01). Unique :
            // c'est lui qu'on tape dans une URL ou une commande, et deux
            // editions du meme nom rendraient toute instruction ambigue.
            $table->string('slug', 64)->unique();
            $table->string('subject');
            // Corps seul, sans en-tete ni pied : ceux-la viennent de la coque.
            $table->longText('html');
            $table->longText('text');
            // Valeurs de NewsletterEditionStatus.
            $table->string('status', 12);
            // Horodatages du cycle de vie. prepared_at sert aussi a departager
            // deux editions PRETE : la plus ancienne part la premiere, sans
            // quoi l'ordre dependrait de l'identifiant, qui n'est pas une
            // intention.
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            // Bilan de la passe, lisible sans recompter la table d'envois.
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->index('status', 'newsletter_editions_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_editions');
    }
};
