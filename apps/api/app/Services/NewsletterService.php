<?php

namespace App\Services;

use App\Enums\NewsletterDeliveryStatus;
use App\Enums\NewsletterEditionStatus;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterEdition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Lettre hebdomadaire aux candidats inscrits.
 *
 * LA CONTRAINTE QUI DETERMINE TOUTE LA CONCEPTION : la liste des adresses ne
 * sort jamais du serveur. Pas d'export CSV, pas de copier-coller dans un
 * outil d'emailing, pas de fichier qui traine dans un dossier Telechargements
 * ou dans une piece jointe. Le serveur lit les comptes en base et envoie
 * lui-meme ; personne ne manipule la liste. C'est aussi pour ca qu'aucune
 * route ni aucune sortie de commande ici ne renvoie une adresse : seulement
 * des comptages.
 *
 * L'ENVOI DU LUNDI PART SANS RELECTURE HUMAINE (decision du 2026-10-01). Tout
 * ce qui protege est donc MECANIQUE, et il n'y a pas de second filet :
 *
 *   1. seule une edition PRETE peut partir. Un brouillon ne part jamais, et
 *      une semaine sans edition prete est une semaine sans lettre — jamais
 *      une vieille lettre renvoyee ;
 *   2. une edition ENVOYEE ne repart par aucun chemin ;
 *   3. chaque destinataire servi laisse une ligne en base AVANT l'envoi, donc
 *      une passe rejouee — le cron d'OVH saute et rejoue des passages —
 *      n'ecrit a personne une seconde fois ;
 *   4. un espace reserve non resolu fait echouer la passe entiere plutot que
 *      de laisser partir « [[offres_total]] » chez 125 personnes ;
 *   5. le declenchement manuel reste a blanc par defaut, et l'envoi de masse
 *      demande une confirmation explicite (lecon du 2026-09-08, ou l'oubli
 *      d'un parametre avait expedie 37 notifications a de vrais candidats).
 *
 * PAS DE FILE D'ATTENTE : l'hebergement mutualise OVH n'accepte aucun worker
 * permanent (CLAUDE.md §11). L'envoi est donc synchrone, par lots, avec une
 * pause entre deux messages — et surtout reprenable, parce qu'il SERA coupe.
 */
class NewsletterService
{
    /** Nombre de comptes lus en base par lot. */
    public const LOT = 50;

    /**
     * Pause entre deux messages, en millisecondes.
     *
     * Resend accepte 2 requetes par seconde par defaut : au-dela, il repond
     * 429 et les messages suivants sont simplement perdus. 550 ms laisse une
     * marge sous cette limite. Pour 125 destinataires, cela fait un peu plus
     * d'une minute de pause cumulee — negligeable pour un envoi hebdomadaire,
     * et tres en deca du temps d'execution releve dans la route de
     * deploiement.
     */
    public const PAUSE_ENTRE_ENVOIS_MS = 550;

    /** Longueur du pre-header, extrait du debut du corps texte. */
    private const PREHEADER_LONGUEUR = 140;

    public function __construct(
        private readonly MailService $mailService,
        private readonly NewsletterPlaceholders $placeholders,
    ) {}

    // =================================================================
    // Les editions
    // =================================================================

    /**
     * L'edition que le prochain envoi doit emporter : la plus ancienne
     * armee.
     *
     * La plus ancienne et non la derniere : si deux editions ont ete
     * preparees, c'est celle qui attend depuis le plus longtemps qui part. Se
     * fier a l'identifiant aurait marche la plupart du temps, mais un
     * identifiant n'est pas une intention.
     */
    public function editionPrete(): ?NewsletterEdition
    {
        return NewsletterEdition::query()
            ->where('status', NewsletterEditionStatus::PRETE)
            ->orderBy('prepared_at')
            ->orderBy('id')
            ->first();
    }

    public function editionParSlug(string $slug): NewsletterEdition
    {
        $edition = NewsletterEdition::query()->where('slug', $slug)->first();

        if ($edition === null) {
            throw new ApiException('NEWSLETTER_EDITION_INCONNUE', "Edition inconnue : {$slug}.", 404);
        }

        return $edition;
    }

    /**
     * Depose une edition, ou met a jour une edition existante.
     *
     * TOUJOURS EN BROUILLON : deposer n'envoie rien et n'arme rien. Si le
     * depot pouvait poser PRETE, un formulaire mal rempli armerait l'envoi du
     * lundi a 125 personnes — or depuis le 2026-10-01, plus personne ne relit
     * entre les deux.
     *
     * Une edition deja ENVOYEE n'est jamais modifiable, et une edition dont
     * des destinataires ont deja ete servis non plus : la modifier ferait
     * exister deux lettres differentes sous un meme nom, dont l'une est deja
     * dans des boites mail.
     */
    public function deposer(string $slug, string $subject, string $html, string $text): NewsletterEdition
    {
        $slug = Str::slug($slug) ?: Str::slug(now()->toDateString().'-lettre');
        $edition = NewsletterEdition::query()->where('slug', $slug)->first();

        if ($edition !== null) {
            if ($edition->estEnvoyee()) {
                throw new ApiException(
                    'NEWSLETTER_EDITION_DEJA_ENVOYEE',
                    "L'edition {$slug} est deja partie : elle ne peut plus etre modifiee. Depose-la sous un autre identifiant.",
                    409,
                );
            }

            if ($edition->deliveries()->exists()) {
                throw new ApiException(
                    'NEWSLETTER_EDITION_DEJA_COMMENCEE',
                    "L'edition {$slug} a deja ete envoyee a une partie des destinataires : la modifier ferait exister deux lettres sous le meme nom.",
                    409,
                );
            }

            $edition->fill(compact('subject', 'html', 'text'));
            $edition->status = NewsletterEditionStatus::BROUILLON;
            $edition->prepared_at = null;
            $edition->save();

            return $edition;
        }

        $edition = new NewsletterEdition;
        $edition->fill(compact('slug', 'subject', 'html', 'text'));
        $edition->status = NewsletterEditionStatus::BROUILLON;
        $edition->save();

        return $edition;
    }

    /**
     * Arme une edition (BROUILLON -> PRETE) ou la desarme (PRETE ->
     * BROUILLON).
     *
     * Ces deux-la seulement. ENVOYEE et ECHOUEE sont des CONSTATS poses par
     * la passe d'envoi : les autoriser a la main permettrait de declarer
     * partie une lettre que personne n'a recue, ce qui ne se rattrape pas
     * puisque la table d'envois ferait foi ensuite.
     */
    public function armer(NewsletterEdition $edition, bool $prete): NewsletterEdition
    {
        if ($edition->estEnvoyee()) {
            throw new ApiException(
                'NEWSLETTER_EDITION_DEJA_ENVOYEE',
                "L'edition {$edition->slug} est deja partie.",
                409,
            );
        }

        // Armer, c'est declarer qu'elle peut partir toute seule lundi : on
        // verifie d'abord qu'elle le peut vraiment. Une edition dont un
        // espace reserve est mal ecrit doit etre refusee MAINTENANT, devant
        // celui qui l'arme, pas lundi a 10 h devant personne.
        if ($prete) {
            $this->verifierGabarit($edition);
        }

        $edition->status = $prete ? NewsletterEditionStatus::PRETE : NewsletterEditionStatus::BROUILLON;
        $edition->prepared_at = $prete ? now() : null;
        $edition->save();

        return $edition;
    }

    // =================================================================
    // Le rendu
    // =================================================================

    /**
     * Les deux corps du message, espaces reserves remplis et coque appliquee.
     *
     * $valeurs est calcule UNE fois par passe et passe ici : recalculer pour
     * chaque destinataire ferait annoncer des nombres differents selon le
     * moment ou l'on tombe dans la liste, et surtout differents entre la
     * version HTML et la version texte du meme message.
     *
     * @param  array<string, string>|null  $valeurs
     * @return array{sujet: string, html: string, texte: string}
     */
    public function rendre(NewsletterEdition $edition, string $lienDesinscription, ?array $valeurs = null): array
    {
        $valeurs ??= $this->placeholders->valeurs();

        $corpsHtml = $this->placeholders->remplacer($edition->html, $valeurs);
        $corpsTexte = $this->placeholders->remplacer($edition->text, $valeurs);
        $sujet = $this->placeholders->remplacer($edition->subject, $valeurs);

        $restants = $this->placeholders->nonResolus($sujet, $corpsHtml, $corpsTexte);
        if ($restants !== []) {
            throw new ApiException(
                'NEWSLETTER_GABARIT_NON_RESOLU',
                'Espace(s) reserve(s) inconnu(s) dans l\'edition '.$edition->slug.' : [['
                    .implode(']], [[', $restants).']]. Connus : [['
                    .implode(']], [[', array_keys($this->placeholders::AIDE)).']].',
                422,
            );
        }

        return [
            'sujet' => $sujet,
            'html' => view('emails.newsletter.layout', [
                'corps' => $corpsHtml,
                'preheader' => $this->preheader($corpsTexte),
                'lienDesinscription' => $lienDesinscription,
            ])->render(),
            'texte' => trim(view('emails.newsletter.layout-texte', [
                'corps' => trim($corpsTexte),
                'lienDesinscription' => $lienDesinscription,
            ])->render()),
        ];
    }

    /**
     * Lien de desinscription propre a UN destinataire.
     *
     * SIGNATURE RELATIVE et non absolue : une signature absolue couvre le
     * nom d'hote, donc le meme lien cesse de fonctionner si le site repond
     * sur www.jeuncy.com au lieu de jeuncy.com, ou en http au lieu de https.
     * Un lien de desinscription qui tombe en panne est le genre de defaut
     * qu'on decouvre par une plainte, pas par un test.
     *
     * AUCUNE EXPIRATION, volontairement : une lettre reste des mois dans une
     * boite mail, et le droit d'opposition ne se perime pas. Un lien expire
     * renverrait la personne vers une erreur au moment precis ou elle veut
     * partir — elle cliquerait alors sur « spam », ce qui coute bien plus
     * cher que le risque, nul, qu'un tiers desinscrive quelqu'un.
     */
    public function lienDesinscription(int $userId): string
    {
        $relatif = URL::signedRoute('newsletter.unsubscribe', ['user' => $userId], null, false);

        return rtrim((string) config('app.url'), '/').$relatif;
    }

    // =================================================================
    // Les destinataires
    // =================================================================

    /**
     * Les destinataires d'une edition : candidats non supprimes, non
     * suspendus, non desinscrits, et pas deja servis pour CETTE edition.
     *
     * Les trois premieres regles passent par les scopes existants
     * (`notDeleted`, `newsletterSubscribed`) : les recopier ici aurait cree
     * une seconde definition de « compte joignable », condamnee a diverger
     * de celle qu'applique deja JobOfferMatchService.
     */
    public function destinataires(NewsletterEdition $edition): Builder
    {
        return User::query()
            ->where('role', UserRole::CANDIDATE)
            ->notDeleted()
            ->where('is_suspended', false)
            ->newsletterSubscribed()
            ->whereNotExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('newsletter_deliveries')
                ->whereColumn('newsletter_deliveries.user_id', 'users.id')
                ->where('newsletter_deliveries.newsletter_edition_id', $edition->id));
    }

    /**
     * Mode a blanc : combien partiraient, et qui est ecarte pour quelle
     * raison. Rien n'est envoye, rien n'est ecrit.
     *
     * Les comptages sont CUMULATIFS, dans l'ordre des exclusions, pour qu'ils
     * s'additionnent exactement : total = supprimes + suspendus + desinscrits
     * + deja servis + destinataires. Des comptages independants auraient
     * compte deux fois un compte a la fois suspendu et desinscrit, et le
     * rapport n'aurait plus tombe juste — on l'aurait cru faux au mauvais
     * moment.
     *
     * Le rendu complet est fait ici AUSSI : une erreur de gabarit ou un
     * espace reserve mal ecrit doit se voir pendant la passe a blanc, pas au
     * 37e destinataire d'un envoi reel.
     *
     * @return array<string, mixed>
     */
    public function apercu(NewsletterEdition $edition): array
    {
        $candidats = fn () => User::query()->where('role', UserRole::CANDIDATE);

        $total = $candidats()->count();
        $vivants = $candidats()->notDeleted();
        $nonSupprimes = (clone $vivants)->count();
        $actifs = (clone $vivants)->where('is_suspended', false);
        $nonSuspendus = (clone $actifs)->count();
        $abonnes = (clone $actifs)->newsletterSubscribed()->count();
        $restants = $this->destinataires($edition)->count();

        $valeurs = $this->placeholders->valeurs();
        $rendu = $this->rendre($edition, $this->lienDesinscription(0), $valeurs);

        return [
            'edition' => $edition->slug,
            'statut' => $edition->status->value,
            'sujet' => $rendu['sujet'],
            'expediteur' => config('services.resend.newsletter_from'),
            'cle_resend' => config('services.resend.key') ? 'presente' : 'ABSENTE — aucun envoi possible',
            'valeurs_du_moment' => $valeurs,
            'comptes' => [
                'comptes_candidats' => $total,
                'exclus_supprimes' => $total - $nonSupprimes,
                'exclus_suspendus' => $nonSupprimes - $nonSuspendus,
                'exclus_desinscrits' => $nonSuspendus - $abonnes,
                'exclus_deja_servis_sur_cette_edition' => $abonnes - $restants,
                'destinataires' => $restants,
            ],
            'envois_enregistres' => $this->bilan($edition),
            'gabarit' => [
                'html_octets' => strlen($rendu['html']),
                'texte_octets' => strlen($rendu['texte']),
            ],
            'duree_estimee_s' => (int) ceil($restants * self::PAUSE_ENTRE_ENVOIS_MS / 1000) + $restants,
        ];
    }

    /**
     * Ce que la table de suivi contient deja pour cette edition.
     *
     * @return array<string, int>
     */
    public function bilan(NewsletterEdition $edition): array
    {
        $parStatut = NewsletterDelivery::query()
            ->where('newsletter_edition_id', $edition->id)
            ->selectRaw('status, count(*) as n')
            ->groupBy('status')
            ->pluck('n', 'status')
            ->all();

        return [
            'envoyes' => (int) ($parStatut[NewsletterDeliveryStatus::SENT->value] ?? 0),
            'en_attente_de_resultat' => (int) ($parStatut[NewsletterDeliveryStatus::PENDING->value] ?? 0),
            'echecs' => (int) ($parStatut[NewsletterDeliveryStatus::FAILED->value] ?? 0),
        ];
    }

    // =================================================================
    // L'envoi
    // =================================================================

    /**
     * Envoi reel d'une edition a tous ses destinataires.
     *
     * $confirme N'A PAS DE VALEUR PAR DEFAUT, a dessein : il n'existe aucun
     * appel de cette methode ou l'on puisse oublier de se prononcer. C'est la
     * traduction en code de la regle « un parametre omis ne declenche jamais
     * un envoi de masse ».
     *
     * $max borne la passe : utile pour un premier envoi reel prudent (cinq
     * personnes, on regarde, on continue). La reprise se charge du reste,
     * puisque les cinq premiers portent deja leur ligne.
     *
     * @return array<string, mixed>
     */
    public function envoyer(NewsletterEdition $edition, bool $confirme, ?int $max = null, ?int $pauseMs = null): array
    {
        if (! $confirme) {
            throw new ApiException(
                'NEWSLETTER_CONFIRMATION_REQUISE',
                "L'envoi de masse doit etre demande explicitement. Sans cette confirmation, rien n'est envoye.",
                400,
            );
        }

        if ($edition->estEnvoyee()) {
            throw new ApiException(
                'NEWSLETTER_EDITION_DEJA_ENVOYEE',
                "L'edition {$edition->slug} est deja partie le "
                    .($edition->sent_at?->format('d/m/Y H:i') ?? '?').' : elle ne repart pas.',
                409,
            );
        }

        if ($edition->status === NewsletterEditionStatus::BROUILLON) {
            throw new ApiException(
                'NEWSLETTER_EDITION_NON_PRETE',
                "L'edition {$edition->slug} est encore un brouillon : marque-la PRETE avant de l'envoyer.",
                409,
            );
        }

        // Refuse AVANT d'ecrire quoi que ce soit : sans cle, chaque
        // destinataire recevrait une ligne FAILED, et l'edition entiere
        // serait a nettoyer a la main avant de pouvoir la renvoyer.
        if (! config('services.resend.key')) {
            throw new ApiException(
                'RESEND_API_KEY_ABSENTE',
                "RESEND_API_KEY n'est pas configuree : aucun envoi n'est possible.",
                500,
            );
        }

        // Les chiffres du MOMENT DE L'ENVOI, calcules une seule fois pour
        // toute la passe : deux destinataires du meme message doivent lire le
        // meme nombre, et un import LBA qui tomberait au milieu de la passe ne
        // doit pas couper la liste en deux.
        $valeurs = $this->placeholders->valeurs();
        // Rendu d'epreuve : un espace reserve inconnu leve ici, donc avant la
        // premiere reservation et avant le premier message.
        $this->rendre($edition, $this->lienDesinscription(0), $valeurs);

        $pause = max(0, $pauseMs ?? self::PAUSE_ENTRE_ENVOIS_MS);
        $debut = microtime(true);
        $envoyes = 0;
        $echecs = 0;
        $traites = 0;
        $premier = true;

        $this->destinataires($edition)
            ->orderBy('id')
            ->chunkById(self::LOT, function ($comptes) use ($edition, $valeurs, $max, $pause, &$envoyes, &$echecs, &$traites, &$premier) {
                foreach ($comptes as $compte) {
                    if ($max !== null && $traites >= $max) {
                        return false;
                    }

                    $ligne = $this->reserver($edition, $compte);
                    if ($ligne === null) {
                        continue;
                    }

                    // La pause vient AVANT l'envoi et non apres : apres, la
                    // derniere attente serait pure perte, et une passe
                    // interrompue aurait dormi pour rien.
                    if (! $premier && $pause > 0) {
                        usleep($pause * 1000);
                    }
                    $premier = false;

                    // Le lien est propre a CE compte : il est donc calcule
                    // par destinataire, et les deux corps avec lui.
                    $lien = $this->lienDesinscription($compte->id);
                    $rendu = $this->rendre($edition, $lien, $valeurs);

                    $ok = $this->mailService->sendNewsletter(
                        $compte->email,
                        $rendu['sujet'],
                        $rendu['html'],
                        $rendu['texte'],
                        $lien,
                    );

                    $ligne->update([
                        'status' => $ok ? NewsletterDeliveryStatus::SENT : NewsletterDeliveryStatus::FAILED,
                        'sent_at' => $ok ? now() : null,
                        // Le detail de l'echec vit dans le journal Laravel,
                        // lisible par /deploy/{token}/logs : le recopier ici
                        // dupliquerait une trace qui contient l'adresse.
                        'error' => $ok ? null : 'Refus ou absence de reponse de Resend (detail dans le journal Laravel).',
                    ]);

                    if ($ok) {
                        $envoyes++;
                    } else {
                        $echecs++;
                    }
                    $traites++;
                }

                return true;
            });

        $restants = $this->destinataires($edition)->count();
        $this->cloturer($edition, $envoyes, $echecs, $restants);

        $rapport = [
            'edition' => $edition->slug,
            'statut' => $edition->fresh()->status->value,
            'sujet' => $edition->subject,
            'valeurs_du_moment' => $valeurs,
            'envoyes' => $envoyes,
            'echecs' => $echecs,
            'restants' => $restants,
            'duree_s' => (int) round(microtime(true) - $debut),
        ];

        // Journal : personne ne regarde partir la lettre du lundi, donc la
        // seule facon de constater apres coup ce qui s'est passe est de
        // l'ecrire. Des comptages, jamais une adresse.
        Log::info('Lettre Jeuncy envoyee', $rapport);

        return $rapport;
    }

    /**
     * Essai : une seule copie, a une adresse choisie, sans toucher la liste
     * ni la table de suivi, et quel que soit le statut de l'edition.
     *
     * POURQUOI IL NE LAISSE AUCUNE TRACE. Un essai n'est pas l'edition : s'il
     * ecrivait une ligne, l'adresse d'essai serait ensuite ecartee de l'envoi
     * reel — et si c'est celle d'un vrai candidat, il ne recevrait jamais la
     * lettre a cause du test qui devait la verifier.
     *
     * Le lien de desinscription pointe sur l'identifiant 0, qui n'existe
     * pas : le lien est cliquable et la page s'affiche, mais aucun compte
     * reel ne peut etre desinscrit par un essai.
     *
     * @return array<string, mixed>
     */
    public function envoyerUnEssai(NewsletterEdition $edition, string $adresse): array
    {
        if (! config('services.resend.key')) {
            throw new ApiException(
                'RESEND_API_KEY_ABSENTE',
                "RESEND_API_KEY n'est pas configuree : aucun envoi n'est possible.",
                500,
            );
        }

        $lien = $this->lienDesinscription(0);
        $rendu = $this->rendre($edition, $lien);

        $ok = $this->mailService->sendNewsletter(
            $adresse,
            '[ESSAI] '.$rendu['sujet'],
            $rendu['html'],
            $rendu['texte'],
            $lien,
        );

        return [
            'edition' => $edition->slug,
            'statut' => $edition->status->value,
            'essai' => $ok ? 'envoye' : 'ECHEC (voir le journal Laravel)',
            'aucune_ligne_ecrite' => true,
        ];
    }

    // =================================================================
    // Interne
    // =================================================================

    /**
     * Pose le constat de fin de passe.
     *
     * Tant qu'il reste des destinataires, l'edition garde son statut : la
     * passe a ete coupee (temps d'execution PHP) et la suivante doit pouvoir
     * reprendre. Ce n'est qu'une fois la liste epuisee qu'on tranche — et
     * ECHOUEE plutot qu'ENVOYEE si pas un seul message n'est parti, sans quoi
     * on croirait la lettre distribuee alors que personne ne l'a recue.
     */
    private function cloturer(NewsletterEdition $edition, int $envoyes, int $echecs, int $restants): void
    {
        $edition->sent_count += $envoyes;
        $edition->failed_count += $echecs;

        if ($restants === 0) {
            $edition->status = $edition->sent_count > 0
                ? NewsletterEditionStatus::ENVOYEE
                : NewsletterEditionStatus::ECHOUEE;
            $edition->sent_at = now();
        }

        $edition->save();
    }

    /**
     * Reserve le destinataire AVANT l'envoi, et renvoie null s'il etait deja
     * pris.
     *
     * La contrainte unique en base est l'arbitre, pas une lecture prealable :
     * deux passes lancees en meme temps (le cron et un clic sur la route de
     * deploiement, par exemple) liraient toutes les deux « pas encore servi »
     * avant que l'une n'ecrive. C'est exactement comme ca qu'on envoie deux
     * fois la meme lettre a quelqu'un.
     */
    private function reserver(NewsletterEdition $edition, User $compte): ?NewsletterDelivery
    {
        try {
            return NewsletterDelivery::create([
                'newsletter_edition_id' => $edition->id,
                'user_id' => $compte->id,
                'status' => NewsletterDeliveryStatus::PENDING,
            ]);
        } catch (QueryException) {
            return null;
        }
    }

    /** Leve si l'edition ne peut pas etre rendue (espace reserve inconnu...). */
    private function verifierGabarit(NewsletterEdition $edition): void
    {
        $this->rendre($edition, $this->lienDesinscription(0));
    }

    /**
     * Pre-header : la phrase que Gmail affiche a cote de l'objet. Tiree du
     * debut du corps texte plutot que saisie a part — un champ de plus a
     * remplir est un champ qu'on laisse vide, et Gmail montre alors les
     * premiers mots du HTML, c'est-a-dire du style inline.
     */
    private function preheader(string $corpsTexte): string
    {
        $propre = trim(preg_replace('/\s+/u', ' ', strip_tags($corpsTexte)) ?? '');

        return Str::limit($propre, self::PREHEADER_LONGUEUR);
    }
}
