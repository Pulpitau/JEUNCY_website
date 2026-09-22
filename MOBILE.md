# Cadrage v2 — Application mobile Jeuncy, modèle match

> Remplace le cadrage du 2026-09-04 (v1, « l'app reprend le site »). Rédigé le
> 2026-09-22 après les décisions prises avec le patron. Chaque fait technique
> cité ici a été relu dans le code à cette date (fichier et ligne entre
> parenthèses) ; ce qui n'a pas pu l'être est signalé comme tel.
>
> Le contexte de la plateforme web reste dans `CLAUDE.md` ; les règles de code
> dans `CONVENTIONS.md`.

## 1. Objectif et positionnement

Jeuncy mobile devient une application de **mise en relation par cartes** : le
candidat découvre des offres une à une et dit s'il est intéressé, l'entreprise
découvre des candidats éligibles et fait de même, un **match** naît du double
oui, et le **dossier** (la candidature existante) part sur un geste explicite du
candidat. Le site reste le back-office complet ; l'app est la porte d'entrée
des jeunes, l'endroit où « Match ton alternance » se joue littéralement.

Trois principes non négociables : jamais de candidature sans geste du candidat,
jamais de donnée de localisation ou de coordonnée côté employeur avant le
dossier, jamais un employeur non vérifié face à un mineur.

**Vocabulaire officiel** (app, site, documents tiers) : « Découvrir »,
« Match », « Ça m'intéresse », « Passer », « Sélection du jour », « Envoyer mon
dossier ». Le nom de l'application de rencontres dont le geste s'inspire
n'apparaît nulle part ailleurs que dans nos notes internes (décision 10, §2).
Slogan : « Match ton alternance ».

## 2. Journal des décisions

### 2026-09-09 — socle

- L'app parle à l'**API de production** avec de vrais comptes de test ; tout
  changement backend est déployé immédiatement (FTP OVH), pas d'environnement
  intermédiaire.
- Seule modification backend : mode mobile de l'authentification par l'en-tête
  `X-Jeuncy-Client: mobile` (`AuthController.php` l.30, `isMobileClient` l.179),
  refresh token dans le corps JSON, jamais de cookie.
- Âge minimum **15 ans**, case déclarative à l'inscription (`inscription.tsx`
  l.46 et l.139 ; côté serveur `RegisterRequest.php` l.33, règle `sometimes`,
  `accepted`).

### 2026-09-17 — pivot vers le modèle match

Le patron demande une app « façon swipe » : cartes, match, géolocalisation. La
phase 1 classique (recherche, profil, candidature, validée sur iPhone) est mise
en pause. Une enquête (trois conceptions, contre-expertises, précédents Switch,
Kudoz, Jobamax, hokify, Brigad) conclut : le geste attire l'installation, mais
aucun « swipe de l'emploi » n'a survécu au swipe seul ; ils sont morts
d'employeurs muets et de piles vides. Le produit qui peut réussir est « réponse
garantie », et commence par le côté entreprise, là où le volume existe.

### 2026-09-22 — les onze décisions (Pierre, avec le patron)

| #   | Décision                                                                                                                                                                                                                               | Raison en une ligne                                                                                                                         |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Geste droit candidat = feuille à deux boutons : « Envoyer mon dossier maintenant » ou « Juste marquer mon intérêt ». Jamais de candidature sans geste.                                                                                 | Une candidature signifie « je veux ce poste » ; c'est la décision du 2026-09-03 appliquée au geste.                                         |
| 2   | Pas de distance ni de tri par proximité côté employeur. Il voit « Sa zone de mobilité couvre ton offre », permis par catégorie, véhicule, et règle un rayon de recrutement sur l'offre. Le candidat, lui, voit la distance des offres. | Le lieu de résidence est un critère discriminatoire (L1132-1, recommandations CNIL recrutement) ; un contentieux coûterait plus que l'info. |
| 3   | Âge minimum de l'app : **16 ans** (le site reste à 15). Règle serveur : Découvrir, intérêts et matchs exigent `birth_date` ≥ 16 ans quel que soit le client.                                                                           | Classification 16+ des stores sans parcours d'exception parentale ; un 15 ans garde le site classique.                                      |
| 4   | CVthèque du site alignée sur « rien avant candidature ».                                                                                                                                                                               | Une seule règle d'exposition ; sinon la promesse « caché avant le match » est fausse dès qu'on ouvre le site.                               |
| 5   | STAFF = Pierre et Claude au début.                                                                                                                                                                                                     | Chaque action admin doit tenir en un clic, tout ce qui peut être automatique (rappels, clôture J+30) l'est.                                 |
| 6   | Périmètre de lancement : Pyrénées-Orientales (66), ouverture par département via configuration.                                                                                                                                        | Réseau atomique d'abord (IDA, Perpignan), comme les précédents qui ont tenu.                                                                |
| 7   | V1 sans messagerie.                                                                                                                                                                                                                    | Coût de modération d'un texte libre entre adultes et mineurs ; le dossier + statuts + coordonnées transmises suffisent.                     |
| 8   | Photo candidat : un portrait unique, montré aux entreprises seulement si le candidat coche « Montrer ma photo aux entreprises » (désactivé par défaut).                                                                                | Opt-in : c'est le candidat qui active ; Apple 1.2 interdit le « hot-or-not », la carte est texte d'abord.                                   |
| 9   | Compte Apple Developer : Pierre pense l'avoir ; à vérifier (un Apple ID n'est pas une adhésion au Developer Program).                                                                                                                  | Sans lui : ni push, ni build de développement, ni TestFlight. Tout se teste en Expo Go en attendant.                                        |
| 10  | Vocabulaire officiel (section 1).                                                                                                                                                                                                      | Lycées, parents, CFA et PME ne doivent jamais lire « Tinder ».                                                                              |
| 11  | Rendu rapide de la base, puis amélioration lot par lot avec les directives du patron.                                                                                                                                                  | Le plan par lots (section 10) livre du visible tôt.                                                                                         |

## 3. Parcours candidat, écran par écran

### 3.1 Onboarding « ce que je cherche » (rattrapable depuis Profil)

1. **Ce que je cherche** : contrats (puces, valeurs de `ContractType` :
   ALTERNANCE, SAISONNIER, BENEVOLAT, JOB_ETUDIANT, STAGE — `ContractType.php`
   l.7-11), 1 à 3 secteurs d'une liste fermée.
2. **Où** : commune + code postal, géocodés côté serveur. Ils restent
   nullables en base et facultatifs sur le site (`StoreCandidateProfileRequest`
   l.35-36 ; migration `2026_07_17_000001` l.21-22), et le lot 1 ne les a pas
   rendus obligatoires — une porte fermée sur un champ vide aurait exclu les 5
   profils de production qui n'en ont pas, sans rien leur proposer. **Sans
   coordonnées** : la pile du candidat tombe sur le département de son code
   postal, puis sur toute la France (`meta.scope`), donc il voit quand même
   des offres ; en revanche **il n'apparaît dans le deck d'aucun employeur**
   (une distance qu'on ne sait pas calculer ne peut pas être comparée aux deux
   rayons), et l'écran l'invite à compléter « Où ? ». Le GPS (`DEVICE`,
   `PUT candidate-profile/location`) prime sur la position géocodée quand il
   est présent, et seulement pour sa propre pile.
3. **Mobilité** : rayon 5-100 km (défaut 30), permis par catégorie, véhicule,
   disponible dès. Le champ texte `driving_license` existant (migration
   `2026_07_21_151050` l.13) est conservé une version pour le gabarit CV.
4. **Visibilité** : « Rendre mon profil visible aux entreprises » et « Montrer
   ma photo aux entreprises », deux interrupteurs **désactivés par défaut**
   pour les nouveaux profils. Le second est la décision 8 ; le premier est une
   recommandation de l'enquête du 2026-09-17 (CNIL), pas l'une des onze
   décisions du 22 — à confirmer avec le patron. Aujourd'hui
   `is_visible_in_cvtheque` est à `true` par défaut (migration
   `2026_08_17_100000` l.23, décision produit du 2026-08-17 motivée par la
   CVthèque payante, depuis abandonnée) : ce défaut changerait pour les
   profils créés après le déploiement, sans toucher aux profils existants.
5. Facultatif : phrase de 160 caractères, portrait.

### 3.2 Découvrir

Une **Sélection du jour** : au plus 20 offres Jeuncy, renouvelée après
l'import LBA de la nuit — pas de curseur infini de ce côté-là. Les offres
partenaires, elles, sont **paginées par 20** (`?page=`, arbitrage du lot 1) :
il y en a 7 779 en production contre une seule offre Jeuncy, et s'arrêter à 20
pour tout le monde viderait la pile en une minute. Offres Jeuncy d'abord,
offres partenaires ensuite. Boutons ronds « Passer » / « Ça m'intéresse »
redondants aux gestes ;
tap = fiche dépliée sans quitter la pile ; « Annuler » sur le dernier geste.

**Carte offre Jeuncy, dans l'ordre d'affichage :**

1. Visuel : photo d'équipe approuvée, sinon logo sur dégradé signature
2. Intitulé (`job_offers.title`)
3. Entreprise ou CFA (`company_id` / `cfa_organization_id`, migration
   `2026_07_17_000009` l.16-17) — nom et logo (`companies.logo_url`)
4. Contrat (`contract_type`), rythme (`training_rhythm`), mode (`work_mode`,
   migration `2026_07_31_110000` l.17)
5. Ville + « à ~12 km » (distance calculée côté serveur, voir §6)
6. Rémunération : `formatCompensation` existant
   (`apps/mobile/src/lib/format-compensation.ts`, copie du web) sur
   `compensation_amount` / `compensation_period` (migration
   `2026_08_19_140000` l.26-29)
7. Horaires, début — colonnes **à créer** (`schedule`, `start_date`)
8. « Ce qu'on attend de toi » : trois compétences de `job_offer_skills`
   (migration `2026_07_28_110001`), celles du candidat en premier
9. Badges : « Permis requis », « Dès 16 ans », badge de réponse de
   l'entreprise (§5)
10. Bandeau « Cette entreprise s'intéresse à toi » si l'employeur a dit oui en
    premier

**Carte offre partenaire (La bonne alternance), dans l'ordre :**

1. Bandeau orange « Offre partenaire — candidature sur le site de
   l'employeur », pas de photo
2. Intitulé, employeur (`external_job_offers.company_name`), ville
   (`city`), distance calculée sur `latitude` / `longitude` déjà en base
   (migration `2026_09_15_120000` l.44-45, alimentées par `LbaOfferMapper.php`
   l.76-78)
3. Début et durée (`contract_start`, `contract_duration_months`), diplôme
   visé (`target_diploma_label`)
4. Mention de source (licence Etalab 2.0)

Sur une offre partenaire, le geste droit s'appelle **« Je garde »** : aucun
match possible, le mot n'apparaît pas, le navigateur ne s'ouvre pas.

### 3.3 Feuille à deux boutons (décision 1)

Sur « Ça m'intéresse » pour une offre Jeuncy :

- **« Envoyer mon dossier maintenant »** ouvre l'écran de candidature existant
  (`postuler.tsx`) pré-rempli : téléphone du profil (l.84-85), dernier CV
  Jeuncy ou PDF joint, message facultatif (`cover_letter`, 3 000 caractères
  max, `postuler.tsx` l.40). Appelle `POST applications`
  (`routes/api/applications.php` l.8) → `ApplicationService::applyForUser`
  (l.29-91), logique inchangée — seul ajout : le rattachement de
  `interest_id` (§8).
- **« Juste marquer mon intérêt »** enregistre la décision et attend le oui de
  l'entreprise.

### 3.4 Matchs

Liste des matchs avec le statut : « Envoie ton dossier », « Dossier envoyé »,
puis les statuts de candidature existants (`ApplicationStatus.php` : SENT,
SEEN, INTERVIEW, ACCEPTED, REJECTED, libellés dans `labels.ts` l.22), « Sans
réponse depuis N jours », « Clôturé par Jeuncy ». Écran de match sobre :
« NexaTech veut te parler », pas d'animation festive.

### 3.5 Candidatures

Écran existant (`candidatures.tsx`, appui long pour retirer via
`DELETE applications/{id}`, `routes/api/applications.php` l.10 →
`withdrawForUser` l.162) + section **« Gardées (site partenaire) »** : chaque
ligne ouvre `apply_url` et propose « C'est fait ».

### 3.6 Profil

Écrans existants (informations, expériences, formations, compétences,
logiciels, langues, CV, import de CV) + « Ce que je cherche », « Mobilité »,
et dans « Confidentialité et données » (`confidentialite.tsx`) : visibilité
(l.44), photo aux entreprises, position GPS effaçable, export (l.54),
suppression (l.82).

## 4. Parcours entreprise et CFA, écran par écran

Rappel (décision du 2026-09-15, `CLAUDE.md`) : l'inscription CFA est fermée
(`AuthService::assertRoleOpenForRegistration`, `CFA_REGISTRATION_CLOSED`) et
l'espace entreprise est gratuit. Le côté CFA de l'app ne concerne donc que
les comptes CFA existants (IDA) ; aucun écran d'inscription CFA n'est à
prévoir, et aucun paiement.

### 4.0 Porte de vérification, avant tout

SIRET obligatoire (14 chiffres + Luhn), existence vérifiée via
`recherche-entreprises.api.gouv.fr` — déjà appelée pour le code NAF par
`TrainingOrganizationDetector::nafFor` (l.174-184, `Http::timeout(4)`).
Statut `PENDING` → `VERIFIED` (automatique si SIRET trouvé et apparié, NAF
autorisé, établissement actif) ou `REJECTED` (Luhn faux, NAF d'enseignement,
établissement fermé), avec `verification_note` lisible. Registre muet =
`PENDING`, **jamais** VERIFIED par défaut. Tant que non vérifié : aucun deck,
aucun intérêt, aucune notification vers un candidat, CVthèque fermée, et
`job-offers/{id}/applications` fermé aussi (`COMPANY_NOT_VERIFIED`, 403).

Deux limites assumées au lot 1, à traiter avant d'ouvrir au-delà d'IDA : il
n'y a **pas de clic admin de vérification** (`admin/verifications`), donc une
entreprise passée `PENDING` parce que le registre était en panne le reste
jusqu'à sa prochaine modification de fiche ; et **un SIRET public actif suffit
à devenir VERIFIED**, sans preuve que le compte appartienne bien à cette
entreprise — c'est le risque résiduel le plus sérieux du lot, puisque ce
statut ouvre des cartes de mineurs. Email de domaine ou validation humaine à
prévoir.

État actuel à corriger : `siret` nullable (`StoreCompanyRequest.php` l.20 ;
`companies.siret` nullable unique, migration `2026_07_17_000007` l.16 ;
`cfa_organizations.siret` nullable, migration `2026_07_31_100000` l.12) et
`SubscriptionService::hasPaidAccess` accorde tout à COMPANY/CFA depuis le mode
gratuit (l.172-184).

### 4.1 Découvrir candidats

Sélecteur d'offre publiée, ou **« offre express »** (intitulé, contrat,
commune + code postal, secteur, rayon de recrutement) créée en une minute et
complétée plus tard — le deck n'est jamais verrouillé derrière le formulaire
long.

**Carte candidat, texte d'abord, dans l'ordre :**

1. Ce qu'il cherche (contrat, secteur)
2. Prénom + initiale du nom
3. Tranche d'âge (`<18` / 18-20 / 21-25 / 26+), jamais l'âge exact — accesseur
   `getAgeBandAttribute` à côté de `getAgeAttribute` (`CandidateProfile.php`).
   `<18` et non « 16-17 » : l'app impose 16 ans, mais le site en accepte 15
   depuis toujours et les profils existants ne disparaissent pas ; une borne
   basse affichée aurait été fausse pour eux. Le deck employeur, lui, exige
   `max(16, minimum_age de l'offre)`.
4. Titre (`headline`, migration `2026_07_21_094319`)
5. « Sa zone de mobilité couvre ton offre » — jamais une ville de résidence,
   jamais une distance
6. Permis (catégories), véhicule, disponible dès
7. Compétences (communes à l'offre en premier), formations, dernière
   expérience
8. Phrase de 160 caractères
9. Portrait en vignette de fin, **seulement** si `show_photo_to_employers`

**Caché avant le dossier** : nom complet, ville et adresse, email, téléphone,
date de naissance, CV. Gestes : « Ça m'intéresse » (30 par jour et par offre)
/ « Passer ». Le deck ne contient que des candidats **éligibles** (jamais un
score) : profil visible, ≥ 16 ans (un `birth_date` nul exclut — la colonne
est nullable en base, migration `2026_07_17_000001` l.19, obligatoire
seulement depuis le 2026-09-11 ; le candidat est invité à la compléter),
contrat souhaité compatible, zone de mobilité déclarée couvrant la commune
de l'offre et rayon de recrutement couvrant la commune déclarée du candidat
(calcul côté serveur uniquement, jamais affichée à l'employeur), non bloqué,
département ouvert.

### 4.2 Matchs, Candidatures reçues, Compte

Matchs avec statut ; Candidatures reçues sur les routes existantes
(`GET job-offers/{id}/applications`, `routes/api/applications.php` l.15 ;
`PATCH applications/{id}/status` l.11 → `updateStatus` l.192). Compte : fiche
existante (`organisation/informations.tsx`, lot E), statut de vérification,
logo, **photos d'équipe** (§7), engagement de réponse.

### 4.3 Règle d'exposition unique (app + CVthèque web)

Avant candidature ou dossier : prénom + initiale, titre, tranche d'âge, zone de
mobilité, permis/véhicule, compétences, formations, expériences ; pas de nom
complet, pas de ville de résidence, pas d'email, téléphone, adresse ni CV ;
photo seulement si autorisée.

Aujourd'hui la CVthèque web (`/candidats`, `App.tsx` l.105) fait autrement :
`CvthequeService::LIST_COLUMNS` (l.48-51) renvoie `last_name`, `city`,
`photo_url` (et `birth_date`, masquée après calcul de l'âge) ; `find()`
(l.147-182) renvoie la ligne complète du profil (`phone`, `address`) et
charge `user:id,email` (l.154), ne masquant que `cv_file_url` et
`birth_date` (l.174) ;
`cvtheque/{id}/cv` (`routes/api/cvtheque.php` l.26, `downloadCv` l.195) livre
le PDF. Les trois changent (décision 4), daté dans la politique de
confidentialité. Après dossier, l'employeur voit ce que le site montre déjà
d'une candidature : nom complet, téléphone (`applications.contact_phone`),
email (`listForOffer` l.124), CV (`generated_cv_id` / `cv_file_url`, migrations
`2026_07_28_120000` et `2026_07_28_130000`), message.

## 5. Le match et le dossier

**États d'une ligne `offer_interests`** (une par couple candidat/offre) :
`candidate_decision` et `employer_decision` (LIKE / PASS / null),
`matched_at`, `application_id` (le dossier), `closed_at` + `closed_reason`.
Match = les deux décisions à LIKE, posé dans la même transaction, idempotent.
Si une candidature existe déjà quand le match naît, il naît « dossier envoyé ».

**Notifications** : `NEW_MATCH` in-app + email aux deux parties.
`ApplicationService` l.76-77 explique déjà pourquoi l'in-app seul ne suffit
pas ; les emails partent via `MailService` (gabarit
`sendNewApplicationEmail` l.243) dans `sendWithoutBreakingTheFlow` (l.229),
jamais bloquants. Nouvelles valeurs de `NotificationType` par migration
`->change()` comme `2026_09_03_090000` l.32, et dans `packages/shared`.

**Ce que voit chaque partie :**

| Étape                  | Candidat                                   | Employeur                              |
| ---------------------- | ------------------------------------------ | -------------------------------------- |
| Intérêt employeur seul | Bandeau sur la carte, notification         | « En attente »                         |
| Intérêt candidat seul  | « En attente »                             | Rien (aucune donnée exposée)           |
| Match                  | « Envoie ton dossier »                     | Carte + « Attend son dossier »         |
| Dossier envoyé         | Statut de candidature                      | Candidature complète (coordonnées, CV) |
| Statut changé          | Notification + email (existant, l.199-216) | —                                      |

**Quand une partie ne répond pas** (cron OVH horaire à marqueur jour — le
montage `unePasseParPeriode` de `bootstrap/app.php` l.41-61, tâches
planifiées l.63-95 ; « J+3 » signifie entre J+3 et J+4) :

- Intérêt employeur sans réponse du candidat : rappel J+3, expiré J+14.
- Intérêt candidat sans réponse de l'employeur : J+7 le candidat est invité à
  envoyer son dossier directement (le geste reste le sien) ; l'employeur ne
  reçoit rien de nominatif (aucune donnée exposée avant le match), au plus un
  rappel agrégé « des candidats t'attendent dans Découvrir » ; expiré J+14.
- Match sans dossier : rappels J+2 et J+7, expiré J+30.
- Dossier sans changement de statut : J+3 rappel employeur (in-app + email),
  J+7 l'employeur entre dans l'onglet admin « Employeurs silencieux » (appel
  STAFF sous 48 h) et le candidat est prévenu, J+14 « Jeuncy a relancé
  l'entreprise », J+30 **« Clôture par Jeuncy »** (`CLOSED_BY_STAFF`, message
  standard au candidat) — jamais un statut posé au nom de l'entreprise.

Badge « Répond en N jours » sur `applications.responded_at` (nouvelle
colonne), affiché à partir de 5 candidatures traitées, sinon « Nouvelle
entreprise ». Transitions à spécifier et tester : match clos avec notification
sur `archiveForUser` (`JobOfferService.php` l.95), `deleteForUser` (l.128),
`ExpireJobOffers`, `withdrawForUser`, `deleteAccount` (`AccountService.php`
l.78).

**Annulation** : le dernier geste, gratuitement, pendant 5 minutes
(`DELETE interests/last`, `UNDO_WINDOW_EXPIRED` au-delà). L'annulation libère
le quota et efface la décision ; une ligne devenue vide est supprimée.

**Tranché au lot 1 (2026-09-22) : un match n'est pas annulable.** Sans worker
ni queue (§12), l'alternative était entre différer l'email au passage suivant
du cron — donc jusqu'à une heure de silence après un match, sur le seul écran
que les deux parties attendent — et l'envoyer tout de suite. C'est l'envoi
immédiat qui a été retenu : la notification in-app **et** l'email partent dans
l'appel qui crée le match, et `candidate_notified_at` / `employer_notified_at`
sont posés dans la foulée. Comme `undoLast` refuse une ligne matchée dont
l'autre partie est déjà notifiée (`MATCH_ALREADY_NOTIFIED`, 409), un match est
donc, **en pratique, toujours définitif dès l'appel qui le crée**. Le reste du
dernier geste (un LIKE sans réponse, un PASS) reste annulable normalement.

La fenêtre de 60 secondes reste **écrite dans le code** d'`InterestService`
(`matched_at` de moins d'une minute ET autre partie non notifiée) : le jour
où une queue existera, il suffira de retarder l'envoi pour que l'annulation
d'un match devienne réelle, sans retoucher la règle. Une ligne portant un
`application_id` ne s'annule jamais ici (`APPLICATION_ATTACHED`, 409) : le
LIKE posé par un dossier se retire en retirant le dossier, sinon une
candidature resterait sans l'intérêt qui la justifie.

**Fermeture d'un match** (`MatchClosingService`) : `archiveForUser`,
`deleteForUser`, `ExpireJobOffers`, `withdrawForUser` et `deleteAccount`
ferment les lignes ouvertes concernées (`closed_reason` OFFER_ARCHIVED /
OFFER_DELETED / OFFER_EXPIRED / APPLICATION_WITHDRAWN / ACCOUNT_DELETED) et
notifient l'autre partie (`MATCH_CLOSED`, in-app seulement) **quand la ligne
était matchée** — un intérêt à sens unique n'avait été annoncé à personne, le
signaler après coup révélerait un geste que le produit n'avait pas montré. Le
destinataire dépend du point d'entrée, jamais de la raison : `closeForOffer`
prévient le candidat, `closeForCandidateProfile` et `closeForApplication`
préviennent l'employeur. Toujours **avant** la suppression physique, que les
cascades emporteraient sinon sans un mot. Idempotent.

## 6. Géolocalisation et mobilité

- **Source par défaut** : commune + code postal du profil, de l'offre et de
  l'organisation, **géocodés côté serveur** (cache `geocode_cache` par
  commune, timeout 4 s, panne non bloquante sur le modèle de `nafFor`,
  rattrapage quotidien). Les offres Jeuncy n'ont aujourd'hui ni `postal_code`
  ni coordonnées (`job_offers` : `location`, `city` — migration
  `2026_07_17_000009` l.23-24 ; `StoreJobOfferRequest` l.24-25) : le code
  postal devient obligatoire à la publication, avec repli sur celui de
  l'organisation, jamais `city` seul (homonymes). Les offres partenaires ont
  déjà les colonnes `latitude` / `longitude` (nullables, migration
  `2026_09_15_120000` l.44-45) ; la part réellement renseignée se lit dans la
  sonde `status?geo=1` (`sans_coordonnees`), pas dans le schéma.
- **GPS facultatif** (`expo-location`, à ajouter : absent de `package.json`,
  fonctionne dans Expo Go) : bouton « Autour de moi » dans les réglages de
  Découvrir, écran de consentement Jeuncy avant l'invite système, autorisation
  « When In Use » seulement, précision basse, **arrondi à ~1 km côté app et
  côté serveur**, une seule valeur stockée, effaçable depuis Confidentialité.
  **Sert uniquement à la pile du candidat**, jamais au deck employeur — sinon
  le texte de consentement serait faux.
- **Rayon candidat** : 5-100 km, défaut 30, élargissement toujours annoncé,
  dernier cran « toute la France » pour les offres Jeuncy. Calcul : boîte
  englobante sur colonnes indexées puis haversine en SQL.
- **Rayon de recrutement** sur l'offre (`recruitment_radius_km`), réglé par
  l'employeur. L'employeur ne voit jamais de distance (décision 2).
- **Permis par catégorie / véhicule** : `driving_license_categories` (json),
  `has_vehicle` ; reprise du texte libre par expression régulière avec
  relecture manuelle des profils existants ; badge sur les cartes ; « permis
  requis » sur l'offre signale, n'exclut pas.
- **Périmètre** : `JEUNCY_MATCH_DEPARTEMENTS=66` au lancement ; `*` = tous.
  Sens explicite d'une valeur vide = **fermé** (test dédié), à l'inverse de
  `LBA_DEPARTEMENTS` où vide = tous (`.env.example` l.120) — la leçon du
  2026-09-18 vaut dans les deux sens.

## 7. Photos et modération

- **Entreprise** : logo existant (`companies.logo_url`, routes `company/logo`
  l.15-16) + jusqu'à 5 photos d'équipe, **pré-modérées** (`PENDING` →
  `APPROVED` / `REJECTED`, onglet admin, objectif 24 h), case obligatoire « les
  personnes visibles ont donné leur accord ». Redimensionnement **côté app**
  (1 200 px, JPEG, retire l'EXIF et donc le GPS) par `expo-image-manipulator`
  — à ajouter, seul `expo-image-picker` ~57.0.17 est installé (`package.json`
  l.23) —, 2 Mo max, envoi par `toFormDataPart` existant
  (`candidate-profile.ts` l.147-149).
- **Candidat** : le portrait existant (`photo_url`, routes
  `candidate-profile/photo` l.34-35), un seul, opt-in `show_photo_to_employers`
  (défaut `false`). Pas de galerie « moments au travail » (droit à l'image des
  tiers).
- **Signalement** (`reports`) depuis toute carte et tout match, traité sous
  24 h ; **blocage** (`user_blocks`) mutuel, appliqué aux decks, à la CVthèque
  et à `listForOffer`.
- **Filtrage texte avant publication** : liste de mots et détection de
  coordonnées (email, téléphone) sur titre, bio, phrase, descriptions et
  légendes — Apple 1.2 exige un filtrage, pas seulement un signalement.

## 8. Modèle de données cible

### Tables nouvelles

| Table                 | Colonnes principales                                                                                                                                                                               | Justification                                                                                                              |
| --------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------- |
| `offer_interests`     | `candidate_profile_id`, `job_offer_id`, `candidate_decision`, `employer_decision`, `matched_at`, `application_id` (nullOnDelete), `closed_at`, `closed_reason`, rappels ; unique (candidat, offre) | Une ligne par couple, comme `applications` (unique l.21 de `2026_07_17_000010`). Le dossier reste une `Application`.       |
| `external_interests`  | `candidate_profile_id`, `external_job_offer_id` (nullOnDelete), `company_siret`, `company_name`, `title`, `apply_url` dénormalisés, `done_at`                                                      | `LbaImportService` supprime chaque nuit les offres absentes de l'export (l.162-165) : sans copie, « Gardées » se viderait. |
| `organization_photos` | `company_id` / `cfa_organization_id`, `url`, `status`, `consent_confirmed`, `reviewed_by`, `reviewed_at`                                                                                           | Pré-modération et preuve du consentement des personnes visibles.                                                           |
| `reports`             | `reporter_user_id`, `reported_user_id`, `context` (carte, match, photo), `reason`, `handled_by`, `handled_at`                                                                                      | Traitement sous 24 h, traçable.                                                                                            |
| `user_blocks`         | `blocker_user_id`, `blocked_user_id`                                                                                                                                                               | Filtre commun aux decks, à la CVthèque et aux candidatures reçues.                                                         |
| `geocode_cache`       | `postal_code`, `city_normalized`, `latitude`, `longitude`, `resolved_at`                                                                                                                           | Une commune géocodée une fois ; panne du géocodeur non bloquante.                                                          |

Colonnes de statut en `string` + enum PHP validé par `Rule::enum`, jamais
d'enum MySQL pour une **nouvelle** table ou colonne (convention déjà suivie
pour `work_mode`, migration `2026_07_30_130000` l.12-17, et pour
`external_job_offers.status`, `2026_09_15_120000` l.67). Les colonnes enum
historiques (`job_offers.contract_type`/`status`/`payment_status`,
`applications.status`, `notifications.type`, `users.role`) restent des enum
MySQL et s'étendent par `->change()`.

### Colonnes ajoutées

- `candidate_profiles` (existant : migration `2026_07_17_000001` l.16-24 +
  `headline`, `hobbies`, `driving_license`, liens, `is_visible_in_cvtheque`,
  `cv_file_url`) : **deux paires de coordonnées** — `latitude`/`longitude`
  (position PROFILE, géocodée depuis commune + code postal, arrondie à 2
  décimales) et `device_latitude`/`device_longitude`/`device_located_at`
  (position DEVICE, le GPS du téléphone, même arrondi). Deux paires plutôt
  qu'une seule accompagnée d'un `location_source` (nom retenu au cadrage,
  abandonné au lot 1) : « le GPS ne sert qu'à la pile du candidat » devient
  alors structurel et non conventionnel — le deck employeur lit
  `latitude`/`longitude` et ne peut pas lire `device_*` par distraction, et un
  test le prouve. `location_source` subsiste comme champ **calculé** dans les
  réponses (`meta.location_source`, `PUT candidate-profile/location`), jamais
  comme colonne. Les cinq colonnes sont dans `$hidden` et ne reviennent au
  propriétaire que par `makeVisible(CandidateProfile::OWNER_VISIBLE)`.
  Également : `search_radius_km`, `mobility_radius_km`,
  `wanted_contract_types` (json), `wanted_sectors` (json),
  `has_driving_license`, `driving_license_categories` (json), `has_vehicle`,
  `available_from`, `pitch` (160), `show_photo_to_employers` (défaut false).
  La colonne texte `driving_license` est **conservée** (reprise par
  `candidates:migrate-driving-license`, §6).
- `job_offers` (existant : `2026_07_17_000009` + compensation, personnalisation,
  `work_mode`, `applications_unlocked_at`, `payment_status` FREE
  `2026_09_15_100000` l.17) : `postal_code`, `latitude`/`longitude`,
  `recruitment_radius_km`, `sector`, `schedule`, `start_date`, `minimum_age`,
  `requires_driving_license`, `missions` (json), `cover_photo_id`.
- `companies` et `cfa_organizations` : `latitude`/`longitude`,
  `verification_status`, `verified_at`, `verified_by` (nullOnDelete),
  `verification_note`, `response_days_avg`, `responded_count`. Les CFA
  existants (IDA) passent VERIFIED dans la migration elle-même ; les nouvelles
  lignes restent PENDING.
- `applications` : `interest_id`, `source` (SITE / APP / MATCH),
  `responded_at`.
- `users` : `age_confirmed_at` — la case des 15/16 ans n'est enregistrée nulle
  part aujourd'hui (`RegisterRequest.php` l.33 valide sans stocker).
- `notifications.type`, par `->change()` : `NEW_MATCH`, `INTEREST_RECEIVED`,
  `MATCH_CLOSED` (livrés au lot 1) ; `MATCH_REMINDER`,
  `APPLICATION_CLOSED_BY_STAFF` (lot 4) et `PHOTO_REVIEWED` (photos d'équipe)
  viendront avec les fonctionnalités qui les émettent — une valeur d'enum sans
  émetteur n'apporte rien, et la migration est bon marché.

### Routes API nouvelles

| Méthode  | Chemin                                                                           | Rôle                    |
| -------- | -------------------------------------------------------------------------------- | ----------------------- |
| GET      | `discover/offers`                                                                | CANDIDATE (≥16)         |
| GET      | `discover/candidates?job_offer_id=`                                              | COMPANY, CFA            |
| POST     | `interests` (LIKE, un par requête)                                               | CANDIDATE, COMPANY, CFA |
| POST     | `interests/batch` (PASS, par lot)                                                | idem                    |
| DELETE   | `interests/last`                                                                 | idem                    |
| POST     | `external-interests`, PATCH `{id}/done`                                          | CANDIDATE               |
| GET      | `matches`, GET `matches/{id}`                                                    | CANDIDATE, COMPANY, CFA |
| PUT      | `candidate-profile/preferences`                                                  | CANDIDATE               |
| PUT      | `candidate-profile/location`, DELETE                                             | CANDIDATE               |
| POST     | `job-offers/express`                                                             | COMPANY, CFA            |
| POST     | `organization/photos`, DELETE `{id}`                                             | COMPANY, CFA            |
| POST     | `reports`, POST `blocks`, DELETE `blocks/{id}`                                   | tous connectés          |
| GET/POST | `admin/photos`, `admin/reports`, `admin/silent-employers`, `admin/verifications` | ADMIN, STAFF            |

Un middleware `role:ADMIN,STAFF` limité à la modération : STAFF est décrit
« sans aucun pouvoir d'administration » (`UserRole.php` l.10-12) et n'a
aujourd'hui accès qu'à la CVthèque (`routes/api/cvtheque.php` l.17).

### Routes existantes réutilisées

`POST/GET applications`, `DELETE applications/{id}`,
`PATCH applications/{id}/status`, `GET job-offers/{id}/applications`
(`applications.php`) ; `GET job-offers/search`, `job-offers/count`,
`job-offers/external/search`, `job-offers/external/{id}`,
`POST job-offers/{id}/publish` (`job-offers.php` l.11-35) ; tout
`candidate-profile/*` (l.15-45) ; `company/*` (l.11-17) ; `notifications/*` ;
`account/export`, `DELETE account` (`account.php` l.9-10) ; auth mobile.

Services modifiés : `CvthequeService` (exposition), `JobOfferMatchService`
(ses règles étaient `private` : extraites dans un `App\Services\MatchScorer`
public, testé seul, que le service injecte — une seule règle change, la
préférence structurée `wanted_contract_types` l'emporte sur l'heuristique
textuelle quand elle est renseignée), `ApplicationService::applyForUser`
(rattache `interest_id`, pose `source` et un LIKE candidat),
`DeployController::version()` et selftest, `bootstrap/app.php` (alias
`match.age`, commandes planifiées).

**Classes nouvelles du lot 1, noms définitifs** : `App\Support\Haversine`
(distance en SQL, boîte englobante), `App\Support\PostalCodes` (département
d'un code postal), `App\Support\MatchPerimeter` (départements ouverts —
**vide = fermé**), `App\Presenters\CandidateCardPresenter` (la règle
d'exposition unique, §4.3), `App\Services\GeocodingService`,
`CompanyVerificationService`, `MatchClosingService`, `BlockService`,
`MatchScorer`, `DiscoverService`, `InterestService`, `MatchService`,
`ExternalInterestService`, `ReportService`, middleware
`App\Http\Middleware\EnsureMatchAge` (alias `match.age`), commandes
`geocode:backfill` et `candidates:migrate-driving-license`.

## 9. Règles non négociables du mobile

1. **Socle intouchable** : `client.ts` (en-tête `X-Jeuncy-Client` l.17 et l.47,
   coalescence du refresh l.72-106, `ApiError.cause` l.149), `auth.ts`,
   `secure-store.ts`, `toFormDataPart` (`candidate-profile.ts` l.147-149 — le
   `fetch` d'Expo 57 refuse le format `{ uri, name, type }` et n'accepte
   qu'une chaîne, un `Blob` ou le `File` d'`expo-file-system`), polices importées **par chemin
   exact** (`_layout.tsx` l.38-44, sinon 9 Mo d'assets), `theme/`, `labels.ts`
   (Record exhaustif : une valeur ajoutée à `packages/shared` refuse de
   compiler sans libellé).
2. **Déploiement** : tout fichier créé **ou modifié pour brancher** une
   fonctionnalité est inscrit dans `DeployController::version()`
   (`DeployController.php` l.140 ; empreinte `sha256` tronquée à 16 l.257 +
   taille l.259 — numéros de la version commitée `7dcb951` ; l'arbre de
   travail porte déjà `deploy-tools-28`, non commité, qui décale tout de
   ~365 lignes) avant l'envoi FTP. Le selftest traverse contrôleur → service →
   insertion. Chaque valeur d'enum ajoutée est écrite réellement en MySQL
   (SQLite des tests ne le prouve pas). Budget : deux allers-retours FTP par lot.
3. **Instrumenter avant de deviner** : une sonde par hypothèse
   (`/deploy/{token}/...`, `routes/web.php` l.25-39), jamais un correctif sur
   une conjecture.
4. **16 ans** sur l'app et sur toute route du match, quel que soit le client.
   Concrètement : `inscription.tsx` (l.46 et l.139) et la borne du sélecteur
   de date de naissance passent de 15 à 16 ; `RegisterRequest.php` (l.33) et
   `StoreCandidateProfileRequest.php` (l.33) restent à 15 pour le site ; la
   garde serveur des 16 ans vit sur `discover/*`, `interests*` et
   `matches*`, pas sur l'inscription.
5. **Rien avant candidature** (§4.3), sur l'app comme sur le site.
6. **Jamais de position ni de distance côté employeur** ; le GPS du candidat
   ne sort pas de sa pile.
7. **Jamais de candidature sans geste** du candidat.
8. Deck maison sur `react-native-reanimated` 4.5.1 + `gesture-handler`
   ~2.32 + `worklets` 0.10.1, déjà dans `package.json` (l.38-43) ; aucune
   bibliothèque tierce de swipe. `GestureHandlerRootView` est **absent** de
   la version commitée de `src/` (`7dcb951`) et doit entourer la racine — le
   prototype non commité du 2026-09-22 l'ajoute déjà dans `src/app/_layout.tsx`.
   `reactCompiler: true` (`app.json` l.48) à valider sur ce prototype avant
   d'écrire le deck définitif.
9. Pas de Google, ni de push, ni de visio dans l'app tant que le compte Apple
   n'est pas prouvé : email + mot de passe seulement (évite Sign in with
   Apple).
10. Comptes de test réalistes pour les trois rôles avant toute soumission.

## 10. Plan par lots

| Lot | Contenu                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | Semaines | Expo Go                                                   | Attend le compte Apple       |
| --- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- | --------------------------------------------------------- | ---------------------------- |
| 0   | Ce cadrage ; vérification du compte Apple ; sonde `/deploy/{token}/status?geo=1` (`DeployController::geoStats`, `deploy-tools-28`, écrite le 2026-09-22 dans l'arbre de travail — non commitée, non déployée : profils par département sans nom ni commune, offres partenaires à 10/30/50 km de Perpignan, `SELECT VERSION()`, `ST_Distance_Sphere`, GD, `memory_limit`) ; reste à mesurer : requêtes parallèles ; maquettes des 4 cartes ; **prototype de deck** sur `job-offers/search` (un premier jet non commité existe déjà : `components/features/discover/swipe-deck.tsx`, `store/swipe-store.ts`, `lib/api/external-offers.ts`) | 2-3      | Prototype de deck                                         | —                            |
| 1   | Socle backend : migrations, enums (PHP + TS + `labels.ts`), `GeocodingService` + rattrapage, reprise du permis, vérification employeur, `CvthequeService` aligné, `MatchScorer`, `DiscoverService`, `InterestService`, `MatchService`, emails, ~40 tests, selftest traversant, déploiement                                                                                                                                                                                                                                                                                                                                               | 3        | Rien de visible                                           | —                            |
| 2   | Deck entreprise + intérêts + match + dossier : onboarding candidat (Où, visibilité), deck candidats, offre express, intérêt employeur (in-app + email), Matchs, dossier, Candidatures reçues ; web : Intéressés, Matchs, offre express                                                                                                                                                                                                                                                                                                                                                                                                   | 3        | Tout                                                      | Textes de permission         |
| 3   | Deck candidat complet : Sélection du jour, carte offre, fiche dépliée, feuille à deux boutons, annuler, offres partenaires « Je garde », rayon, GPS opt-in, drapeau d'ouverture                                                                                                                                                                                                                                                                                                                                                                                                                                                          | 3        | Tout (localisation au premier plan fonctionne en Expo Go) | —                            |
| 4   | Modération, relances, admin : photos d'équipe, signalement, blocage, `matches:remind`, purge, stats de réponse, onglets admin en un clic, page CSAE, recours parental                                                                                                                                                                                                                                                                                                                                                                                                                                                                    | 2        | Tout                                                      | —                            |
| 5   | Cohérence web et légal : `/profile`, `/mes-candidatures`, CVthèque, politique de confidentialité datée, CGU, documents commerciaux, accessibilité                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | 2        | —                                                         | —                            |
| 6   | Pilote 66 + stores : pilote Perpignan, ratios hebdomadaires, build de développement EAS, push (`device_tokens`), questionnaire + classification 16+, Data safety, TestFlight, soumission                                                                                                                                                                                                                                                                                                                                                                                                                                                 | 3        | —                                                         | Push, TestFlight, soumission |

Total **18-19 semaines nominales, 20-24 avec la marge FTP**. Si le compte
Apple tarde, seul le lot 6 glisse ; les lots 1 à 5 se testent dans Expo Go sur
l'iPhone de Pierre. Le lot 2 est livré avant le lot 3 à dessein : le volume
existe côté candidats, pas côté offres Jeuncy.

Découvrir candidat s'ouvre quand N offres Jeuncy vérifiées existent à moins de
30 km (N = 10 proposé, drapeau `jeuncy.match_actif`), les offres partenaires
servant de remplissage. Ratios suivis chaque semaine : cartes matchables par
candidat actif ≥ 5, réponse employeur sous 72 h ≥ 50 %.

## 11. Historique v1 (phases 0 et 1, lots A à E)

- Phase 0 (2026-09-09, validée sur iPhone le 11) : Expo SDK 57, React Native
  0.86, Expo Router, thème Jeuncy, client API, auth mobile, coffre sécurisé.
- Lot A : onglets, recherche publique (mot-clé et ville temporisés,
  `index.tsx` l.60-61, pagination infinie l.73), détail d'offre.
- Lot B : profil candidat complet, un écran par sujet.
- Lot C1/C2 : CV, candidature (`postuler.tsx`), suivi, import de CV avec
  relecture ; photo corrigée (`toFormDataPart`).
- Lot D : notifications (`hrefForNotification`), confidentialité, export,
  suppression, textes légaux en feuille Safari.
- Lot E (commit `002d593`, 2026-09-15) : onglets entreprise/CFA, fiche
  d'organisation ; `mes-offres.tsx` et `cvtheque.tsx` sont des écrans d'attente
  (lots F et H, abandonnés au profit de ce cadrage).
- Les renvois de `CLAUDE.md` à `MOBILE.md` §9.2 (pages légales), §9.3 (case
  des 15 ans) et §9.5 (push) visent la v1 du 2026-09-04, lisible par
  `git show 7dcb951:MOBILE.md` ; ils ne correspondent plus à la numérotation
  de ce document.
- Bilan sur les **7 590 lignes** de `src` (70 fichiers suivis par git,
  comptés le 2026-09-22 ; trois fichiers de prototype non suivis en plus, voir
  lot 0) : 72 %
  gardées intactes, 25 % adaptées, 3 % jetées (`index.tsx` recherche,
  `cvtheque.tsx`, `mes-offres.tsx`) ; **~4 000 lignes neuves** à écrire (deck,
  deux cartes, fin de pile, réglages, Matchs et détail × 2 rôles, Candidatures
  reçues, deck employeur, onboarding, signalement, blocages, trois modules
  API). L'app finale fera ~11 500 lignes, dont la moitié existe déjà.

## 12. Contraintes et risques

- **Démarrage à froid** : une seule offre Jeuncy publiée au 2026-09-11, 53
  profils candidats dont la géographie réelle est inconnue. Traitement : sonde
  `status?geo=1` au lot 0, deck employeur d'abord, ouverture de Découvrir sous
  condition, offres partenaires en remplissage, ratios hebdomadaires.
- **Hébergement OVH mutualisé** : pas de worker, pas de websocket, cron horaire
  à minute arbitraire (mesuré à 09:41:03, `bootstrap/app.php` l.28-29),
  `php.ini` figé, nombre de processus PHP inconnu. Traitement : chaque LIKE =
  une requête, PASS par lot, tâches quotidiennes par morceaux, aucun polling
  hors premier plan.
- **Compte Apple Developer** : à vérifier au lot 0 (Organisation, D-U-N-S
  obtenu au nom de SAS JEUNCY le 2026-09-08). Sans lui : ni push, ni build de
  développement, ni TestFlight. C'est un jalon daté, pas une décision ouverte.
- **CNIL et AIPD** : scoring d'éligibilité + personnes vulnérables (mineurs) +
  localisation = analyse d'impact obligatoire avant le pilote ; politique de
  confidentialité datée (exposition, localisation, photos) ; recours parental
  documenté ; page CSAE.
- **Classification 16+** : questionnaire honnête puis override 16+ sur les
  deux stores ; l'app refuse un 15 ans, le site l'accepte — les deux textes
  doivent le dire.
- **Modération** : signalements sous 24 h, photos validées, employeurs muets
  relancés — Pierre et Claude au début. Sans une personne qui répond, aucun
  badge de réponse ni promesse « réponse garantie » n'est affiché.
- **Déploiement FTP** : ~40 fichiers créés, ~20 modifiés, pannes muettes dans
  les `try/catch` (leçon du 2026-09-08). Traitement : règle 2 de la section 9.
- **Offres partenaires** : aucun démarchage des employeurs LBA avant accord
  écrit de La bonne alternance (licence non lucrative) ; `LBA_SIRET_WHITELIST`
  est vide dans `.env.example` (l.121) et porte encore le placeholder
  `SIRET_IDA` dans le `.env` de production (`CLAUDE.md`, 2026-09-17), à
  remplacer par le vrai SIRET d'IDA.
