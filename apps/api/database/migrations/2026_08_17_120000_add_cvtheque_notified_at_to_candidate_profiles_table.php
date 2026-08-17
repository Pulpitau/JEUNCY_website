<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Horodatage de l'email annoncant l'ouverture de la CVtheque aux candidats
    // deja inscrits (voir MailService::sendCvthequeAnnouncementEmail).
    //
    // Colonne plutot qu'un simple filtre sur created_at : c'est ce qui rend
    // l'envoi unique VERIFIABLE. Un operateur qui relance la commande par
    // acquit de conscience — ou un cron mal configure — ne peut pas renvoyer
    // l'email, et on peut prouver a posteriori qui a ete informe et quand,
    // ce qu'un simple "je crois l'avoir lance" ne permet pas en cas de
    // reclamation RGPD.
    //
    // Nullable : les candidats inscrits APRES l'ouverture voient la mention
    // directement sur le formulaire d'inscription, ils n'ont pas a etre
    // notifies et gardent donc null.
    public function up(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->timestamp('cvtheque_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('candidate_profiles', function (Blueprint $table) {
            $table->dropColumn('cvtheque_notified_at');
        });
    }
};
