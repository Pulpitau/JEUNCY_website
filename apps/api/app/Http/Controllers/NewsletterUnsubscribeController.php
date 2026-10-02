<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Response;

/**
 * Desinscription de la lettre hebdomadaire, depuis le lien de l'email.
 *
 * SANS AUTHENTIFICATION, et c'est le point : la personne clique depuis sa
 * boite mail, ou elle n'est pas connectee — lui demander de se connecter pour
 * pouvoir partir serait a la fois hostile et, dans les faits, une facon de
 * rendre le droit d'opposition inapplicable. L'URL signee (cle de
 * l'application) tient lieu de preuve : elle ne peut pas etre fabriquee, et
 * un identifiant modifie la casse.
 *
 * DEUX ROUTES, ET LE GET N'AGIT PAS. Les antivirus de messagerie, les apercus
 * de lien et les proxys d'entreprise ouvrent les URL contenues dans un email
 * sans que personne n'ait clique : un GET qui desinscrirait couperait la
 * lettre a des gens qui ne l'ont jamais demande, et on ne s'en apercevrait
 * qu'en voyant le nombre de destinataires fondre sans raison. Le GET affiche
 * donc une page avec un bouton, et c'est le POST qui agit — la meme forme que
 * la reinitialisation de mot de passe du projet, ou le lien de l'email ouvre
 * une page et c'est le formulaire qui decide.
 *
 * Ce POST sert AUSSI le « one-click » de Gmail et d'Outlook (RFC 8058) : le
 * bouton natif de la boite mail poste directement a cette adresse, sans
 * afficher la page. Une seule route couvre les deux usages.
 *
 * AUCUNE DONNEE PERSONNELLE N'EST AFFICHEE, pas meme l'adresse concernee :
 * le lien se transfere avec l'email et reste dans l'historique du navigateur.
 */
class NewsletterUnsubscribeController extends Controller
{
    public function show(int $user): Response
    {
        return $this->page(fait: false, userId: $user);
    }

    public function unsubscribe(int $user): Response
    {
        $compte = User::find($user);

        // Idempotent : recliquer, ou que Gmail poste une seconde fois, ne
        // doit ni echouer ni ecraser la date de la premiere opposition —
        // c'est elle qui fait foi.
        if ($compte !== null && $compte->newsletter_unsubscribed_at === null) {
            $compte->newsletter_unsubscribed_at = now();
            $compte->save();
        }

        // Un compte introuvable (supprime entre-temps, ou identifiant d'essai)
        // obtient la meme page : il n'y a rien a lui apprendre, et une erreur
        // ne ferait que l'inquieter pour un message qu'il ne recevra plus de
        // toute facon.
        return $this->page(fait: true, userId: $user);
    }

    private function page(bool $fait, int $userId): Response
    {
        return response()->view('newsletter.unsubscribe', [
            'fait' => $fait,
            // L'action reprend l'URL signee telle qu'elle a ete ouverte : la
            // signature couvre le chemin et la query, pas la methode HTTP,
            // donc le meme lien vaut pour le GET et pour le POST.
            'action' => request()->fullUrl(),
            'contact' => config('services.contact.email'),
        ]);
    }
}
