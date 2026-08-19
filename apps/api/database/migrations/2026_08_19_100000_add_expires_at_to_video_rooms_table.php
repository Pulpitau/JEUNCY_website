<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Duree de vie du lien d'invitation.
//
// Jusqu'ici, /demo/{jitsi_room_name} restait valide indefiniment tant que
// l'hote n'avait pas ferme la salle a la main. Or ce nom de salle EST le seul
// controle d'acces de la page publique (pas d'authentification, un prospect
// sans compte doit pouvoir rejoindre). Un lien transfere, colle dans un ticket
// ou retrouve dans un historique donnait donc un acces permanent.
//
// NULL = pas d'expiration : les salles creees avant cette migration gardent
// leur comportement actuel plutot que d'etre invalidees d'un coup au
// deploiement. Seules les nouvelles salles reçoivent une echeance.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('video_rooms', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('video_rooms', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
