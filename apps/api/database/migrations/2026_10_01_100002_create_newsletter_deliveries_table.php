<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une ligne par edition et par destinataire : c'est ce qui rend l'envoi
 * REPRENABLE, et ce qui interdit d'ecrire deux fois a la meme personne.
 *
 * Le temps d'execution de PHP sur l'hebergement mutualise coupe une passe en
 * cours de route, et le cron d'OVH est horaire et saute des passages. Sans
 * cette table, reprendre un envoi interrompu signifierait reecrire a tous
 * ceux qui ont deja recu la lettre — visible immediatement par de vraies
 * personnes, et irreparable. Depuis que l'envoi du lundi part sans relecture
 * humaine, c'est la garde mecanique la plus importante du dispositif.
 *
 * L'ADRESSE EMAIL N'EST PAS STOCKEE ICI : `user_id` suffit a savoir qui a
 * recu quoi. La dupliquer ajouterait une seconde copie de la liste a
 * proteger, pour rien.
 *
 * Nom d'index EXPLICITE : MySQL limite les identifiants a 64 caracteres et
 * SQLite n'a pas cette limite — le 2026-09-22, une suite de 807 tests verts
 * n'a pas empeche la migration d'echouer en production pour cette seule
 * raison. Ici le nom genere serait
 * `newsletter_deliveries_newsletter_edition_id_user_id_unique` (58
 * caracteres) : il tiendrait, de justesse. La regle ne vaut que si on
 * l'applique sans d'abord compter les caracteres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_deliveries', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete sur l'edition : supprimer une edition emporte
            // ses traces d'envoi, qui n'ont plus de sens sans elle.
            $table->foreignId('newsletter_edition_id')->constrained()->cascadeOnDelete();
            // cascadeOnDelete : trace d'envoi rattachee a une personne, elle
            // disparait avec son compte (droit a l'effacement RGPD). Rien ne
            // s'y perd : un compte supprime n'est plus destinataire.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Valeurs de NewsletterDeliveryStatus (PENDING / SENT / FAILED).
            $table->string('status', 10);
            // Message d'erreur, tronque : il sert a comprendre un echec, pas
            // a rejouer la requete.
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // LA garde anti-doublon, et elle est en BASE : une seconde passe
            // lancee en parallele de la premiere se heurte a la contrainte
            // plutot qu'a une verification applicative qui aurait pu lire
            // avant que l'autre n'ecrive.
            $table->unique(['newsletter_edition_id', 'user_id'], 'newsletter_deliveries_edition_user_unique');
            $table->index(['newsletter_edition_id', 'status'], 'newsletter_deliveries_edition_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_deliveries');
    }
};
