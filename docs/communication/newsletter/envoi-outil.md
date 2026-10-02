# Lettre hebdomadaire — outil d'envoi

> Manifeste de deploiement et mode d'emploi. Ecrit le 2026-10-01.
> `{token}` = la valeur de `DEPLOY_TOKEN` du `.env` de production.

## 1. Ce que l'outil fait, en une page

Pierre ecrit une lettre, la depose **depuis le navigateur** (aucun envoi FTP),
la relit, l'arme, puis declenche l'envoi. Le serveur lit les candidats en base
et envoie lui-meme.

**La liste des adresses ne sort jamais du serveur.** Aucun export, aucun
fichier, aucune adresse dans une URL, dans un rapport JSON, sur la page de
desinscription ni dans la table de suivi (seul `user_id` y figure).

Destinataires : comptes **CANDIDATE**, non supprimes, non suspendus, non
desinscrits, et pas deja servis sur cette edition. Un candidat **sans profil**
est bien destinataire — c'est le cas le plus frequent en production, et c'est a
lui qu'on a le plus de raisons d'ecrire.

## 2. Fichiers a envoyer

Masque d'exclusion WinSCP, a regler une fois pour toutes (lecon du
2026-09-22, ou l'envoi de `bootstrap/cache/` a mis l'API entiere a 500) :

```
vendor/; bootstrap/cache/; storage/; .env; tests/; .phpunit.result.cache
```

**Ne jamais synchroniser `apps/api` a la racine.**

### Nouveaux (16)

| Chemin (depuis `apps/api/`)                                                           | sha256 (16)        | Octets |
| ------------------------------------------------------------------------------------- | ------------------ | ------ |
| `database/migrations/2026_10_01_100000_add_newsletter_unsubscribe_to_users_table.php` | `6346a731045376ca` | 1 339  |
| `database/migrations/2026_10_01_100001_create_newsletter_editions_table.php`          | `e6e60b3192dae06c` | 2 747  |
| `database/migrations/2026_10_01_100002_create_newsletter_deliveries_table.php`        | `4968a68edc65a559` | 3 082  |
| `app/Enums/NewsletterDeliveryStatus.php`                                              | `6be933b26bb07ca2` | 1 238  |
| `app/Enums/NewsletterEditionStatus.php`                                               | `770b20a34834ca50` | 1 436  |
| `app/Models/NewsletterDelivery.php`                                                   | `3790b72ede1fbda1` | 1 071  |
| `app/Models/NewsletterEdition.php`                                                    | `340a9ed573206b72` | 1 409  |
| `app/Services/NewsletterService.php`                                                  | `12efbf716206b55c` | 25 703 |
| `app/Services/NewsletterPlaceholders.php`                                             | `dcf5f78a1f2a8afc` | 3 953  |
| `app/Support/OffersCount.php`                                                         | `efd2d19c58142c26` | 1 651  |
| `app/Http/Controllers/NewsletterUnsubscribeController.php`                            | `d30e0252b7dafc48` | 3 015  |
| `resources/views/emails/newsletter/layout.blade.php`                                  | `00e8cd006e2c2fc1` | 4 872  |
| `resources/views/emails/newsletter/layout-texte.blade.php`                            | `4d3ee199f9dc86dd` | 991    |
| `resources/views/newsletter/unsubscribe.blade.php`                                    | `e47193d95124d3dd` | 3 489  |
| `resources/views/deploy/newsletter-editions.blade.php`                                | `00af5883b4eb6b74` | 6 375  |
| `database/factories/NewsletterEditionFactory.php`                                     | —                  | —      |

> `database/factories/` ne sert qu'aux tests : inutile en production, sans
> danger s'il part.

### Modifies (7) — l'angle mort historique

C'est ici qu'etaient **les deux pannes muettes de septembre** (2026-09-04 et
2026-09-08) : des fichiers modifies pour _brancher_ une fonctionnalite, jamais
surveilles, jamais arrives. Un fichier perime ne produit aucune erreur, juste
une fonctionnalite qui ne fait rien.

| Chemin                                              | sha256 (16)        | Octets  | Sans lui                                                                |
| --------------------------------------------------- | ------------------ | ------- | ----------------------------------------------------------------------- |
| `app/Models/User.php`                               | `5d853b0b69a29e6c` | 5 048   | colonne de desinscription ignoree : on reecrit a ceux qui ont dit non   |
| `app/Services/MailService.php`                      | `a4a7c3ec4ccffbcc` | 25 110  | `sendNewsletter` n'existe pas : erreur des le premier envoi             |
| `app/Http/Controllers/PublicJobOfferController.php` | `9f8865690d6eb023` | 1 367   | `OffersCount` introuvable : **le compteur public du site tombe en 500** |
| `app/Http/Controllers/DeployController.php`         | `fb9ac7dab3cf923e` | 114 876 | toutes les routes `/newsletter` en 404                                  |
| `config/services.php`                               | `ffd82f02fefe4901` | 11 199  | expediteur `no-reply@`, alors que la lettre invite a repondre           |
| `routes/web.php`                                    | `2a91930b2ed1551c` | 6 255   | **le lien de desinscription de chaque lettre deja partie renvoie 404**  |
| `.env.example`                                      | —                  | —       | documentation seulement                                                 |

> `PublicJobOfferController.php` et `routes/web.php` sont les deux a ne surtout
> pas oublier : le premier casse une page publique, le second casse des liens
> qui sont deja dans des boites mail.

Tous ces chemins sont dans la liste surveillee de `DeployController::version()`
(`deploy-tools-33`).

## 3. Variable `.env` a ajouter en production

```
NEWSLETTER_FROM_EMAIL=bonjour@jeuncy.com
```

Volontairement une vraie boite et non `no-reply@` : la lettre se termine par
« reponds simplement a ce mail ». Le domaine `jeuncy.com` est deja verifie chez
Resend, donc SPF/DKIM passent. `RESEND_API_KEY` doit etre presente — sans elle
l'outil **refuse de commencer** plutot que de marquer toute l'edition en echec.

## 4. Ordre des operations apres l'envoi FTP

1. **Migrations** — `GET /deploy/{token}/migrate`
   (3 nouvelles : desinscription, editions, envois).
2. **Verifier ce qui est reellement arrive** — `GET /deploy/{token}/version`
   Comparer les empreintes au tableau ci-dessus. `deploy_tools_version` doit
   dire `deploy-tools-33`. Un seul `ABSENT` = s'arreter la.
3. **Verifier le `.env`** — `GET /deploy/{token}/env-check`
4. **Deposer la lettre n° 1** — `GET /deploy/{token}/newsletter/editions`
   Coller :
   - identifiant : `2026-10-01-lettre-01`
   - objet : `[[offres_total]] offres d'alternance t'attendent sur Jeuncy`
   - corps HTML : `docs/communication/newsletter/2026-10-01-lettre-01-corps.html`
   - corps texte : `docs/communication/newsletter/2026-10-01-lettre-01-corps.txt`

   Elle arrive en `BROUILLON`. Deposer n'envoie rien et n'arme rien.

5. **Relire** — `GET /deploy/{token}/newsletter/editions/2026-10-01-lettre-01/apercu`
   (et `?format=texte`). C'est l'email **complet**, coque et chiffres compris.
6. **Armer** — bouton « Armer (PRETE) » sur la page des editions. C'est a ce
   moment que les espaces reserves sont verifies : une edition au gabarit casse
   est refusee ici, devant toi.
7. **Compter a blanc** — `GET /deploy/{token}/newsletter`
   Rien n'est envoye, rien n'est ecrit. Lire `comptes.destinataires` et
   `valeurs_du_moment`.
8. **Essai sur un seul destinataire** — `GET /deploy/{token}/newsletter?essai=1`
   Une copie a `CONTACT_EMAIL` (`bonjour@jeuncy.com`), objet prefixe `[ESSAI]`.
   Aucune ligne n'est ecrite : l'essai ne consomme pas l'edition.
9. **Envoi reel** — `GET /deploy/{token}/newsletter?envoyer=1&tous=1`
   Pour un premier envoi prudent, ajouter `&max=5`, regarder, puis relancer
   sans `max` : la reprise ne reecrit a personne.

## 5. URL exactes

| URL                                                      | Ce que ca fait                                                                                 |
| -------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| `GET /deploy/{token}/newsletter`                         | **A blanc.** Compte les destinataires, les exclusions et les chiffres du moment. N'ecrit rien. |
| `GET /deploy/{token}/newsletter?edition=SLUG`            | Idem, sur une edition precise.                                                                 |
| `GET /deploy/{token}/newsletter?essai=1`                 | Une copie a l'adresse de contact. Aucune trace.                                                |
| `GET /deploy/{token}/newsletter?envoyer=1&tous=1`        | **Envoi reel.**                                                                                |
| `GET /deploy/{token}/newsletter?envoyer=1&tous=1&max=N`  | Envoi reel borne a N, reprenable.                                                              |
| `GET /deploy/{token}/newsletter/editions`                | Page de depot et liste des editions.                                                           |
| `POST /deploy/{token}/newsletter/editions`               | Depose une edition (toujours en `BROUILLON`).                                                  |
| `POST /deploy/{token}/newsletter/editions/{slug}/statut` | Arme (`prete=1`) ou desarme (`prete=0`).                                                       |
| `GET /deploy/{token}/newsletter/editions/{slug}/apercu`  | L'email complet, HTML. `?format=texte` pour la version texte.                                  |

`?envoyer=1` **seul est refuse** (400). Il faut `&tous=1`. C'est la lecon du
2026-09-08, payee par 37 notifications expediees a de vrais candidats : un
parametre omis ne declenche jamais l'action la plus visible.

## 6. Les espaces reserves

Ecrits `[[nom]]` dans le HTML **et** dans le texte, remplaces par le serveur
**au moment de l'envoi** — jamais a la redaction.

| Espace reserve           | Valeur                                            |
| ------------------------ | ------------------------------------------------- |
| `[[offres_total]]`       | Toutes les offres en ligne (Jeuncy + partenaires) |
| `[[offres_jeuncy]]`      | Offres publiees sur Jeuncy                        |
| `[[offres_partenaires]]` | Offres importees de La bonne alternance           |

C'est **le meme calcul** que `GET job-offers/count` (meme cache
`offres.compteur`, vide a chaque import LBA) : le site et la lettre ne peuvent
pas annoncer deux chiffres differents. Nombres formates a la francaise, avec
une espace **insecable** (`6 823`, jamais `6823` ni une espace ordinaire qui
pourrait couper le nombre en fin de ligne).

**Un espace reserve inconnu fait echouer l'envoi** (`NEWSLETTER_GABARIT_NON_RESOLU`, 422) avant la moindre reservation et le moindre message : un candidat ne doit
jamais recevoir une lettre montrant sa tuyauterie. La verification a lieu deux
fois — a l'armement, et au debut de chaque passe d'envoi.

## 7. Desinscription

Chaque message porte, **depuis la coque et non depuis l'edition** :

- un lien « Je ne veux plus recevoir cette lettre » dans le pied,
- les en-tetes `List-Unsubscribe` et `List-Unsubscribe-Post` (RFC 8058), qui
  font apparaitre le bouton natif de Gmail et d'Outlook. Sans eux, les gens
  cliquent « spam » a la place, et c'est la reputation de `jeuncy.com` qui
  trinque.

Le lien est **signe** (cle de l'application), **sans authentification** (on
clique depuis sa boite mail) et **sans expiration** (une lettre reste des mois
dans une boite, et le droit d'opposition ne se perime pas).

**Le `GET` ne desinscrit pas** : il affiche une page avec un bouton, et c'est
le `POST` qui agit. Les antivirus de messagerie et les apercus de lien ouvrent
les URL d'un email sans que personne n'ait clique — un `GET` agissant aurait
desinscrit des gens qui n'ont rien demande. Le meme `POST` sert le bouton « un
clic » de Gmail. Signature **relative** : le lien survit a un changement d'hote
(`www.` / `api.` / http-https), ce qu'une signature absolue n'aurait pas fait.

La page n'affiche **aucune adresse email**.

## 8. Ce qui protege mecaniquement

| Garde                                             | Ou                                                                         |
| ------------------------------------------------- | -------------------------------------------------------------------------- |
| Une edition `BROUILLON` ne part jamais            | `NewsletterService::envoyer`                                               |
| Une edition `ENVOYEE` ne repart jamais            | idem                                                                       |
| Une passe rejouee n'ecrit a personne deux fois    | contrainte unique `newsletter_deliveries_edition_user_unique`, en **base** |
| Une passe coupee reprend ou elle s'est arretee    | ligne reservee **avant** l'envoi (`PENDING`)                               |
| En cas de doute (`PENDING`), on ne reecrit pas    | selection des destinataires                                                |
| Envoi de masse impossible sur un parametre oublie | `&tous=1` obligatoire                                                      |
| Gabarit non resolu = rien ne part                 | `NEWSLETTER_GABARIT_NON_RESOLU`                                            |

Chaque passe journalise ce qui est parti (edition, nombre envoye, echecs,
duree) — des comptages, jamais une adresse. Lisible par
`GET /deploy/{token}/logs`.

## 9. Limites connues

- **L'envoi n'est pas planifie.** Il n'y a ni commande Artisan ni tache
  hebdomadaire : l'envoi se declenche a la main par l'URL du §5. Un envoi
  automatique du lundi matin etait prevu ; il n'a pas pu etre construit dans
  cette session (voir le rapport de la session du 2026-10-01). Tant qu'il
  n'existe pas, la lettre part quand Pierre ouvre l'URL.
- **Un envoi en echec n'est pas reessaye** automatiquement : renvoyer en boucle
  a une adresse qui refuse abime la reputation du domaine pour tous les autres.
  Les lignes `FAILED` restent visibles dans `envois_enregistres`. Les rejouer
  demanderait de supprimer ces lignes a la main — non outille a dessein.
- **Rythme d'envoi** : une pause de 550 ms entre deux messages, pour rester
  sous la limite de 2 requetes/seconde de Resend. 125 destinataires ≈ 2 min.
  Le temps d'execution PHP est releve a 900 s sur la route ; si la passe est
  coupee malgre tout, la relancer reprend.
- **Pas de statistiques d'ouverture ni de clic** : aucun pixel, aucun lien
  reecrit. C'est un choix — ils demanderaient de tracer chaque destinataire.
- La version texte est **obligatoire** au depot : une lettre en HTML seul part
  bien plus facilement en indesirables.
