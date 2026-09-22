# Lot 1 — socle backend du match : contrat technique

> Rédigé le 2026-09-22 à partir de `MOBILE.md` (§3-§8), des arbitrages du lot
> et d'une relecture du code à cette date (fichier:ligne cités sur la branche
> `feature/mobile-match`). Ce document est le contrat de trois développeurs
> travaillant en parallèle : chaque fichier est attribué à un lot et un seul.
> Ce qui n'est pas ici n'est pas dans le lot 1.

## 0. Portée, invariants, ordre de travail

**Livré par le lot 1** : migrations, enums, modèles, géocodage, vérification
employeur, préférences et position du candidat, offre express, découverte
(deux piles), intérêts, match, intérêts partenaires, blocages/signalements,
règle d'exposition unique appliquée à la CVthèque, fermetures, export RGPD,
tests, selftest. **Rien de visible dans l'app** (lots 2-3). **Pas dans ce
lot** : rappels J+N (`MATCH_REMINDER`, lot 4), photos d'équipe, admin des
signalements, changement du défaut `is_visible_in_cvtheque` (à confirmer avec
le patron, `MOBILE.md` §3.1), **clic admin de vérification** (`admin/verifications`,
`MOBILE.md` §4.0 et §8 : une entreprise PENDING n'est re-vérifiée qu'à la
prochaine modification de sa fiche), filtrage texte général de `MOBILE.md` §7
(seule la garde minimale sur `pitch`, §2.1, est livrée : c'est le seul texte
libre nouveau exposé avant le dossier), fermeture des matchs par les chemins
dormants ou admin (`ArchiveExpiredTrialOffers` en mode payant, `AdminService`
archivage forcé — à brancher sur `MatchClosingService` quand l'admin des
signalements arrivera).

**Invariants que chaque lot doit tenir** (`MOBILE.md` §9) : jamais de
candidature sans geste du candidat ; jamais de ville, distance ni coordonnée
côté employeur avant le dossier ; jamais d'employeur non `VERIFIED` face à un
candidat ; 16 ans sur toute route du match quel que soit le client ; codes
`ApiException` en majuscules, enveloppe `{success, data}` (`bootstrap/app.php`
l.140-149) ; aucun enum MySQL sur une nouvelle table ou colonne ; `$table`
explicite sur chaque modèle ; Pint ; chaque règle testée.

**Ordre** : F (fondations, un développeur, ~1 jour) → A, B, C en parallèle →
I (intégration : `DeployController`, selftest, `MOBILE.md` §5, déploiement).
A/B/C ne touchent aucun fichier de F ni entre eux ; ce dont deux lots ont besoin
vit en F avec sa signature fixée ici (§7).

**Mesure de production (2026-09-22, sonde `status?geo=1`)** : 115 candidats
(43 dans le 66, 5 sans code postal, 3 de moins de 16 ans, 89 avec un texte de
permis), 0 entreprise, 1 CFA (IDA), 1 offre Jeuncy publiée sans code postal,
7 779 offres partenaires toutes géolocalisées (40 dans le 66). La haversine SQL
est retenue plutôt que `ST_Distance_Sphere` : elle tourne aussi sous SQLite en
tests (§6). `queue=sync`, `cache=file`, fuseau serveur UTC.

## 1. Lot F — fondations

### 1.1 Migrations (`apps/api/database/migrations/`)

Statuts en `string` + enum PHP validé par `Rule::enum`, jamais d'enum MySQL sur
une nouvelle colonne (`2026_07_30_130000` l.12-17). Cascades justifiées en
commentaire, comme `2026_07_17_000001` l.14-16.

| Fichier                                                                          | Contenu                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   |
| -------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026_09_22_100000_add_match_fields_to_candidate_profiles_table.php`             | `latitude`, `longitude` decimal(9,6) null (position **PROFILE**, géocodée) ; `device_latitude`, `device_longitude` decimal(9,6) null, `device_located_at` timestamp null (position **DEVICE**, pile candidat seulement) ; `search_radius_km` unsignedSmallInteger défaut 30 ; `mobility_radius_km` unsignedSmallInteger défaut 30 ; `wanted_contract_types` json null ; `wanted_sectors` json null ; `has_driving_license` boolean défaut false ; `driving_license_categories` json null ; `has_vehicle` boolean défaut false ; `available_from` date null ; `pitch` string(160) null ; `show_photo_to_employers` boolean défaut false. Index `(latitude, longitude)`. La colonne texte `driving_license` (`2026_07_21_151050` l.13) est **conservée**.                                                                                                                                                                                                                                   |
| `2026_09_22_100001_add_match_fields_to_job_offers_table.php`                     | `postal_code` string(10) null ; `latitude`, `longitude` decimal(9,6) null ; `recruitment_radius_km` unsignedSmallInteger défaut 30 ; `sector` string(40) null ; `schedule` string(255) null ; `start_date` date null ; `minimum_age` unsignedTinyInteger défaut 16 ; `requires_driving_license` boolean défaut false ; `missions` json null. Index `(status, latitude, longitude)`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `2026_09_22_100002_add_verification_and_coordinates_to_organizations_tables.php` | Sur `companies` **et** `cfa_organizations` : `verification_status` string(20) défaut `'PENDING'` ; `verified_at` timestamp null ; `verified_by` FK `users` nullable **nullOnDelete** (un admin supprimé ne doit pas invalider la vérification qu'il a faite) ; `verification_note` string null (raison lisible d'un REJECTED/PENDING) ; `latitude`, `longitude` decimal(9,6) null. Puis, dans le même `up()`, `DB::table('cfa_organizations')->update(['verification_status' => 'VERIFIED', 'verified_at' => now()])` sur les lignes existantes (IDA) — les nouvelles restent PENDING. **Pas de `NOW()` SQL** : la migration tourne aussi sous SQLite (`RefreshDatabase`), qui n'a pas cette fonction ; le query builder avec `now()` PHP est portable. Pas de `->after()` (leçon de `2026_08_19_141000` l.25-27).                                                                                                                                                                        |
| `2026_09_22_100003_add_age_confirmed_at_to_users_table.php`                      | `age_confirmed_at` timestamp null.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `2026_09_22_100004_create_geocode_cache_table.php`                               | `id`, `postal_code` string(5), `city_normalized` string(120), `latitude`, `longitude` decimal(9,6) **null** (null = géocodeur sans réponse, réessayé après 7 jours), `resolved_at` timestamp, timestamps ; unique `(postal_code, city_normalized)`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |
| `2026_09_22_100005_create_offer_interests_table.php`                             | `id` ; `candidate_profile_id` FK **cascadeOnDelete** (donnée personnelle rattachée au profil, droit à l'effacement — même raison que `applications`, `2026_07_17_000010` l.13-16) ; `job_offer_id` FK **cascadeOnDelete** (un intérêt sans offre n'a pas de sens ; la fermeture avec notification se fait **avant** la suppression, voir `MatchClosingService`) ; `candidate_decision` string(4) null ; `employer_decision` string(4) null ; `candidate_decided_at`, `employer_decided_at` timestamp null ; `matched_at` timestamp null ; `application_id` FK `applications` nullable **nullOnDelete** (le retrait d'un dossier ne détruit pas l'historique du match, il le ferme) ; `closed_at` timestamp null ; `closed_reason` string(30) null ; `candidate_notified_at`, `employer_notified_at` timestamp null ; timestamps. Unique `(candidate_profile_id, job_offer_id)` ; index `(job_offer_id, employer_decision)`, `(candidate_profile_id, candidate_decided_at)`, `matched_at`. |
| `2026_09_22_100006_add_match_fields_to_applications_table.php`                   | `interest_id` FK `offer_interests` nullable **nullOnDelete** (créée après `offer_interests`, d'où l'ordre) ; `source` string(10) défaut `'SITE'` ; `responded_at` timestamp null.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |
| `2026_09_22_100007_create_external_interests_table.php`                          | `candidate_profile_id` FK **cascadeOnDelete** (RGPD) ; `external_job_offer_id` FK nullable **nullOnDelete** (`LbaImportService` supprime chaque nuit les offres absentes de l'export, l.162-165 : sans copie « Gardées » se viderait) ; `decision` string(4) ; `company_siret` string(14) null, `company_name`, `title`, `city` string null, `apply_url` string(1000) — dénormalisés ; `decided_at` timestamp ; `done_at` timestamp null ; timestamps. Unique `(candidate_profile_id, external_job_offer_id)` (MySQL admet plusieurs NULL) ; index `(candidate_profile_id, decision)`.                                                                                                                                                                                                                                                                                                                                                                                                    |
| `2026_09_22_100008_create_user_blocks_table.php`                                 | `blocker_user_id`, `blocked_user_id` FK `users` **cascadeOnDelete** (un blocage n'a plus de sens sans l'un des deux comptes) ; `created_at` ; unique `(blocker_user_id, blocked_user_id)`.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                |
| `2026_09_22_100009_create_reports_table.php`                                     | `reporter_user_id` FK **cascadeOnDelete** ; `reported_user_id` FK nullable **nullOnDelete** (le signalement reste lisible par l'équipe si le compte visé disparaît) ; `job_offer_id` FK nullable nullOnDelete ; `context` string(10) ; `reason` string(40) ; `details` text null ; `handled_by` FK `users` nullable nullOnDelete ; `handled_at` timestamp null ; timestamps.                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              |
| `2026_09_22_100010_add_match_types_to_notifications_type_enum.php`               | `->change()` sur l'enum MySQL historique comme `2026_09_03_090000` l.18-33 : + `NEW_MATCH`, `INTEREST_RECEIVED`, `MATCH_CLOSED`. **Écriture réelle en MySQL à vérifier au déploiement** (SQLite ne le prouve pas).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| `2026_09_22_100011_add_coordinates_index_to_external_job_offers_table.php`       | Index `(status, latitude, longitude)` : la pile partenaire filtre par boîte englobante, colonnes non indexées aujourd'hui (`2026_09_15_120000` l.77-80).                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  |

### 1.2 Enums PHP (`apps/api/app/Enums/`) et pendant TS (`packages/shared/src/enums/`)

| PHP                                   | Valeurs                                                                                                                                                                                                                                                                                | TS                            |
| ------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------- |
| `OfferSector`                         | COMMERCE, RESTAURATION_HOTELLERIE, BTP, INDUSTRIE, LOGISTIQUE_TRANSPORT, SANTE_SOCIAL, INFORMATIQUE_NUMERIQUE, ADMINISTRATIF_GESTION, BANQUE_ASSURANCE, COMMUNICATION_MARKETING, AGRICULTURE_ENVIRONNEMENT, ART_CULTURE, EDUCATION_FORMATION, BEAUTE_BIEN_ETRE, SPORT_ANIMATION, AUTRE | `offer-sector.ts`             |
| `VerificationStatus`                  | PENDING, VERIFIED, REJECTED                                                                                                                                                                                                                                                            | `verification-status.ts`      |
| `InterestDecision`                    | LIKE, PASS                                                                                                                                                                                                                                                                             | `interest-decision.ts`        |
| `ExternalInterestDecision`            | KEEP, PASS                                                                                                                                                                                                                                                                             | —                             |
| `MatchClosedReason`                   | OFFER_ARCHIVED, OFFER_DELETED, OFFER_EXPIRED, APPLICATION_WITHDRAWN, ACCOUNT_DELETED                                                                                                                                                                                                   | `match-closed-reason.ts`      |
| `ApplicationSource`                   | SITE, APP, MATCH                                                                                                                                                                                                                                                                       | `application-source.ts`       |
| `DrivingLicenseCategory`              | AM, A1, A2, A, B1, B, BE, C, D                                                                                                                                                                                                                                                         | `driving-license-category.ts` |
| `ReportContext`                       | CARD, MATCH, PHOTO, OFFER                                                                                                                                                                                                                                                              | —                             |
| `NotificationType` (existant, l.7-18) | + NEW_MATCH, INTEREST_RECEIVED, MATCH_CLOSED                                                                                                                                                                                                                                           | `notification-type.ts` (+3)   |

Chaque fichier TS suit le gabarit `as const` + type dérivé de
`contract-type.ts`, exporté depuis `enums/index.ts`. `apps/mobile/src/lib/labels.ts`
n'a pas de `Record` sur `NotificationType` ni sur les nouveaux enums : la
compilation mobile ne casse pas (vérifié l.8-28). Recompiler avec
`pnpm --filter shared build`.

### 1.3 Modèles (`apps/api/app/Models/`)

Nouveaux, tous avec `protected $table`, `#[Fillable]`, `HasFactory` :

- `OfferInterest` (`offer_interests`) : fillable `candidate_profile_id, job_offer_id, candidate_decision, employer_decision, candidate_decided_at, employer_decided_at, matched_at, application_id, closed_at, closed_reason, candidate_notified_at, employer_notified_at` ; casts `candidate_decision`/`employer_decision` → `InterestDecision` (nullable), `closed_reason` → `MatchClosedReason`, timestamps `datetime` ; relations `candidateProfile()`, `jobOffer()`, `application()` ; scopes `open()` (`closed_at` null), `matched()` (`matched_at` non null).
- `ExternalInterest` (`external_interests`) : fillable toutes colonnes ; casts `decision` → `ExternalInterestDecision`, `decided_at`/`done_at` datetime ; relations `candidateProfile()`, `externalJobOffer()`.
- `UserBlock` (`user_blocks`, `$timestamps = false`, `created_at` `useCurrent`) ; `Report` (`reports`, cast `context` → `ReportContext`) ; `GeocodeCache` (`geocode_cache`).

Modifiés :

- `CandidateProfile` (fillable l.11-16, casts l.32-39) : fillable + toutes les colonnes de la migration 100000 **sauf** `latitude`, `longitude`, `device_*` (affectées uniquement par `GeocodingService`/`CandidateProfileService`, jamais par mass-assignment client — même raison que `trial_*` sur `Company` l.10-12) ; casts `wanted_contract_types`, `wanted_sectors`, `driving_license_categories` → `array`, `has_driving_license`, `has_vehicle`, `show_photo_to_employers` → boolean, `available_from` date, `device_located_at` datetime, coordonnées float ; **`protected $hidden = ['latitude', 'longitude', 'device_latitude', 'device_longitude', 'device_located_at']`** et `OWNER_VISIBLE` (même mécanisme que `Company` l.31-37) : le profil est sérialisé tel quel dans `listForOffer` (`ApplicationService` l.123-126) après le dossier, et une coordonnée ne doit jamais y passer ; le propriétaire les récupère via `makeVisible(CandidateProfile::OWNER_VISIBLE)` dans **toutes** les méthodes de `CandidateProfileService` qui lui renvoient son profil (`getForUser`, `createForUser`, `updateForUser`, `updatePreferences`, `setDeviceLocation`, `clearDeviceLocation`) et dans `AccountService::exportData` (l'export RGPD doit contenir la position GPS stockée, sinon il est incomplet). Accesseur `getAgeBandAttribute(): ?string` ('<18', '18-20', '21-25', '26+', null sans `birth_date`) à côté de `getAgeAttribute` l.27 (**pas** dans `$appends`). Relations `offerInterests()`, `externalInterests()`.
- `JobOffer` (fillable l.15-21) : + `postal_code, recruitment_radius_km, sector, schedule, start_date, minimum_age, requires_driving_license, missions` (coordonnées hors fillable) ; casts `sector` → `OfferSector`, `missions` array, `start_date` date, `requires_driving_license` boolean, coordonnées float ; relation `offerInterests()`.
- `Company` / `CfaOrganization` : `verification_*` et coordonnées **hors fillable** ; casts `verification_status` → `VerificationStatus`, `verified_at` datetime ; `$hidden` + `verified_by`, `verification_note`, `latitude`, `longitude` (fiche servie publiquement, l.18-31) ; `OWNER_VISIBLE` + `verification_note` ; `verification_status` reste visible (signal de confiance, `MOBILE.md` §4.2) ; méthode `isVerified(): bool`.
- `Application` (fillable l.10) : + `interest_id, source, responded_at` ; casts `source` → `ApplicationSource`, `responded_at` datetime ; relation `interest()`.
- `User` (fillable l.13) : + `age_confirmed_at` (cast datetime) ; relations `blocks()` (hasMany `UserBlock`, `blocker_user_id`), `blockedBy()`.

### 1.4 Factories (`apps/api/database/factories/`)

Aucun modèle n'utilise `HasFactory` aujourd'hui (grep vide sur `app/Models`) ;
les tests créent tout par `::create` (`ApplicationServiceTest` l.52-78). F ajoute
le trait sur `User`, `CandidateProfile`, `Company`, `CfaOrganization`,
`JobOffer`, `ExternalJobOffer`, `OfferInterest` et écrit `CandidateProfileFactory`
(états `adult()` = 20 ans, `minor15()`, `located($lat, $lng)`, `withPreferences()`),
`CompanyFactory` (`verified()`, `located()`), `CfaOrganizationFactory`,
`JobOfferFactory` (`published()`, `located()`), `ExternalJobOfferFactory`
(`active()`, `located()`), `OfferInterestFactory` (`candidateLiked()`,
`employerLiked()`, `matched()`). Les tests existants ne changent pas.

### 1.5 Support partagé (F, utilisé par A, B, C)

- `tests/TestCase.php` : dans `setUp()`, si le pilote est `sqlite`, injecter
  `sin, cos, asin, sqrt` (1 arg), `radians` → `deg2rad`, `power` → `pow` (2 args)
  via `sqliteCreateFunction`, généralisé depuis `DeployGeoStatsTest::activerTrigonometrieSqlite`
  (l.77-85) ; tout test hérite ainsi de la haversine sans rien faire.
  **Et** ajouter au `Http::fake([...])` existant (l.29-35) une entrée
  `'data.geopf.fr/*' => Http::response(['features' => []])` : dès que A branche
  le géocodage sur la création de profil/offre/organisation, **des centaines de
  tests existants** créent des lignes avec `city`/`postal_code` (16 fichiers) et
  appelleraient le vrai géocodeur IGN — `Http::fake` avec un tableau laisse
  passer les URL non listées vers le réseau (aucun `preventStrayRequests` dans
  la suite aujourd'hui). Ajouter aussi `Http::preventStrayRequests()` dans ce
  `setUp()` pour que tout nouvel appel réseau oublié échoue bruyamment.
  `GeocodingServiceTest` (A) remplace ce stub par le sien via une closure, sur
  le modèle de `$this->registreEntreprises` (l.18) : ajouter une propriété
  `protected ?Closure $geocodeur = null` consultée par le stub.
- `App\Support\Haversine` : `sql(string $latColumn, string $lngColumn): string`
  renvoie l'expression de `DeployController::autourDePerpignan` (l.408-411,
  bindings `[lat, lat, lng]`), `bindings(float $lat, float $lng): array`,
  `boundingBox(float $lat, float $lng, int $km): array{minLat,maxLat,minLng,maxLng}`
  (1° lat = 111 km, 1° lng = 111 × cos(lat) km).
- `App\Support\PostalCodes::department(?string $postalCode): ?string` : logique de
  `DeployController::departementDuCodePostal` (l.196-214), `null` au lieu de
  `'inconnu'`. `App\Support\MatchPerimeter` : `departments(): array` lit
  `config('services.jeuncy.match_departements')` et **accepte indifféremment
  une chaîne brute ou un tableau** (A stocke un tableau via `config/services.php`,
  B teste par `Config::set` avec une chaîne : `''`, `'*'`, `'66,11'`) —
  `null`/`''`/`[]` → `[]` = **fermé**, `'*'`/`['*']` → `['*']` = tous, sinon liste
  normalisée (`trim`, majuscules pour `2A`/`2B`) — et `isOpen(?string $postalCode): bool`
  (`null` ou code inexploitable → `false`).
- `App\Presenters\CandidateCardPresenter` (§4) et `App\Services\MatchClosingService`
  (§2.5) : requis par les trois lots, donc en F.
- `App\Services\BlockService` **en F, pas en B** : `block(User, int $blockedUserId): UserBlock`
  (`CANNOT_BLOCK_SELF` 400, idempotent sur un doublon), `unblock(User, UserBlock)`
  (`blocker_user_id !== $user->id` → `FORBIDDEN` 403), `blockedUserIdsFor(User): array`
  (ids bloqués **dans les deux sens**). C (`CvthequeService`) et B (decks,
  `MatchService`, `listForOffer`) l'appellent tous deux à l'exécution : un service
  partagé par deux lots parallèles vit en F. B garde `BlockController`,
  `StoreBlockRequest`, la route et `BlockReportTest`.
- **Pré-adaptation des fixtures existantes (F, séquentiel, suite verte)** : les
  gardes de A (code postal à la publication), B (`requireVerified` sur
  `listForOffer`) et C (`requireVerified` sur la CVthèque) cassent des tests
  existants **répartis sur trois lots**, et `ModeGratuitTest` est touché par les
  trois à la fois (`company()`/`cfa()` l.41-53 créent une organisation sans
  SIRET ni code postal, puis publient, lisent les candidatures et ouvrent la
  CVthèque). Plutôt qu'un fichier à trois propriétaires, **F adapte les helpers
  avant A/B/C**, sans changer une seule assertion : `ModeGratuitTest::company()/cfa()/draftOfferFor()`
  (organisation avec `postal_code '66000'`, `city 'Perpignan'`, puis
  `verification_status = VERIFIED` par affectation directe ; offre avec
  `postal_code '66000'`), `CvthequeServiceTest::makeSubscriber()` (l.28-41),
  `CvthequeDownloadTest::makeSubscriber()` (l.36), `CandidateAgeTest::abonne()`
  (l.45) — ces trois helpers créent un `User` COMPANY **sans aucune `Company`**,
  donc `organizationFor()` rendra `null` et `requireVerified` refusera tout :
  chacun crée désormais une `Company` VERIFIED —, `ApplicationServiceTest` (les
  entreprises qui appellent `listForOffer` l.162-247 passent en VERIFIED).
  Après F ces changements sont sans effet (les gardes n'existent pas encore) ;
  après A, B ou C, chacun seul, la suite reste verte.
- `App\Services\CompanyVerificationService`, **squelette** : `organizationFor(User): Company|CfaOrganization|null`,
  `isVerified(User): bool`, `requireVerified(User): void` → `COMPANY_NOT_VERIFIED`
  (403, « Ton entreprise doit être vérifiée avant d'accéder aux candidats. »).
  ADMIN/STAFF passent. A complète le fichier (§2.1).

## 2. Services

### 2.1 Lot A — géo, vérification, préférences, offres

**`App\Services\GeocodingService`** (nouveau).
`geocode(?string $postalCode, ?string $city): ?array{lat: float, lng: float}` :
code postal réduit à 5 chiffres, ville normalisée (`Str::ascii`, minuscules,
trim) ; lecture `geocode_cache` ; sinon `Http::timeout(4)->get('https://data.geopf.fr/geocodage/search', ['q' => $city ?: $postalCode, 'postcode' => $postalCode, 'limit' => 1])`,
`features.0.geometry.coordinates` = `[lng, lat]` ; écriture en cache (y compris
`null` sur échec, `resolved_at`) ; **toute exception ou réponse non 2xx → `null`
et `Log::warning`**, jamais d'exception (modèle `TrainingOrganizationDetector::nafFor`
l.174-206). `apply(Model $target, ?string $postalCode, ?string $city, string $latColumn = 'latitude', string $lngColumn = 'longitude'): void`
écrit les coordonnées arrondies (`round(…, 2)` pour `CandidateProfile`, 6 décimales
sinon) ou `null`, par affectation directe + `saveQuietly()`.
`App\Console\Commands\GeocodeBackfill` (`geocode:backfill {--chunk=100} {--only=profiles|offers|organizations}`) :
parcourt les lignes avec code postal et sans coordonnées, idempotent, planifiable
(`bootstrap/app.php`, ajout à l'intégration). Test : `Http::fake` sur
`data.geopf.fr/*`.

**`CompanyVerificationService`** (complété).
`verify(Company|CfaOrganization $organization): VerificationStatus` : SIRET
absent → PENDING ; 14 chiffres + **Luhn** invalide → REJECTED (`verification_note`
« SIRET invalide ») ; consultation du registre par `TrainingOrganizationDetector::lookupEstablishment(string $siret): ?array`
(nouvelle méthode publique **extraite** de `nafFor` l.181-200, qui l'appelle ; rend
`['naf' => …, 'active' => bool]` à partir de `etat_administratif === 'A'` de
l'établissement apparié dans `matching_etablissements`, sinon de l'unité légale) ; registre
muet → PENDING (« Vérification en attente ») ; NAF bloqué (`isBlockedNaf` l.166) →
REJECTED ; établissement fermé → REJECTED ; sinon **VERIFIED** avec `verified_at = now()`,
`verified_by = null` (automatique). Écriture par affectation directe. Jamais
VERIFIED par défaut. Précisions issues du code existant :

- la règle de Luhn vit **aussi** dans les Form Requests (`App\Rules\ValidSiret`, A) :
  une faute de frappe est refusée en `INVALID_INPUT` (400, « Ce numéro SIRET
  n'est pas valide. ») avant d'être écrite, plutôt que stockée en REJECTED ; le
  contrôle dans `verify()` reste une ceinture pour les lignes écrites hors Form
  Request (seeder, tinker, backfill). Exception connue : les SIRET de La Poste
  (SIREN `356000000`) ne respectent pas Luhn (somme des chiffres multiple de 5)
  — ce SIREN passe ;
- `CompanyService::createForUser` (l.96-100) et `updateForUser` (l.113-119)
  appellent **d'abord** `assertNotTrainingOrganization`, qui **lève** une
  `ApiException` sur un NAF bloqué : la branche « NAF bloqué → REJECTED » de
  `verify()` n'est donc atteinte que par un chemin qui ne passe pas par le
  détecteur (backfill, re-vérification). Pour ne pas consulter le registre deux
  fois par requête, `lookupEstablishment` mémorise sa dernière réponse par SIRET
  le temps de la requête (propriété d'instance, le service n'est pas singleton),
  et `nafFor` la réutilise.

**`CompanyService`** (l.87-124) : `createForUser` exige `siret` (Form Request,
§3) puis `verify()` ; `updateForUser` : après fusion, un `siret` null → `SIRET_REQUIRED`
(400) ; `verify()` rejoué si `siret` change **ou** si le statut n'est pas VERIFIED ;
géocodage si `postal_code`/`city` changent. **`CfaOrganizationService`** (`createForUser`
l.70, `updateForUser` l.79) : même chose, mais `siret` exigé seulement à la
création (l'inscription CFA est fermée, `AuthService` l.58-68). **Écart assumé
par rapport à l'arbitrage** (« obligatoire à la création ET à la mise à jour »),
motivé par « IDA n'a pas de SIRET en base » — **affirmation non prouvée** : la
sonde `status?geo=1` ne donne `avec_siret` que pour les entreprises (0), pas pour
le CFA. À vérifier avant de coder (`SELECT siret FROM cfa_organizations` via
tinker ou `/deploy/{token}/status`) ; si IDA a un SIRET, l'exiger aussi à la mise
à jour comme pour l'entreprise, et l'écart disparaît.

**`JobOfferService`** (`archiveForUser` l.95, `deleteForUser` l.128, `publishFreeForUser` l.275) :

- `publishFreeForUser` : si `postal_code` null, repli sur celui de l'organisation
  (et `city` idem) ; toujours null → `JOB_OFFER_POSTAL_CODE_REQUIRED` (409,
  « Indique le code postal du poste avant de publier. ») ; géocodage avant
  publication ; le reste inchangé (`FREE`, `notifyMatchingCandidates`).
- `createForUser`/`updateForUser` : géocodage quand `postal_code` change.
- **`updateForUser` (l.67-69) refuse aujourd'hui toute offre non `DRAFT`**
  (`requireOwnedDraftOffer`, `JOB_OFFER_NOT_DRAFT` 409). Or l'offre express est
  publiée dès sa création et doit être « complétée plus tard » (`MOBILE.md` §4.1),
  et la seule offre publiée de production (IDA, sans code postal) doit recevoir
  un code postal pour entrer dans Découvrir. A assouplit donc : une offre
  `PUBLISHED` **et** `payment_status = FREE` reste modifiable (statut,
  `published_at` et `applications_unlocked_at` conservés) ; la restriction
  historique visait les offres payées, ce qui ne concerne plus une offre
  gratuite. `test_update_rejects_already_published_offer` (`JobOfferServiceTest`
  l.102, offre PUBLISHED avec `payment_status` PENDING) reste valide ; ajouter
  `test_update_allows_published_free_offer`. Côté web, le bouton « Modifier »
  de `/mes-offres` n'apparaît que pour un brouillon : C peut l'ouvrir aux
  offres FREE publiées (facultatif, l'app le fera au lot 2).
- `createExpressForUser(User $user, array $data): JobOffer` : `createForUser` avec
  `description` générée (« {title} — {contrat} à {city} ({postal_code}). Description
  à compléter depuis Mes offres. ») puis `publishFreeForUser`, dans une transaction.
  `publishFreeForUser` lève `FREE_PUBLICATION_DISABLED` quand `services.jeuncy.gratuit`
  est faux, et **`phpunit.xml` force `JEUNCY_GRATUIT=false`** (l.49) :
  `JobOfferExpressTest` fait `Config::set('services.jeuncy.gratuit', true)` dans
  son `setUp()` comme `ModeGratuitTest` (l.38).
- `archiveForUser` et `deleteForUser` appellent `MatchClosingService::closeForOffer`
  (avant `delete()` pour l'un, après `update` pour l'autre).
- `requireOwnedOffer` (l.368) inchangé, réutilisé par B.

**`CandidateProfileService`** (l.27-48) : géocodage quand `city`/`postal_code`
changent (PROFILE) ; `updatePreferences(User, array): CandidateProfile` ;
`setDeviceLocation(User, float $lat, float $lng): CandidateProfile` (arrondi
`round(…, 2)` **côté serveur**, `device_located_at = now()`) ; `clearDeviceLocation(User)` ;
`getForUser` (l.22) rend visibles `OWNER_VISIBLE`. `requireProfile` (l.274) inchangé.

**`AuthService::register`** (l.33-49) : nouveau paramètre `bool $ageConfirmed = false`
→ `age_confirmed_at = now()` si vrai ; `AuthController::register` (l.36-45) passe
`$validated['age_confirmed'] ?? false`. La règle des 15 ans du site (`RegisterRequest`
l.33) ne change pas.

**`App\Console\Commands\MigrateDrivingLicense`** (`candidates:migrate-driving-license {--apply}`) :
lit `driving_license` (texte normalisé par `Str::ascii` + minuscules) ; règle :
`permis b`, `b` isolé, `permis de conduire`, `oui` seul → B ; `permis a`, `a1`,
`a2`, `moto` → A1/A2/A ; `am`, `bsr` → AM ; `permis be`, `b96` → BE ; `permis c`,
`poids lourd` → C ; `permis d` → D ; « en cours », « pas de permis », « non »,
« sans » → aucune (`has_driving_license = false`). **Jamais `a`, `c` ou `d` isolés** :
après normalisation, « véhicule à disposition » donne `a` isolé et « d'un
véhicule » donne `d` isolé — deux fausses catégories sur des textes réels. Un
texte non reconnu est listé sans être écrit. Dry-run par défaut : tableau
`id, texte, catégories déduites` sans nom ; `--apply` écrit
`has_driving_license` + `driving_license_categories`, colonne texte conservée.
Relecture manuelle du rapport avant `--apply` (`MOBILE.md` §6), 89 profils
concernés.

**Garde minimale sur `pitch`** (`UpdateCandidatePreferencesRequest`) : le seul
texte libre nouveau montré à un employeur avant le dossier. Refusé s'il contient
une adresse email ou une suite de 10 chiffres (téléphone), `not_regex`, message
« Ta phrase ne doit pas contenir de coordonnées : elles sont transmises avec ton
dossier. » — sans quoi l'invariant « ni téléphone ni email avant candidature »
se contourne en une ligne. Le filtrage général de `MOBILE.md` §7 reste hors lot.

### 2.2 Lot B — découvrir, intérêts, match

**`App\Services\MatchScorer`** (extrait de `JobOfferMatchService` l.206-338,
méthodes rendues publiques **avec les mêmes noms**) : `keywordsOf(JobOffer): array`,
`normalize(string): string`, `contractIsExcluded(CandidateProfile, JobOffer): bool`,
`sharesKeyword(CandidateProfile, array): bool`, `matches(CandidateProfile, JobOffer, array $keywords, string $city): bool`,
`score(CandidateProfile, JobOffer): int` = (même ville normalisée ? 2 : 0) + (mot
partagé ? 1 : 0) + (`sector` de l'offre dans `wanted_sectors` ? 1 : 0), 0 si contrat
exclu. **Un seul changement de règle** : quand `wanted_contract_types` est non vide,
`contractIsExcluded` l'utilise à la place de l'heuristique textuelle (l.238-260) ;
un profil sans préférence garde exactement l'ancien comportement, donc les 16
tests de `JobOfferMatchServiceTest` passent sans modification. `JobOfferMatchService`
garde `isReachable` (l.206) et les deux méthodes publiques, injecte `MatchScorer`.
`DeployController::matchDebug` (l.846-869) appelle ces méthodes par réflexion :
il bascule sur `app(MatchScorer::class)` à l'intégration.

**`App\Services\DiscoverService`.**
`offersForCandidate(User $user): array` → `{jeuncy, partner, meta}` :

1. position = `device_*` si présente, sinon `latitude/longitude` (PROFILE) ;
   `meta.location_source` = 'DEVICE' | 'PROFILE' | null, `meta.has_coordinates`,
   `meta.radius_km = search_radius_km`, `meta.department = PostalCodes::department(postal_code)`.
2. **jeuncy** : `JobOffer` `PUBLISHED`, `whereDoesntHave('offerInterests', décidée par ce candidat)`
   (une ligne avec `candidate_decision` non null ou `closed_at` non null),
   **et `whereDoesntHave('applications', du profil)`** (une offre à laquelle il a
   postulé depuis le site sans passer par un LIKE ne doit pas rester dans sa
   pile — ceinture en plus du LIKE posé par `applyForUser`, voir plus bas), propriétaire
   non bloqué dans les deux sens (`UserBlock`), contrat dans `wanted_contract_types`
   si la liste est non vide (**ajout par rapport à l'arbitrage**, qui ne filtre pas
   la pile par contrat ; cohérent avec `MOBILE.md` §3.1 « ce que je cherche ») ; **avec coordonnées** : boîte englobante + haversine
   `<= search_radius_km` ; **sans** : même département (`postal_code` de l'offre) ;
   si le résultat est vide, toute la France (`meta.scope` = 'radius' | 'department' | 'france').
   Jamais borné au périmètre `JEUNCY_MATCH_DEPARTEMENTS`. Jusqu'à 200 lignes chargées,
   tri en PHP : `employer_interested` (ligne avec `employer_decision = LIKE`) d'abord,
   puis `MatchScorer::score` décroissant, puis `distance_km` croissante, puis
   `published_at` décroissant ; **20 max**. Chaque offre = `JobOffer` avec
   `company`/`cfaOrganization`/`skills` + `distance_km` (arrondi 1 décimale, null
   sans coordonnées), `employer_interested`, `already_applied`.
3. **partner** : `ExternalJobOffer` `ACTIVE`, colonnes `PUBLIC_COLUMNS` (l.31-37)
   - `distance_km`, exclusion des lignes `external_interests` KEEP (toujours) et PASS
     de moins de 60 jours (`decided_at`), même rayon/département/France, tri SQL
     `distance_km` puis `published_at` desc, `paginate(20)` (`?page=`).
4. `meta.quota = {limit: 20, used: n, active: bool}` (voir `InterestService`).

`candidatesForOffer(User $user, JobOffer $offer): LengthAwarePaginator` :
`requireOwnedOffer` → `CompanyVerificationService::requireVerified` → offre sans
`postal_code` ou sans coordonnées → `JOB_OFFER_NOT_LOCATED` (409, « Indique le
code postal du poste pour découvrir des candidats. ») → `MatchPerimeter::isOpen($offer->postal_code)`
sinon `MATCH_NOT_OPEN_HERE` (403, « Découvrir n'est pas encore ouvert dans ce
département. »). **Dans cet ordre** : l'offre publiée de production n'a pas de
code postal, et `isOpen(null)` répondrait « pas ouvert ici » — faux et
inexploitable pour IDA, alors que « indique le code postal » dit quoi faire.
Éligibilité **en SQL, sans score** :
`is_visible_in_cvtheque`, `birth_date` non null et âge ≥ `max(16, minimum_age)`
(seuil calculé en PHP : `whereDate('birth_date', '<=', now()->subYears($n))`,
portable, comme `CvthequeService` l.94-97), `latitude/longitude` PROFILE non null
(jamais `device_*`), contrat de l'offre dans `wanted_contract_types` ou liste
vide/null (`whereJsonContains`, que Laravel compile en `JSON_CONTAINS` sur MySQL
et en `json_each` sur SQLite), haversine `<= mobility_radius_km` **et** `<= recruitment_radius_km`
(boîte englobante à 100 km), compte non suspendu/supprimé, pas de blocage dans les
deux sens, aucune ligne `offer_interests` pour cette offre avec `employer_decision`
non null **ou `candidate_decision = PASS`** (un candidat qui a passé l'offre n'a
rien à faire dans le deck : un LIKE employeur sur lui ne produirait ni match ni
notification, et son absence est indiscernable d'une inéligibilité — rien n'est
révélé). Tri : `candidate_decision = LIKE` sur cette offre d'abord, puis
`updated_at` desc ; `paginate(20)`. Chaque élément = `CandidateCardPresenter::present($profile, $offer, coversOffer: true)`

- `candidate_interested` + `skills_in_common` (noms).

**`App\Services\InterestService`.**

- `like(User $user, JobOffer $offer, ?CandidateProfile $target = null): array` :
  candidat → `requireProfile`, offre `PUBLISHED` sinon `JOB_OFFER_NOT_PUBLISHED`
  (409), blocage → `USER_BLOCKED` (403), quota (ci-dessous) → `INTEREST_QUOTA_REACHED`
  (429) ; employeur → `requireOwnedOffer`, `requireVerified`, périmètre, cible
  éligible (mêmes critères que le deck, sinon `CANDIDATE_NOT_ELIGIBLE` 409), quota
  30/jour/offre. Puis `MatchService::record(...)` (§ suivant). Réponse `{interest, matched: bool}`
  où `interest` est **projeté par le service**, jamais le modèle brut :
  `{id, job_offer_id, candidate_profile_id, decision (celle de l'appelant), decided_at, matched_at, application_id}`.
  **Jamais la décision de l'autre partie** : sérialiser `OfferInterest` tel quel
  renverrait `employer_decision: "PASS"` au candidat (ou `candidate_decision: "PASS"`
  à l'employeur), ce que rien dans `MOBILE.md` §5 ne montre (« En attente » des deux
  côtés). Même projection dans `undoLast` et `passBatch`.
- `passBatch(User $user, int $jobOfferId|array $jobOfferIds, array $candidateProfileIds = []): int` :
  PASS par lot (50 max), `updateOrCreate` sans jamais écraser une décision existante,
  aucune notification. Candidat : `requireProfile`, seules **ses** lignes
  (`candidate_profile_id` = son profil, quel que soit le corps) ; employeur :
  `requireOwnedOffer` + `requireVerified` sur `job_offer_id`, sinon un employeur
  poserait des PASS sur l'offre d'un autre. Ni périmètre ni éligibilité pour un
  PASS : il ne révèle rien et ne crée rien de visible.
- `undoLast(User $user): array` : dernier geste de l'appelant (candidat :
  `candidate_decided_at` max ; employeur : `employer_decided_at` max sur ses offres) ;
  absent → `NOTHING_TO_UNDO` (404) ; plus de 5 minutes → `UNDO_WINDOW_EXPIRED` (409) ;
  `matched_at` non null et (plus de 60 s **ou** autre partie déjà notifiée) →
  `MATCH_ALREADY_NOTIFIED` (409) ; ligne portant un `application_id` →
  `APPLICATION_ATTACHED` (409, « Retire ta candidature pour annuler. ») : le LIKE
  posé par `applyForUser` s'annule par `DELETE applications/{id}`, pas ici, sinon
  un dossier resterait sans intérêt. Sinon décision, date et `matched_at` remis à null,
  ligne supprimée si vide. **Décision du lot, à écrire dans le code et `MOBILE.md`
  §5** : sans worker, notification et email partent dans l'appel qui crée le match,
  `*_notified_at` sont posés au même instant, un match n'est donc jamais annulable
  en pratique ; la fenêtre de 60 s reste codée pour une future queue.
- Quota candidat : 20 lignes `candidate_decision = LIKE` dont `candidate_decided_at`
  est dans les 24 h glissantes (**les PASS posent aussi `candidate_decided_at` et
  ne comptent pas**), **actif seulement** si ≥ 20 offres Jeuncy `PUBLISHED` sont à
  portée de son rayon (même requête que la pile, périmètre inclus : rayon, sinon
  département, sinon France). Employeur : 30 lignes `employer_decision = LIKE`
  / 24 h / offre. Une annulation supprime ou vide la ligne, donc libère le quota.

**`App\Services\MatchService`.**
`record(int $candidateProfileId, int $jobOfferId, string $side, InterestDecision $decision): OfferInterest`
avec `$side` ∈ `{'candidate', 'employer'}` (deux constantes publiques `SIDE_CANDIDATE`,
`SIDE_EMPLOYER`) — signature fixée ici, appelée par `InterestService` et par
`ApplicationService::applyForUser` :
`DB::transaction` → `OfferInterest::where(pair)->lockForUpdate()->first()` ou création ;
si la décision du côté est déjà posée : identique → idempotent (retour), différente →
`INTEREST_ALREADY_DECIDED` (409) ; ligne `closed_at` non null → `INTEREST_CLOSED` (409).
Pose décision + date. Si les deux sont LIKE et `matched_at` null → `matched_at = now()`,
rattache `application_id` si une `Application` existe pour le couple (et `applications.interest_id`
en retour), commit, puis **hors transaction** : notification `NEW_MATCH` aux deux
(candidat → lien `/mes-candidatures`, employeur → `/mes-offres`), emails
`MailService::sendNewMatchEmail` aux deux dans `sendWithoutBreakingTheFlow`
(copie de `ApplicationService` l.229-236), `*_notified_at = now()`. Si `application_id`
existe, le message dit « dossier déjà envoyé » et n'invite pas à l'envoyer. LIKE
employeur seul → notification `INTEREST_RECEIVED` au candidat (in-app, pas d'email,
lien `/offres/{id}`) **sauf si `candidate_decision = PASS`** (le candidat a écarté
l'offre : on ne la lui remet pas sous les yeux ; le deck employeur ne devrait
d'ailleurs plus le proposer, voir `candidatesForOffer`). LIKE candidat seul → rien
chez l'employeur. Paramètre `bool $notify = true` : `applyForUser` (ci-dessous)
passe `false` quand le LIKE candidat accompagne un dossier, la notification
`NEW_APPLICATION` existante tenant lieu d'annonce du match — un `NEW_MATCH` en
plus dirait deux fois la même chose ; `*_notified_at` sont posés quand même.
`listForUser(User): Collection` et `findForUser(User, OfferInterest): OfferInterest` :
lignes `matched_at` non null, `closed_at` null, filtrées par profil (candidat) ou
offres possédées (employeur, **après `requireVerified`** : une entreprise passée
REJECTED à la re-vérification ne doit plus lire de carte, invariant « jamais
d'employeur non VERIFIED face à un candidat ») ; candidat : `jobOffer` + organisation publique
(`$hidden` du modèle) + `application` (statut) ; employeur : `candidate` =
`CandidateCardPresenter::present($profile, $offer)` + `application` complète si
envoyée (même contenu que `listForOffer`). Blocages appliqués ; ligne étrangère
ou bloquée → `MATCH_NOT_FOUND` (404), jamais 403 (ne pas confirmer l'existence).

**`App\Services\ExternalInterestService`** : `decide(User, ExternalJobOffer, ExternalInterestDecision): ExternalInterest`
(`updateOrCreate` sur le couple, dénormalise `company_siret, company_name, title, city, apply_url`,
`decided_at = now()`) ; `markDone(User, ExternalInterest)` (propriété vérifiée,
`FORBIDDEN` 403) ; `listKept(User): Collection` (KEEP, `done_at` d'abord null,
`decided_at` desc) ; `undoLast(User)` (5 minutes, même code d'erreur que `InterestService`).

**`BlockService`** est en F (§1.5). B écrit `BlockController` : la **cible se
désigne sans `user_id`**, que ni la carte candidat (`user_id` interdit par le
présenteur) ni la fiche organisation (`user_id` dans `$hidden`) n'exposent.
`StoreBlockRequest` accepte exactement un de `candidate_profile_id` (employeur →
`CandidateProfile::user_id`), `job_offer_id` (candidat → `JobOfferService::ownerUser`
l.379) ou `user_id` (ADMIN/STAFF seulement), et le contrôleur résout l'id avant
`BlockService::block`. Cible introuvable → `BLOCK_TARGET_NOT_FOUND` (404).
**`ReportService`** : `report(User, array): Report`, même résolution de la cible
(`candidate_profile_id` en contexte CARD/MATCH/PHOTO côté employeur, `job_offer_id`
en contexte OFFER côté candidat, `MATCH` accepte aussi `offer_interest_id` d'un
match de l'appelant) ;
un signalement par (reporter, cible, contexte) et par 24 h, sinon `REPORT_ALREADY_SENT` (429).

**`App\Http\Middleware\EnsureMatchAge`** (alias `match.age` dans `bootstrap/app.php`
l.123-125, ajouté à l'intégration ; B le déclare aussi dans ses tests via
`Route::middleware`) : pour `role === CANDIDATE` uniquement : profil absent →
`CANDIDATE_PROFILE_REQUIRED` (403, « Crée ton profil pour découvrir des offres. ») ;
`birth_date` null → `BIRTH_DATE_REQUIRED` (403) ; âge < 16 → `MATCH_MIN_AGE` (403,
« Découvrir est réservé aux 16 ans et plus. ») ; constante publique `MIN_AGE = 16`.
Les autres rôles passent (leurs gardes sont dans les services).

**`ApplicationService`** : `applyForUser` (l.29-91) dans une transaction, pose
`source` (`MATCH` si un `offer_interests` matché existe pour le couple, sinon `APP`
si `X-Jeuncy-Client: mobile` — le contrôleur lit l'en-tête comme `AuthController::isMobileClient`
l.179 et passe `bool $fromMobile` —, sinon `SITE`). **Un dossier vaut LIKE** : après
la création de l'`Application`, `MatchService::record(profil, offre, SIDE_CANDIDATE, LIKE, notify: false)`
(idempotent : un LIKE déjà posé ne change rien ; un PASS antérieur est **remplacé**
par LIKE, seul cas où une décision change — postuler est un geste plus fort que
passer, et `INTEREST_ALREADY_DECIDED` n'a pas de sens ici). Ainsi, quand
l'employeur avait dit oui en premier, le match naît « dossier envoyé » au moment
du dossier (`MOBILE.md` §5 : « Si une candidature existe déjà quand le match
naît »), l'offre quitte la pile du candidat, et `interest_id`/`application_id`
sont écrits des deux côtés. `listForOffer`
(l.108) : `requireVerified` **avant** `APPLICATIONS_ACCESS_REQUIRED`, blocages exclus.
Conséquence sur `ApplicationServiceTest` (l.162-247, B) : les entreprises de ces
tests passent VERIFIED (pré-adaptées par F, §1.5) ; le test du 402 (l.180)
garde son sens avec une entreprise VERIFIED sans abonnement.
`updateStatus` (l.192) : `responded_at = now()` si null. `withdrawForUser` (l.162) :
`MatchClosingService::closeForApplication($application, APPLICATION_WITHDRAWN)` avant
`delete()`.

**`MailService`** : `sendNewMatchEmail(string $to, string $counterpartLabel, string $offerTitle, bool $applicationSent, string $url): void`,
même `send()`/`wrapEmailHtml` (l.373-441), sobre : « {Entreprise} veut te parler »
côté candidat, « Un candidat a répondu à ton intérêt » côté employeur (prénom +
initiale seulement) ; `sendMatchClosedEmail` n'existe pas (in-app suffit).

### 2.3 Lot C — exposition, RGPD, expiration

**`CvthequeService`** (l.32-270) : `requireCvthequeAccess` (l.60) **puis**
`requireVerified` pour COMPANY/CFA — dans cet ordre, pour que
`test_search_is_refused_without_active_subscription` (l.69, `makeNonSubscriber`
sans `Company`) continue de recevoir son 402 et non un 403 ; `search` (l.71)
sélectionne toutes les colonnes nécessaires au
présenteur, filtres `city` **supprimé** (un filtre par ville révèle la ville par
inférence — décision 2 du `MOBILE.md` §2), `driving_license` remplacé par
`has_driving_license`, `q` ne cherche plus `last_name`/`CONCAT` (l.127-129) sauf pour
ADMIN/STAFF, ni `bio` (l.131, non exposée : chercher dans ce qu'on ne montre
pas révèle par inférence, même raison que la ville) ; blocages exclus
(`BlockService::blockedUserIdsFor`, F) ; `->through(fn ($p) => $this->presenter->present($p))`.
`find` (l.147) renvoie **un tableau** `present($profile) + ['cv_available' => bool]`
(type de retour changé, `CvthequeController::show` suit). `downloadCv`
(l.195) charge donc le profil lui-même (même requête visible-seulement que
`find`, extraite dans un `visibleProfileOrFail(int $id): CandidateProfile` privé)
au lieu d'appeler `find()` comme aujourd'hui (l.197) : `cv_available` = ADMIN/STAFF,
ou existence d'une `Application` du profil sur une offre de l'organisation de
l'appelant (`whereHas('jobOffer', company_id|cfa_organization_id)`) ; sinon
`CV_NOT_SHARED` (403, « Le CV est partagé quand le candidat postule à l'une de
tes offres. »). `resolveCvFor` et `CvDownload` inchangés. `LIST_COLUMNS` (l.48-51)
disparaît.

**`AccountService`** : `exportData` (l.30) ajoute `offer_interests` (avec
`jobOffer:id,title`), `external_interests`, `user_blocks` et `reports` émis, et
rend visibles les coordonnées du profil (`makeVisible(CandidateProfile::OWNER_VISIBLE)`,
sinon `$hidden` les retire de l'export) ;
`deleteAccount` (l.78) appelle `closeForCandidateProfile($profile, ACCOUNT_DELETED)`
**avant** la transaction (les lignes partent ensuite par cascade) — **et, pour un
compte COMPANY/CFA, `closeForOffer($offre, ACCOUNT_DELETED)` sur chacune de ses
offres** : les deux chemins (l.113-115 `company?->delete()` et l.129
`$user->delete()`) suppriment l'organisation, donc ses offres et leurs
`offer_interests` par cascade, sans qu'aucun candidat matché ne soit prévenu.

**`ExpireJobOffers`** (`retirerLesEchues`) : `closeForOffer($offre, OFFER_EXPIRED)`
par offre retirée ; une offre FREE n'expire jamais (l.79), seules les payées passent ici.

### 2.4 Verification, périmètre, âge : où chaque garde s'applique

| Garde                      | Code (HTTP)                                                                | Appliquée dans                                                                                                                                                                                                             |
| -------------------------- | -------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Employeur vérifié          | `COMPANY_NOT_VERIFIED` (403)                                               | `DiscoverService::candidatesForOffer`, `InterestService::like`/`passBatch` (employeur), `MatchService::listForUser/findForUser` (employeur), `CvthequeService::search/find/downloadCv`, `ApplicationService::listForOffer` |
| Département ouvert (offre) | `MATCH_NOT_OPEN_HERE` (403)                                                | `candidatesForOffer`, `like` employeur — **jamais** côté candidat                                                                                                                                                          |
| 16 ans (candidat)          | `CANDIDATE_PROFILE_REQUIRED`, `BIRTH_DATE_REQUIRED`, `MATCH_MIN_AGE` (403) | middleware `match.age` sur `discover/offers`, `interests*`, `external-interests*`, `matches*`                                                                                                                              |
| Blocage                    | `USER_BLOCKED` (403) ou exclusion silencieuse                              | `like`, decks, CVthèque, `listForOffer`, `matches`                                                                                                                                                                         |

### 2.5 `MatchClosingService` (F)

`closeForOffer(JobOffer, MatchClosedReason): int`, `closeForApplication(Application, …)`,
`closeForCandidateProfile(CandidateProfile, …)` : `offer_interests` ouvertes concernées
→ `closed_at = now()`, `closed_reason` ; pour chaque ligne **matchée**, notification
`MATCH_CLOSED` à l'autre partie (candidat : « L'offre « … » n'est plus disponible »,
lien `/mes-candidatures` ; employeur : « Un candidat a retiré son dossier » ou
« a supprimé son compte », lien `/mes-offres`), sans email. Retourne le nombre de
lignes fermées. Idempotent (`whereNull('closed_at')`).

## 3. Routes, Form Requests, JSON

Nouveaux fichiers de routes inclus dans `routes/api.php` (l.3-16, ajout à
l'intégration) : `discover.php`, `interests.php`, `matches.php`,
`external-interests.php`, `blocks-reports.php`. Tous sous `auth:api` ; réponses
enveloppées par `WrapApiResponse`. Codes d'erreur communs : `INVALID_INPUT` (400),
`UNAUTHORIZED` (401), `FORBIDDEN` (403, middleware de rôle).

| Méthode, chemin                                                                                                    | Middleware                                | Form Request (règles)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | Réponse `data`                                                                                |
| ------------------------------------------------------------------------------------------------------------------ | ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `PUT candidate-profile/preferences` (A, `candidate-profile.php`)                                                   | `role:CANDIDATE`                          | `UpdateCandidatePreferencesRequest` : `wanted_contract_types` sometimes array max 5, `.*` `Rule::enum(ContractType)` ; `wanted_sectors` sometimes array max 3, `.*` `Rule::enum(OfferSector)` ; `search_radius_km`, `mobility_radius_km` sometimes integer 5-100 ; `has_driving_license`, `has_vehicle`, `show_photo_to_employers` sometimes boolean ; `driving_license_categories` sometimes array, `.*` `Rule::enum(DrivingLicenseCategory)` ; `available_from` sometimes nullable date after_or_equal today ; `pitch` sometimes nullable string max 160 | profil complet (comme `GET candidate-profile`)                                                |
| `PUT candidate-profile/location` (A)                                                                               | idem                                      | `UpdateCandidateLocationRequest` : `latitude` required numeric -90..90, `longitude` required numeric -180..180                                                                                                                                                                                                                                                                                                                                                                                                                                             | `{location_source: 'DEVICE', device_located_at}` — jamais les coordonnées arrondies en retour |
| `DELETE candidate-profile/location` (A)                                                                            | idem                                      | —                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | `{location_source: 'PROFILE'                                                                  | null}` |
| `POST job-offers/express` (A, `job-offers.php`, **avant** `{jobOffer}`)                                            | `role:COMPANY,CFA`                        | `StoreExpressJobOfferRequest` : `title` required max 255 ; `contract_type` required enum ; `postal_code` required regex `^\d{5}$` ; `city` required max 255 ; `sector` required `Rule::enum(OfferSector)` ; `recruitment_radius_km` nullable integer 5-100                                                                                                                                                                                                                                                                                                 | offre publiée (201)                                                                           |
| `POST/PATCH job-offers` (A)                                                                                        | idem                                      | `StoreJobOfferRequest`/`UpdateJobOfferRequest` : + `postal_code` nullable regex `^\d{5}$`, `recruitment_radius_km` 5-100, `sector` enum, `schedule` max 255, `start_date` date, `minimum_age` integer 16-18, `requires_driving_license` boolean, `missions` array max 8, `.*` string max 200                                                                                                                                                                                                                                                               | inchangé                                                                                      |
| `POST/PATCH company` (A)                                                                                           | `role:COMPANY`                            | `StoreCompanyRequest` l.20 : `siret` **required**, regex `^\d{14}$`, unique ; `UpdateCompanyRequest` : `siret` sometimes required regex unique (ignore soi-même)                                                                                                                                                                                                                                                                                                                                                                                           | fiche + `verification_status`, `verification_note`                                            |
| `GET discover/offers` (B)                                                                                          | `role:CANDIDATE`, `match.age`             | `DiscoverOffersRequest` : `page` sometimes integer min 1                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | voir exemple                                                                                  |
| `GET discover/candidates?job_offer_id=` (B)                                                                        | `role:COMPANY,CFA`                        | `DiscoverCandidatesRequest` : `job_offer_id` required exists ; `page`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | paginé, `data[]` cartes                                                                       |
| `POST interests` (B)                                                                                               | `role:CANDIDATE,COMPANY,CFA`, `match.age` | `StoreInterestRequest` : `job_offer_id` required exists ; `candidate_profile_id` required_if rôle employeur, exists — **ignoré pour un candidat** (le service prend toujours `requireProfile($user)`, jamais le corps)                                                                                                                                                                                                                                                                                                                                     | `{interest, matched}` (projection du §2.2, sans la décision de l'autre partie)                |
| `POST interests/batch` (B)                                                                                         | idem                                      | `PassInterestsRequest` : `job_offer_ids` required array max 50 (candidat) ; `job_offer_id` + `candidate_profile_ids` array max 50 (employeur)                                                                                                                                                                                                                                                                                                                                                                                                              | `{passed: n}`                                                                                 |
| `DELETE interests/last` (B)                                                                                        | idem                                      | —                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | `{undone: {job_offer_id, candidate_profile_id}}`                                              |
| `GET matches`, `GET matches/{offerInterest}` (B)                                                                   | idem                                      | —                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | liste / détail                                                                                |
| `POST external-interests` (B)                                                                                      | `role:CANDIDATE`, `match.age`             | `StoreExternalInterestRequest` : `external_job_offer_id` required exists ; `decision` required enum KEEP/PASS                                                                                                                                                                                                                                                                                                                                                                                                                                              | `ExternalInterest`                                                                            |
| `PATCH external-interests/{externalInterest}/done`, `GET external-interests`, `DELETE external-interests/last` (B) | idem                                      | —                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | ligne / liste des KEEP / `{undone}`                                                           |
| `POST blocks`, `DELETE blocks/{userBlock}`, `POST reports` (B, `blocks-reports.php`)                               | tout connecté                             | `StoreBlockRequest` : exactement un de `candidate_profile_id` (exists), `job_offer_id` (exists), `user_id` (exists, ADMIN/STAFF seulement) — voir §2.2, aucun client ne connaît le `user_id` d'autrui ; `DELETE blocks/{userBlock}` : `FORBIDDEN` (403) si `blocker_user_id` n'est pas l'appelant ; `StoreReportRequest` : `context` enum, `reason` required max 40, `details` nullable max 2000, cible désignée comme pour le blocage (+ `offer_interest_id` en contexte MATCH), `required_without_all` entre les champs de cible                         | `UserBlock` / `{deleted: true}` / `Report` (201)                                              |
| `GET cvtheque`, `GET cvtheque/{id}`, `GET cvtheque/{id}/cv` (C, `cvtheque.php` l.17-27)                            | inchangé                                  | `SearchCvthequeRequest` : `city` retiré, `driving_license` → `has_driving_license` boolean, `age_min` min 16                                                                                                                                                                                                                                                                                                                                                                                                                                               | cartes / carte + `cv_available` / PDF ou 403                                                  |
| `POST auth/register` (A)                                                                                           | —                                         | `RegisterRequest` inchangé (l.33)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | inchangé ; `age_confirmed_at` posé                                                            |

**`GET discover/offers`** :

```json
{
  "success": true,
  "data": {
    "jeuncy": [
      {
        "id": 12,
        "title": "Vendeur conseil en alternance",
        "contract_type": "ALTERNANCE",
        "city": "Perpignan",
        "postal_code": "66000",
        "sector": "COMMERCE",
        "schedule": "35h, samedi travaillé",
        "start_date": "2026-10-01",
        "minimum_age": 16,
        "requires_driving_license": false,
        "missions": ["Accueil", "Mise en rayon"],
        "compensation_amount": 900,
        "compensation_period": "MONTH",
        "work_mode": "PRESENTIEL",
        "training_rhythm": null,
        "company": {
          "id": 3,
          "name": "NexaTech",
          "logo_url": null,
          "verification_status": "VERIFIED"
        },
        "cfa_organization": null,
        "skills": [{ "id": 4, "name": "Vente" }],
        "distance_km": 3.2,
        "employer_interested": true,
        "already_applied": false,
        "published_at": "2026-09-20T08:00:00Z"
      }
    ],
    "partner": {
      "data": [
        {
          "id": 5401,
          "source": "lba",
          "title": "Boulanger H/F",
          "company_name": "Au pain doré",
          "city": "Canet-en-Roussillon",
          "contract_start": "2026-11-02",
          "contract_duration_months": 24,
          "target_diploma_label": "CAP",
          "apply_url": "https://…",
          "distance_km": 11.8
        }
      ],
      "current_page": 1,
      "last_page": 3,
      "total": 47
    },
    "meta": {
      "radius_km": 30,
      "department": "66",
      "has_coordinates": true,
      "location_source": "PROFILE",
      "scope": "radius",
      "quota": { "limit": 20, "used": 2, "active": false }
    }
  }
}
```

**`POST interests`** (candidat) : requête `{ "job_offer_id": 12 }` ; réponse
`{ "success": true, "data": { "interest": { "id": 77, "job_offer_id": 12, "candidate_profile_id": 41, "decision": "LIKE",
"decided_at": "2026-09-22T10:15:03Z", "matched_at": "2026-09-22T10:15:03Z", "application_id": null }, "matched": true } }`
— `decision` est celle de l'appelant ; la décision de l'autre partie n'apparaît
jamais (un `matched_at` non null dit tout ce qu'il y a à dire).
Employeur : `{ "job_offer_id": 12, "candidate_profile_id": 41 }`. Erreur quota :
`{ "success": false, "error": { "code": "INTEREST_QUOTA_REACHED", "message": "Tu as atteint tes 20 « Ça m'intéresse » du jour. Reviens demain." } }` (429).

**`GET discover/candidates?job_offer_id=12`** : `data[]` =

```json
{
  "id": 41,
  "first_name": "Léa",
  "last_name_initial": "G",
  "age_band": "18-20",
  "headline": "Vendeuse en alternance",
  "pitch": "Motivée, disponible dès septembre.",
  "wanted_contract_types": ["ALTERNANCE"],
  "wanted_sectors": ["COMMERCE"],
  "mobility": { "covers_offer": true },
  "has_driving_license": true,
  "driving_license_categories": ["B"],
  "has_vehicle": false,
  "available_from": "2026-09-01",
  "skills": [
    { "id": 4, "name": "Vente", "in_common": true },
    { "id": 9, "name": "Caisse", "in_common": false }
  ],
  "software": [{ "id": 2, "name": "Excel" }],
  "languages": [{ "name": "Anglais", "level": "B1" }],
  "educations": [
    {
      "degree": "Bac pro Commerce",
      "school": "Lycée Maillol",
      "field_of_study": null,
      "start_date": "2024-09-01",
      "end_date": null
    }
  ],
  "experiences": [
    {
      "title": "Vendeuse",
      "company": "Zara",
      "start_date": "2025-06-01",
      "end_date": "2025-08-31"
    }
  ],
  "photo_url": null,
  "has_uploaded_cv": true,
  "candidate_interested": false,
  "skills_in_common": ["Vente"]
}
```

avec `current_page`, `last_page`, `total` de la pagination Laravel.

**`GET matches`** (candidat) : `[ { "id": 77, "matched_at": "…", "job_offer": { …offre + organisation publique… },
"application": { "id": 9, "status": "SEEN", "responded_at": null } | null, "status": "AWAITING_APPLICATION" | "APPLICATION_SENT" } ]` ;
(employeur) : `application` devient la candidature complète de `listForOffer` et
`candidate` la carte du présenteur.

**`PUT candidate-profile/location`** : requête `{ "latitude": 42.68871, "longitude": 2.89483 }` ;
le serveur stocke `42.69, 2.89` ; réponse `{ "location_source": "DEVICE", "device_located_at": "…" }`.

## 4. Règle d'exposition : `App\Presenters\CandidateCardPresenter` (F)

Une seule méthode publique : `present(CandidateProfile $profile, ?JobOffer $offer = null, ?bool $coversOffer = null): array`.
**Liste blanche** — ce qui n'y figure pas ne sort pas :

`id`, `first_name`, `last_name_initial` (première lettre de `last_name`, majuscule),
`age_band` (accesseur, null sans date), `headline`, `pitch`, `wanted_contract_types`,
`wanted_sectors`, `mobility` (**uniquement** `{covers_offer: bool}` quand `$offer` et
`$coversOffer` sont fournis, sinon la clé est absente), `has_driving_license`,
`driving_license_categories`, `has_vehicle`, `available_from`, `skills` (`{id, name, in_common}`,
celles de `$offer->skills` d'abord), `software` (`{id, name}`), `languages` (`{name, level}`),
`educations` (`{degree, school, field_of_study, start_date, end_date}`), `experiences`
(`{title, company, start_date, end_date}` — pas de `location`, pas de `description`),
`photo_url` (**null sauf** `show_photo_to_employers`), `has_uploaded_cv` (`cv_file_url !== null`).

**Jamais** : `last_name`, `city`, `postal_code`, `address`, `phone`, `email`, `birth_date`,
`age` exact, `latitude`/`longitude`/`device_*`, `cv_file_url`, `cv_original_filename`,
`linkedin_url`, `video_url`, `portfolio_url`, `bio`, `hobbies`, `user_id`, `updated_at`.
`bio` et `hobbies` sont exclus par le principe de liste blanche (texte libre où
ville et téléphone finissent souvent) — à confirmer avec le patron si la CVthèque
web y perd trop.

Appelé par : `DiscoverService::candidatesForOffer` (avec offre), `MatchService::listForUser/findForUser`
côté employeur (avec offre), `CvthequeService::search` et `find` (sans offre).
La candidature complète (`ApplicationService::listForOffer` l.108-127, `Application`

- `candidateProfile` + `user:id,email`) reste **inchangée** : c'est le dossier, envoyé
  par un geste du candidat. Le test `CandidateCardPresenterTest::test_forbidden_keys_never_leak`
  construit un profil avec toutes les colonnes renseignées et vérifie l'absence de chaque
  clé interdite, y compris dans `experiences[*]` et `educations[*]`.

## 5. Impact web (`apps/web`, lot C)

- `packages/shared` (F) : nouveaux enums (§1.2) ; `pnpm --filter shared build`.
- `src/lib/api/cvtheque.ts` (l.8-57) : `CvthequeCandidate` remplacé par `CandidateCard`
  (forme du §4) ; `CvthequeCandidateDetail = CandidateCard & { cv_available: boolean }` ;
  `CvthequeSearchFilters` : `city` retiré, `driving_license` → `has_driving_license`,
  `toQueryString` (l.78-93) aligné ; `downloadCvthequeCv` inchangé.
- `src/lib/offer-sector-labels.ts` (nouveau) : `Record<OfferSector, string>` exhaustif ;
  `src/lib/age-band-labels.ts` : `'<18'` → « moins de 18 ans », etc.
- `src/pages/Cvtheque.tsx` : `filtersFromParams` (l.26-36) sans `city` ; `initials`
  (l.38-40) sur `last_name_initial` ; `CandidateCard` (l.72-135) : « Léa G. », `age_band`,
  **pas de ville**, badge permis depuis `has_driving_license` + catégories, `has_vehicle`,
  secteurs souhaités, photo seulement si `photo_url` non null ; formulaire (l.237-247) :
  champ ville retiré, case « Permis » liée à `has_driving_license`, `age_min` minimum 16.
- `src/pages/CvthequeCandidate.tsx` : en-tête (l.85-125) « Léa G. », `age_band`, sans
  `city` ; **bloc coordonnées supprimé** (l.128-185 : email, téléphone, LinkedIn,
  portfolio, vidéo) ; `DownloadCvButton` (l.188-192) rendu **seulement** si `cv_available`,
  sinon un paragraphe « Le CV est partagé dès que le candidat postule à l'une de vos
  offres. » ; sections `bio`/`hobbies` (l.194-310) remplacées par `pitch`, « Ce qu'il
  cherche » (contrats, secteurs, `available_from`), mobilité (permis, véhicule) ;
  `fallbackFilename` = `CV-${first_name}-${last_name_initial}.pdf`.
- `src/components/features/cvtheque/DownloadCvButton.tsx` : gère `ApiError` code
  `CV_NOT_SHARED` avec le message serveur (déjà le cas via `caught.message`, l.44-47).
- `src/components/features/organization/CompanyForm.tsx` (l.15-19) et `CfaForm.tsx`
  (l.16) : `siret` requis (`z.string().regex(/^\d{14}$/)`), plus de `.optional()` côté
  entreprise ; `src/lib/api/company.ts` et `cfa-organization.ts` : `verification_status`,
  `verification_note` sur le type ; `OrganizationSummary.tsx` affiche « Vérifiée » /
  « En attente de vérification » / « Refusée : {note} ».
- `src/lib/api/candidate-profile.ts` (l.41-71) : champs de préférences en lecture,
  optionnels ; les écrans `/profile` sont lot 5. La cloche (`notifications.ts`)
  affiche `message` + `link` : rien à changer pour les trois nouveaux types.
- Tests Vitest : `Cvtheque.test.tsx` (ni nom complet ni ville rendus),
  `CvthequeCandidate.test.tsx` (bouton CV absent sans `cv_available`).
- Hors lot 1 : `apps/mobile/.../organisation/informations.tsx` (l.56) laisse le
  SIRET facultatif ; le serveur répondra `INVALID_INPUT`, corrigé au lot 2.

## 6. Tests à écrire (`apps/api/tests/Feature/`)

Portabilité : le helper de `TestCase::setUp` (§1.5) rend la haversine exécutable
sous SQLite ; `whereJsonContains` y fonctionne (Laravel le traduit en `json_each`),
`DiscoverCandidatesTest::test_contract_filter_works_on_this_driver` le prouve.
Form Requests testées par requête HTTP (`actingAs($user, 'api')`), services par
`app()->make`, `MailService` mocké via `$this->app->instance` + Mockery.

| Fichier                                                                          | Méthode                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | Prouve                                                                                                                                                                                                                                                                           |
| -------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `SqliteTrigonometryTest` (F)                                                     | `test_haversine_sql_runs_on_sqlite`, `test_perpignan_to_canet_is_about_12_km`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | le helper injecte les fonctions ; `Haversine::sql` donne la bonne distance                                                                                                                                                                                                       |
| `MatchPerimeterTest` (A)                                                         | `test_empty_value_means_closed`, `test_star_means_all`, `test_list_restricts`, `test_corsica_and_overseas_codes`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | vide = fermé (test dédié exigé), `*`, liste, 2A/2B/971                                                                                                                                                                                                                           |
| `CandidateCardPresenterTest` (C)                                                 | `test_forbidden_keys_never_leak`, `test_photo_only_when_opted_in`, `test_mobility_only_with_offer_context`, `test_skills_in_common_come_first`, `test_age_band_boundaries` (17→'<18', 18, 20, 21, 25, 26, null)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | la liste blanche                                                                                                                                                                                                                                                                 |
| `MatchClosingServiceTest` (F)                                                    | `test_closes_open_interests_of_an_offer`, `test_notifies_the_other_party_only_when_matched`, `test_is_idempotent`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                   | fermeture + notification `MATCH_CLOSED`                                                                                                                                                                                                                                          |
| `CompanyVerificationServiceTest` (A)                                             | `test_luhn_rejects_invalid_siret`, `test_la_poste_siren_passes_luhn_exception`, `test_registry_down_gives_pending_never_verified`, `test_blocked_naf_gives_rejected`, `test_closed_establishment_gives_rejected`, `test_active_establishment_gives_verified_automatically`, `test_new_cfa_is_pending`, `test_require_verified_throws_403`, `test_form_request_refuses_invalid_siret` (400)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | chaque état, via `$this->registreEntreprises` (`TestCase` l.18). **Pas de test « IDA vérifiée par la migration »** : sous `RefreshDatabase` la migration tourne sur une base vide, elle ne peut rien prouver — c'est le selftest §8 (`cfa_verifiee`) qui le prouve en production |
| `CompanyServiceTest` (A, existant)                                               | `test_siret_is_required_on_create`, `test_update_cannot_leave_siret_empty`, `test_siret_change_reverifies`, `test_city_change_geocodes`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | gardes SIRET, déclenchement                                                                                                                                                                                                                                                      |
| `GeocodingServiceTest` (A)                                                       | `test_uses_cache_before_http`, `test_stores_null_on_failure_without_throwing`, `test_candidate_coordinates_are_rounded_to_2_decimals`, `test_backfill_is_idempotent`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | cache, panne muette, arrondi, commande                                                                                                                                                                                                                                           |
| `JobOfferExpressTest` (A)                                                        | `test_express_creates_and_publishes_in_one_call`, `test_publish_requires_postal_code_with_organization_fallback`, `test_archive_and_delete_close_interests`, `test_update_allows_published_free_offer`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              | offre express, repli, fermetures, complétion après publication (`Config::set('services.jeuncy.gratuit', true)` dans `setUp`)                                                                                                                                                     |
| `CandidatePreferencesTest` (A)                                                   | `test_preferences_are_validated` (secteurs > 3, rayon 4, catégorie inconnue), `test_pitch_refuses_email_and_phone`, `test_device_location_is_rounded_server_side`, `test_delete_location_falls_back_to_profile`, `test_owner_sees_coordinates_but_employer_never_does`, `test_export_includes_device_location`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | routes A + `$hidden`                                                                                                                                                                                                                                                             |
| `MigrateDrivingLicenseTest` (A)                                                  | `test_dry_run_writes_nothing`, `test_permis_b_gives_category_b`, `test_isolated_a_or_d_never_gives_a_category` (« véhicule à disposition », « titulaire d'un permis B » → B seul), `test_unknown_text_is_listed_not_written`, `test_apply_keeps_text_column`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | reprise du permis                                                                                                                                                                                                                                                                |
| `AuthServiceTest` (A, existant)                                                  | `test_age_confirmed_sets_timestamp`, `test_google_registration_leaves_it_null`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | `age_confirmed_at`                                                                                                                                                                                                                                                               |
| `MatchScorerTest` (B)                                                            | mêmes 16 cas que `JobOfferMatchServiceTest` l.84-260 réécrits sur le scorer + `test_structured_preference_overrides_text_heuristic`, `test_score_orders_city_then_keyword_then_sector`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              | extraction sans régression                                                                                                                                                                                                                                                       |
| `EnsureMatchAgeTest` (B)                                                         | `test_no_profile_403`, `test_no_birth_date_403`, `test_15_years_old_403`, `test_16_years_old_passes`, `test_employer_is_not_checked`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                | la garde des 16 ans                                                                                                                                                                                                                                                              |
| `DiscoverOffersTest` (B)                                                         | `test_radius_uses_device_location_when_present`, `test_falls_back_to_department_then_france`, `test_pile_is_never_bounded_by_perimeter`, `test_decided_and_blocked_offers_are_excluded`, `test_employer_interested_comes_first_then_score_then_distance`, `test_max_20_jeuncy_offers`, `test_partner_offers_exclude_kept_and_recent_pass`, `test_partner_pass_reappears_after_60_days`, `test_each_offer_carries_distance_flags`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | la pile candidat                                                                                                                                                                                                                                                                 |
| `DiscoverCandidatesTest` (B)                                                     | `test_requires_owned_offer`, `test_requires_verified_company`, `test_requires_open_department`, `test_eligibility_each_criterion` (visible, 16 ans et `minimum_age`, date absente, coordonnées PROFILE seulement — un profil avec `device_*` seul est exclu —, contrat, double rayon, blocage, déjà décidé), `test_no_distance_no_city_in_payload`, `test_candidate_liked_first_then_recent`, `test_contract_filter_works_on_this_driver`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | le deck employeur                                                                                                                                                                                                                                                                |
| `InterestServiceTest` (B)                                                        | `test_candidate_like_alone_notifies_nobody_at_employer`, `test_employer_like_alone_notifies_candidate_in_app_only`, `test_employer_like_on_a_passed_candidate_notifies_nobody`, `test_double_like_creates_match_once` (appel répété idempotent), `test_match_attaches_existing_application_without_invite`, `test_match_notifies_and_emails_both`, `test_candidate_quota_inactive_under_20_offers_in_range`, `test_candidate_quota_20_per_sliding_day` (les PASS ne comptent pas), `test_employer_quota_30_per_offer`, `test_like_on_unpublished_offer_409`, `test_employer_like_requires_owned_offer_verified_and_open_department`, `test_pass_batch_never_overwrites_a_decision`, `test_pass_batch_employer_requires_owned_offer`, `test_candidate_body_profile_id_is_ignored`, `test_different_decision_is_409`, `test_response_never_carries_the_other_side_decision`                                                                                                                                                                                                                                           | intérêts, quotas, match                                                                                                                                                                                                                                                          |
| `UndoLastInterestTest` (B)                                                       | `test_undo_within_5_minutes`, `test_undo_after_5_minutes_409`, `test_match_is_not_undoable_once_notified`, `test_undo_frees_quota`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | annulation                                                                                                                                                                                                                                                                       |
| `MatchServiceTest` (B)                                                           | `test_list_for_candidate_shows_offer_and_status`, `test_list_for_employer_shows_card_then_full_application`, `test_closed_matches_are_hidden`, `test_foreign_match_is_404`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | Matchs                                                                                                                                                                                                                                                                           |
| `ExternalInterestServiceTest` (B)                                                | `test_keep_denormalizes_offer`, `test_kept_survive_offer_deletion`, `test_done_requires_ownership`, `test_pass_hides_60_days`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       | offres partenaires                                                                                                                                                                                                                                                               |
| `BlockReportTest` (B)                                                            | `test_block_hides_in_both_decks_cvtheque_and_applications`, `test_cannot_block_self`, `test_block_by_candidate_profile_and_by_offer`, `test_unblock_requires_ownership`, `test_report_deduplicated_24h`, `test_offer_report_targets_owner`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                          | blocages, signalements                                                                                                                                                                                                                                                           |
| `ApplicationServiceTest` (B, existant)                                           | `test_apply_sets_source_site_app_or_match`, `test_apply_links_interest_both_ways`, `test_apply_records_candidate_like_and_matches_silently_when_employer_liked_first`, `test_applied_offer_leaves_the_candidate_pile`, `test_update_status_sets_responded_at_once`, `test_withdraw_closes_match_and_notifies_employer`, `test_list_for_offer_requires_verified_company`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | rattachements                                                                                                                                                                                                                                                                    |
| `CvthequeServiceTest`, `CvthequeDownloadTest`, `CandidateAgeTest` (C, existants) | `test_detail_exposes_contact_details` (l.151) **inversé** en `test_detail_exposes_no_contact_details`, `test_city_filter_narrows_results` (l.163) supprimé, `test_search_results_expose_no_direct_contact_details` (l.133) : l'assertion `city === 'Perpignan'` (l.145) devient `assertArrayNotHasKey('city')`, `test_keyword_filter_matches_headline_and_bio` (l.173) → `headline` seul, `test_driving_license_filter_excludes_empty_values` (l.183) réécrit sur `has_driving_license`, `CandidateAgeTest` l.78-101 : `age` → `age_band` (`'18-20'`, `'<18'`, null), `test_list_shows_age_band_never_age_or_birth_date`, `test_download_refused_without_application` (`CV_NOT_SHARED`), `test_download_allowed_after_application`, `test_admin_downloads_without_application`, `test_last_name_search_only_for_staff`, `test_unverified_company_gets_403` ; **les 10 tests de `CvthequeDownloadTest` (l.79-216) qui téléchargent avec un abonné COMPANY** créent d'abord une `Application` du candidat sur une offre de cette entreprise (helper `makeApplicationFor()`), sinon ils reçoivent tous `CV_NOT_SHARED` | CVthèque alignée                                                                                                                                                                                                                                                                 |
| `StaffRoleTest` (C, existant)                                                    | inchangé : STAFF passe `requireVerified` et garde la recherche par nom (l.60)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                       |                                                                                                                                                                                                                                                                                  |
| `ModeGratuitTest` (F puis **aucun**)                                             | inchangé après la pré-adaptation des fixtures (§1.5) ; `test_a_company_without_subscription_reads_its_applications` (l.169) et `..._opens_the_cvtheque` (l.182) prouvent au passage qu'une entreprise VERIFIED gratuite passe les nouvelles gardes                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | mode gratuit + gardes                                                                                                                                                                                                                                                            |
| `AccountServiceTest` (C, existant)                                               | `test_export_includes_interests_and_blocks`, `test_delete_closes_matches_and_notifies_employers`, `test_company_deletion_closes_its_offers_matches_and_notifies_candidates`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         | RGPD                                                                                                                                                                                                                                                                             |
| `ExpireJobOffersCommandTest` (C, existant)                                       | `test_expiry_closes_matches_with_notification`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | fermeture                                                                                                                                                                                                                                                                        |
| `PublicDataExposureTest` (F, existant)                                           | `test_public_company_hides_verified_by_note_and_coordinates`                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | `$hidden`                                                                                                                                                                                                                                                                        |
| `DeployGeoStatsTest` (I)                                                         | inchangé ; sa méthode privée `activerTrigonometrieSqlite` peut être retirée                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                         |                                                                                                                                                                                                                                                                                  |
| `DiscoverOffersTest` (B), en plus                                                | `test_geocoding_is_never_called_for_real` (`Http::assertNothingSent()` vers `data.geopf.fr` hors `GeocodingServiceTest`, via le stub de `TestCase`)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 | le fake de F est effectif                                                                                                                                                                                                                                                        |

Objectif : ~55 tests nouveaux. **La suite compte 543 tests aujourd'hui**
(`php artisan test --compact` le 2026-09-22 : 543/543, 1 314 assertions, 43 s),
pas 373 : tous restent verts hors ceux listés ci-dessus. Aucun test ne couvre
`DeployController::matchDebug` : sa cassure entre B (méthodes déplacées dans
`MatchScorer`) et I (bascule) ne se verra qu'à l'appel de `/deploy/{token}/match/{id}`.

## 7. Répartition en lots de fichiers disjoints

**F — fondations (préalable, séquentiel)** : les 12 migrations ; `app/Enums/{OfferSector, VerificationStatus, InterestDecision, ExternalInterestDecision, MatchClosedReason, ApplicationSource, DrivingLicenseCategory, ReportContext}.php` + `NotificationType.php` ; `app/Models/{OfferInterest, ExternalInterest, UserBlock, Report, GeocodeCache}.php` + modifications `CandidateProfile, JobOffer, Company, CfaOrganization, Application, User, ExternalJobOffer` (`HasFactory`) ; `database/factories/*` ; `app/Support/{Haversine, PostalCodes, MatchPerimeter}.php` ; `app/Presenters/CandidateCardPresenter.php` ; `app/Services/MatchClosingService.php` ; `app/Services/BlockService.php` ; `app/Services/CompanyVerificationService.php` (squelette) ; `tests/TestCase.php` (trigonométrie + stub `data.geopf.fr` + `preventStrayRequests`) ; **pré-adaptation des fixtures** de `tests/Feature/{ModeGratuitTest, CvthequeServiceTest, CvthequeDownloadTest, CandidateAgeTest, ApplicationServiceTest}.php` (§1.5, helpers seulement, aucune assertion) ; `packages/shared/src/enums/*` + `index.ts` ; tests `SqliteTrigonometryTest`, `MatchClosingServiceTest`, `BlockServiceTest` (`blockedUserIdsFor` dans les deux sens, `unblock` étranger refusé), `PublicDataExposureTest`.

**A — geo-verif** : `app/Services/{GeocodingService, CompanyVerificationService (complément), CompanyService, CfaOrganizationService, JobOfferService, CandidateProfileService, AuthService, TrainingOrganizationDetector}.php` ; `app/Console/Commands/{GeocodeBackfill, MigrateDrivingLicense}.php` ; `app/Http/Controllers/{JobOfferController, CompanyController, CfaOrganizationController, CandidateProfileController, Auth/AuthController}.php` ; `app/Http/Requests/CandidateProfile/{UpdateCandidatePreferencesRequest, UpdateCandidateLocationRequest}.php`, `JobOffer/{StoreExpressJobOfferRequest, StoreJobOfferRequest, UpdateJobOfferRequest}.php`, `Company/{StoreCompanyRequest, UpdateCompanyRequest}.php`, `CfaOrganization/StoreCfaOrganizationRequest.php` ; `app/Rules/ValidSiret.php` ; `routes/api/{candidate-profile, company, cfa-organization, job-offers}.php` ; `config/services.php` (clé `jeuncy.match_departements`, `env('JEUNCY_MATCH_DEPARTEMENTS', '66')` — **défaut `'66'`** comme l'arbitrage l'exige — normalisé en tableau comme `lba.departements` l.89-95 mais vide = `[]`), `.env.example` (`JEUNCY_MATCH_DEPARTEMENTS=66`) ; tests A du §6 (y compris `MatchPerimeterTest`, qui teste la classe de F avec la config de A ; `test_empty_value_means_closed` pose `Config::set(..., '')` explicitement, le défaut étant `'66'`). `JobOfferServiceTest` (existant) reçoit `test_update_allows_published_free_offer`.

**B — decouvrir-match** : `app/Services/{MatchScorer, JobOfferMatchService, DiscoverService, InterestService, MatchService, ExternalInterestService, ReportService, ApplicationService, MailService}.php` (`BlockService` est en F) ; `app/Http/Middleware/EnsureMatchAge.php` ; `app/Http/Controllers/{DiscoverController, InterestController, MatchController, ExternalInterestController, BlockController, ReportController, ApplicationController, JobOfferApplicationController}.php` ; `app/Http/Requests/{Discover, Interest, ExternalInterest, Block, Report}/*.php` ; `routes/api/{discover, interests, matches, external-interests, blocks-reports, applications}.php` ; tests B du §6.

**C — exposition** : `app/Services/{CvthequeService, AccountService}.php` ; `app/Http/Controllers/CvthequeController.php` ; `app/Http/Requests/Cvtheque/SearchCvthequeRequest.php` ; `app/Console/Commands/ExpireJobOffers.php` ; tests C du §6 ; **tout `apps/web`** (§5).

**I — intégration (après fusion de A, B, C)** : `routes/api.php` (5 `require`), `bootstrap/app.php` (alias `match.age`, `geocode:backfill` planifié quotidien via `$unePasseParPeriode`), `app/Http/Controllers/DeployController.php` (§8, dont `matchDebug` basculé sur `MatchScorer`), `MOBILE.md` §5 (paragraphe « contrainte à trancher » remplacé par la décision de l'annulation), §3.1 point 2 (« commune + code postal obligatoires pour activer Découvrir » → « sans coordonnées, la pile tombe sur le département puis la France ; l'employeur, lui, ne voit pas ce candidat »), §4.1 (tranche « 16-17 » → `'<18'`), §8 (`location_source` → deux paires `latitude/longitude` et `device_*`), `CLAUDE.md` §11.

Aucun fichier n'apparaît dans deux lots parallèles. `CompanyVerificationService.php`,
`tests/TestCase.php` et les cinq fichiers de tests pré-adaptés sont touchés par F
puis par un seul de A/B/C (ou aucun) — jamais par deux lots parallèles.
**Points de contact, signatures fixées ici pour coder sans attendre** :
`MatchClosingService::closeForOffer/closeForApplication/closeForCandidateProfile` (§2.5, F → A, B, C) ;
`CompanyVerificationService::requireVerified(User): void` et `isVerified` (§1.5, F → B, C) ;
`BlockService::blockedUserIdsFor(User): array` (§1.5, F → B, C) ;
`CandidateCardPresenter::present(CandidateProfile, ?JobOffer, ?bool): array` (§4, F → B, C) ;
`MatchPerimeter::isOpen(?string): bool` (F → B) ; `Haversine::sql/bindings/boundingBox` (F → B) ;
`MatchService::record(int, int, string, InterestDecision, bool $notify = true)` (§2.2, B interne, appelée par `ApplicationService` également en B) ;
`JobOfferService::requireOwnedOffer` et `ownerUser` (A, **inchangées**, appelées par B et F) ;
`CandidateProfileService::requireProfile` (A, **inchangée**, appelée par B).

## 8. Fichiers déployés et selftest (`DeployController`, lot I)

À ajouter à la liste de `version()` (l.556-568) — **chaque fichier nouveau ou modifié**,
leçon des 2026-09-04 et 2026-09-08 :

```
database/migrations/2026_09_22_1000{00..11}_*.php
app/Enums/{OfferSector,VerificationStatus,InterestDecision,ExternalInterestDecision,MatchClosedReason,ApplicationSource,DrivingLicenseCategory,ReportContext,NotificationType}.php
app/Models/{OfferInterest,ExternalInterest,UserBlock,Report,GeocodeCache,CandidateProfile,JobOffer,Company,CfaOrganization,Application,User,ExternalJobOffer}.php
app/Support/{Haversine,PostalCodes,MatchPerimeter}.php
app/Presenters/CandidateCardPresenter.php
app/Rules/ValidSiret.php
app/Services/{MatchClosingService,CompanyVerificationService,GeocodingService,CompanyService,CfaOrganizationService,JobOfferService,CandidateProfileService,AuthService,TrainingOrganizationDetector,MatchScorer,JobOfferMatchService,DiscoverService,InterestService,MatchService,ExternalInterestService,BlockService,ReportService,ApplicationService,MailService,CvthequeService,AccountService}.php
app/Console/Commands/{GeocodeBackfill,MigrateDrivingLicense,ExpireJobOffers}.php
app/Http/Middleware/EnsureMatchAge.php
app/Http/Controllers/{DiscoverController,InterestController,MatchController,ExternalInterestController,BlockController,ReportController,ApplicationController,JobOfferApplicationController,JobOfferController,CompanyController,CfaOrganizationController,CandidateProfileController,CvthequeController,DeployController,Auth/AuthController}.php
app/Http/Requests/CandidateProfile/{UpdateCandidatePreferencesRequest,UpdateCandidateLocationRequest}.php
app/Http/Requests/JobOffer/{StoreExpressJobOfferRequest,StoreJobOfferRequest,UpdateJobOfferRequest}.php
app/Http/Requests/Company/{StoreCompanyRequest,UpdateCompanyRequest}.php
app/Http/Requests/CfaOrganization/StoreCfaOrganizationRequest.php
app/Http/Requests/Cvtheque/SearchCvthequeRequest.php
app/Http/Requests/Discover/{DiscoverOffersRequest,DiscoverCandidatesRequest}.php
app/Http/Requests/Interest/{StoreInterestRequest,PassInterestsRequest}.php
app/Http/Requests/ExternalInterest/StoreExternalInterestRequest.php
app/Http/Requests/Block/StoreBlockRequest.php  app/Http/Requests/Report/StoreReportRequest.php
routes/api.php  routes/api/{candidate-profile,company,cfa-organization,job-offers,applications,discover,interests,matches,external-interests,blocks-reports,cvtheque}.php
bootstrap/app.php  config/services.php
```

Frontend et `packages/shared` sont déployés par build, hors de cette liste.
`.env` de production : `JEUNCY_MATCH_DEPARTEMENTS=66`.

**Selftest** (`selfTest`, l.699) — nouvelles entrées, toutes dans
`DB::beginTransaction()` … `DB::rollBack()` (`finally`), aucune donnée réelle touchée :

1. `cablage_match` : par réflexion sur les constructeurs (comme l.973),
   `JobOfferMatchService` reçoit `MatchScorer`, `DiscoverService` le présenteur,
   `CvthequeService` `CompanyVerificationService` et le présenteur.
2. `enum_notification_mysql` : `Notification::insert` d'une ligne `NEW_MATCH` sur un
   compte de sonde créé dans la transaction (prouve la valeur d'enum en MySQL).
3. `parcours_match` : création dans la transaction d'un employeur `VERIFIED` avec
   offre publiée géolocalisée dans le 66 et d'un candidat de 18 ans à 5 km ; puis,
   **par le noyau HTTP** (`app(Kernel::class)->handle(Request::create(...))` avec
   `Bearer` issu de `JwtService::issueAccessToken` l.19) : `GET discover/offers`
   (attend l'offre avec `distance_km`), `GET discover/candidates?job_offer_id=`
   (attend la carte sans clé interdite), `POST interests` employeur puis candidat
   (attend `matched: true`), `GET matches` des deux côtés ; `MailService` remplacé
   par un double inerte via `app()->instance`. Chaque étape passe par `essai()`
   (l.790) et rapporte statut HTTP + exception réelle.
4. `geocodeur` : `GeocodingService::geocode('66000', 'Perpignan')`, réseau réel.
5. `perimetre` : `MatchPerimeter::departments()` tel que lu du `.env`.
6. `cfa_verifiee` : nombre de `cfa_organizations` en `VERIFIED` (attendu ≥ 1
   après la migration 100002 : c'est la seule preuve possible de sa partie
   données, voir §6) et nombre d'offres `PUBLISHED` sans coordonnées (attendu 1
   tant qu'IDA n'a pas complété son code postal — après quoi `geocode:backfill`
   ou la modification de l'offre les renseigne).

Sonde sans effet de bord : `GET /api/discover/offers` avec un jeton de candidat
de 15 ans répond `MATCH_MIN_AGE` seulement une fois le middleware déployé.

**Après déploiement, une action humaine** : l'offre publiée d'IDA n'a pas de
code postal ; tant qu'il n'est pas saisi (modification d'une offre FREE publiée,
§2.1), `discover/candidates` répond `JOB_OFFER_NOT_LOCATED` et la pile des
candidats du 66 la classe en repli « département », sans distance.

## 9. Décisions de conception prises ici

1. **Deux paires de coordonnées** sur le profil (`latitude/longitude` géocodées =
   PROFILE, `device_*` = GPS) plutôt qu'une paire + `location_source` : « le GPS ne
   sert qu'à la pile du candidat » devient structurel, le deck employeur ne lit
   jamais `device_*`, un test le prouve.
2. **Présenteur, `MatchClosingService`, `MatchPerimeter`, `Haversine` et le squelette
   de `CompanyVerificationService` vivent en F** : B et C en ont besoin à l'exécution.
   C garde les tests d'exposition et le câblage CVthèque.
3. **Liste blanche** dans le présenteur : `bio` et `hobbies` sortent (non listés, à
   confirmer) ; ce qui n'est pas explicitement autorisé n'est pas exposé.
4. **Filtre `city` de la CVthèque supprimé**, recherche par nom réservée à ADMIN/STAFF :
   filtrer par ville sans l'afficher la révèle par inférence.
5. **CV téléchargeable par ADMIN/STAFF sans candidature** (accès interne, même
   raisonnement que `cvtheque.php` l.12-16) ; COMPANY/CFA seulement après un dossier.
6. **`MatchScorer` garde les noms de méthodes** et une seule règle change (préférence
   structurée > heuristique texte) : les 16 tests existants passent tels quels.
7. **La pile candidat filtre par `wanted_contract_types`** quand la liste est non vide,
   et n'est jamais bornée au périmètre départemental.
8. **Match jamais annulable après l'appel qui le crée** : notification et email
   partent en synchrone, `*_notified_at` posés dans la foulée ; la fenêtre de 60 s
   reste codée pour une future queue. À écrire dans `MOBILE.md` §5.
9. **Une décision est finale** hors fenêtre d'annulation : LIKE répété idempotent,
   décision contraire = `INTEREST_ALREADY_DECIDED`, PASS par lot n'écrase rien, PASS
   partenaire masque 60 jours.
10. **Jamais VERIFIED par défaut** (registre muet = PENDING) ; SIRET requis à la
    création pour entreprise et CFA, à la mise à jour pour l'entreprise seule (IDA
    n'aurait pas de SIRET en base — **à vérifier**, §2.1) ; `verification_status`
    public, `verified_by`/note/coordonnées masqués comme `siret` l'est déjà.
11. **Un dossier vaut LIKE** (`applyForUser` pose `candidate_decision = LIKE`,
    match silencieux si l'employeur avait dit oui) : sinon une offre à laquelle le
    candidat a postulé depuis le site resterait dans sa pile et un match né d'un
    dossier n'existerait jamais comme match. Rien n'est envoyé sans geste du
    candidat — postuler est le geste.
12. **La décision de l'autre partie ne sort jamais d'une réponse** (projection de
    l'`OfferInterest` dans `InterestService`) ; **une cible se désigne par
    `candidate_profile_id` ou `job_offer_id`**, jamais par un `user_id` qu'aucun
    client ne connaît.
13. **`BlockService` en F** et **pré-adaptation des fixtures existantes en F** :
    un fichier appelé ou cassé par deux lots parallèles n'a qu'un moyen de rester à
    un seul propriétaire, être livré avant eux.
14. **Une offre FREE publiée reste modifiable** (§2.1) : l'offre express doit
    pouvoir être complétée, et IDA doit pouvoir saisir un code postal.

## 10. Écarts assumés et risques résiduels (relecture adverse du 2026-09-22)

- **ADMIN/STAFF téléchargent le CV sans candidature** (décision 5) : écart par
  rapport à l'arbitrage « n'est servi que si le candidat a une candidature sur une
  offre de cet employeur », justifié par l'accès interne existant (`cvtheque.php`
  l.12-16). À confirmer avec le patron ; le retirer coûte une ligne.
- **SIRET du CFA non exigé à la mise à jour** (décision 10) : repose sur un fait
  non prouvé (§2.1).
- **Filtre de la pile candidat par `wanted_contract_types`** (décision 7) :
  ajout par rapport à l'arbitrage, aligné sur `MOBILE.md` §3.1.
- **Pagination des offres partenaires** : l'arbitrage (20 par page, `?page=`)
  prime sur `MOBILE.md` §3.2 (« lot fini de 20 cartes, pas de curseur infini ») ;
  à harmoniser dans `MOBILE.md` à l'intégration.
- **Deux appels au registre** par création/modification de fiche entreprise si
  la mémorisation par requête (§2.1) est oubliée — sans effet fonctionnel, mais
  8 s de pire cas au lieu de 4.
- **Une entreprise PENDING (registre en panne) le reste** jusqu'à sa prochaine
  modification de fiche : pas de re-vérification planifiée ni de clic admin dans
  ce lot. Si le registre tombe le jour d'un salon, les inscrits de la journée
  ne voient aucun candidat jusqu'à ce qu'ils retouchent leur fiche.
- **Chemins de fermeture non branchés** : `ArchiveExpiredTrialOffers` (mode
  payant, dormant) et `AdminService` (archivage forcé) n'appellent pas
  `MatchClosingService` ; un match sur une offre archivée par l'admin resterait
  affiché « ouvert » côté candidat.
- **`matchDebug` non testé** entre B et I (§6).
- **`RESEND` présent en production** : le premier match réel enverra de vrais
  emails ; vérifier le gabarit sur un compte de test avant d'ouvrir le 66.
- **Vérification employeur sans preuve d'identité** : un SIRET public actif
  suffit à devenir VERIFIED automatiquement ; n'importe qui connaissant le SIRET
  d'une PME du 66 peut se présenter comme elle et voir des cartes de mineurs.
  Hors périmètre de l'arbitrage (qui ne demande que l'existence), mais c'est le
  risque résiduel le plus sérieux du lot : prévoir au minimum l'email de
  domaine ou le clic admin (`MOBILE.md` §4.0) avant d'ouvrir au-delà d'IDA.
