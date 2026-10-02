<?php

namespace Tests\Feature;

use App\Enums\ExternalJobOfferStatus;
use App\Enums\JobOfferStatus;
use App\Enums\NewsletterDeliveryStatus;
use App\Enums\NewsletterEditionStatus;
use App\Exceptions\ApiException;
use App\Models\ExternalJobOffer;
use App\Models\JobOffer;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterEdition;
use App\Models\User;
use App\Services\MailService;
use App\Services\NewsletterPlaceholders;
use App\Services\NewsletterService;
use App\Support\OffersCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

/**
 * La lettre hebdomadaire : editions, selection des destinataires, garde-fous
 * d'envoi, reprise, substitution des chiffres, et desinscription.
 *
 * AUCUN TEST N'ENVOIE DE VRAI MAIL. MailService est remplace par un mock dans
 * presque tous les cas ; les deux tests qui exercent le vrai service passent
 * par le stub Resend de Tests\TestCase, qui intercepte l'appel HTTP. Les
 * adresses utilisees viennent des factories, jamais de la production.
 *
 * CE QUI COMPTE ICI, dans l'ordre de ce que ca couterait si ca cassait :
 *   1. ecrire deux fois a la meme personne (une passe rejouee) ;
 *   2. laisser partir un espace reserve non remplace chez 125 personnes ;
 *   3. ecrire a quelqu'un qui a dit non, ou dont le compte est suspendu ;
 *   4. declencher un envoi de masse sur un parametre oublie ;
 *   5. un lien de desinscription qui ne desinscrit pas.
 */
class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'secret-token';

    private $mail;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mail = Mockery::mock(MailService::class);
        $this->app->instance(MailService::class, $this->mail);

        // phpunit.xml vide RESEND_API_KEY pour que rien ne parte jamais ; les
        // tests d'envoi doivent donc poser une cle factice, sinon le service
        // refuse de commencer (ce qui est teste explicitement plus bas).
        Config::set('services.resend.key', 'cle-de-test');
        Config::set('app.deploy_token', self::TOKEN);
    }

    private function service(): NewsletterService
    {
        return $this->app->make(NewsletterService::class);
    }

    private function edition(string $etat = 'prete'): NewsletterEdition
    {
        return NewsletterEdition::factory()->{$etat}()->create();
    }

    /**
     * Repose le statut d'une edition a la main.
     *
     * Pas via update() : `status` n'est volontairement pas fillable (voir le
     * modele), donc un mass-assignment l'ignorerait EN SILENCE — le test
     * passerait alors pour une raison fausse, en exercant un autre etat que
     * celui qu'il croit exercer.
     */
    private function forcerStatut(NewsletterEdition $edition, NewsletterEditionStatus $statut): NewsletterEdition
    {
        $edition->status = $statut;
        $edition->save();

        return $edition->fresh();
    }

    // =================================================================
    // 1. Qui est destinataire, et qui ne l'est pas
    // =================================================================

    public function test_an_active_candidate_is_a_recipient(): void
    {
        $candidat = User::factory()->candidate()->create();

        $destinataires = $this->service()->destinataires($this->edition())->pluck('id')->all();

        $this->assertSame([$candidat->id], $destinataires);
    }

    // Un compte suspendu, un compte supprime et un desinscrit sont ecartes
    // pour trois raisons differentes, mais le resultat doit etre le meme :
    // zero destinataire. Un seul test les couvre ensemble parce que c'est
    // ensemble qu'ils arrivent dans la vraie base.
    public function test_suspended_deleted_and_unsubscribed_candidates_are_never_recipients(): void
    {
        User::factory()->candidate()->suspended()->create();
        User::factory()->candidate()->create(['deleted_account_at' => now()]);
        User::factory()->candidate()->create(['newsletter_unsubscribed_at' => now()->subDay()]);

        $this->assertSame(0, $this->service()->destinataires($this->edition())->count());
    }

    // La lettre s'adresse aux candidats. Une entreprise, un CFA ou un admin
    // n'ont rien demande et ne doivent pas la recevoir.
    public function test_non_candidate_accounts_are_never_recipients(): void
    {
        User::factory()->company()->create();
        User::factory()->cfa()->create();
        User::factory()->admin()->create();

        $this->assertSame(0, $this->service()->destinataires($this->edition())->count());
    }

    // Le cas le plus courant en production : un compte cree, profil jamais
    // complete. C'est a lui qu'on a le plus de raisons d'ecrire, et une
    // selection basee sur candidate_profiles l'aurait manque.
    public function test_a_candidate_without_a_profile_is_still_a_recipient(): void
    {
        $candidat = User::factory()->candidate()->create();

        $this->assertNull($candidat->candidateProfile);
        $this->assertSame(1, $this->service()->destinataires($this->edition())->count());
    }

    // =================================================================
    // 2. Les editions et leur cycle de vie
    // =================================================================

    public function test_a_deposited_edition_is_always_a_draft(): void
    {
        $edition = $this->service()->deposer('2026-10-06-lettre-02', 'Objet', '<p>corps</p>', 'corps');

        $this->assertSame(NewsletterEditionStatus::BROUILLON, $edition->status);
        $this->assertNull($edition->prepared_at);
    }

    // Deposer n'envoie rien : c'est la propriete qui permet d'ecrire la
    // lettre tranquillement, et de l'armer seulement quand elle est finie.
    public function test_depositing_an_edition_sends_nothing(): void
    {
        User::factory()->candidate()->count(3)->create();
        $this->mail->shouldNotReceive('sendNewsletter');

        $this->service()->deposer('2026-10-06-lettre-02', 'Objet', '<p>corps</p>', 'corps');

        $this->assertDatabaseCount('newsletter_deliveries', 0);
    }

    public function test_a_draft_edition_is_never_sent(): void
    {
        User::factory()->candidate()->create();
        $this->mail->shouldNotReceive('sendNewsletter');

        try {
            $this->service()->envoyer($this->edition('brouillon'), confirme: true, pauseMs: 0);
            $this->fail('Un brouillon ne doit jamais partir.');
        } catch (ApiException $e) {
            $this->assertSame('NEWSLETTER_EDITION_NON_PRETE', $e->errorCode);
        }

        $this->assertDatabaseCount('newsletter_deliveries', 0);
    }

    public function test_a_sent_edition_never_goes_out_again(): void
    {
        User::factory()->candidate()->create();
        $this->mail->shouldNotReceive('sendNewsletter');

        try {
            $this->service()->envoyer($this->edition('envoyee'), confirme: true, pauseMs: 0);
            $this->fail('Une edition deja envoyee ne doit jamais repartir.');
        } catch (ApiException $e) {
            $this->assertSame('NEWSLETTER_EDITION_DEJA_ENVOYEE', $e->errorCode);
        }
    }

    public function test_a_ready_edition_becomes_sent(): void
    {
        User::factory()->candidate()->count(2)->create();
        $edition = $this->edition();
        $this->mail->shouldReceive('sendNewsletter')->times(2)->andReturn(true);

        $this->service()->envoyer($edition, confirme: true, pauseMs: 0);

        $edition->refresh();
        $this->assertSame(NewsletterEditionStatus::ENVOYEE, $edition->status);
        $this->assertNotNull($edition->sent_at);
        $this->assertSame(2, $edition->sent_count);
    }

    // Une passe qui n'a fait partir AUCUN message n'est pas un envoi reussi.
    // Les confondre aurait laisse croire la lettre distribuee alors que
    // personne ne l'a recue.
    public function test_an_edition_whose_every_send_failed_is_marked_failed(): void
    {
        User::factory()->candidate()->create();
        $edition = $this->edition();
        $this->mail->shouldReceive('sendNewsletter')->once()->andReturn(false);

        $this->service()->envoyer($edition, confirme: true, pauseMs: 0);

        $this->assertSame(NewsletterEditionStatus::ECHOUEE, $edition->fresh()->status);
    }

    // Modifier une edition deja partie ferait exister deux lettres sous le
    // meme nom, dont l'une est deja dans des boites mail.
    public function test_a_sent_edition_cannot_be_edited(): void
    {
        $edition = $this->edition('envoyee');

        try {
            $this->service()->deposer($edition->slug, 'Autre objet', '<p>autre</p>', 'autre');
            $this->fail('Une edition envoyee ne doit pas pouvoir etre modifiee.');
        } catch (ApiException $e) {
            $this->assertSame('NEWSLETTER_EDITION_DEJA_ENVOYEE', $e->errorCode);
        }
    }

    // Armer, c'est accepter qu'elle parte seule. Une edition dont un espace
    // reserve est mal ecrit doit etre refusee a CE moment-la, devant celui
    // qui arme — pas plus tard, devant personne.
    public function test_arming_refuses_an_edition_with_an_unknown_placeholder(): void
    {
        $edition = NewsletterEdition::factory()->avecGabaritCasse()->create();

        try {
            $this->service()->armer($edition, true);
            $this->fail('Une edition au gabarit casse ne doit pas pouvoir etre armee.');
        } catch (ApiException $e) {
            $this->assertSame('NEWSLETTER_GABARIT_NON_RESOLU', $e->errorCode);
        }

        $this->assertSame(NewsletterEditionStatus::BROUILLON, $edition->fresh()->status);
    }

    public function test_arming_and_disarming_an_edition(): void
    {
        $edition = $this->edition('brouillon');

        $this->assertSame(NewsletterEditionStatus::PRETE, $this->service()->armer($edition, true)->status);
        $this->assertNotNull($edition->fresh()->prepared_at);

        $this->assertSame(NewsletterEditionStatus::BROUILLON, $this->service()->armer($edition, false)->status);
        $this->assertNull($edition->fresh()->prepared_at);
    }

    // La plus ancienne armee, pas la derniere deposee : c'est celle qui
    // attend depuis le plus longtemps qui part.
    public function test_the_oldest_prepared_edition_is_the_one_that_goes_out(): void
    {
        $ancienne = NewsletterEdition::factory()->create([
            'status' => NewsletterEditionStatus::PRETE,
            'prepared_at' => now()->subDays(3),
        ]);
        NewsletterEdition::factory()->create([
            'status' => NewsletterEditionStatus::PRETE,
            'prepared_at' => now(),
        ]);

        $this->assertSame($ancienne->id, $this->service()->editionPrete()->id);
    }

    public function test_there_is_no_ready_edition_when_every_edition_is_a_draft(): void
    {
        NewsletterEdition::factory()->count(2)->create();

        $this->assertNull($this->service()->editionPrete());
    }

    // =================================================================
    // 3. Les chiffres du moment de l'envoi
    // =================================================================

    private function offres(int $jeuncy, int $partenaires): void
    {
        Cache::forget(OffersCount::CLE_CACHE);
        JobOffer::query()->delete();
        ExternalJobOffer::query()->delete();

        if ($jeuncy > 0) {
            JobOffer::factory()->count($jeuncy)->create(['status' => JobOfferStatus::PUBLISHED]);
        }
        if ($partenaires > 0) {
            ExternalJobOffer::factory()->count($partenaires)->create(['status' => ExternalJobOfferStatus::ACTIVE]);
        }
    }

    public function test_placeholders_are_filled_with_the_current_counts(): void
    {
        $this->offres(jeuncy: 2, partenaires: 3);
        $edition = NewsletterEdition::factory()->prete()->avecCompteur()->create();

        $rendu = $this->service()->rendre($edition, 'https://jeuncy.test/u/1');

        $this->assertStringContainsString('Il y a 5 offres en ligne.', $rendu['texte']);
        $this->assertStringContainsString('Il y a 5 offres en ligne.', $rendu['html']);
    }

    // LE test du lot : l'edition est redigee un jour, envoyee un autre, et
    // c'est le chiffre de l'ENVOI qui doit apparaitre. Le stock partenaire a
    // perdu 956 offres en neuf jours — un nombre fige serait deja faux.
    public function test_the_number_is_the_one_at_send_time_not_at_deposit_time(): void
    {
        $this->offres(jeuncy: 1, partenaires: 1);
        $edition = NewsletterEdition::factory()->prete()->avecCompteur()->create();

        // Le monde bouge entre le depot et l'envoi.
        $this->offres(jeuncy: 4, partenaires: 6);

        $capture = null;
        User::factory()->candidate()->create();
        $this->mail->shouldReceive('sendNewsletter')
            ->once()
            ->andReturnUsing(function ($to, $sujet, $html, $texte) use (&$capture) {
                $capture = $texte;

                return true;
            });

        $this->service()->envoyer($edition, confirme: true, pauseMs: 0);

        $this->assertStringContainsString('Il y a 10 offres en ligne.', $capture);
        $this->assertStringNotContainsString('Il y a 2 offres', $capture);
    }

    public function test_jeuncy_and_partner_offers_can_be_announced_separately(): void
    {
        $this->offres(jeuncy: 2, partenaires: 7);
        $edition = NewsletterEdition::factory()->prete()->create([
            'html' => '<p>[[offres_jeuncy]] chez nous, [[offres_partenaires]] chez nos partenaires.</p>',
            'text' => '[[offres_jeuncy]] chez nous, [[offres_partenaires]] chez nos partenaires.',
        ]);

        $rendu = $this->service()->rendre($edition, 'https://jeuncy.test/u/1');

        $this->assertStringContainsString('2 chez nous, 7 chez nos partenaires.', $rendu['texte']);
    }

    // Separateur de milliers insecable : sans lui, « 6 823 » peut se couper
    // en fin de ligne et se lire comme deux nombres.
    public function test_thousands_are_separated_by_a_non_breaking_space(): void
    {
        $this->offres(jeuncy: 0, partenaires: 0);
        Cache::forget(OffersCount::CLE_CACHE);
        Cache::put(OffersCount::CLE_CACHE, ['jeuncy' => 823, 'partenaires' => 6000, 'total' => 6823], 600);

        $valeurs = (new NewsletterPlaceholders)->valeurs();

        $this->assertSame("6\u{00A0}823", $valeurs['offres_total']);
        $this->assertStringNotContainsString('6823', $valeurs['offres_total']);
    }

    // Le point le plus important sans relecture humaine : une lettre ne doit
    // JAMAIS partir en montrant sa tuyauterie.
    public function test_an_unknown_placeholder_stops_the_send(): void
    {
        User::factory()->candidate()->create();
        $edition = NewsletterEdition::factory()->prete()->avecGabaritCasse()->create();
        $this->mail->shouldNotReceive('sendNewsletter');

        try {
            $this->service()->envoyer($edition, confirme: true, pauseMs: 0);
            $this->fail('Un espace reserve inconnu doit faire echouer l envoi.');
        } catch (ApiException $e) {
            $this->assertSame('NEWSLETTER_GABARIT_NON_RESOLU', $e->errorCode);
            $this->assertStringContainsString('ofres_total', $e->getMessage());
        }

        // Et surtout : rien n'a ete reserve, donc l'edition reste envoyable
        // une fois corrigee.
        $this->assertDatabaseCount('newsletter_deliveries', 0);
        $this->assertSame(NewsletterEditionStatus::PRETE, $edition->fresh()->status);
    }

    // Le compteur de la lettre et celui du site sont le MEME calcul : deux
    // requetes separees auraient fini par se contredire sous les yeux du
    // candidat, qui recoit l'email puis ouvre le site.
    public function test_the_letter_and_the_site_announce_the_same_number(): void
    {
        $this->offres(jeuncy: 3, partenaires: 4);

        // La reponse publique est enveloppee par WrapApiResponse
        // (CONVENTIONS.md §6) : le total vit sous `data`.
        $siteTotal = $this->getJson('/api/job-offers/count')->assertOk()->json('data.total');
        $lettre = (new NewsletterPlaceholders)->valeurs()['offres_total'];

        $this->assertSame((string) $siteTotal, $lettre);
    }

    // =================================================================
    // 4. Le mode a blanc
    // =================================================================

    public function test_the_dry_run_sends_nothing_and_writes_nothing(): void
    {
        User::factory()->candidate()->count(3)->create();
        $this->mail->shouldNotReceive('sendNewsletter');

        $apercu = $this->service()->apercu($this->edition());

        $this->assertSame(3, $apercu['comptes']['destinataires']);
        $this->assertDatabaseCount('newsletter_deliveries', 0);
    }

    // Les comptages doivent s'additionner exactement : un rapport qui ne
    // tombe pas juste fait douter de tout le reste au mauvais moment.
    public function test_the_dry_run_counts_add_up(): void
    {
        User::factory()->candidate()->count(2)->create();
        User::factory()->candidate()->suspended()->create();
        User::factory()->candidate()->create(['deleted_account_at' => now()]);
        User::factory()->candidate()->create(['newsletter_unsubscribed_at' => now()]);

        $c = $this->service()->apercu($this->edition())['comptes'];

        $this->assertSame(5, $c['comptes_candidats']);
        $this->assertSame(1, $c['exclus_supprimes']);
        $this->assertSame(1, $c['exclus_suspendus']);
        $this->assertSame(1, $c['exclus_desinscrits']);
        $this->assertSame(0, $c['exclus_deja_servis_sur_cette_edition']);
        $this->assertSame(2, $c['destinataires']);
        $this->assertSame(
            $c['comptes_candidats'],
            $c['exclus_supprimes'] + $c['exclus_suspendus'] + $c['exclus_desinscrits']
                + $c['exclus_deja_servis_sur_cette_edition'] + $c['destinataires'],
        );
    }

    // =================================================================
    // 5. L'envoi de masse et ses garde-fous
    // =================================================================

    public function test_a_mass_send_is_refused_without_the_explicit_flag(): void
    {
        User::factory()->candidate()->create();
        $this->mail->shouldNotReceive('sendNewsletter');

        try {
            $this->service()->envoyer($this->edition(), confirme: false);
            $this->fail('Un envoi non confirme doit etre refuse.');
        } catch (ApiException $e) {
            $this->assertSame('NEWSLETTER_CONFIRMATION_REQUISE', $e->errorCode);
        }

        $this->assertDatabaseCount('newsletter_deliveries', 0);
    }

    // Sans cle Resend, on refuse AVANT d'ecrire : sinon toute l'edition
    // serait marquee en echec et il faudrait nettoyer la table a la main
    // avant de pouvoir la renvoyer.
    public function test_nothing_is_written_when_the_api_key_is_missing(): void
    {
        Config::set('services.resend.key', null);
        User::factory()->candidate()->create();

        try {
            $this->service()->envoyer($this->edition(), confirme: true, pauseMs: 0);
            $this->fail('Sans cle Resend, l envoi doit etre refuse.');
        } catch (ApiException $e) {
            $this->assertSame('RESEND_API_KEY_ABSENTE', $e->errorCode);
        }

        $this->assertDatabaseCount('newsletter_deliveries', 0);
    }

    public function test_a_real_send_reaches_every_recipient_once(): void
    {
        User::factory()->candidate()->count(3)->create();
        $this->mail->shouldReceive('sendNewsletter')->times(3)->andReturn(true);

        $resultat = $this->service()->envoyer($this->edition(), confirme: true, pauseMs: 0);

        $this->assertSame(3, $resultat['envoyes']);
        $this->assertSame(0, $resultat['echecs']);
        $this->assertSame(0, $resultat['restants']);
        $this->assertDatabaseCount('newsletter_deliveries', 3);
    }

    // =================================================================
    // 6. La reprise — le point le plus cher si ca casse
    // =================================================================

    public function test_a_replayed_pass_writes_to_nobody(): void
    {
        User::factory()->candidate()->count(3)->create();
        $edition = $this->edition();
        $this->mail->shouldReceive('sendNewsletter')->times(3)->andReturn(true);

        $this->service()->envoyer($edition, confirme: true, pauseMs: 0);

        // Deuxieme passe : l'edition est passee a ENVOYEE, donc elle est
        // refusee d'entree — la premiere des deux gardes.
        try {
            $this->service()->envoyer($edition->fresh(), confirme: true, pauseMs: 0);
            $this->fail('Une seconde passe ne doit rien renvoyer.');
        } catch (ApiException $e) {
            $this->assertSame('NEWSLETTER_EDITION_DEJA_ENVOYEE', $e->errorCode);
        }

        $this->assertDatabaseCount('newsletter_deliveries', 3);
    }

    // La seconde garde, independante de la premiere : meme si l'edition
    // etait encore armee (passe coupee), personne n'est servi deux fois.
    // C'est elle qui tient quand le cron rejoue un passage.
    public function test_a_replayed_pass_on_a_still_armed_edition_writes_to_nobody(): void
    {
        User::factory()->candidate()->count(3)->create();
        $edition = $this->edition();
        $this->mail->shouldReceive('sendNewsletter')->times(3)->andReturn(true);

        $this->service()->envoyer($edition, confirme: true, pauseMs: 0);

        // On remet l'edition en PRETE a la main, comme si la passe avait ete
        // coupee avant la cloture : plus un seul appel n'est autorise.
        $second = $this->service()->envoyer(
            $this->forcerStatut($edition, NewsletterEditionStatus::PRETE),
            confirme: true,
            pauseMs: 0,
        );

        $this->assertSame(0, $second['envoyes']);
        $this->assertDatabaseCount('newsletter_deliveries', 3);
    }

    // Une passe bornee s'arrete, et la suivante reprend EXACTEMENT ou elle
    // s'est arretee : c'est ce qui rend une coupure de PHP sans consequence.
    public function test_an_interrupted_pass_resumes_where_it_stopped(): void
    {
        User::factory()->candidate()->count(5)->create();
        $edition = $this->edition();
        $this->mail->shouldReceive('sendNewsletter')->times(2)->andReturn(true);

        $premier = $this->service()->envoyer($edition, confirme: true, max: 2, pauseMs: 0);
        $this->assertSame(2, $premier['envoyes']);
        $this->assertSame(3, $premier['restants']);
        // Toujours armee : la passe n'est pas finie.
        $this->assertSame(NewsletterEditionStatus::PRETE, $edition->fresh()->status);

        $this->mail->shouldReceive('sendNewsletter')->times(3)->andReturn(true);
        $second = $this->service()->envoyer($edition->fresh(), confirme: true, pauseMs: 0);

        $this->assertSame(3, $second['envoyes']);
        $this->assertSame(0, $second['restants']);
        $this->assertSame(5, $edition->fresh()->sent_count);
        $this->assertDatabaseCount('newsletter_deliveries', 5);
    }

    // Un echec laisse une trace FAILED et, surtout, n'est pas reessaye tout
    // seul : renvoyer en boucle a une adresse qui refuse abime la reputation
    // du domaine pour tous les autres.
    public function test_a_failed_send_is_recorded_and_not_retried(): void
    {
        $candidat = User::factory()->candidate()->create();
        $edition = $this->edition();
        $this->mail->shouldReceive('sendNewsletter')->once()->andReturn(false);

        $resultat = $this->service()->envoyer($edition, confirme: true, pauseMs: 0);

        $this->assertSame(1, $resultat['echecs']);
        $this->assertDatabaseHas('newsletter_deliveries', [
            'user_id' => $candidat->id,
            'status' => NewsletterDeliveryStatus::FAILED->value,
            'sent_at' => null,
        ]);

        // Seconde passe : aucun appel supplementaire n'est autorise.
        $rearmee = $this->forcerStatut($edition, NewsletterEditionStatus::PRETE);
        $this->assertSame(0, $this->service()->envoyer($rearmee, confirme: true, pauseMs: 0)['envoyes']);
    }

    public function test_a_pending_row_is_never_served_twice(): void
    {
        $candidat = User::factory()->candidate()->create();
        $edition = $this->edition();

        // Simule une passe coupee entre la reservation et la reponse de
        // Resend : on ne sait pas si le message est parti. En cas de doute,
        // on ne reecrit pas.
        NewsletterDelivery::create([
            'newsletter_edition_id' => $edition->id,
            'user_id' => $candidat->id,
            'status' => NewsletterDeliveryStatus::PENDING,
        ]);

        $this->mail->shouldNotReceive('sendNewsletter');

        $this->assertSame(0, $this->service()->envoyer($edition, confirme: true, pauseMs: 0)['envoyes']);
    }

    // Un destinataire desinscrit APRES avoir recu la lettre precedente ne
    // doit plus rien recevoir de la suivante.
    public function test_unsubscribing_between_two_editions_stops_the_next_one(): void
    {
        $candidat = User::factory()->candidate()->create();
        $candidat->newsletter_unsubscribed_at = now();
        $candidat->save();

        $this->mail->shouldNotReceive('sendNewsletter');

        $this->assertSame(0, $this->service()->envoyer($this->edition(), confirme: true, pauseMs: 0)['envoyes']);
    }

    public function test_the_tracking_table_never_stores_the_email_address(): void
    {
        $candidat = User::factory()->candidate()->create();
        $this->mail->shouldReceive('sendNewsletter')->once()->andReturn(true);

        $this->service()->envoyer($this->edition(), confirme: true, pauseMs: 0);

        $colonnes = array_keys(NewsletterDelivery::where('user_id', $candidat->id)->firstOrFail()->getAttributes());

        $this->assertNotContains('email', $colonnes);
    }

    // =================================================================
    // 7. Le lien de desinscription
    // =================================================================

    public function test_the_signed_link_unsubscribes_on_post(): void
    {
        $candidat = User::factory()->candidate()->create();
        $lien = $this->service()->lienDesinscription($candidat->id);

        $this->post($lien)->assertOk();

        $this->assertNotNull($candidat->fresh()->newsletter_unsubscribed_at);
    }

    // Le GET affiche la page mais ne desinscrit PAS : les antivirus de
    // messagerie et les apercus de lien ouvrent les URL d'un email sans que
    // personne n'ait clique.
    public function test_opening_the_link_does_not_unsubscribe(): void
    {
        $candidat = User::factory()->candidate()->create();

        $this->get($this->service()->lienDesinscription($candidat->id))
            ->assertOk()
            ->assertSee('Confirmer la désinscription', escape: false);

        $this->assertNull($candidat->fresh()->newsletter_unsubscribed_at);
    }

    // La page ne doit jamais afficher l'adresse concernee : le lien se
    // transfere avec l'email et reste dans l'historique du navigateur.
    public function test_the_page_never_shows_the_email_address(): void
    {
        $candidat = User::factory()->candidate()->create(['email' => 'destinataire-de-test@example.com']);

        $this->get($this->service()->lienDesinscription($candidat->id))
            ->assertOk()
            ->assertDontSee('destinataire-de-test@example.com');
    }

    public function test_a_tampered_link_is_refused(): void
    {
        $victime = User::factory()->candidate()->create();
        $autre = User::factory()->candidate()->create();

        // L'identifiant est change dans une URL par ailleurs valide : c'est
        // la tentative la plus evidente, desinscrire quelqu'un d'autre.
        $trafique = str_replace(
            '/newsletter/desinscription/'.$victime->id.'?',
            '/newsletter/desinscription/'.$autre->id.'?',
            $this->service()->lienDesinscription($victime->id),
        );

        $this->post($trafique)->assertForbidden();

        $this->assertNull($autre->fresh()->newsletter_unsubscribed_at);
        $this->assertNull($victime->fresh()->newsletter_unsubscribed_at);
    }

    public function test_a_link_without_any_signature_is_refused(): void
    {
        $candidat = User::factory()->candidate()->create();

        $this->post('/newsletter/desinscription/'.$candidat->id)->assertForbidden();

        $this->assertNull($candidat->fresh()->newsletter_unsubscribed_at);
    }

    // Idempotence : Gmail peut poster deux fois, et la personne peut
    // recliquer. La premiere date est celle qui fait foi — c'est elle qui
    // prouve quand l'opposition a ete exprimee.
    public function test_unsubscribing_twice_keeps_the_first_date(): void
    {
        $candidat = User::factory()->candidate()->create();
        $lien = $this->service()->lienDesinscription($candidat->id);

        $this->post($lien)->assertOk();
        $premiere = $candidat->fresh()->newsletter_unsubscribed_at;

        $this->travel(2)->days();
        $this->post($lien)->assertOk();

        $this->assertEquals($premiere, $candidat->fresh()->newsletter_unsubscribed_at);
    }

    // La signature est relative : le meme lien doit fonctionner quel que soit
    // l'hote qui sert la requete (jeuncy.com, www.jeuncy.com, api.jeuncy.com).
    // Une signature absolue aurait casse tous les liens deja partis le jour
    // ou l'hote change.
    public function test_the_link_survives_a_different_host(): void
    {
        $candidat = User::factory()->candidate()->create();
        $lien = $this->service()->lienDesinscription($candidat->id);

        $relatif = (string) parse_url($lien, PHP_URL_PATH).'?'.parse_url($lien, PHP_URL_QUERY);

        $this->post('https://un-autre-hote.example.com'.$relatif)->assertOk();

        $this->assertNotNull($candidat->fresh()->newsletter_unsubscribed_at);
    }

    // Le lien doit etre absolu dans l'email : un chemin relatif n'est
    // cliquable dans aucun client mail.
    public function test_the_unsubscribe_link_is_absolute(): void
    {
        $lien = $this->service()->lienDesinscription(1);

        $this->assertStringStartsWith(rtrim((string) config('app.url'), '/'), $lien);
        $this->assertStringContainsString('signature=', $lien);
    }

    public function test_the_generated_link_matches_the_route_signature(): void
    {
        $attendu = URL::signedRoute('newsletter.unsubscribe', ['user' => 42], null, false);

        $this->assertStringEndsWith($attendu, $this->service()->lienDesinscription(42));
    }

    // Le lien de desinscription vient de la coque, pas de l'edition : c'est
    // ce qui garantit qu'aucune edition ne peut partir sans.
    public function test_every_edition_carries_an_unsubscribe_link_even_if_its_body_has_none(): void
    {
        $edition = NewsletterEdition::factory()->prete()->create([
            'html' => '<p>Rien d autre.</p>',
            'text' => 'Rien d autre.',
        ]);

        $rendu = $this->service()->rendre($edition, 'https://jeuncy.test/desinscription/7?signature=abc');

        $this->assertStringContainsString('https://jeuncy.test/desinscription/7?signature=abc', $rendu['html']);
        $this->assertStringContainsString('https://jeuncy.test/desinscription/7?signature=abc', $rendu['texte']);
    }

    // =================================================================
    // 8. Les en-tetes de desinscription (le vrai MailService)
    // =================================================================

    // Seuls tests qui exercent le vrai MailService. Le stub Resend pose par
    // Tests\TestCase intercepte l'appel : rien ne sort sur le reseau, et
    // aucun mail ne part. On passe par $this->resend et non par un nouvel
    // Http::fake : le premier stub enregistre l'emporte toujours.
    public function test_the_list_unsubscribe_headers_are_sent_to_resend(): void
    {
        $this->resend = fn () => Http::response(['id' => 'fake'], 200);

        $envoye = (new MailService)->sendNewsletter(
            'destinataire@example.com',
            'Objet de test',
            '<p>html</p>',
            'texte',
            'https://jeuncy.test/newsletter/desinscription/1?signature=abc',
        );

        $this->assertTrue($envoye);

        Http::assertSent(function ($requete) {
            $corps = $requete->data();

            return $corps['headers']['List-Unsubscribe']
                    === '<https://jeuncy.test/newsletter/desinscription/1?signature=abc>'
                && $corps['headers']['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click'
                // Le corps texte accompagne toujours le HTML : un message en
                // HTML seul part bien plus facilement en indesirables.
                && $corps['text'] === 'texte'
                // Expediteur « bonjour@ » et non « no-reply@ » : la lettre
                // invite a repondre.
                && $corps['from'] === config('services.resend.newsletter_from');
        });
    }

    public function test_a_resend_failure_is_reported_and_not_swallowed(): void
    {
        $this->resend = fn () => Http::response(['message' => 'quota'], 429);

        $envoye = (new MailService)->sendNewsletter('destinataire@example.com', 'Objet', '<p>x</p>', 'x', 'https://jeuncy.test/u/1');

        // Le reste de MailService avale ses echecs, a dessein ; celui-ci doit
        // les remonter, sans quoi on marquerait « recue » une lettre jamais
        // partie et personne ne la renverrait jamais.
        $this->assertFalse($envoye);
    }

    public function test_the_default_sender_is_a_real_mailbox(): void
    {
        $this->assertSame('bonjour@jeuncy.com', config('services.resend.newsletter_from'));
    }

    // =================================================================
    // 9. L'essai
    // =================================================================

    // Un essai ne laisse AUCUNE ligne : s'il en laissait une, l'adresse
    // d'essai serait ensuite ecartee de l'envoi reel — et si elle appartient
    // a un vrai candidat, il ne recevrait jamais la lettre a cause du test
    // cense la verifier.
    public function test_a_test_send_leaves_no_trace(): void
    {
        User::factory()->candidate()->create();
        $edition = $this->edition();
        $this->mail->shouldReceive('sendNewsletter')->once()->andReturn(true);

        $this->service()->envoyerUnEssai($edition, 'equipe@example.com');

        $this->assertDatabaseCount('newsletter_deliveries', 0);
        $this->assertSame(1, $this->service()->destinataires($edition)->count());
        $this->assertSame(NewsletterEditionStatus::PRETE, $edition->fresh()->status);
    }

    // =================================================================
    // 10. Les routes de deploiement — elles doivent traverser tout le chemin
    // =================================================================

    public function test_the_deploy_route_is_a_dry_run_by_default(): void
    {
        User::factory()->candidate()->count(2)->create();
        $this->edition();
        $this->mail->shouldNotReceive('sendNewsletter');

        $this->get('/deploy/'.self::TOKEN.'/newsletter')
            ->assertOk()
            ->assertJsonPath('mode', 'a blanc (rien envoye, rien ecrit)')
            ->assertJsonPath('comptes.destinataires', 2);

        $this->assertDatabaseCount('newsletter_deliveries', 0);
    }

    // Sans edition armee, la route le dit au lieu de tomber en erreur : c'est
    // l'etat normal entre deux lettres.
    public function test_the_deploy_route_says_when_no_edition_is_ready(): void
    {
        $this->get('/deploy/'.self::TOKEN.'/newsletter')
            ->assertOk()
            ->assertJsonPath('edition', null);
    }

    // La lecon du 2026-09-08, cote route : ?envoyer=1 seul ne suffit pas.
    public function test_the_deploy_route_refuses_a_mass_send_without_tous(): void
    {
        User::factory()->candidate()->create();
        $this->edition();
        $this->mail->shouldNotReceive('sendNewsletter');

        $this->get('/deploy/'.self::TOKEN.'/newsletter?envoyer=1')->assertStatus(400);

        $this->assertDatabaseCount('newsletter_deliveries', 0);
    }

    public function test_the_deploy_route_sends_with_both_parameters(): void
    {
        User::factory()->candidate()->create();
        $this->edition();
        $this->mail->shouldReceive('sendNewsletter')->once()->andReturn(true);

        $this->get('/deploy/'.self::TOKEN.'/newsletter?envoyer=1&tous=1&max=1')
            ->assertOk()
            ->assertJsonPath('mode', 'ENVOI REEL')
            ->assertJsonPath('envoyes', 1);
    }

    // La route ne renvoie jamais d'adresse, dans aucun mode : un rapport se
    // recopie dans un message, et la liste ne doit pas sortir du serveur.
    public function test_the_deploy_route_never_returns_an_email_address(): void
    {
        User::factory()->candidate()->create(['email' => 'destinataire-de-test@example.com']);
        $this->edition();

        $this->get('/deploy/'.self::TOKEN.'/newsletter')
            ->assertOk()
            ->assertDontSee('destinataire-de-test@example.com');
    }

    public function test_an_unknown_edition_is_reported_not_guessed(): void
    {
        $this->get('/deploy/'.self::TOKEN.'/newsletter?edition=jamais-ecrite')
            ->assertStatus(404)
            ->assertJsonPath('erreur', 'NEWSLETTER_EDITION_INCONNUE');
    }

    public function test_the_deploy_routes_stay_inert_without_a_token(): void
    {
        Config::set('app.deploy_token', null);

        $this->get('/deploy/'.self::TOKEN.'/newsletter')->assertNotFound();
        $this->get('/deploy/'.self::TOKEN.'/newsletter/editions')->assertNotFound();
    }

    // Deposer depuis la page d'outillage : le chemin que Pierre empruntera
    // chaque semaine. Il doit traverser la vraie route HTTP, sans quoi un
    // `use` manquant dans DeployController ne se verrait nulle part (c'est
    // arrive le 2026-09-29 avec MatchReminderService).
    public function test_the_deploy_page_deposits_an_edition_without_sending_anything(): void
    {
        User::factory()->candidate()->count(2)->create();
        $this->mail->shouldNotReceive('sendNewsletter');

        $this->post('/deploy/'.self::TOKEN.'/newsletter/editions', [
            'slug' => '2026-10-06-lettre-02',
            'subject' => 'Deuxieme lettre',
            'html' => '<p>Corps</p>',
            'text' => 'Corps',
        ])->assertRedirect();

        $this->assertDatabaseHas('newsletter_editions', [
            'slug' => '2026-10-06-lettre-02',
            'status' => NewsletterEditionStatus::BROUILLON->value,
        ]);
        $this->assertDatabaseCount('newsletter_deliveries', 0);
    }

    // Une lettre sans version texte part bien plus facilement en
    // indesirables : le formulaire refuse plutot que de laisser deposer.
    public function test_the_deploy_page_refuses_an_edition_without_a_text_body(): void
    {
        $this->post('/deploy/'.self::TOKEN.'/newsletter/editions', [
            'slug' => 'incomplete',
            'subject' => 'Objet',
            'html' => '<p>Corps</p>',
            'text' => '',
        ])->assertRedirect();

        $this->assertDatabaseCount('newsletter_editions', 0);
    }

    public function test_the_deploy_page_arms_an_edition(): void
    {
        $edition = $this->edition('brouillon');

        $this->post('/deploy/'.self::TOKEN.'/newsletter/editions/'.$edition->slug.'/statut', ['prete' => '1'])
            ->assertRedirect();

        $this->assertSame(NewsletterEditionStatus::PRETE, $edition->fresh()->status);
    }

    public function test_the_preview_renders_the_whole_email(): void
    {
        $this->offres(jeuncy: 1, partenaires: 2);
        $edition = NewsletterEdition::factory()->avecCompteur()->create();

        $this->get('/deploy/'.self::TOKEN.'/newsletter/editions/'.$edition->slug.'/apercu')
            ->assertOk()
            // La coque est bien appliquee...
            ->assertSee('La lettre Jeuncy')
            // ... et les chiffres substitues.
            ->assertSee('Il y a 3 offres en ligne.');
    }

    public function test_the_text_preview_is_served_as_plain_text(): void
    {
        $edition = NewsletterEdition::factory()->create();

        $this->get('/deploy/'.self::TOKEN.'/newsletter/editions/'.$edition->slug.'/apercu?format=texte')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
