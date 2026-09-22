# Lot 1 du modèle match — manifeste de déploiement

> Rédigé le 2026-09-22, après `vendor/bin/pint` et `php artisan test`
> (**807 tests, 807 passés, 2 402 assertions**). Empreintes et tailles
> calculées sur les fichiers réellement présents dans le dépôt à cet instant,
> branche `feature/mobile-match`, aucun commit.
>
> **Pourquoi ce document existe.** Les deux pannes les plus chères de
> septembre (2026-09-04 et 2026-09-08, neuf allers-retours à elles deux) ne
> venaient pas de code fautif : elles venaient de **fichiers jamais arrivés
> sur le serveur**. Un fichier absent ne produit aucune erreur lisible — il
> produit une fonctionnalité qui ne fait simplement rien. La parade est
> mécanique : comparer, fichier par fichier, l'empreinte du dépôt à celle que
> `/deploy/{token}/version` renvoie du serveur.

## 0. En un coup d'œil

|                                  |                                                                 |
| -------------------------------- | --------------------------------------------------------------- |
| Fichiers à envoyer (`apps/api`)  | **114** — 74 nouveaux, 40 modifiés, 543 061 octets              |
| Migrations                       | 12 (`2026_09_22_100000` → `100011`)                             |
| Nouvelle version des outils      | `deploy-tools-29`                                               |
| Nouvelle variable `.env`         | `JEUNCY_MATCH_DEPARTEMENTS=66`                                  |
| Build web à publier              | `apps/web/dist` (~728 Ko)                                       |
| Action humaine après déploiement | saisir le code postal de l'offre d'IDA, puis `geocode:backfill` |

## 1. Fichiers à envoyer

Chemins **relatifs à `apps/api`**. Empreinte = `sha256sum` tronquée aux 16
premiers caractères, exactement ce que renvoie `/deploy/{token}/version`.
Taille en octets, après Pint.

Ne sont pas dans cette liste, et c'est volontaire : `tests/` (jamais déployé),
`.env.example` (documentation, le vrai `.env` se modifie à la main, §5), et
`packages/shared` + `apps/web` (déployés par build, §4).

**103 de ces 114 fichiers sont surveillés par `/deploy/{token}/version`.** Les
onze qui ne le sont pas sont les fabriques de `database/factories/` : elles ne
servent qu'aux tests, `fakerphp` n'est pas installé en production
(`composer --no-dev`), et rien du code applicatif ne les charge. Leur absence
du serveur n'aurait aucun effet — elles y vont quand même pour garder dépôt et
serveur alignés, mais on ne les surveille pas, pour que `/version` ne parle
que de fichiers dont l'absence casse quelque chose.

| Fichier                                                                                              | État        | Empreinte (16)     | Octets |
| ---------------------------------------------------------------------------------------------------- | ----------- | ------------------ | ------ |
| `app/Console/Commands/ExpireJobOffers.php`                                                           | modifié     | `94ff11c82a7fbb4c` | 8742   |
| `app/Console/Commands/GeocodeBackfill.php`                                                           | **nouveau** | `af48235dba5fba1e` | 3104   |
| `app/Console/Commands/MigrateDrivingLicense.php`                                                     | **nouveau** | `467da42dbd91fc8d` | 7017   |
| `app/Enums/ApplicationSource.php`                                                                    | **nouveau** | `63a4f243a756f30b` | 367    |
| `app/Enums/DrivingLicenseCategory.php`                                                               | **nouveau** | `f326a96215eaefae` | 503    |
| `app/Enums/ExternalInterestDecision.php`                                                             | **nouveau** | `f7b069b9012c83f8` | 478    |
| `app/Enums/InterestDecision.php`                                                                     | **nouveau** | `749b541676753500` | 379    |
| `app/Enums/MatchClosedReason.php`                                                                    | **nouveau** | `2839477fc309b007` | 525    |
| `app/Enums/NotificationType.php`                                                                     | modifié     | `cefde70441f151fb` | 1158   |
| `app/Enums/OfferSector.php`                                                                          | **nouveau** | `bbb299c5d97b7b0a` | 1035   |
| `app/Enums/ReportContext.php`                                                                        | **nouveau** | `40524311bf62b6dc` | 404    |
| `app/Enums/VerificationStatus.php`                                                                   | **nouveau** | `617d634c99715959` | 443    |
| `app/Http/Controllers/ApplicationController.php`                                                     | modifié     | `81780b8ad9aeee6f` | 1869   |
| `app/Http/Controllers/Auth/AuthController.php`                                                       | modifié     | `6902b35f08512350` | 9365   |
| `app/Http/Controllers/BlockController.php`                                                           | **nouveau** | `98df813876d595a7` | 1886   |
| `app/Http/Controllers/CandidateProfileController.php`                                                | modifié     | `05132c8675933c0e` | 2493   |
| `app/Http/Controllers/CvthequeController.php`                                                        | modifié     | `21a1f939fc433f08` | 2051   |
| `app/Http/Controllers/DeployController.php`                                                          | modifié     | `024c50078b66340a` | 92471  |
| `app/Http/Controllers/DiscoverController.php`                                                        | **nouveau** | `81db8f35542e35fa` | 1094   |
| `app/Http/Controllers/ExternalInterestController.php`                                                | **nouveau** | `924383dbfb89a6f8` | 1337   |
| `app/Http/Controllers/InterestController.php`                                                        | **nouveau** | `6386e2fca32750bc` | 1846   |
| `app/Http/Controllers/JobOfferController.php`                                                        | modifié     | `a9edb16fcaea7b52` | 2384   |
| `app/Http/Controllers/MatchController.php`                                                           | **nouveau** | `bfc6bb4982dfcc14` | 632    |
| `app/Http/Controllers/ReportController.php`                                                          | **nouveau** | `7d191b402c8aa181` | 463    |
| `app/Http/Middleware/EnsureMatchAge.php`                                                             | **nouveau** | `3ab5e15d138f6111` | 1941   |
| `app/Http/Requests/Block/StoreBlockRequest.php`                                                      | **nouveau** | `ea8d595f43a9d592` | 1699   |
| `app/Http/Requests/CandidateProfile/UpdateCandidateLocationRequest.php`                              | **nouveau** | `4f5daad12ace94c7` | 872    |
| `app/Http/Requests/CandidateProfile/UpdateCandidatePreferencesRequest.php`                           | **nouveau** | `a3d1c01c1c5b7d82` | 3128   |
| `app/Http/Requests/CfaOrganization/StoreCfaOrganizationRequest.php`                                  | modifié     | `fe5c822cad6a027f` | 1586   |
| `app/Http/Requests/CfaOrganization/UpdateCfaOrganizationRequest.php`                                 | modifié     | `413700209b37d86e` | 1886   |
| `app/Http/Requests/Company/StoreCompanyRequest.php`                                                  | modifié     | `4d22dc70e5dc81d1` | 1645   |
| `app/Http/Requests/Company/UpdateCompanyRequest.php`                                                 | modifié     | `99783674305b7145` | 1792   |
| `app/Http/Requests/Cvtheque/SearchCvthequeRequest.php`                                               | modifié     | `8ccdd73ce325120b` | 2866   |
| `app/Http/Requests/Discover/DiscoverCandidatesRequest.php`                                           | **nouveau** | `9229471ec2e2dc9d` | 588    |
| `app/Http/Requests/Discover/DiscoverOffersRequest.php`                                               | **nouveau** | `ad21029327b75af2` | 476    |
| `app/Http/Requests/ExternalInterest/StoreExternalInterestRequest.php`                                | **nouveau** | `462dcebb01f3f324` | 559    |
| `app/Http/Requests/Interest/PassInterestsRequest.php`                                                | **nouveau** | `0ece0bacbe3e87e4` | 1123   |
| `app/Http/Requests/Interest/StoreInterestRequest.php`                                                | **nouveau** | `0c47861048400542` | 976    |
| `app/Http/Requests/JobOffer/StoreExpressJobOfferRequest.php`                                         | **nouveau** | `398c30d27f5b4495` | 1736   |
| `app/Http/Requests/JobOffer/StoreJobOfferRequest.php`                                                | modifié     | `c038e2a907803dd8` | 3384   |
| `app/Http/Requests/JobOffer/UpdateJobOfferRequest.php`                                               | modifié     | `5c946783132a71fe` | 2635   |
| `app/Http/Requests/Report/StoreReportRequest.php`                                                    | **nouveau** | `0aec8d13534fe38c` | 1305   |
| `app/Models/Application.php`                                                                         | modifié     | `03bbf444ae66cf3f` | 1377   |
| `app/Models/CandidateProfile.php`                                                                    | modifié     | `63c0d388711f4e51` | 6624   |
| `app/Models/CfaOrganization.php`                                                                     | modifié     | `b05d37f388dc185e` | 2677   |
| `app/Models/Company.php`                                                                             | modifié     | `89a5331fe72099d6` | 3592   |
| `app/Models/ExternalInterest.php`                                                                    | **nouveau** | `43f21131d9981ed8` | 1243   |
| `app/Models/ExternalJobOffer.php`                                                                    | modifié     | `39b18ebab0b13995` | 2251   |
| `app/Models/GeocodeCache.php`                                                                        | **nouveau** | `1d4925734fb141d4` | 1222   |
| `app/Models/JobOffer.php`                                                                            | modifié     | `4098853af854a222` | 2825   |
| `app/Models/OfferInterest.php`                                                                       | **nouveau** | `33ec28b73c1d8398` | 2497   |
| `app/Models/Report.php`                                                                              | **nouveau** | `657c22b76e1d1935` | 1264   |
| `app/Models/User.php`                                                                                | modifié     | `ee686a09a6ff1955` | 4127   |
| `app/Models/UserBlock.php`                                                                           | **nouveau** | `1d653717c450b9ac` | 941    |
| `app/Presenters/CandidateCardPresenter.php`                                                          | **nouveau** | `0ab88542fad43594` | 8702   |
| `app/Rules/ValidSiret.php`                                                                           | **nouveau** | `639e88a52e6b6696` | 2489   |
| `app/Services/AccountService.php`                                                                    | modifié     | `e412095d04ae81a8` | 10294  |
| `app/Services/ApplicationService.php`                                                                | modifié     | `34d09da7e8c8b251` | 15843  |
| `app/Services/AuthService.php`                                                                       | modifié     | `c0438e470962dd9b` | 9038   |
| `app/Services/BlockService.php`                                                                      | **nouveau** | `a777c825b26e448d` | 3357   |
| `app/Services/CandidateProfileService.php`                                                           | modifié     | `88e79a4ff8d1bfc5` | 13544  |
| `app/Services/CfaOrganizationService.php`                                                            | modifié     | `78a8aa3c0f1e27ab` | 6353   |
| `app/Services/CompanyService.php`                                                                    | modifié     | `f9d71dad88d12c0c` | 9075   |
| `app/Services/CompanyVerificationService.php`                                                        | **nouveau** | `e5fc54a1cc771c53` | 6320   |
| `app/Services/CvthequeService.php`                                                                   | modifié     | `a84bf940855399fb` | 15695  |
| `app/Services/DiscoverService.php`                                                                   | **nouveau** | `4312bfa25e2d4158` | 25653  |
| `app/Services/ExternalInterestService.php`                                                           | **nouveau** | `42c65e798b237ac9` | 4008   |
| `app/Services/GeocodingService.php`                                                                  | **nouveau** | `a660ea3c81d39bde` | 8368   |
| `app/Services/InterestService.php`                                                                   | **nouveau** | `3ce6e581cee917a6` | 15894  |
| `app/Services/JobOfferMatchService.php`                                                              | modifié     | `720013894d52415d` | 8402   |
| `app/Services/JobOfferService.php`                                                                   | modifié     | `823d0455720f136d` | 26411  |
| `app/Services/MailService.php`                                                                       | modifié     | `dafe793cc87ff9a5` | 20914  |
| `app/Services/MatchClosingService.php`                                                               | **nouveau** | `c6a2094a3eb223d5` | 7856   |
| `app/Services/MatchScorer.php`                                                                       | **nouveau** | `42957980a6792f4c` | 8771   |
| `app/Services/MatchService.php`                                                                      | **nouveau** | `a2ab862a60204dd2` | 16222  |
| `app/Services/ReportService.php`                                                                     | **nouveau** | `8dc00dc3d72c4869` | 4894   |
| `app/Services/TrainingOrganizationDetector.php`                                                      | modifié     | `f542d0040149a878` | 10676  |
| `app/Support/Haversine.php`                                                                          | **nouveau** | `39e60f92c0ede601` | 4187   |
| `app/Support/MatchPerimeter.php`                                                                     | **nouveau** | `81bc98881eea4cba` | 2808   |
| `app/Support/PostalCodes.php`                                                                        | **nouveau** | `1a488b9e0da4709e` | 1843   |
| `bootstrap/app.php`                                                                                  | modifié     | `f605f8612e8f8c7d` | 10483  |
| `config/services.php`                                                                                | modifié     | `91c2bfd9beb562f0` | 8957   |
| `database/factories/CandidateProfileFactory.php`                                                     | **nouveau** | `5e94641659012a86` | 3396   |
| `database/factories/CfaOrganizationFactory.php`                                                      | **nouveau** | `bedca4110d60e4e5` | 1414   |
| `database/factories/CompanyFactory.php`                                                              | **nouveau** | `b79083a9d31b2d6a` | 1815   |
| `database/factories/ExternalInterestFactory.php`                                                     | **nouveau** | `c4749e0da5bde5c0` | 1124   |
| `database/factories/ExternalJobOfferFactory.php`                                                     | **nouveau** | `61d6e6e2f0c312ca` | 1751   |
| `database/factories/GeocodeCacheFactory.php`                                                         | **nouveau** | `1ea544d48b4f684a` | 690    |
| `database/factories/JobOfferFactory.php`                                                             | **nouveau** | `e28c9ca28a167590` | 2410   |
| `database/factories/OfferInterestFactory.php`                                                        | **nouveau** | `01dedec0e4b85882` | 2037   |
| `database/factories/ReportFactory.php`                                                               | **nouveau** | `bad0285541bb3942` | 754    |
| `database/factories/UserBlockFactory.php`                                                            | **nouveau** | `cea1013dc48548c8` | 424    |
| `database/factories/UserFactory.php`                                                                 | modifié     | `c0cde09a499b7612` | 1266   |
| `database/migrations/2026_09_22_100000_add_match_fields_to_candidate_profiles_table.php`             | **nouveau** | `dfe134beb76fdc76` | 3648   |
| `database/migrations/2026_09_22_100001_add_match_fields_to_job_offers_table.php`                     | **nouveau** | `6945ddef410030ab` | 2533   |
| `database/migrations/2026_09_22_100002_add_verification_and_coordinates_to_organizations_tables.php` | **nouveau** | `3f8751169497268e` | 2794   |
| `database/migrations/2026_09_22_100003_add_age_confirmed_at_to_users_table.php`                      | **nouveau** | `7c354de1be6e289c` | 849    |
| `database/migrations/2026_09_22_100004_create_geocode_cache_table.php`                               | **nouveau** | `accb1de738ad9f73` | 1474   |
| `database/migrations/2026_09_22_100005_create_offer_interests_table.php`                             | **nouveau** | `1b24e8ee3148fa94` | 3247   |
| `database/migrations/2026_09_22_100006_add_match_fields_to_applications_table.php`                   | **nouveau** | `f4430ece935e2288` | 1460   |
| `database/migrations/2026_09_22_100007_create_external_interests_table.php`                          | **nouveau** | `b1aba757d7e54caa` | 2184   |
| `database/migrations/2026_09_22_100008_create_user_blocks_table.php`                                 | **nouveau** | `197a30be52977c4f` | 1110   |
| `database/migrations/2026_09_22_100009_create_reports_table.php`                                     | **nouveau** | `806edcdfce7567bd` | 1795   |
| `database/migrations/2026_09_22_100010_add_match_types_to_notifications_type_enum.php`               | **nouveau** | `293c21be051f0ac2` | 1579   |
| `database/migrations/2026_09_22_100011_add_coordinates_index_to_external_job_offers_table.php`       | **nouveau** | `ed2204eb31b0efb1` | 843    |
| `routes/api.php`                                                                                     | modifié     | `f0b5b41d33e96e6c` | 971    |
| `routes/api/blocks-reports.php`                                                                      | **nouveau** | `2e73e0f5f39bfdfd` | 779    |
| `routes/api/candidate-profile.php`                                                                   | modifié     | `7d72dc72f34d4558` | 2708   |
| `routes/api/discover.php`                                                                            | **nouveau** | `0779cd0226249fbc` | 752    |
| `routes/api/external-interests.php`                                                                  | **nouveau** | `5789607de07eaec3` | 780    |
| `routes/api/interests.php`                                                                           | **nouveau** | `dca1259c0b5dd69c` | 707    |
| `routes/api/job-offers.php`                                                                          | modifié     | `37e13c22e23cddaa` | 2615   |
| `routes/api/matches.php`                                                                             | **nouveau** | `60fa031eb238cafd` | 533    |
| `routes/web.php`                                                                                     | modifié     | `b86e4620fed7e000` | 2679   |

**Les onze fichiers dont l'absence serait la plus coûteuse**, parce que leur
manque ne produit aucune erreur lisible :

| Fichier                                     | Ce qui se passe s'il manque                                                                                                                                                                    |
| ------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `routes/api.php`                            | Les cinq fichiers de routes sont là, mais aucun n'est inclus : **tout le lot répond 404** alors que `/version` affiche chaque fichier présent.                                                 |
| `app/Enums/NotificationType.php`            | `NEW_MATCH` n'existe pas : l'insertion échoue, et le `try/catch` qui protège l'enregistrement d'un profil avale l'exception. C'est exactement la panne du 2026-09-08.                          |
| `database/migrations/…100010…`              | Même effet, côté colonne MySQL : la valeur d'enum est refusée par la base.                                                                                                                     |
| `app/Support/Haversine.php`                 | `DiscoverService` ne se construit pas : les deux decks répondent 500. Panne bruyante, au moins.                                                                                                |
| `app/Support/MatchPerimeter.php`            | Idem, et c'est la garde qui empêche un employeur hors 66 de voir des cartes.                                                                                                                   |
| `app/Presenters/CandidateCardPresenter.php` | `CvthequeService` et `DiscoverService` ne se construisent plus : la CVthèque du site tombe **aussi**.                                                                                          |
| `app/Http/Middleware/EnsureMatchAge.php`    | `bootstrap/app.php` référence l'alias : toutes les routes du match répondent 500. Si l'alias manque mais pas le fichier, c'est l'inverse : Découvrir s'ouvre aux moins de 16 ans, sans un mot. |
| `bootstrap/app.php`                         | L'alias `match.age` et la planification de `geocode:backfill` manquent. Le fichier n'appartient à aucun dossier qu'on envoie d'habitude — piège déjà rencontré le 2026-08-17.                  |
| `app/Services/MatchScorer.php`              | `JobOfferMatchService` ne se construit plus : la notification « une offre te correspond » tombe, et `/deploy/{token}/match/{id}` aussi.                                                        |
| `app/Services/MatchClosingService.php`      | `JobOfferService`, `ApplicationService`, `AccountService` et `ExpireJobOffers` l'appellent : archiver une offre répond 500.                                                                    |
| `config/services.php`                       | `services.jeuncy.match_departements` absent → `MatchPerimeter::departments()` lit `null` → **fermé partout**. Découvrir ne montre aucun candidat, sans erreur.                                 |

`/deploy/{token}/version` surveille tout cela, plus le reste, et un test
(`DeploySelfTestTest::test_every_match_file_is_watched`) échoue si un fichier
du lot sort de la liste surveillée.

## 2. Envoi par WinSCP

**Le piège d'abord.** Le serveur porte une arborescence parasite : un dossier
`app/` complet traîne dans `/api-app/app/Http/`, qui contient donc `Auth`,
`Console`, `Enums`, `Exceptions`, `Http`, `Models`, `Providers`, `Services`
en plus de ses trois dossiers légitimes. Ces copies sont **inertes** (le PSR-4
mappe `App\Services\X` sur `app/Services/X.php`), mais elles servent de piège
à fichiers : c'est là qu'ont atterri les trois fichiers introuvables du
2026-09-04. **Vérifier, avant de lancer la synchronisation, que le panneau
distant est bien sur `/api-app/app` et non sur `/api-app/app/Http/app`**, et
ne jamais déposer un fichier à la main dans un chemin qui contient deux fois
`app`.

Procédure, dossier par dossier — et non en un seul passage à la racine : une
synchronisation de `/api-app` entier toucherait `vendor/`, `storage/` et le
`.env` de production.

1. Ouvrir WinSCP sur le compte FTP `jeuncy.com`, panneau local sur
   `C:\Users\Pierre\Documents\JEUNCY\apps\api`, panneau distant sur le dossier
   correspondant de `/api-app`.
2. Pour chacun de ces cinq dossiers, dans cet ordre :
   **`app/`**, **`config/`**, **`database/`**, **`routes/`**, **`bootstrap/`**.
3. Menu **Commandes > Synchroniser** (Ctrl+S), puis :
   - Direction : **Distant**
   - Mode : **Synchroniser fichiers**
   - Critère de comparaison : date de modification **et** taille
   - **Décocher « Supprimer les fichiers »** — ce lot ne retire rien, et une
     suppression accidentelle côté serveur ne se rattrape pas.
   - Laisser « Prévisualiser les changements » coché : la liste doit contenir
     les fichiers du §1 appartenant à ce dossier, **et rien d'autre**. Un
     fichier inattendu signale soit un panneau distant mal placé, soit une
     divergence à examiner avant d'écraser.
4. `resources/views/` **n'est pas concerné** : aucun gabarit Blade n'a été
   touché par ce lot. Le nouvel email de match est construit en PHP dans
   `MailService` (même `wrapEmailHtml` que les autres), pas dans une vue.
5. `database/` emporte au passage les onze fabriques (`database/factories/`).
   Elles ne servent qu'aux tests et `fakerphp` n'est pas installé en
   production ; elles y sont donc inertes, mais les envoyer garde dépôt et
   serveur alignés — ce qui est précisément ce que `/version` sert à vérifier.

**Divergence connue, à réaligner au passage** :
`app/Services/CvImportService.php` diffère d'un mot-clé de visibilité entre le
dépôt (`private`, `20047ab81a2fe1a6`) et le serveur (`public`,
`5ca94613966ea432`). Aucun effet fonctionnel. Ce fichier n'appartient pas au
lot 1 ; si la synchronisation le propose, l'accepter règle une vieille dette.

## 3. Après l'envoi, dans cet ordre

Toutes ces routes exigent le `DEPLOY_TOKEN` de production (jamais commité).

### 1. `/deploy/{token}/clear-cache` — **avant** tout le reste

À rebours de l'ordre habituel, et pour une raison précise : OPcache sert
l'ancienne version du code tant qu'il n'est pas vidé, donc `/version`
lui-même pourrait rendre les empreintes d'avant l'envoi. Cette route vide
aussi les caches de configuration et de routes — indispensable, puisque
`routes/api.php` et `config/services.php` ont changé.

### 2. `/deploy/{token}/version`

Attendu : `"version_outils_deploiement": "deploy-tools-29"`. **Si c'est encore
`deploy-tools-28`, s'arrêter là** : `DeployController.php` n'est pas arrivé,
et tout ce que renverra la suite décrira l'ancien serveur.

Puis comparer les 114 empreintes du §1 à celles renvoyées. Aucune ne doit
valoir `ABSENT`, aucune ne doit différer. Comparaison rapide dans PowerShell
(`manifeste-fichiers.txt` = deux colonnes « chemin empreinte », extraites du
tableau du §1) :

```powershell
$distant = (Invoke-RestMethod "https://jeuncy.com/deploy/$env:DEPLOY_TOKEN/version").fichiers
Get-Content manifeste-fichiers.txt | ForEach-Object {
  $chemin, $attendu = $_ -split ' '
  $recu = $distant.$chemin.empreinte
  if ($recu -ne $attendu) { "DIVERGE  $chemin  depot=$attendu serveur=$recu" }
}
```

### 3. `/deploy/{token}/migrate`

Les douze migrations `2026_09_22_1000xx`. Deux d'entre elles écrivent des
**données** en plus du schéma, et c'est ce qu'il faudra vérifier à l'étape
suivante : `100002` passe les CFA existants (IDA) en `VERIFIED`, `100010`
étend l'enum MySQL de `notifications.type`.

### 4. `/deploy/{token}/selftest`

Le contrôle qui compte. Tout s'y passe dans une transaction annulée et la clé
Resend est neutralisée le temps de l'essai : **aucune donnée réelle touchée,
aucun email envoyé**. Ce qu'il faut lire :

| Entrée                    | Attendu                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `cablage_match`           | `ok` pour les sept services. Autre chose = une version périmée sur le serveur, qui se construit sans erreur et n'appelle jamais son collaborateur.                                                                                                                                                                                                                                                                                                                                                                                                       |
| `perimetre`               | `["66"]`. `[]` = `JEUNCY_MATCH_DEPARTEMENTS` absent du `.env`, donc Découvrir fermé partout.                                                                                                                                                                                                                                                                                                                                                                                                                                                             |
| `cfa_verifiee`            | `cfa_verifies` ≥ 1 : c'est la seule preuve possible de la partie données de `100002`, puisque sous `RefreshDatabase` la migration tourne sur une base vide. `offres_publiees_sans_coordonnees` = 1 tant qu'IDA n'a pas saisi son code postal.                                                                                                                                                                                                                                                                                                            |
| `enum_notification_mysql` | `NEW_MATCH, INTEREST_RECEIVED, MATCH_CLOSED : acceptes par la colonne`. C'est **ici** que la migration `100010` se prouve — SQLite ne prouve rien là-dessus.                                                                                                                                                                                                                                                                                                                                                                                             |
| `geocodage_simule`        | `cache lu sans reseau` : une commune déjà résolue ne repart jamais sur le réseau, ce qui rend un géocodage synchrone tenable sans worker.                                                                                                                                                                                                                                                                                                                                                                                                                |
| `parcours_match`          | Le cœur : deux decks, deux « Ça m'intéresse », un match — par de **vraies requêtes HTTP** avec de vrais jetons, donc à travers les middlewares de rôle et des 16 ans, les Form Requests, les contrôleurs et les services. Attendu : `discover_offers.statut` 200 avec `distance_km_de_la_sonde` ≈ 5 (un `null` ou un `0` dénoncerait une haversine cassée), `discover_candidates.statut` 200 et `cles_interdites_trouvees: "aucune"`, `interet_employeur.matched` false, `interet_candidat.matched` **true**, `match_en_base` « 1 ligne(s) matchee(s) ». |
| `geocodeur`               | Appel réel à l'IGN. `INDISPONIBLE` n'est pas bloquant (c'est le comportement attendu d'une panne), mais alors `geocode:backfill` ne fera rien non plus : réessayer plus tard.                                                                                                                                                                                                                                                                                                                                                                            |

La leçon du 2026-09-04 est intégrée ici : le selftest d'alors appelait le
**service** sans passer par le contrôleur, donc il validait précisément la
partie qui marchait. `parcours_match` traverse tout le chemin. Un défaut
trouvé en testant la sonde elle-même le confirme : le gestionnaire
d'authentification mémorisait l'utilisateur d'une sous-requête à l'autre, si
bien que la sonde rapportait un parcours « qui marchait » sans jamais créer
de match.

### 5. `/deploy/{token}/geocode-backfill`

Route ajoutée par ce lot. **Sans `?executer=1`, elle ne fait rien** et se
contente de compter ce qui reste à géocoder : une commande qui interroge un
service externe des centaines de fois ne doit pas partir sur une simple
visite d'URL.

```
/deploy/{token}/geocode-backfill                          -> compte seulement
/deploy/{token}/geocode-backfill?executer=1               -> lance la passe
/deploy/{token}/geocode-backfill?executer=1&chunk=50      -> lots plus petits
/deploy/{token}/geocode-backfill?executer=1&only=profiles -> (ou offers, organizations)
```

Attendu en production : ~110 profils avec code postal, 1 CFA, et 1 offre
Jeuncy **non géocodable tant que son code postal n'est pas saisi** (étape 6).
La commande est **idempotente** : elle ne reprend que les lignes avec code
postal et sans coordonnées. Si `max_execution_time` (165 s) coupe la passe, il
suffit de relancer la même URL — rien n'est refait deux fois, et le cache de
communes rend la deuxième passe quasi instantanée.

Sans cette route, la même chose se fait par le cron : `geocode:backfill` est
planifiée une fois par jour (`bootstrap/app.php`), et
`/deploy/{token}/scheduler` doit désormais la lister parmi les tâches
attendues.

### 6. Action humaine : le code postal de l'offre d'IDA

La seule offre Jeuncy publiée n'a pas de code postal. Tant qu'il n'est pas
saisi, `discover/candidates` répond `JOB_OFFER_NOT_LOCATED` (« Indique le code
postal du poste pour découvrir des candidats. ») et l'offre n'entre dans
aucune pile par distance. Le lot 1 a rendu **une offre FREE publiée à nouveau
modifiable** précisément pour cela : IDA peut éditer son offre depuis
`/mes-offres` sans la dépublier, et le géocodage part tout seul à
l'enregistrement.

### 7. Sonde sans effet de bord, pour confirmer de l'extérieur

`GET /api/discover/offers` avec le jeton d'un compte candidat de moins de
16 ans répond `MATCH_MIN_AGE` (403) **une fois le middleware déployé**, et
`404` avant. Comme la sonde mobile du 2026-09-09, elle prouve que le code
s'exécute, là où une empreinte prouve seulement qu'un fichier est présent.

## 4. Build web à déployer

```bash
pnpm --filter shared build      # les six nouveaux enums TS
pnpm --filter web build         # -> apps/web/dist
```

Vérifié le 2026-09-22 : `tsc` et ESLint propres (un avertissement préexistant
sur `components/ui/button.tsx`), 20 tests Vitest verts, build en 5 s.

Contenu de `apps/web/dist` (~728 Ko) : `index.html`, `favicon.ico`,
`robots.txt`, `sitemap.xml`, `logo/`, `assets/` (un CSS et un JS au nom haché
— le hachage change à chaque build, donc **synchroniser `assets/` AVEC
suppression des anciens fichiers**, contrairement au backend : sans cela les
bundles périmés s'accumulent indéfiniment).

Ce que ce lot change côté site : la CVthèque n'affiche plus ni nom complet ni
ville (prénom + initiale, tranche d'âge), le bouton de téléchargement du CV
n'apparaît que si le candidat a postulé à une offre de l'entreprise, le SIRET
devient obligatoire dans le formulaire entreprise, et un badge
« Vérifiée / En attente / Refusée » apparaît sur la fiche organisation.

`packages/shared` n'est pas déployé tel quel : il est compilé dans le bundle
de `apps/web`.

## 5. Variables `.env` de production

Une seule à ajouter :

```
# Perimetre du modele match, cote EMPLOYEUR uniquement (Decouvrir des
# candidats, interet employeur) : « 66 » au lancement, « 66,11 » pour une
# liste, « * » pour tous les departements.
# ATTENTION : vide = FERME (aucun departement), l'inverse de LBA_DEPARTEMENTS.
JEUNCY_MATCH_DEPARTEMENTS=66
```

**L'inversion est délibérée, et c'est le point à ne pas rater** :
`LBA_DEPARTEMENTS` vide signifie « tous », `JEUNCY_MATCH_DEPARTEMENTS` vide
signifie « aucun ». Une variable oubliée au déploiement doit fermer la porte,
pas ouvrir des cartes de mineurs aux employeurs de toute la France. Le
selftest affiche la valeur lue (`perimetre`) : un `[]` s'y voit
immédiatement.

Rien d'autre n'est requis. Le géocodeur de l'IGN est public et sans clé (point
de terminaison en dur dans `GeocodingService`), et la vérification
d'entreprise réutilise `recherche-entreprises.api.gouv.fr`, déjà appelée par
le détecteur d'écoles.

Pour mémoire, deux valeurs déjà en production et inchangées : `RESEND_API_KEY`
est **présente**, donc le premier match réel enverra de vrais emails — vérifier
le gabarit sur un compte de test avant d'ouvrir le 66 ; et
`LBA_SIRET_WHITELIST` porte encore le remplaçant `SIRET_IDA`, à changer pour
le vrai SIRET quand Pierre l'aura.

## 6. Ce qui n'est pas dans ce lot

À ne pas chercher dans le selftest, ce serait le chercher en vain :

- **Clic admin de vérification** (`admin/verifications`) : une entreprise
  passée `PENDING` parce que le registre était en panne le reste jusqu'à sa
  prochaine modification de fiche.
- **Rappels J+N** (`MATCH_REMINDER`, employeurs silencieux) : lot 4.
- **Photos d'équipe**, **administration des signalements** : lots ultérieurs.
- **Fermeture des matchs par les chemins dormants** :
  `ArchiveExpiredTrialOffers` (mode payant, inactif) et l'archivage forcé
  d'`AdminService` n'appellent pas encore `MatchClosingService` — un match sur
  une offre archivée par l'admin resterait affiché « ouvert » côté candidat.
- **Rien de visible dans l'application mobile** : c'est le socle serveur. Les
  écrans arrivent aux lots 2 et 3.
