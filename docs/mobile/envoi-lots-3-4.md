# Envoi des lots 3 et 4 — manifeste

> Établi le 2026-09-29, mis à jour le même jour après le premier envoi.
> État de référence en production : lot 1 (commit `5664604`, déployé le
> 2026-09-22). Cible : `fe7711f` sur `feature/mobile-match`.
>
> **Second envoi requis : un seul fichier.** Le premier envoi (23 fichiers)
> est arrivé intact — vérifié par `version` — mais `matches-remind`
> répondait 500 en production. Cause trouvée via `/deploy/{token}/logs` :
> `use App\Services\MatchReminderService;` manquant dans
> `DeployController.php` (le contrôleur appelait la classe par son nom
> court, PHP la cherchait dans le mauvais namespace). Corrigé, testé par
> une contre-épreuve (le nouveau test échoue bien sans le correctif), et
> **`DeployController.php` a donc une nouvelle empreinte** — c'est le seul
> fichier qui change dans ce second envoi.

## Ce qu'il faut envoyer, et ce qu'il ne faut pas

|                             |                                                                                                                                           |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| **API (`apps/api`)**        | **23 fichiers**, liste ci-dessous                                                                                                         |
| Site (`apps/web`)           | **Rien.** Le bundle en ligne est identique au bit près à un build neuf (`index-gb-nhVRP.js` → `932b1671ee94235e`). Vérifié le 2026-09-29. |
| Application (`apps/mobile`) | **Rien.** Elle n'est publiée nulle part : Expo Go recharge depuis ton PC.                                                                 |
| `.env` du serveur           | **Rien à changer.** Les deux nouveaux drapeaux ont le bon défaut (`JEUNCY_MATCH_ACTIF` vrai, `JEUNCY_RELANCES_ACTIVES` faux).             |

**Masque d'exclusion WinSCP, à vérifier avant de lancer quoi que ce soit :**

```
vendor/; bootstrap/cache/; storage/; .env; tests/; .phpunit.result.cache
```

C'est `bootstrap/cache/` qui a mis l'API par terre le 22 septembre. Ne jamais
synchroniser `apps/api` à la racine sans ce masque.

## Les 23 fichiers

Chemins relatifs à `apps/api/`. « N » = fichier nouveau, « M » = modifié.

|     | Fichier                                                                                   | sha256(16)         | Octets  |
| --- | ----------------------------------------------------------------------------------------- | ------------------ | ------- |
| N   | `app/Console/Commands/RemindMatches.php`                                                  | `225364a3319ed6f5` | 1 617   |
| M   | `app/Enums/MatchClosedReason.php`                                                         | `09fb630db4e7f3f6` | 1 198   |
| N   | `app/Enums/MatchReminderStage.php`                                                        | `897142f72336d6e8` | 2 866   |
| M   | `app/Enums/NotificationType.php`                                                          | `8d36c8a74540529e` | 1 339   |
| N   | `app/Http/Controllers/Admin/ModerationController.php`                                     | `a8a4df54cd95dba0` | 2 467   |
| M   | `app/Http/Controllers/DeployController.php` **(v2, voir note en tête)**                   | `f0128a0e9f1e84c5` | 101 215 |
| M   | `app/Http/Controllers/ExternalInterestController.php`                                     | `4b03697808aa48e4` | 1 540   |
| N   | `app/Http/Requests/Admin/DecideVerificationRequest.php`                                   | `85520e3db753d009` | 1 395   |
| N   | `app/Services/AdminModerationService.php`                                                 | `92cafad2cd2f5420` | 7 085   |
| M   | `app/Services/CvService.php`                                                              | `fd14442e71b7b5a6` | 17 648  |
| M   | `app/Services/DiscoverService.php`                                                        | `8fe974446f7a70a1` | 27 167  |
| N   | `app/Services/EmployerResponseStats.php`                                                  | `6a2c1c6f366c7671` | 4 355   |
| M   | `app/Services/ExternalInterestService.php`                                                | `643be7cf9192da69` | 5 747   |
| M   | `app/Services/MailService.php`                                                            | `a3c20e2d89456812` | 22 094  |
| N   | `app/Services/MatchReminderService.php`                                                   | `a46ce749110f04fb` | 18 274  |
| N   | `app/Support/SquarePhoto.php`                                                             | `e6773cf1e2e9e591` | 2 485   |
| M   | `bootstrap/app.php`                                                                       | `6b1a34d1356db677` | 11 193  |
| M   | `config/services.php`                                                                     | `c9fdf2d3a689b474` | 10 702  |
| N   | `database/migrations/2026_09_23_100000_add_match_reminder_to_notifications_type_enum.php` | `d1463b021ea6e468` | 1 684   |
| N   | `database/migrations/2026_09_23_100001_add_reminder_tracking_to_match_tables.php`         | `6725fc167a3d5819` | 2 853   |
| M   | `routes/api/admin.php`                                                                    | `ab690fa51420d365` | 3 960   |
| M   | `routes/api/external-interests.php`                                                       | `473b19ee1de97ace` | 1 126   |
| M   | `routes/web.php`                                                                          | `c65dc1bcc1459471` | 3 427   |

Les 23 sont dans la liste surveillée de `DeployController::version()` : après
l'envoi, la route te redonne ces mêmes empreintes, ou te dit lesquelles
manquent.

Deux fichiers changent aussi dans le dépôt mais **ne servent à rien sur le
serveur** — ne les envoie pas : `phpunit.xml` (lanceur de tests, absent en
prod) et `.env.example` (documentation).

## Après l'envoi, dans cet ordre

Remplace `{token}` par la valeur de `DEPLOY_TOKEN` du `.env` de production.
Ces routes sont **sans** `/api`.

1. **Les migrations** — il y en a 2 nouvelles.

   `https://api.jeuncy.com/deploy/{token}/migrate`

2. **Vérifier que tout est bien arrivé** — comparer avec le tableau ci-dessus,
   et voir `deploy-tools-32`.

   `https://api.jeuncy.com/deploy/{token}/version`

3. **Le selftest** — il exerce les chemins sensibles et renvoie la vraie
   exception s'il y en a une.

   `https://api.jeuncy.com/deploy/{token}/selftest`

4. **La passe de relances À BLANC** — elle compte ce qui partirait, sans rien
   envoyer ni écrire.

   `https://api.jeuncy.com/deploy/{token}/matches-remind`

   **À lire avant toute chose.** La première passe réelle tombe sur tout
   l'historique d'un coup : des intérêts et des candidatures de vraies
   personnes, vieux de plusieurs mois. Si les chiffres te surprennent,
   n'active rien et dis-le-moi.

5. **N'active les relances que si le compte à blanc te convient** : ajouter
   `JEUNCY_RELANCES_ACTIVES=true` au `.env` de production. Tant que la ligne
   est absente, la tâche est planifiée mais inerte.

## Ce que l'envoi apporte

- **Les relances** (`matches:remind`) — la promesse « réponse garantie » rendue
  concrète. Inertes tant que l'étape 5 n'est pas faite.
- **Les trois files de modération admin** — signalements, vérifications en
  attente, employeurs silencieux. C'est ce qui répond `404` aujourd'hui.
- **Le badge « Répond en N jours »** sur les cartes d'offre de l'app.
- **Le drapeau `JEUNCY_MATCH_ACTIF`** — le frein d'urgence de la pile candidat.
- **Le retrait d'une offre gardée** — `DELETE external-interests/{id}`.
