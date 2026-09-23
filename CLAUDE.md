# CLAUDE.md — Contexte projet Jeuncy

> Ce fichier est lu automatiquement par Claude Code au début de chaque session.
> Il décrit le projet, la stack, les règles et les commandes utiles.
> Voir aussi `CONVENTIONS.md` pour les règles de code détaillées.

## 1. Le projet

**Jeuncy** est une plateforme web de recrutement dédiée aux **alternants, saisonniers et
bénévoles**. Elle met en relation :

- des **candidats** (jeunes) qui créent un profil, génèrent un CV et postulent à des offres,
- des **entreprises** et **CFA** qui publient des offres (payantes) et gèrent les candidatures.

Elle inclut aussi un module de **visioconférence** pour faire des démonstrations en direct
du site/logiciel (côté commercial/onboarding).

Positionnement : agence de recrutement nouvelle génération — visible, accessible, humaine,
efficace. Ton : jeune sans être ado, pro sans être froid, dynamique sans être agressif.
Signature : « Match ton alternance » (décision du patron, 2026-09-18 ; remplace « Job Jeune & Match » du 17, qui remplaçait « Ton alternance commence ici. »)

## 2. Identité visuelle (obligatoire à respecter)

| Élément                   | Valeur                                                               |
| ------------------------- | -------------------------------------------------------------------- |
| Bleu nuit (corporate)     | `#061D4F`                                                            |
| Rose corail (CTA/énergie) | `#FF2D55`                                                            |
| Orange (opportunité)      | `#FF8A32`                                                            |
| Blanc                     | `#FFFFFF`                                                            |
| Off-white (fond clair)    | `#FAFAF8`                                                            |
| Dégradé signature         | `linear-gradient(90deg, #FF2D55, #FF8A32)` — réservé aux accents/CTA |
| Typo titres/CTA           | Poppins                                                              |
| Typo textes longs         | Inter                                                                |

Le logo (J + C avec deux silhouettes) ne doit jamais être déformé, incliné, ombré ou recoloré.
Prévoir une version dark (fond bleu nuit) et une version light pour le toggle jour/nuit.

**Dark mode** : obligatoire, toggle visible dans le header, persistant (localStorage), doit
inverser fond ↔ texte tout en gardant corail/orange comme accents constants.

## 3. Stack technique (architecture découplée)

### Frontend — `apps/web`

- React 18 + Vite + TypeScript
- React Router (navigation)
- TanStack Query (fetch/cache des données API)
- Zustand (état global léger : thème, session utilisateur)
- Tailwind CSS + shadcn/ui, thème custom Jeuncy
- next-themes n'étant pas utilisable hors Next.js : dark mode géré via un simple
  `ThemeProvider` custom + attribut `data-theme` sur `<html>`
- `@react-pdf/renderer` pour la prévisualisation client du CV (le rendu final se fait côté API)
- React Hook Form + Zod pour les formulaires
- `@jitsi/react-sdk` (embed de l'instance publique meet.jit.si) pour la visioconférence

### Backend — `apps/api`

> **Changement de stack (2026-07-17)** : le backend était initialement en NestJS/TypeScript.
> L'hébergement du projet est l'offre **OVH mutualisée PRO (jeuncy.com)**, qui ne supporte
> que PHP (pas de Node.js en production) — voir §11. Le backend a donc été entièrement
> réécrit en **Laravel/PHP**, seul changement d'architecture ; toutes les autres décisions
> (JWT access+refresh, format de réponse, rôles, modèle de données MySQL) sont conservées
> à l'identique.

- Laravel 13 (PHP 8.3), un Controller + un Service + des Form Requests par domaine métier
  (voir `CONVENTIONS.md` §4)
- Eloquent ORM + MySQL 8, migrations dans `apps/api/database/migrations`
- JWT custom (`firebase/php-jwt` + `App\Auth\JwtGuard`, garde Laravel native via
  `Auth::extend('jwt', ...)`) : access token courte durée (JSON) + refresh token longue
  durée (cookie httpOnly) — même design que l'ancienne implémentation NestJS
- Laravel Socialite pour le Google OAuth
- Middleware `role:XXX` (`App\Http\Middleware\EnsureUserHasRole`) pour la vérification de rôle
- Form Requests (`app/Http/Requests/...`) pour la validation des entrées
- `@react-pdf/renderer` reste côté client uniquement (prévisualisation) ; le rendu PDF
  final du CV côté serveur devra utiliser une lib PHP (ex : `barryvdh/laravel-dompdf` ou
  `spatie/browsershot`) — à choisir en phase 2, `@react-pdf/renderer` n'étant pas
  utilisable en PHP
- Stripe PHP SDK (Checkout + webhooks) pour les offres payantes
- Resend, appelé directement via son API HTTP (façade `Http` de Laravel) pour les emails
  transactionnels
- Génération de noms de salle Jitsi uniques et non devinables (UUID) côté API

### Repo

- Monorepo avec pnpm workspaces pour la partie JS (`apps/web`, `packages/shared` — types
  partagés utilisés par le frontend). `apps/api` est un projet Composer/PHP autonome, hors
  du pipeline pnpm/Turborepo (pas de `package.json`)
- Turborepo orchestre les scripts (dev, build, lint) de `apps/web` et `packages/shared`
  uniquement ; `apps/api` se pilote via Composer/Artisan (voir §9)

## 4. Rôles utilisateurs et permissions

- **CANDIDATE** : profil, CV, recherche d'offres, candidatures, suivi, peut rejoindre une
  visio de démo.
- **COMPANY** : profil entreprise, publication d'offres (payant), gestion des candidatures,
  peut organiser une visio de démo avec un candidat.
- **CFA** : similaire à COMPANY + gestion multi-offres pour ses filières.
- **ADMIN** : modération, statistiques, gestion des paiements, supervision des salles de visio.

Toute route API vérifie le rôle via `@Roles()` + `RolesGuard` avant d'agir.

## 5. Structure de dossiers cible

```
/apps
  /web                     # React + Vite
    /src
      /pages
      /components
        /ui                # composants shadcn/ui customisés
        /features           # CardOffre, CVBuilder, VideoRoom, etc.
      /hooks
      /lib                 # api client, utils
      /store               # Zustand
  /api                      # Laravel (PHP), projet Composer autonome
    /app
      /Http
        /Controllers        # un par domaine métier (Auth, JobOffers, Applications...)
        /Requests            # Form Requests de validation
        /Middleware          # WrapApiResponse, EnsureUserHasRole
      /Services              # logique métier (AuthService, JwtService, MailService...)
      /Models                # Eloquent (User, CandidateProfile, JobOffer, VideoRoom...)
      /Enums                 # backed enums (UserRole, JobOfferStatus...)
      /Auth                  # JwtGuard custom
      /Exceptions            # ApiException
    /routes
      api.php                # inclut routes/api/*.php par domaine
    /database
      /migrations
      /seeders
      /factories
    /tests
      /Feature               # PHPUnit
/packages
  /shared                   # types/enums TS partagés, utilisés par apps/web uniquement
```

## 6. Modèle de données — entités clés

`User`, `CandidateProfile`, `Experience`, `Education`, `Skill`, `GeneratedCV`, `Company`,
`CfaOrganization`, `JobOffer` (status: DRAFT/PUBLISHED/EXPIRED/ARCHIVED, paymentStatus),
`Application` (status: SENT/SEEN/INTERVIEW/ACCEPTED/REJECTED), `Payment`, `Notification`,
`VideoRoom` (hostId, participantId, jitsiRoomName, status: SCHEDULED/LIVE/ENDED).

## 7. Module visioconférence

- Objectif : permettre à une entreprise/CFA (ou l'équipe Jeuncy) de faire une démo live du
  site/logiciel à un candidat ou un prospect, en visio intégrée directement à la plateforme
  (pas de lien externe Zoom/Meet).
- Solution retenue : **Jitsi Meet**, instance publique gratuite `meet.jit.si`, intégrée via
  l'iframe API / `@jitsi/react-sdk` côté front. Zéro coût, zéro infra à gérer, zéro
  développement WebRTC.
- Fonctionnement : l'API `video-rooms` génère un nom de salle unique et non devinable (UUID)
  à la création, stocke la `VideoRoom` (hôte, participant, statut) en base, et renvoie ce nom
  au front qui monte le composant Jitsi avec ce nom de salle. Le lien d'invitation pointe vers
  une page Jeuncy (`/demo/:roomId`) qui embarque l'iframe, pas directement vers meet.jit.si,
  pour garder le contrôle de l'UX et du branding autour de la salle.
- Fonctionnalités attendues : création de salle depuis le back-office, lien d'invitation
  unique à usage limité dans le temps, partage d'écran (prioritaire pour la démo logiciel),
  personnalisation possible via la config Jitsi (nom affiché, désactivation de certains
  boutons de la toolbar, page de pré-connexion).
- Limites connues à documenter pour l'utilisateur : l'instance publique meet.jit.si ne permet
  pas un branding complet (logo Jeuncy dans l'interface d'appel) ni l'enregistrement des
  sessions. Depuis fin 2023, meet.jit.si exige aussi que **le premier participant à
  entrer dans une salle (l'hôte) se connecte via un compte Google, GitHub ou Facebook** —
  ce n'est plus possible de créer une salle de façon anonyme sur l'instance publique. Le
  participant/candidat invité, lui, peut toujours rejoindre librement sans compte. Décision
  prise (2026-07-23) : garder meet.jit.si tel quel plutôt que migrer, la contrainte étant
  mineure pour des hôtes internes (entreprise/CFA/admin) qui ont déjà un compte Google —
  documenté dans l'UI de `/mes-visios`. Si ces besoins deviennent prioritaires, migration
  possible vers un **self-hosting Jitsi via Docker** (même API front, juste changer le
  domaine du serveur), à évaluer en V2 si le besoin se confirme — mais impossible sur
  l'hébergement mutualisé OVH actuel (pas de VPS/Docker), nécessiterait un serveur dédié
  supplémentaire.

## 8. Dépôt Git

Le code doit être versionné sur : https://github.com/Pulpitau/JEUNCY_website.git

```bash
git init                                   # si le repo local n'est pas déjà initialisé
git remote add origin https://github.com/Pulpitau/JEUNCY_website.git
git add .
git commit -m "chore: initialisation du monorepo Jeuncy"
git branch -M main
git push -u origin main
```

## 9. Commandes utiles

```bash
# Frontend (apps/web) + packages/shared
pnpm install                      # installer les deps JS du monorepo
pnpm --filter web dev             # lance le serveur Vite
pnpm lint                         # ESLint sur apps/web + packages/shared
pnpm test                         # tests Vitest de apps/web

# Backend (apps/api) — Laravel, projet Composer autonome
cd apps/api
composer install                  # installer les deps PHP
php artisan serve --port=3000     # lance l'API en local
php artisan migrate               # applique les migrations
php artisan migrate:fresh --seed  # reset + données de démo
vendor/bin/pint                   # formatage PHP (équivalent Prettier)
php artisan test                  # tests PHPUnit
```

## 10. Règles non négociables

- Toujours valider les inputs côté serveur (DTO + class-validator/Zod), jamais confiance au client.
- Secrets (mots de passe, clés Stripe) uniquement via `.env`, jamais commités.
- RGPD : export/suppression des données candidat, consentement avant enregistrement d'une visio.
- Accessibilité : contrastes AA, navigation clavier, alt text.
- Mobile-first : la majorité du public cible navigue depuis un téléphone.
- CORS configuré strictement entre `apps/web` et `apps/api` (pas de wildcard en prod).

## 11. État d'avancement

À mettre à jour à chaque session : lister les modules terminés, en cours, à faire.
(Phase 1 : socle monorepo / Phase 2 : profil + CV / Phase 3 : offres + paiement /
Phase 4 : candidatures / Phase 5 : visioconférence / Phase 6 : admin + polish)

**Phase 1 — socle monorepo : terminée**

- Monorepo pnpm workspaces + Turborepo (`apps/web`, `packages/shared`), lié
  à `origin/main` (github.com/Pulpitau/JEUNCY_website). `apps/api` était initialement
  scaffoldé en NestJS ici ; voir "Bascule NestJS → Laravel" plus bas — c'est désormais un
  projet Composer/Laravel autonome, hors du pipeline pnpm/Turborepo.
- `apps/web` : Vite + React 18 + TS, Tailwind, React Router, TanStack Query, Zustand,
  RHF + Zod, bases shadcn/ui
- `apps/api` (historique, phase 1) : scaffoldé en NestJS + TS avec `ConfigModule`,
  `ValidationPipe` global, CORS, Prisma configuré — entièrement remplacé depuis par
  Laravel (voir plus bas)
- `packages/shared` : enums de statut (`UserRole`, `JobOfferStatus`, `ApplicationStatus`,
  `VideoRoomStatus`) et type `ApiResponse`
- ESLint (flat config) + Prettier + husky/lint-staged fonctionnels sur tout le monorepo
- Design system : tokens Tailwind Jeuncy (couleurs, polices, dégradé signature),
  `ThemeProvider` dark/light (Zustand + `data-theme`, persistant, anti-FOUC), composants
  de base (`Button`, `Card`, `Input`, `Badge`, `Navbar`, `Footer`), page d'accueil de
  démonstration vérifiée en navigateur (build/lint/test OK)
- Workflow : une branche par étape, mergée dans `main` (voir `CONVENTIONS.md` §8)

**Modèle de données : terminé** (couvre les entités des phases 2 à 5, pas encore la
logique métier — controllers/services/Form Requests à écrire phase par phase)

- Schéma complet : `User`, `CandidateProfile`, `Experience`, `Education`,
  `Skill`/`CandidateSkill`, `GeneratedCv`, `Company`, `CfaOrganization`, `JobOffer`,
  `Application`, `Payment`, `Notification`, `VideoRoom`, avec enums PHP (`UserRole`,
  `JobOfferStatus`, `ContractType`, `ApplicationStatus`, `PaymentStatus`,
  `NotificationType`, `VideoRoomStatus`) — `snake_case` natif en base (colonnes Eloquent),
  cascades justifiées en commentaire dans chaque migration
- Défini originellement via `prisma/schema.prisma` (phase 1, NestJS), puis intégralement
  recréé en 14 migrations Laravel (`apps/api/database/migrations/`) lors de la bascule
  vers PHP — **appliqué et validé contre une vraie base MySQL** (`php artisan migrate` +
  seed, voir section "Base de données de dev" ci-dessous)
- `apps/api/database/seeders/DatabaseSeeder.php` : données de démo réalistes (2
  candidats, 2 entreprises, 1 CFA, 3 offres, candidatures, paiement, notification, salle
  de visio) — exécuté et vérifié contre la vraie base Clever Cloud
- `packages/shared` : enums `ContractType`, `PaymentStatus`, `NotificationType` présents
  côté TS (frontend) ; leur pendant PHP vit dans `apps/api/app/Enums/`, synchronisation
  manuelle entre les deux depuis la bascule (voir `CONVENTIONS.md` §5)
- Choix de modélisation faits sans validation préalable (à relire) : `JobOffer` rattachée
  à `Company` OU `CfaOrganization` via deux FK nullables (invariant validé côté Form
  Request, pas en base) ; `ContractType` (ALTERNANCE/SAISONNIER/BENEVOLAT) déduit du
  positionnement produit mais non explicitement listé dans ce fichier ; `Payment` non
  cascade-supprimé avec l'utilisateur (obligation légale de conservation des pièces
  comptables)

**Authentification : terminée** (implémentation Laravel actuelle ; voir "Bascule NestJS
→ Laravel" ci-dessous pour l'implémentation NestJS d'origine, remplacée)

- `apps/api` : register/login/refresh/logout/me, mot de passe oublié (token JWT signé,
  email via Resend), Google OAuth (création ou association de compte par email) via
  `App\Services\AuthService`/`JwtService`/`MailService`, `App\Auth\JwtGuard` (garde
  Laravel custom, `auth:api`) + middleware `role:XXX`
  (`App\Http\Middleware\EnsureUserHasRole`)
- Access token courte durée (15 min, réponse JSON) + refresh token longue durée (7 jours,
  cookie httpOnly `jeuncy_refresh_token`, jamais exposé au JS) — rotation à chaque refresh
- Format de réponse standard `{ success, data }` / `{ success, error }` appliqué
  globalement via `App\Http\Middleware\WrapApiResponse` (succès) et les callbacks
  `render`/`respond` de `bootstrap/app.php` (erreurs)
- `apps/web` : store Zustand de session **non persisté** (accessToken en mémoire
  uniquement), `AuthProvider` qui restaure la session via `/auth/refresh` au chargement,
  client API typé avec retry automatique sur 401, pages login/register/mot de passe
  oublié/réinitialisation + callback Google OAuth (RHF + Zod, erreurs `role="alert"`) —
  inchangé par la bascule backend, consomme la même API HTTP
- 15 tests PHPUnit sur `AuthService` (`apps/api/tests/Feature/AuthServiceTest.php`,
  `RefreshDatabase` + Mockery pour `MailService`)
- Choix faits sans validation préalable (à relire) : inscription Google sans sélection de
  rôle → `CANDIDATE` par défaut ; page `/auth/callback` reçoit l'access token en query
  string (pas idéal niveau exposition — historique navigateur — mais le refresh token
  reste protégé en cookie httpOnly, seul l'access token courte durée est concerné)
- **Testé en conditions réelles contre une vraie base MySQL** (voir section suivante) :
  register/login/refresh/logout/me/forgot-password/reset-password, erreurs de
  validation, email déjà utilisé, mauvais mot de passe, accès non authentifié — tous
  conformes (parité de réponse vérifiée avec l'ancienne implémentation NestJS), via curl.
  **Toujours pas testé** : connexion Google OAuth (`GOOGLE_CLIENT_ID` placeholder), envoi
  d'email réel (`RESEND_API_KEY` absent → log au lieu d'envoyer, comportement vérifié)

**Bascule NestJS → Laravel (PHP) : terminée**

- Décidé le 2026-07-17, à la demande explicite de l'utilisateur : l'hébergement du
  projet reste l'offre OVH mutualisée PRO déjà souscrite (`jeuncy.com`), qui ne supporte
  que PHP (aucun runtime Node.js possible, confirmé dans le manager OVH). Plutôt que de
  migrer vers un hébergement supportant Node.js, le choix a été de réécrire le backend
  en PHP pour rester sur cet hébergement.
- `apps/api` (NestJS + Prisma) entièrement supprimé et réécrit en **Laravel 13 / PHP
  8.3**, en conservant à l'identique toutes les décisions déjà validées : même design JWT
  access+refresh, même format de réponse, mêmes rôles, même modèle de données (voir
  sections ci-dessus). JWT via `firebase/php-jwt` (le package `jwt-auth` habituel de
  l'écosystème Laravel ne supporte pas encore Laravel 13), OAuth via Laravel Socialite,
  style PHP via Laravel Pint.
- Toute la suite d'endpoints d'authentification revalidée par des appels curl réels
  contre la même base Clever Cloud MySQL, avec des réponses identiques à l'ancienne
  implémentation NestJS (y compris les cas d'erreur).
- Bugs rencontrés en installant/configurant Laravel (corrigés) : garde JWT du package
  `jwt-auth` incompatible Laravel 13 → écriture d'une garde custom
  (`App\Auth\JwtGuard`) ; middleware d'auth par défaut de Laravel tente de rediriger
  vers une route `login` web inexistante sur une 401 (au lieu de répondre en JSON) →
  `redirectGuestsTo(fn () => null)` dans `bootstrap/app.php` pour forcer le JSON ;
  pluralisation automatique d'Eloquent devine `education` (singulier) au lieu de la
  vraie table `educations` → `$table` explicite ajouté sur les 13 modèles par sécurité,
  pas seulement celui en défaut.
- `packages/shared` reste en TypeScript, utilisé uniquement par `apps/web` désormais —
  plus de génération de types partagée avec le backend (PHP), synchronisation manuelle
  requise pour toute évolution d'enum.

**Base de données de dev**

- MySQL hébergé chez **Clever Cloud** (plan DEV gratuit, 10 Mo) — suffisant pour valider
  le schéma et le seed, pas dimensionné pour de la vraie donnée en croissance
- La base MySQL mutualisée OVH existante (`jeuncykbdd` sur `jeuncy.com`) s'est révélée
  **injoignable depuis l'extérieur du réseau OVH** (confirmé : le port TCP répond mais
  la négociation du protocole MySQL échoue systématiquement, aucune option de
  restriction IP dans l'interface — limite structurelle de cette offre, pas un réglage
  à activer)
- Identifiants réels dans `apps/api/.env` (gitignored, jamais commité) — à régénérer/
  changer si le plan DEV Clever Cloud est abandonné plus tard

**Phase 2 — profil + CV : terminée**

- `apps/api` : CRUD complet du profil candidat (`App\Services\CandidateProfileService`)
  — infos personnelles (création/mise à jour), expériences, formations, compétences
  (sync par nom via `Skill::firstOrCreate`, dédoublonnage), chaque ressource imbriquée
  (expérience/formation) vérifiée comme appartenant bien au profil de l'utilisateur
  authentifié avant modification/suppression (`FORBIDDEN` sinon). Routes sous
  `candidate-profile/*`, protégées par `auth:api` + `role:CANDIDATE`.
- Génération de CV PDF côté serveur via **`barryvdh/laravel-dompdf`** (choix tranché en
  phase 2, cf. note ci-dessous) : template Blade aux couleurs Jeuncy
  (`resources/views/cv/template.blade.php`), PDF stocké sur le disque `public` (symlink
  `storage:link`), historique des CV générés par profil (`GeneratedCv`,
  `App\Services\CvService`). `@react-pdf/renderer` reste non utilisé pour l'instant : pas
  d'aperçu live côté client avant génération, seulement le PDF final téléchargeable — à
  réévaluer si le besoin d'une prévisualisation instantanée se confirme.
- `apps/web` : page `/profile` (TanStack Query pour le fetch/cache + mutations, RHF/Zod
  pour chaque formulaire), protégée par un nouveau composant `RequireAuth` (redirige vers
  `/login` si non connecté, vers `/` si le rôle ne correspond pas), lien "Mon profil"
  ajouté à la Navbar pour les candidats connectés. Composants découpés dans
  `components/features/profile/` (`ProfileInfoForm`, `ExperienceSection`,
  `EducationSection`, `SkillsSection`, `CvSection`).
- 10 tests PHPUnit sur `CandidateProfileService`
  (`apps/api/tests/Feature/CandidateProfileServiceTest.php`, 25/25 au total avec ceux
  d'auth), plus **testé en conditions réelles contre la vraie base MySQL** (curl :
  création/duplication/mise à jour de profil, CRUD expérience/formation avec garde
  d'appartenance croisée entre deux comptes, sync de compétences, génération + téléchargement
  de CV, 401/403) et **vérifié dans le navigateur** avec le compte de démo Léa Girard
  (formulaires, ajout de compétence, génération de CV, light et dark mode).
- Deux bugs latents découverts et corrigés pendant cette vérification (préexistants,
  pas introduits par phase 2) : `apps/web/.env.example` documentait
  `VITE_API_URL=http://localhost:3000` sans le préfixe `/api` qu'utilisent toutes les
  routes Laravel (`bootstrap/app.php` préfixe automatiquement les routes `api.php`) —
  cassait silencieusement tout appel API côté frontend tant qu'aucun `.env` local ne
  corrigeait la valeur ; `packages/shared` était compilé en CommonJS (correctif fait en
  phase 1 pour l'ancien backend NestJS, qui `require()`-ait le package), mais Vite/Rollup
  ne sait pas analyser statiquement les ré-exports `export *` d'un module CJS et
  refusait de builder dès qu'un import nommé (`UserRole`) traversait ce barrel — recompilé
  en ESM (`"type": "module"` dans `packages/shared/package.json`), correct désormais
  puisque `apps/api` (Laravel) n'est plus un consommateur JS de ce package.

**Retouches post-phase-2 (CV, photo de profil, logo) : terminées**

- Suite à retour utilisateur après inspection en local, le template de CV a été
  entièrement repensé façon Canva (`resources/views/cv/template.blade.php`) : mise en
  page deux colonnes, sidebar navy plein-hauteur (photo circulaire, contact, compétences
  en pills, formations) + colonne principale (bio, expériences en timeline avec accent
  corail). Le fond plein-hauteur de la sidebar est un rectangle positionné en absolu
  derrière le contenu (z-index explicite) — dompdf n'étire pas le fond d'une cellule de
  tableau au-delà de son contenu, et `height` sur une `table-cell` casse carrément la
  pagination (piège rencontré et documenté en commentaire dans le template). Vérifié à
  chaque itération par rasterisation du PDF généré (`pdfjs-dist` + `@napi-rs/canvas` en
  script ponctuel, pas de dépendance ajoutée au projet).
- Upload/suppression de la photo de profil : `POST`/`DELETE candidate-profile/photo`
  (image, 2 Mo max, remplace l'ancienne photo si besoin), intégrée au CV via une data URI
  base64 (lecture directe du fichier local, sans aller-retour HTTP vers le serveur
  lui-même) avec repli sur un avatar "initiales" généré si aucune photo n'est fournie.
  Composant `ProfilePhotoUpload` côté frontend ; le client API (`lib/api/client.ts`) gère
  désormais aussi les requêtes `FormData` (jusque-là uniquement JSON).
- Logos (`logo-light.png`, `logo-dark.png`) : le fond carré du PNG était rempli en noir
  plein (aucun canal alpha), visible comme une bordure/coin noir autour du cercle sur
  tout fond non-noir. Réexportés avec un masque alpha circulaire (transparent en dehors
  du cercle) via un script `sharp` ponctuel (scratchpad, pas de dépendance ajoutée).
- 3 tests PHPUnit supplémentaires sur l'upload/suppression de photo (28/28 au total).
  Vérifié contre la vraie base MySQL (curl : upload, génération de CV avec et sans photo)
  et visuellement (rendu PDF rasterisé + navigateur pour le logo et le composant d'upload
  — l'upload de fichier lui-même n'a pas pu être piloté depuis le navigateur automatisé,
  restriction navigateur standard sur la valeur programmatique d'un `<input type="file">`).

**Connu et à traiter plus tard**

- Déploiement réel sur l'hébergement OVH mutualisée PRO pas encore fait/documenté (accès
  FTP disponibles, mais process de déploiement — build du frontend, upload PHP, config
  `.env` prod, cron si besoin d'une queue — à définir en phase de mise en prod)
- Pas de prévisualisation client (`@react-pdf/renderer`) avant génération du CV — voir
  note phase 2 ci-dessus
- Logos `apps/web/public/logo/logo-light.png` et `logo-dark.png` sont les versions
  circulaires (badge) redimensionnées à 128×128, désormais à fond transparent ; la
  version pleine avec tagline (`logo_jeuncy.png` à la racine, hors repo web) n'a pas
  encore d'usage assigné

**Phase 3 — offres + paiement : terminée**

- `apps/api` : profil entreprise (`CompanyService`) et profil CFA
  (`CfaOrganizationService`), CRUD miroir de `CandidateProfileService` (show/
  store/update, `COMPANY_NOT_FOUND`/`CFA_ORGANIZATION_NOT_FOUND` sinon).
  Routes `company/*` (role `COMPANY`) et `cfa-organization/*` (role `CFA`).
- CRUD des offres d'emploi (`JobOfferService`/`JobOfferController`, routes
  `job-offers/*`, role `COMPANY,CFA`) : création en `DRAFT`/`payment_status
PENDING`, l'appartenance à une `Company` OU un `CfaOrganization` (jamais les
  deux) est déterminée côté service à partir du rôle de l'utilisateur —
  jamais acceptée en entrée côté client. Garde d'appartenance
  (`requireOwnedOffer`) réutilisée pour la mise à jour, l'archivage et le
  paiement.
- Recherche/consultation publique des offres (`PublicJobOfferController`,
  routes `GET job-offers/search` et `GET job-offers/{id}`, sans
  authentification) : uniquement les offres `PUBLISHED`, filtres mot-clé
  (titre/description), type de contrat, ville, pagination (12/page). La page
  `/offres/{id}` d'une offre non publiée renvoie `JOB_OFFER_NOT_FOUND` (404),
  pas de fuite des brouillons d'un concurrent.
- Paiement Stripe (`PaymentService`, `stripe/stripe-php`) : `POST
job-offers/{id}/checkout` crée une session Stripe Checkout (montant fixe,
  `STRIPE_JOB_OFFER_PRICE_CENTS`, 49,00 € par défaut) après avoir vérifié que
  l'offre appartient à l'utilisateur et est encore en brouillon, et crée un
  `Payment` local (`PENDING`, `stripe_session_id`). `POST stripe/webhook`
  (hors `auth:api`, signature Stripe vérifiée à la place) traite
  `checkout.session.completed` : marque le `Payment` `SUCCEEDED`, publie
  l'offre (`status PUBLISHED`, `payment_status SUCCEEDED`, `published_at`),
  et notifie l'utilisateur (`NotificationType::PAYMENT_SUCCEEDED`). La
  logique métier du webhook (`markPaymentSucceeded`) est volontairement
  isolée de la vérification de signature pour rester testable sans réseau.
- `apps/web` : page `/organization` unique (formulaire entreprise avec SIRET
  ou formulaire CFA selon `user.role`), tableau de bord `/mes-offres`
  (création, édition, archivage, bouton "Publier (paiement)" qui redirige
  vers Stripe Checkout), page publique `/offres` (recherche/filtre, état dans
  l'URL via `useSearchParams`, pagination) et `/offres/:id` (détail). La
  barre de recherche de la page d'accueil redirige désormais vers `/offres`.
  `RequireAuth` accepte maintenant `role` sous forme de tableau
  (`[UserRole.COMPANY, UserRole.CFA]`) en plus d'un rôle unique.
- 17 tests PHPUnit sur les 4 nouveaux services (45/45 au total) : profil
  entreprise/CFA (création, doublon refusé, mise à jour), offres (invariant
  `company_id`/`cfa_organization_id`, garde d'appartenance croisée entre deux
  entreprises, `requireOwnedDraftOffer` rejette une offre déjà publiée,
  recherche publique ne renvoie que les offres publiées), et paiement
  (`markPaymentSucceeded` publie l'offre et notifie, idempotent sur un même
  événement Stripe rejoué, ignore une session inconnue).
- **Testé en conditions réelles contre la vraie base MySQL** (curl) : création
  de profils entreprise/CFA, création/mise à jour/archivage d'offres, garde
  d'appartenance croisée (403), recherche publique avec filtres, offre en
  brouillon invisible publiquement (404), guard de rôle (403 pour un
  candidat), et **simulation du webhook via `tinker`** (création du `Payment`
  puis `PaymentService::markPaymentSucceeded` directement, contournant
  uniquement l'appel réseau à Stripe) pour valider l'effet complet — offre
  publiée, paiement marqué réussi, notification créée — contre la vraie base.
  **Vérifié dans le navigateur** : profil NexaTech pré-rempli, création et
  édition d'une offre, recherche/détail publics, light et dark mode.
- **Non testé contre le vrai Stripe** : aucune clé API disponible dans cet
  environnement (`STRIPE_SECRET_KEY`/`STRIPE_WEBHOOK_SECRET` vides), même
  limitation documentée que Google OAuth/Resend. Le endpoint de paiement
  échoue proprement (500, message clair) faute de clé — vérifié que ça ne
  casse pas le reste du contrôleur (voir bug corrigé ci-dessous). À tester
  dès que des clés de test Stripe seront disponibles.
- Bug réel découvert et corrigé pendant la vérification : `PaymentService`
  construisait un `StripeClient` dès son constructeur, donc **le endpoint
  webhook plantait aussi** faute de clé Stripe alors qu'il n'en a pourtant pas
  besoin (`Stripe\Webhook::constructEvent` est un appel statique) — le client
  Stripe est désormais construit à la demande (`stripe()`, appelée uniquement
  par `createCheckoutSessionForOffer`), pas au constructeur du service.
- `apps/api/.env.example` n'avait jamais été mis à jour depuis la bascule
  NestJS → Laravel : il manquait `JWT_*`, `FRONTEND_URL`, `GOOGLE_*`,
  `RESEND_*` en plus des nouvelles variables `STRIPE_*` — corrigé au passage
  pour refléter les vraies variables consommées par `apps/api/.env`.

**Connu et à traiter plus tard (phase 3)**

- Paiement jamais vérifié contre le vrai Stripe (voir ci-dessus) — prévoir un
  test avec de vraies clés de test (`sk_test_...`) et `stripe listen` avant la
  mise en production.
- Pas de liste des paiements/factures côté entreprise (le modèle `Payment`
  existe et est peuplé, mais aucun endpoint ne l'expose encore côté
  frontend).
- Pas de gestion de l'expiration des offres (`status EXPIRED`, `expires_at`
  existent en base mais rien ne les fait transitionner automatiquement —
  prévoir une tâche planifiée en phase 6).

**Phase 4 — candidatures : terminée**

- `apps/api` : candidature côté candidat (`ApplicationService::applyForUser`)
  — nécessite un profil candidat existant, l'offre doit être `PUBLISHED`
  (`JOB_OFFER_NOT_PUBLISHED` sinon), un seul essai par offre (contrainte
  unique `candidate_profile_id`/`job_offer_id` en base + garde applicative,
  `APPLICATION_ALREADY_EXISTS` sinon). Routes `POST/GET applications`, role
  `CANDIDATE`.
- Gestion côté entreprise/CFA : `GET job-offers/{id}/applications` (liste des
  candidatures reçues, garde d'appartenance réutilisée de
  `JobOfferService::requireOwnedOffer`) et `PATCH applications/{id}/status`
  (transition vers `SEEN`/`INTERVIEW`/`ACCEPTED`/`REJECTED` — jamais `SENT`,
  statut initial automatique, exclu de la validation). Routes role
  `COMPANY,CFA`.
- Notifications : le modèle `Notification` existait depuis la phase 1 (schéma
  de données) mais n'avait ni service ni endpoint avant cette phase — ajout de
  `NotificationService`/`NotificationController` (liste des 30 dernières,
  marquer comme lu, tout marquer comme lu). Chaque candidature crée une
  notification `NEW_APPLICATION` pour le propriétaire de l'offre ; chaque
  changement de statut crée une notification `APPLICATION_STATUS_CHANGED`
  pour le candidat.
- `apps/web` : bouton "Postuler" sur `/offres/:id` (`ApplyToOfferSection`,
  lettre de motivation facultative, visible uniquement pour un candidat
  connecté, invite à se connecter sinon), page `/mes-candidatures` (suivi des
  candidatures et de leur statut). Côté entreprise/CFA, un bouton "Voir les
  candidatures" sur chaque offre publiée dans `/mes-offres` déplie la liste
  des candidats avec un sélecteur de statut. Cloche de notifications dans la
  Navbar (badge de compteur, panneau déroulant, rafraîchissement toutes les
  30s, clic = marque comme lu + navigue vers le lien associé) visible pour
  tout utilisateur connecté.
- 11 tests PHPUnit sur les 2 nouveaux services (56/56 au total) : candidature
  - notification au propriétaire, refus offre non publiée, refus de doublon,
    garde d'appartenance croisée sur la liste et le changement de statut,
    notification au candidat lors du changement de statut, marquage lu/tout
    lu, garde sur une notification étrangère.
- **Testé en conditions réelles contre la vraie base MySQL** (curl) : parcours
  complet candidat → entreprise (candidature, doublon refusé, offre brouillon
  refusée, liste des candidatures reçues, garde d'appartenance croisée 403,
  changement de statut, statut `SENT` refusé en entrée 400) et vérifié via
  `tinker` que la notification est bien créée. **Vérifié dans le navigateur**
  avec les comptes de démo Léa Girard (candidate) et NexaTech (entreprise) :
  candidature envoyée avec lettre de motivation depuis le détail d'offre,
  visible dans "Mes candidatures", changement de statut depuis "Mes offres"
  côté NexaTech, notification reçue et badge de la cloche mis à jour côté
  Léa en quasi temps réel (rafraîchissement 30s ou changement de page).

**Connu et à traiter plus tard (phase 4)**

- Pas de suppression/annulation de candidature côté candidat (une fois
  envoyée, elle ne peut être retirée).
- Pas de notification email (Resend) en plus de la notification in-app — à
  évaluer si le besoin se confirme, `MailService` existe déjà pour le socle
  technique (reset de mot de passe).

**Phase 5 — visioconférence : terminée**

- `apps/api` : `VideoRoomService` — création de salle (`jitsi_room_name`
  généré via `Str::uuid()`, non devinable), résolution facultative d'un
  participant par email (candidat existant, sinon `PARTICIPANT_NOT_FOUND`),
  listing des salles pour un utilisateur (hôte OU participant, avec
  `host`/`participant` chargés), démarrage/fin de salle réservés au hôte
  (`requireHost` privé, 403 `FORBIDDEN` sinon). Routes `video-rooms/*`
  (`VideoRoomController`, `auth:api` + `role:COMPANY,CFA,ADMIN`) : `index`/
  `store`/`{id}/start`/`{id}/end`.
- Consultation publique (`PublicVideoRoomController`, route
  `GET video-rooms/room/{roomName}`, **sans authentification**, déclarée
  avant le groupe protégé pour ne pas passer par `auth:api`) : renvoie un
  payload volontairement minimal (`jitsi_room_name`, `status`,
  `scheduled_at`) sans identité hôte/participant — décision proactive de
  hygiène de données, le nom de salle UUID étant le seul mécanisme de
  contrôle d'accès sur cet endpoint ouvert (nécessaire pour qu'un
  prospect sans compte Jeuncy puisse rejoindre une démo).
- 9 tests PHPUnit sur `VideoRoomService`
  (`apps/api/tests/Feature/VideoRoomServiceTest.php`, 65/65 au total) :
  création avec/sans participant, participant email inconnu rejeté,
  salle publique introuvable/trouvée, démarrage/fin (statut + horodatage),
  démarrage rejeté pour un non-hôte, listing hôte + participant.
- **Testé en conditions réelles contre la vraie base MySQL** (curl) :
  création de salle, listing, démarrage/fin, garde de rôle (403 pour un
  candidat non hôte), consultation publique sans authentification.
- `apps/web` : `JitsiRoom` (`components/features/video-rooms/`) —
  wrapper de `@jitsi/react-sdk` sur l'instance publique `meet.jit.si`,
  page de pré-connexion **native de Jitsi** (pas de formulaire custom,
  choix de minimisation de scope), toolbar allégée avec le partage
  d'écran en priorité (`configOverwrite`/`toolbarButtons`). Page publique
  `/demo/:roomId` (`DemoRoom.tsx`, **hors `RequireAuth`** — un prospect
  sans compte doit pouvoir rejoindre) : gère salle introuvable et salle
  déjà terminée avant de monter le composant Jitsi. Tableau de bord
  `/mes-visios` (`MyVideoRooms.tsx`, `RequireAuth`
  `[UserRole.COMPANY, UserRole.CFA]`) : formulaire de création (email
  participant + date facultatifs, RHF + Zod), liste des salles avec
  badge de statut, lien d'invitation copiable (`/demo/{jitsi_room_name}`),
  actions démarrer/terminer. Lien "Visio démo" ajouté à la `Navbar` pour
  COMPANY/CFA, à côté de "Mon entreprise"/"Mes offres".
- **Vérifié dans le navigateur** avec le compte de démo NexaTech
  (`rh@nexatech.example.com`) : création d'une salle pour
  `malik.benali@example.com` (apparaît immédiatement dans la liste),
  démarrage puis fin de salle (statut `Programmée` → `En cours` →
  `Terminée`, requêtes réseau confirmées), navigation vers
  `/demo/{roomId}` qui récupère la salle publique et monte le composant
  Jitsi (l'iframe a bien déclenché une demande d'accès caméra/micro,
  preuve que `JitsiMeeting` s'est initialisé correctement — la capture
  d'écran de cette étape précise n'a pas pu être prise, le bac à sable du
  navigateur de développement restant bloqué sur cette permission ;
  limite de l'environnement de vérification, pas un défaut de l'app,
  confirmée via le texte de page et les requêtes réseau à la place).
- Limites connues à documenter pour l'utilisateur (déjà énoncées en
  section 7) : l'instance publique `meet.jit.si` ne permet pas de
  branding complet (logo Jeuncy) ni l'enregistrement des sessions —
  migration vers un self-hosting Jitsi via Docker envisageable en V2 si
  le besoin se confirme.

**Connu et à traiter plus tard (phase 5)**

- Pas de rappel/notification automatique avant une visio programmée
  (`scheduled_at` stocké mais rien ne notifie l'hôte/le participant à
  l'approche de l'heure — prévoir une tâche planifiée en phase 6, comme
  pour l'expiration des offres).
- Pas de limite de durée de vie du lien d'invitation (`/demo/:roomId`
  reste valide tant que la salle n'est pas marquée `ENDED` par l'hôte,
  aucune expiration automatique côté serveur).
- Statuts `LIVE`/`ENDED` gérés manuellement par l'hôte (pas de détection
  automatique de connexion/déconnexion des participants via l'API Jitsi
  — hors scope pour une simple démo).

**Phase 6 — admin + polish : terminée**

- `apps/api` : `AdminService` + controllers `Admin/*` (`StatsController`,
  `UserController`, `JobOfferController`, `PaymentController`,
  `VideoRoomController`), routes `admin/*` (`auth:api` + `role:ADMIN`) :
  - statistiques plateforme (`GET admin/stats`) : utilisateurs par rôle,
    offres par statut, candidatures, paiements réussis/revenus, salles de
    visio.
  - modération des comptes (`GET admin/users` filtrable par rôle, `POST
admin/users/{id}/suspend`/`reactivate`) : un admin ne peut pas se
    suspendre lui-même (`CANNOT_SUSPEND_SELF`).
  - modération des offres (`GET admin/job-offers` filtrable par statut,
    `POST admin/job-offers/{id}/archive`) : archivage forcé sans
    vérification de propriétaire, contrairement à
    `JobOfferService::archiveForUser` réservé au propriétaire.
  - supervision des paiements (`GET admin/payments` filtrable par statut,
    lecture seule) et des visios (`GET admin/video-rooms` filtrable par
    statut, `POST admin/video-rooms/{id}/end` sans vérification d'hôte).
- Suspension de compte : migration `users.is_suspended` (boolean, défaut
  `false`). Bloquée dans `AuthService` à la connexion par mot de passe, au
  rafraîchissement de token et à la connexion Google OAuth (existant ou
  associé), toutes via `assertNotSuspended()` (`ApiException
ACCOUNT_SUSPENDED`, 403). Un access token déjà émis est **aussi** coupé
  immédiatement dans `JwtGuard::user()` — pas besoin d'attendre son
  expiration (15 min) pour qu'une suspension prenne effet.
- Polish (items notés "à traiter plus tard" dans les phases précédentes) :
  - `job-offers:expire` (`app/Console/Commands/ExpireJobOffers.php`) :
    passe au statut `EXPIRED` les offres `PUBLISHED` dont `expires_at` est
    dépassé. Planifiée quotidiennement via `bootstrap/app.php`
    (`->withSchedule(...)`, nécessite un cron `schedule:run` côté
    hébergement en production — non configuré dans cet environnement de
    dev).
  - `GET payments/mine` (`PaymentService::listOwn`, role `COMPANY,CFA`) :
    historique des paiements de l'entreprise/CFA connecté(e), manquant
    depuis la phase 3.
- Compte de démo `admin@jeuncy.com` ajouté au seeder (même mot de passe
  commun).
- 26 tests PHPUnit supplémentaires (79/79 au total) :
  `AdminServiceTest` (stats, filtres, garde anti-auto-suspension,
  archivage/fin forcés ignorant la propriété/l'hôte), suspension dans
  `AuthServiceTest` (login et refresh rejetés), `ExpireJobOffersCommandTest`
  (offre expirée transitionnée, offre encore valide inchangée),
  `PaymentServiceTest::test_list_own_returns_only_users_payments`.
- **Testé en conditions réelles contre la vraie base MySQL** (curl) :
  stats, suspension/réactivation, connexion refusée pour un compte
  suspendu, accès coupé en cours de session sur un access token déjà émis
  (`/auth/me` passe de 200 à 401 dès la suspension, sans attendre
  l'expiration du token), garde anti-auto-suspension, modération d'offre,
  listes paginées/filtrées (utilisateurs/offres/paiements/visios), fin
  forcée d'une salle, garde de rôle (403 pour un compte non-admin) et
  `payments/mine` (compte entreprise), commande d'expiration exécutée
  manuellement (`php artisan job-offers:expire`) sur une offre à la date
  backdatée.
- `apps/web` : page `/admin` (`RequireAuth role={UserRole.ADMIN}`) —
  tableau de bord à 5 onglets (boutons simples, pas de nouveau composant
  `Tabs` shadcn ajouté pour une seule page) : Statistiques (grille de
  cartes), Utilisateurs/Offres/Paiements/Visios (chacun : filtre par
  statut/rôle, liste paginée via un composant `AdminPager` partagé,
  actions de modération le cas échéant). Page `/mes-paiements`
  (`RequireAuth role={[UserRole.COMPANY, UserRole.CFA]}`) : historique des
  paiements. Liens "Administration" (rôle ADMIN) et "Paiements"
  (COMPANY/CFA) ajoutés à la `Navbar`.
- **Vérifié dans le navigateur** avec le compte de démo `admin@jeuncy.com`
  : les 5 onglets du tableau de bord, suspension puis réactivation de
  `contact@cafedeslices.example.com` (badge et bouton mis à jour en
  place), archivage forcé de l'offre bénévole en brouillon, fin forcée
  d'une salle de visio programmée (jamais démarrée par son hôte). Vérifié
  aussi `/mes-paiements` avec le compte NexaTech (paiement de démo
  affiché, badge "Réussi"), light et dark mode.

**Connu et à traiter plus tard (phase 6)**

- Planification réelle du cron Laravel (`schedule:run` toutes les
  minutes) non configurée dans cet environnement de dev — à mettre en
  place lors du déploiement en production (cron système ou tâche
  planifiée de l'hébergeur).
- Pas de remboursement Stripe depuis le back-office (le modèle `Payment`
  a un statut `REFUNDED` mais aucune action ne le déclenche encore) — même
  limitation que le reste de l'intégration Stripe, jamais testée contre
  de vraies clés (voir phase 3).
- Pas de rappel automatique avant une visio programmée, ni de suppression/
  annulation de candidature côté candidat — reportés depuis les phases 4
  et 5, toujours pas traités.
- Les 6 phases du plan initial (`CLAUDE.md` section 11) sont maintenant
  toutes terminées ; les items listés ici et dans les sections "connu et à
  traiter plus tard" des phases précédentes constituent le backlog restant
  avant une mise en production complète (voir aussi section "Connu et à
  traiter plus tard" générale après la phase 3 pour le déploiement OVH).

**Notification "une offre te correspond" (2026-09-03) : terminée**

- Demande initiale (patron) : faire postuler **automatiquement** les
  candidats correspondants dès qu'une entreprise paie sa publication.
  Déconseillé et **écarté après validation du patron** : une candidature
  signifie "je veux ce poste", l'envoyer à la place du candidat lui fait
  dire ce qu'il n'a pas dit, et l'entreprise — celle qui paie — appelle
  alors des gens qui n'ont rien demandé. Retenu à la place : prévenir le
  candidat, qui postule lui-même.
- `App\Services\JobOfferMatchService::notifyMatchingCandidates()` : un
  candidat est prévenu si l'offre est **dans sa ville OU** si son profil
  (titre, bio, compétences, logiciels) partage un mot significatif avec
  l'intitulé — le OU est volontaire, exiger les deux ne notifierait
  presque personne. Mots trop fréquents ("alternance", "poste",
  "stage"...) exclus par une liste de `STOPWORDS`, sans quoi toutes les
  offres correspondraient à tous les candidats. Un type de contrat
  **explicitement** mentionné par le candidat exclut les autres ; un
  profil muet sur ce point reste éligible à tout.
- Exclus : candidats ayant déjà postulé à cette offre, comptes suspendus,
  comptes supprimés. Insertion groupée par lots de 200, volontairement
  synchrone (pas de worker possible sur l'hébergement mutualisé).
- Branché sur les **trois** chemins de publication — essai gratuit et
  abonnement (`JobOfferService`), paiement à l'unité (`PaymentService`,
  webhook Stripe) : n'en brancher que certains rendrait la notification
  dépendante de la façon dont l'entreprise a payé.
- Nouveau type `NotificationType::JOB_OFFER_MATCH` (migration d'enum côté
  MySQL + `packages/shared` côté TS). Aucun changement frontend
  nécessaire : la cloche affiche `message` et navigue vers `link`
  (`/offres/{id}`), où le formulaire de candidature pré-remplit déjà le
  téléphone et sélectionne le dernier CV généré — "postule en un clic"
  est littéralement vrai.
- 17 tests PHPUnit ajoutés (301/301 au total) : 13 sur la règle de
  correspondance (ville, mot-clé, compétence, exclusions, volume) et 4
  d'intégration vérifiant que la notification part réellement depuis
  chacun des trois chemins et **pas** à la création d'un brouillon.
- **Vérifié contre la vraie base MySQL** : migration appliquée, puis
  mesure à blanc dans une transaction annulée sur les offres et candidats
  réels — le développeur de Rennes est notifié de l'offre de développeur
  à Rennes, la commerciale de Nantes ne l'est pas, et un candidat ayant
  déjà postulé ne l'est pas non plus. Écriture réelle de la valeur d'enum
  confirmée (ce que SQLite des tests ne prouve pas).

**Connu et à traiter plus tard (notification de correspondance)**

- Notification in-app uniquement, pas d'email — `MailService` existe si
  le besoin se confirme.
- Pas de réglage côté candidat pour se désabonner de ces notifications
  (aucune préférence de notification n'existe encore dans le produit).
- La correspondance ne lit que le **titre** de l'offre, pas sa
  description : suffisant tant que les intitulés sont explicites, à
  revoir si des offres au titre vague apparaissent.

**Outil de correction des noms de candidats (2026-09-04) : terminé**

- Deux profils réels avaient été créés au nom de « Permis B » et d'une suite
  de compétences : l'import de CV crée le profil **sans que le candidat
  relise** l'identité lue dans son PDF, donc une extraction ratée ne se voit
  qu'après coup, dans la CVthèque, sous les yeux des recruteurs.
- Extraction corrigée (`CvImportService::extractName`) : étiquettes de
  gabarit rejetées (`permis`, `nationalité`, `téléphone`…), mots impossibles
  dans un nom (`alternance`, `stage`, `licence`…), et surtout **l'adresse
  email comme corroboration** — `rostomghazli64@gmail.com` confirme « ROSTOM
  GHAZLI » où qu'il se trouve dans le document, ce qui règle le cas des CV à
  deux colonnes où le nom sort de la fenêtre de lecture.
- `admin/candidate-profiles` (liste, filtre `suspicious`) et
  `PATCH admin/candidate-profiles/{id}/name` + onglet « Candidats » dans
  `/admin`. Le filtre s'évalue en PHP (aucune clause WHERE ne sait
  reconnaître « Permis B »), et s'appuie aussi sur le **référentiel de
  compétences** : « Prospection Encaissement » ne se distingue d'un vrai nom
  que parce que ces deux mots sont des compétences connues.
- Vérifié sur les 53 profils réels de production : 0 faux positif (« Louis
  MOUCHE », « Hugo Dos Santos », « Laura FAYE » ne sont pas signalés).

**Leçon de déploiement (2026-09-04) — à lire avant tout envoi OVH**

- Le serveur porte une **arborescence parasite** : un dossier `app/` complet a
  été déposé un jour dans `/api-app/app/Http/`, qui contient donc Auth,
  Console, Enums, Exceptions, Http, Models, Providers, Services en plus de ses
  trois dossiers légitimes. Ces copies sont **inertes** (le PSR-4 mappe
  `App\Services\X` sur `app/Services/X.php`), mais elles servent de piège :
  des fichiers y ont atterri au lieu de leur vraie destination.
- C'est ce qui a coûté **cinq allers-retours** : trois fichiers
  (`Admin/CandidateProfileController.php`, `Admin/ListCandidateProfilesRequest.php`,
  `Admin/UpdateCandidateNameRequest.php`) n'étaient jamais arrivés, et quatre
  hypothèses successives ont cherché un bug dans du code qui n'en avait pas.
- **Toujours ajouter les fichiers d'une nouvelle fonctionnalité à la liste
  surveillée de `DeployController::version()`** avant de les faire envoyer :
  un fichier absent produit une panne indistinguable d'un bug de code.
- `/deploy/{token}/selftest` (`deploy-tools-4`) exécute les chemins sensibles
  et renvoie l'exception réelle. Piège rencontré : il appelait le **service**
  sans passer par le contrôleur, donc il validait précisément la partie qui
  marchait. Une instrumentation qui ne traverse pas tout le chemin donne une
  fausse assurance.
- `CvImportService.php` diffère d'un mot-clé de visibilité entre le dépôt
  (`private`, 20047ab81a2fe1a6) et le serveur (`public`, 5ca94613966ea432) —
  aucun effet fonctionnel, à réaligner au prochain envoi backend.

**Notification de correspondance : deux fichiers manquants (2026-09-08)**

- Symptôme : offre publiée, candidat du même domaine et de la même ville,
  aucune notification. Quatre diagnostics faux avant d'y voir clair.
- Causes réelles, **deux fichiers jamais arrivés sur le serveur**, aucun des
  deux surveillé : `JobOfferService.php` (sans le câblage, rien n'était
  appelé) et `app/Enums/NotificationType.php` (sans la valeur
  `JOB_OFFER_MATCH`, l'insertion échouait).
- **Pourquoi c'était invisible** : l'exception était avalée par le `try/catch`
  de `CandidateProfileService`, posé pour qu'un candidat ne perde jamais son
  profil à cause d'une notification ratée. La protection est juste ; elle rend
  la panne muette.
- Angle mort à retenir : je surveillais les fichiers **créés**, jamais ceux
  **modifiés pour brancher** une fonctionnalité. C'est exactement là qu'était
  la panne, deux fois.
- Outils construits, à réutiliser avant toute conjecture :
  - `/deploy/{token}/match/{id}` — explique, candidat par candidat, pourquoi
    il est notifié ou non (ville, mot partagé, contrat, déjà notifié), plus
    les mots-clés réellement retenus de l'intitulé. Aucune donnée personnelle.
  - `cablage` dans cette réponse — vérifie **par réflexion** que
    `JobOfferService`, `PaymentService` et `CandidateProfileService` reçoivent
    bien `JobOfferMatchService`. Une empreinte prouve qu'un fichier est là ;
    ceci prouve que l'appel existe.
  - `?profil=N&envoyer=1` — envoi ciblé sur un seul candidat, **hors
    try/catch** : c'est ce qui a fini par afficher l'exception réelle.
  - `?effacer=1` — supprime les notifications de correspondance d'une offre,
    y compris quand l'offre a été supprimée.
- **Envoi de masse verrouillé** : `?envoyer=1` seul est refusé, il faut
  `&tous=1`. Oublier `?profil=N` avait envoyé 37 notifications à de vrais
  candidats pour une offre de test. Une action visible par des tiers ne doit
  jamais être le comportement par défaut d'un paramètre omis.
- Deux défauts de la règle corrigés au passage : le mot « étudiant » excluait
  toutes les alternances (il déclenchait la préférence JOB_ETUDIANT), et la
  comparaison des mots était littérale — « commerce » ne correspondait pas à
  « commercial ». Comparaison par famille désormais : six caractères communs,
  ou l'un préfixe l'autre à partir de cinq.

**Application mobile — phase 0, socle (2026-09-09) : terminée**

- Nouveau `apps/mobile` : Expo SDK 57, React Native 0.86, React 19.2, Expo
  Router, TypeScript strict. Intégré au workspace pnpm ; il consomme
  `@jeuncy/shared` comme `apps/web`. Le cadrage complet est dans `MOBILE.md`.
- **Une seule modification backend** : mode mobile de l'authentification
  (`AuthController`). Un client natif se déclare par l'en-tête
  `X-Jeuncy-Client: mobile` et reçoit alors le refresh token dans le corps
  JSON, sans qu'aucun cookie soit posé. `AuthService` n'a pas bougé.
- **Garde anti-XSS, le point à ne jamais assouplir** : en mode mobile,
  `/auth/refresh` lit le jeton dans le corps et **ignore le cookie**. Sans
  cela, un script injecté dans le navigateur appellerait la route avec cet
  en-tête, le navigateur joindrait le cookie httpOnly automatiquement, et le
  serveur renverrait en clair un refresh token de 7 jours — la protection
  httpOnly du site annulée par une fonctionnalité mobile.
- 10 tests dans `tests/Feature/MobileAuthTest.php` (354/354 au total). **Piège
  rencontré, consigné dans le fichier** : le harnais de test de Laravel ne
  joint aucun cookie à une requête `postJson`. Le test de la garde passait donc
  pour la mauvaise raison — il vérifiait qu'un cookie absent ne servait à rien.
  Corrigé en passant par `post()`, avec une contre-épreuve qui prouve que le
  même cookie fonctionne dès qu'on ne se déclare plus mobile.
- **Sonde de déploiement** (aucun effet de bord, aucune donnée touchée) :
  `POST /api/auth/refresh` avec l'en-tête mobile et
  `{"refreshToken":"sonde"}` répond `MISSING_REFRESH_TOKEN` (400) sur
  l'ancienne version, `INVALID_REFRESH_TOKEN` (401) une fois le fichier
  déployé. Elle prouve que le code s'exécute, là où une empreinte prouve
  seulement qu'un fichier est présent.
- Côté application : thème Jeuncy (`src/theme/`, valeurs de la section 2
  recopiées à la main — aucune génération commune avec `tailwind.config.ts`,
  synchronisation manuelle), mode clair/sombre/système persistant, client API
  porté depuis le web (même enveloppe, même rejeu sur 401, même coalescence des
  refresh), refresh token dans `expo-secure-store` (Keychain/Keystore), écrans
  de connexion, inscription et mot de passe oublié, accueil par rôle.
- Case **« J'ai 15 ans ou plus »** à l'inscription (décision du 2026-09-09,
  `MOBILE.md` §9.3). **Déclaratif seulement : rien n'est encore enregistré
  côté serveur.** Suffisant pour Apple, insuffisant pour prouver le
  consentement — une colonne en base reste à ajouter avant la soumission.
- Vérifié : `expo export --platform ios` produit un bundle complet (Metro
  résout les alias `@/`, le paquet du workspace et les assets), types et lint
  propres, Metro démarre. **Vérifié sur l'iPhone de Pierre le 2026-09-11**
  (Expo Go, backend déployé) : inscription refusée sans la case des 15 ans,
  doublon d'email refusé, connexion avec un compte existant du site (même
  base — l'exigence « même compte web et mobile » est prouvée), **session
  conservée après fermeture complète de l'app** (le coffre fonctionne),
  clair/sombre/système, déconnexion.
- Deux pièges natifs traités : une police custom n'a pas de graisse (chaque
  graisse est un fichier, `fontWeight` est sans effet sur Android — d'où les
  constantes de `theme/typography.ts`) ; et importer les polices depuis la
  racine de `@expo-google-fonts/*` embarque les 18 graisses de chaque famille,
  italiques comprises — 9,1 Mo d'assets contre 1,7 Mo en important chaque
  fichier par son chemin exact.

**Connu et à traiter plus tard (mobile phase 0)**

- Google OAuth, notifications push et achats intégrés **ne fonctionnent pas
  dans Expo Go** : ils exigent un _development build_, donc le compte Apple
  Developer (chemin critique, plusieurs semaines de validation pour un compte
  Organisation).
- Âge minimum non enregistré côté serveur (voir ci-dessus).
- `logout` incrémente `token_version`, ce qui révoque **tous** les appareils :
  se déconnecter du téléphone déconnecte aussi le site. Comportement déjà
  existant entre navigateurs, mais plus visible avec deux clients.
- `pnpm-workspace.yaml` liste désormais des `minimumReleaseAgeExclude` pour les
  paquets Expo 57, ajoutés automatiquement par pnpm : sa politique par défaut
  refuse les paquets publiés trop récemment (protection chaîne
  d'approvisionnement). Assouplissement assumé, à relire aux montées de version.

**Application mobile — phase 1, lots A et B (2026-09-11) : terminés et
validés sur iPhone**

- **Lot A — navigation et offres.** Pile (écrans de détail avec retour natif)
  au-dessus d'une barre d'onglets Offres / Candidatures / Notifications /
  Profil ; les onglets propres au candidat sont masqués (`href: null`) pour
  les autres rôles, qui voient « Compte » à la place de « Profil ».
  Recherche publique : mot-clé et ville temporisés (400 ms), type de contrat
  et mode de travail en puces, pagination infinie, tirer pour rafraîchir,
  états vide et erreur avec issue. Détail aligné sur `PublicJobOfferView`
  (rubriques entreprise vs CFA), 404 expliqué. Libellés des enums centralisés
  dans `lib/labels.ts` (Record exhaustif : une valeur ajoutée à
  `packages/shared` refuse de compiler sans libellé). `formatCompensation`
  copié à l'identique du web. `age_confirmed` envoyé à l'inscription.
- **Lot B — profil candidat.** Identité avec photo, sections expériences,
  formations, compétences, logiciels, langues ; un compte sans profil est
  invité à le créer (prénom, nom, date de naissance — 15 ans minimum, borne
  du sélecteur alignée sur `StoreCandidateProfileRequest`). Un écran par
  sujet, poussé dans la pile ; expérience et formation servent à l'ajout et à
  la modification (élément lu dans le cache TanStack). Compétences et
  logiciels : éditeur de puces, un seul `PUT` à l'enregistrement. Niveau de
  langue guidé (CECRL + « Natif »). Dates : sélecteur natif, conversions ISO
  dans `lib/dates.ts` à midi local (évite le décalage d'un jour).
- **Validé sur iPhone par Pierre** (compte candidat de test créé depuis
  l'app) : lot A en entier, y compris filtres excluants et mode sombre ; lot B
  en entier **sauf la photo** — et surtout, **cohérence vérifiée avec le
  site** : les saisies faites sur l'iPhone apparaissent à l'identique sur
  jeuncy.com avec le même compte.
- État de la production au moment des tests : **une seule offre publiée**
  (IDA, CFA, Perpignan). Pagination et défilement infini écrits et compilés
  mais non observables avant 13 offres.
- Piège rencontré : le générateur de routes typées d'Expo Router, en mode
  veille pendant `expo start`, a pris les fichiers créés dans `src/lib`,
  `src/components` et `src/hooks` pour des écrans (`/../lib/labels`). Sans
  effet à l'exécution, `.expo/` est ignoré par git, et un redémarrage du
  serveur régénère le fichier proprement. Conséquence pratique : après
  l'ajout de fichiers hors `src/app`, `npx expo start` doit être relancé
  avant de faire confiance à `tsc`.
- Commande de lancement : `cd apps/mobile` puis `npx expo start` — `pnpm`
  n'est pas accessible depuis le terminal PowerShell de Pierre (voir
  `apps/mobile/README.md`).

**Connu et à traiter en premier (mobile, lot B)**

- **Upload de la photo de profil : échoue sur iPhone** avec le message
  `NETWORK_ERROR` de l'app (« Connexion impossible ») — `fetch` rejette, donc
  aucune réponse HTTP n'arrive. Tout le reste du profil fonctionne avec le
  même client, le même compte et le même réseau : le problème est propre à
  l'envoi multipart d'un fichier. **Ne pas deviner** : instrumenter d'abord
  (message natif de l'erreur, taille et URI du fichier choisi, essai avec
  une image minuscule), puis seulement corriger. Piste à vérifier en premier
  : taille du fichier (une photo iPhone recadrée à `quality: 0.8` peut
  dépasser ce que l'hébergement accepte avant de couper la connexion, ce qui
  produit exactement un échec réseau sans réponse). Correctif probable quelle
  que soit la cause : redimensionner à ~800 px avant l'envoi
  (`expo-image-manipulator`), une photo de profil n'a pas besoin de plus.

**Application mobile — phase 1, lots C et D (2026-09-15) : terminés et
validés sur iPhone — la phase 1 est complète**

- **Photo de profil corrigée** — cause prouvée dans le code source d'Expo :
  le SDK 54+ remplace le `fetch` de React Native par `expo/fetch`
  (`expo/src/winter/runtime.native.ts`), qui assemble lui-même le multipart et
  n'accepte qu'une chaîne, un `Blob`, ou un objet doté de `bytes()` — le `File`
  d'`expo-file-system`. Le format historique `{ uri, name, type }` échoue avec
  « Unsupported FormDataPart implementation ». Mesuré avant de corriger : le
  serveur acceptait 4 Mo sans broncher, la taille n'était pas en cause.
  `toFormDataPart()` (`lib/api/candidate-profile.ts`) sert désormais à tous
  les envois de fichiers (photo, CV déposé, CV joint, import). `ApiError`
  conserve l'erreur native dans `cause` : sans elle, un upload raté est
  indistinguable d'un Wi-Fi coupé.
- **Lot C1 — CV et candidature.** Profil : dépôt d'un PDF, génération du CV
  Jeuncy (ouvert aussitôt), historique. Détail d'offre : barre fixe
  « Postuler » / « Tu as déjà postulé · statut ». Écran de candidature :
  téléphone pré-rempli, CV = dernière version Jeuncy ou PDF joint, lettre
  facultative. Onglet Candidatures : statut coloré, appui long pour retirer.
  **Validé sur iPhone (6/6)**, y compris la cohérence avec le site : la
  candidature envoyée depuis l'app est complète sur jeuncy.com, CV et lettre
  compris.
- **Lot C2 — import de CV.** Le serveur lit le PDF, l'app impose une
  **relecture** (cases cochées par défaut, le candidat décoche) avant
  d'appliquer — leçon du 2026-09-04. Ce que le profil a déjà n'est pas
  proposé. **Validé sur iPhone** ; qualité de lecture jugée ~70 % par Pierre
  sur son propre CV, ce qui relève de `CvImportService` (partagé avec le
  site), pas de l'app.
- **Lot D — notifications, confidentialité, légal.** Onglet Notifications
  avec badge de non-lus et rafraîchissement 30 s ; les liens du site sont
  traduits en écrans de l'app (`hrefForNotification`). Écran « Confidentialité
  et données » : retrait de la CVthèque, export JSON via la feuille de partage
  iOS, suppression du compte (email en confirmation, exigence Apple 5.1.1(v)).
  **Textes légaux en feuille Safari intégrée, pas recopiés** — écart assumé
  par rapport à `MOBILE.md` §9.2 : la politique a changé le 2026-09-11, une
  copie serait périmée à la première évolution. À revoir si Apple l'exige.
  **Validé sur iPhone (2026-09-15)** : retrait de la CVthèque cohérent avec le
  site dans les deux sens, export JSON ouvert dans Fichiers, feuilles Safari
  intégrées avec retour à l'app, bouton de suppression grisé sans le bon
  email puis compte réellement supprimé. **Non observé** : la mécanique de
  l'onglet Notifications (badge, marquage lu, navigation) — un candidat
  fraîchement créé n'en a aucune, et c'est exact ; à vérifier avec l'outil
  `/deploy/{token}/match/{id}?profil=N&envoyer=1` (une notification de
  correspondance à un seul profil) ou naturellement en phase 2.
- **Retours à traiter côté serveur** (hors app, envoi FTP à grouper) : le CV
  généré a trop de blanc en haut de page (photo et nom à remonter,
  `resources/views/cv/template.blade.php`) ; la lecture des CV importés est
  imprécise (~30 % d'erreurs sur un CV réel, `CvImportService`).

**Connu et à traiter plus tard (mobile phase 1)**

- Pas de réglage de désactivation des notifications (prévu avec le push,
  phase 2, `MOBILE.md` §9.5).
- Le formulaire de candidature ne propose que la **dernière** version du CV
  Jeuncy, là où le site laisse choisir parmi toutes. Simplification mobile
  délibérée : regénérer prend deux secondes.
- Le CV déposé au profil (`cv_file_url`) n'est pas proposé comme CV de
  candidature — même limite que le site, l'API ne l'accepte pas directement.

**Jeuncy gratuit pour les entreprises, inscription CFA fermée (2026-09-15) :
terminé, à déployer**

- Décision de réunion (business plan revu) : remplir la plateforme en volume
  avant de monétiser. **L'espace entreprise devient entièrement gratuit** :
  publication illimitée, candidatures, CVthèque. La valeur se fait ailleurs —
  chaque jeune inscrit est un candidat pour l'école partenaire (IDA), rémunérée
  par l'OPCO à l'inscription d'un apprenti. D'où la seconde décision : **aucun
  CFA ne peut s'inscrire seul** tant que le rôle des écoles (clientes ou non)
  n'est pas tranché.
- Deux drapeaux dans `config/services.php` (`jeuncy.gratuit`, défaut `true` ;
  `jeuncy.inscription_cfa_ouverte`, défaut `false`), **rien n'a été
  supprimé** : Stripe, essai, abonnement et offre fondateur restent en place,
  désactivés. Les tests du modèle payant tournent toujours (phpunit.xml force
  `JEUNCY_GRATUIT=false`), le mode gratuit a les siens (`ModeGratuitTest`).
- Côté API : `POST job-offers/{id}/publish` →
  `JobOfferService::publishFreeForUser` (`payment_status FREE`, nouvelle valeur
  d'enum + migration, `expires_at null`, candidatures incluses, notification de
  correspondance) ; `SubscriptionService::hasPaidAccess` accorde tout aux
  COMPANY/CFA ; les deux checkouts répondent `PAYMENTS_DISABLED` ;
  `founder-offer.available` est faux ; `ArchiveExpiredTrialOffers` convertit
  les essais en cours en FREE au lieu de les retirer (sans quoi l'offre d'IDA
  disparaissait le 19 septembre).
- Inscription CFA : `AuthService::assertRoleOpenForRegistration` refuse le rôle
  par formulaire (`CFA_REGISTRATION_CLOSED`, 403) et par Google (retour vers
  `/register?role=CFA`, qui explique). Les comptes CFA existants se connectent
  normalement — la garde porte sur la création, jamais sur la connexion.
- **La porte évidente pour un CFA est de s'inscrire comme entreprise.**
  `TrainingOrganizationDetector` la ferme à la création et à la modification
  de la fiche entreprise, sur trois indices : code NAF de l'établissement (via
  `recherche-entreprises.api.gouv.fr`, public, sans clé, bloquant pour 85.31Z,
  85.32Z, 85.41Z, 85.42Z, 85.59A/B, 85.60Z — pas pour crèches, auto-écoles ou
  clubs sportifs), mots du nom (« CFA », « école », « campus », « formation »…
  mais pas « institut »), tournures de la description (« nos entreprises
  partenaires », « titre RNCP »…). Une panne du registre ne bloque jamais.
  Un vrai employeur refusé à tort est invité à écrire à l'adresse de contact.
- Côté web : page `/gratuit` (`FreePlatform.tsx`, `/tarifs` y mène encore),
  onglet « Gratuit » visible de tous, badge « 100 % gratuit » qui y renvoie,
  `/mes-offres` réduit à un bouton « Publier — gratuit », inscription sans
  choix CFA, accueil/À propos/Contact réécrits, politique de confidentialité
  mise à jour (la CVthèque n'est plus « réservée aux abonnés » : c'est un
  changement de qui accède aux données candidat, daté au 15 septembre).
  Mobile : choix CFA retiré de l'inscription.
- **À faire ensuite** : réécrire les cinq documents commerciaux
  (`docs/commercial/`), qui portent encore les tarifs ; décider du sort des
  offres périmées (une offre gratuite n'a plus d'échéance — prévoir un rappel
  « toujours d'actualité ? » après 60 ou 90 jours) ; l'import des offres de La
  bonne alternance (recherche faite le 2026-09-15, voir mémoire
  `api-la-bonne-alternance`) réutilisera le détecteur d'écoles.

**Import des offres de La bonne alternance (2026-09-15) : terminé, à
déployer et à mesurer**

- But : remplir Jeuncy d'offres d'alternance en volume **sans jamais y
  laisser entrer une école** (règle n° 1 de Pierre : Jeuncy travaille avec IDA).
  Source : export quotidien complet de l'API officielle (`GET /job/v1/export`,
  3h Paris, clé Bearer de production, usage non lucratif, licence Etalab 2.0 →
  source mentionnée sur chaque offre). Détails : mémoire `api-la-bonne-alternance`.
- **Table séparée** `external_job_offers` + `external_employer_blocks`. Public :
  `GET job-offers/external/search` et `/{id}`, servis **à part** pour que
  `/offres` affiche les offres Jeuncy d'abord puis une section « Offres
  partenaires » (pagination `pp`), et pour ne rien changer à l'app mobile.
  Fiche `/offres/partenaire/{id}` : candidature sur le site d'origine.
- `lba:import` (planifié après 4h Paris, période `jour-des-4h`) : téléchargement
  en flux, `JsonArrayStreamer` maison (rien à déployer dans `vendor/`), périmètre
  `LBA_DEPARTEMENTS` (Occitanie), puis **filtre en six couches**
  (`ExternalOfferFilter`) : liste blanche SIRET, blocage manuel, `is_delegated`,
  NAF enseignement, liste des ~1 800 CFA de LBA (`LbaCfaBlocklist`, MIT) ou nom
  d'école, tournures d'école dans la description. Exclues **conservées avec leur
  raison**. Idempotent (`import_batch`), suppression seulement après lecture
  complète. Rapport en cache exposé dans `/admin` (onglet « Offres
  partenaires » : audit, bouton « C'est une école ») et `/scheduler`.
- **`LBA_MESURE_SEULEMENT=true` au premier déploiement** : la nuit compte sans
  publier, on lit le rapport, puis `false`. `LBA_SIRET_WHITELIST` = SIRET d'IDA.
- Non fait à dessein : pas de notification de correspondance sur ces offres,
  pas de candidature via l'API LBA, pas d'affichage mobile. À mesurer sur le
  vrai export : taille du fichier et durée de la passe sur OVH.

**Import LBA en production, filtre affiné, compteur (2026-09-16 → 17) :
terminé**

- **En ligne depuis le 2026-09-16** : ~682 offres partenaires d'Occitanie
  visibles sur `/offres` (810 dans le périmètre, ~128 exclues), import
  automatique chaque nuit vers 6h41 Paris, vérifié le lendemain (727 → 682
  après affinage, nouvelles offres de la veille présentes). L'export réel :
  **tableau à la racine** (pas `{jobs: [...]}`), ~550 Mo, 307 000 lignes dont
  294 000 « recruteurs » sans offre — seulement ~12 500 vraies annonces pour
  toute la France. Lecture en 18 s sur OVH.
- Outils `/deploy/{token}` ajoutés : `lba-import?maintenant=1` (import au
  prochain passage du cron), `?executer=1` (import immédiat dans la requête,
  ~35 s), `?apercu=1` (premiers octets de l'export), `?annuler=1` ;
  `env-check` affiche le bloc `_lba` et `_jeuncy`. Compteur public
  `GET job-offers/count` (cache 10 min, invalidé à chaque import), affiché
  sur l'accueil et `/offres`.
- **Filtre affiné le 2026-09-17** après relecture des 727 offres en ligne
  par un workflow de 16 agents (classement par lots, double contre-expertise
  à charge de réfuter, mesure de chaque règle sur tout le corpus) : 24
  formations déguisées confirmées (3,3 %). Sept signées par une école (IFRIA
  sous le nom « Association régionale des entreprises alimentaires », PRH 360,
  H et C Conseil, Grand Sud Formation) → 18 tournures à zéro faux positif +
  noms locaux + organismes reconnus dans le texte. Dix-sept anonymes de
  l'**ISCOD** via France Travail (titre `Alternance <poste> - <ville> (F/H)`,
  employeur vide, pas un mot d'école) → tout le gabarit exclu (38 offres),
  décision de Pierre. **Mesurées et rejetées** : « rncp » (17 vraies offres,
  dont la SNCF), « titre professionnel » (53), « centre de formation » (56),
  « école » (31), « entreprise d'accueil » (15), « organisme de formation »
  (6) — c'est le vocabulaire des GEIQ, groupements d'employeurs et agences
  d'intérim, premiers recruteurs d'apprentis. Toute règle future doit être
  re-mesurée contre eux.
- Admin : « Retirer cette offre » / « Rétablir » (colonne
  `excluded_by_admin_at`, réappliquée après chaque passe), en plus de « C'est
  une école ». `.env` prod : `LBA_SIRET_WHITELIST` porte encore le placeholder
  `SIRET_IDA` — à remplacer par le vrai SIRET quand Pierre l'a.
- Limite connue et assumée : une école qui se présente comme une entreprise
  sans un mot d'école est indétectable au texte ; le zéro se garantit par
  filtre + œil de l'admin + chaque cas signalé transformé en règle.

**Import LBA : France entière, CFA d'entreprise exclus, slogan (2026-09-18)**

- Relecture des 32 offres arrivées dans la nuit : une école passait (« L'école
  NextStepAcademy recrute pour l'un de ses partenaires ») — la règle « école
  recrute » exigeait les deux mots collés. Élargie à trois mots d'écart,
  organisme ajouté aux noms reconnus. Mesurée sur 759 offres réelles : une
  seule de plus attrapée.
- **Décision de Pierre** : un employeur qui forme lui-même dans **son propre
  CFA** (« avec son CFA d'entreprise 100 % en ligne », La Poste et
  Formaposte, « notre centre de formation ») proposera ce CFA au candidat →
  l'offre sort même si le poste est réel. Règles `cfa d entreprise` et
  `(son|notre|nos|leur|leurs) (propre) (cfa|centre de formation)` ; « votre
  CFA » reste autorisé (l'employeur parle de l'école du candidat). Sur le
  corpus : 19 offres (4 boulangeries, 5 La Poste, 9 Vitalliance, 1 Armand
  Thiery). Le test « La Poste passe » du 17 est inversé pour cette raison.
- **France entière** (décision du patron) : `LBA_DEPARTEMENTS=*` ou vide =
  tous les départements (une liste restreint). Sans ce sens explicite, une
  liste vide aurait vidé le site en une nuit — test ajouté. Attendu ~12 500
  offres au lieu de ~700 ; la section « Offres partenaires » est triée par
  date, donc un candidat voit d'abord les offres de toute la France — prévoir
  un filtre département / tri par distance si ça gêne.
- Slogan : « Match ton alternance » (troisième en trois jours ; les sept
  emplacements sont listés par `grep -rn "Match ton alternance"`).

**Relecture France entière par agents, filtre v3 (2026-09-18 → 21) : terminé**

- Trois workflows (≈ 210 agents) : 4 468 employeurs nommés jugés un par un
  (deux contre-experts par signalement), 1 239 offres anonymes lues en
  entier, 491 exclusions textuelles relues à charge de trouver les vrais
  employeurs retirés à tort, échantillon de 120 du gabarit ISCOD. Corpus
  local reconstruit depuis l'export (`scratchpad/lba-france/`, script
  `dump-france.php`), chaque règle mesurée avant adoption
  (`mesure-france.php`, `delta-france.php`, `retour-france.php`).
- **Résultat net sur les 9 698 offres en ligne : 1 431 retirées, 75
  rétablies.** Écoles qui postaient comme employeurs : Actual Talent (180,
  organisme de formation du groupe Actual : « Une formation en alternance qui
  recrute ! »), AGEPAC (115), Disciplina (92, « Centre de Formation
  d'Apprentis » en toutes lettres), Koann, Runapp, Arefip, EF-OI (Réunion),
  Skale, My-BS, ESUP, SEPR, Evolu'Santé, Healthcademia, Altern'Emploi (26
  anonymes), IFP Atlantique, Acadénice, One Education, HBC School… Gabarits
  anonymes : « une entreprise partenaire du secteur X recherche… »,
  « Rythme : 4 jours entreprise / 1 jour école », « Préparez en seulement
  1 an un Titre Professionnel », titre = catalogue de trois diplômes
  (sociétés-écrans Sentinelle14, Screenova, Vinsales, Adslink).
- **Décisions de Pierre** : Chambres de métiers (215, relaient des artisans
  mais forment dans leurs CFA) → retirées ; IFAC (Brest, ~150 annonces
  d'artisans rédigées par le CFA) → retirées par cohérence ; Burger King :
  seules les 11 « propositions de formation » (« vous former directement au
  sein d'un restaurant ») sortent, les 99 autres restent ; La Poste →
  Formaposte (propre CFA), y compris les 31 « Facteur » anonymes ; Carrefour
  CQP « les formations se passent au sein de votre magasin » (88) ;
  France Travail comme employeur (40, campus interne) ; Dalkia, Lauak, Loxam,
  Chopard, Hermès, Korian, LIP, Vitalliance : formation maison.
- **Faux positifs corrigés** (22 % des exclusions textuelles étaient de vrais
  employeurs) : les tournures faibles (« nos entreprises partenaires »,
  « aucun frais de formation », « équipe pédagogique »…) sont neutralisées
  quand le texte se présente comme GEIQ / groupement d'employeurs / intérim /
  ESN (`INTERMEDIARY_CONTEXT`), « équipe pédagogique » l'est en crèche ;
  « notre école / CFA / centre de formation **partenaire** » n'est plus
  « notre école » ; « annonce ouverte par un CFA » ne vaut que sans employeur
  nommé ; retirées : « frais de scolarité », « rentrée en formation »,
  « poursuivre votre cursus », « lieu = l'école », « clients partenaires »
  (ESN), « vous serez formé au CFA de… ». Bug corrigé : deux tournures avec
  apostrophe ne pouvaient jamais matcher (le texte normalisé n'en a pas).
- Gabarit ISCOD (582 offres France) : l'échantillon montre qu'environ la
  moitié décrivent un vrai poste, mais la candidature part vers
  l'intermédiaire anonyme qui inscrit le jeune chez lui — exclusion
  maintenue. Seul critère sûr pour en récupérer une partie : une marque
  nommée dans le texte (~28 %), à décider si le volume manque.
- Douteux non tranchés, à l'œil de l'admin : Koann (Réunion, 29 — retiré
  par nom), Hermès Sellier (30, descriptions vides), NOVI/Beauty Success
  (« notre partenaire IBCBS », gardés), UIMM (39, job board d'employeurs
  réels, gardé), Conservatoire de Lyon (gardé).

**Application mobile — pivot vers le modèle match, lot 0 (2026-09-17 → 22) :
terminé, sonde à déployer**

- Le patron veut une app « façon swipe » : pile de cartes, match à deux oui,
  géolocalisation avec rayon. La phase 1 classique (lots A–E, validée sur
  iPhone) est **mise en pause**, pas jetée : 72 % du code mobile est gardé
  tel quel, 25 % adapté, 3 % jeté. Enquête du 17 (Tinder, précédents Switch /
  Kudoz / Jobamax / hokify, audit du code, conformité, trois conceptions
  contre-expertisées) : aucun « swipe de l'emploi » n'a survécu au swipe seul,
  ils sont morts d'employeurs muets et de piles vides ; le produit qui peut
  tenir est « réponse garantie », et commence par le côté entreprise.
- **Le cadrage v2 est `MOBILE.md`, réécrit en entier** (les renvois des
  sections précédentes à `MOBILE.md` §9.2/§9.3/§9.5 visent la v1, lisible
  par `git show 7dcb951:MOBILE.md`). Onze décisions de Pierre datées du 22 :
  geste droit = feuille à deux boutons (dossier maintenant / intérêt seul),
  **aucune distance ni tri par proximité côté employeur** (lieu de résidence
  = critère discriminatoire, L1132-1 ; zone de mobilité déclarée + permis +
  véhicule + rayon de recrutement sur l'offre à la place), **16 ans sur
  l'app** (le site reste à 15 ; garde serveur sur Découvrir / intérêts /
  matchs), CVthèque web alignée sur « rien avant candidature », STAFF =
  Pierre + Claude, périmètre 66, pas de messagerie en V1, portrait candidat
  opt-in, vocabulaire « Découvrir / Match / Ça m'intéresse / Passer ».
- **Sonde `status?geo=1`** (`DeployController::geoStats`, `deploy-tools-28`,
  seul fichier à envoyer : `cd3e336e2182ad18`, 59 713 octets) : répartition
  des candidats par département (cases < 3 regroupées sous `autres`, secret
  statistique), offres Jeuncy par département, offres partenaires dans le 66
  et à 10/30/50/100 km de Perpignan (haversine SQL, vérifiée à la main sur
  Perpignan→Narbonne = 55,78 km), capacités serveur (version MySQL,
  `ST_Distance_Sphere`, GD/Imagick, limites PHP, présence des clés). Aucune
  donnée personnelle, aucune écriture. 14 tests (`DeployGeoStatsTest`,
  543/543 au total) ; sous SQLite les fonctions trigonométriques sont
  injectées dans le PDO pour exécuter réellement la requête.
- **Prototype du deck candidat en Expo Go, sans backend** (branche
  `feature/mobile-match`) : onglet « Découvrir » à la place de la recherche
  (déplacée dans `offres/recherche.tsx` derrière une loupe), pile de 3 cartes
  — offres Jeuncy publiées puis offres partenaires du département choisi
  (`job-offers/external/search?department=`) —, gestes droite/gauche avec
  tampons, seuil de distance et de vitesse, boutons redondants, annulation du
  dernier geste, feuille à deux boutons (offre Jeuncy) ou « Je garde » /
  « Ouvrir le site » (partenaire), fiche partenaire, section « Gardées » dans
  Candidatures, feuille « Où ? ». Gestes stockés **localement** dans
  `swipe-store.ts` (AsyncStorage) en attendant `offer_interests`, et l'écran
  le dit tel quel. Deck maison sur reanimated 4.5.1 + gesture-handler 2.32 +
  worklets 0.10.1 déjà présents ; **`scheduleOnRN` de `react-native-worklets`**
  (`runOnJS` est déprécié dans reanimated 4) ; `GestureHandlerRootView` ajouté
  à la racine ; transformation par le compilateur React vérifiée à blanc.
- Pièges attrapés en vérification adverse : `onEnd` de gesture-handler est
  aussi appelé quand le système annule le geste (appel entrant) avec la
  dernière translation → tester `success` avant de décider ;
  `expo-web-browser` présente Safari depuis le contrôleur le plus haut, donc
  fermer la feuille modale avant d'ouvrir le site ne montre jamais Safari ;
  échec de lecture d'AsyncStorage → `onRehydrateStorage` doit lever
  `hydrated` dans tous les cas, sinon la pile reste sur « On prépare… » ;
  `fetchNextPage` en boucle après une erreur réseau si l'effet ne teste pas
  `isError`.
- Lots suivants (`MOBILE.md` §10) : 1 socle backend (tables, géocodage,
  vérification SIRET, exposition CVthèque, garde 16 ans), 2 deck entreprise +
  intérêts + match + dossier, 3 deck candidat complet + rayon + GPS, 4
  modération/relances/admin, 5 cohérence web + légal, 6 pilote 66 + stores.
  Compte Apple Developer à vérifier (Apple ID ≠ Developer Program).

**Modèle match — lot 1, socle backend (2026-09-22) : terminé et déployé**

- **807 tests verts** (543 avant le lot), Pint propre, web build + lint OK.
  Contrat technique dans `docs/mobile/lot-1-backend.md`, manifeste
  d'envoi dans `docs/mobile/lot-1-deploiement.md` (**114 fichiers**, 74
  nouveaux, empreintes sha256(16) et tailles, procédure WinSCP par
  synchronisation dossier par dossier, `deploy-tools-29`).
- Tables nouvelles : `offer_interests` (une ligne par couple candidat/offre,
  `candidate_decision`/`employer_decision`, `matched_at`, `application_id`,
  `closed_at`/`closed_reason`), `external_interests` (offres partenaires
  gardées, colonnes dénormalisées car `LbaImportService` supprime chaque nuit
  les offres absentes de l'export), `organization_photos` non, `reports`,
  `user_blocks`, `geocode_cache`. Colonnes ajoutées : préférences et mobilité
  du candidat, permis structuré, `show_photo_to_employers` ; `postal_code`,
  coordonnées, `recruitment_radius_km`, `sector`, `minimum_age` sur les
  offres ; `verification_status` sur les organisations ; `interest_id`,
  `source`, `responded_at` sur les candidatures ; `age_confirmed_at`.
- **Deux paires de coordonnées** sur `candidate_profiles` : `latitude/longitude`
  (commune géocodée) et `device_*` (GPS). Le deck employeur ne lit
  structurellement jamais `device_*` — la décision « aucune distance côté
  employeur » est garantie par le schéma, pas par une règle qu'on peut oublier.
- Routes : `discover/offers`, `discover/candidates`, `interests` (+`batch`,
  `last`), `matches`, `external-interests`, `blocks`, `reports`,
  `candidate-profile/preferences`+`location`, `job-offers/express`.
  124 routes API au total, 0 doublon. Garde `match.age` (16 ans) sur tout le
  parcours match ; `JEUNCY_MATCH_DEPARTEMENTS=66` ne restreint **que** le côté
  employeur.
- **Règle d'exposition unique** (`CandidateCardPresenter`) appliquée au deck,
  aux matchs **et à la CVthèque du site** : prénom + initiale, tranche d'âge,
  jamais ville/email/téléphone/adresse/coordonnées/CV. Le filtre « ville » de
  la CVthèque est supprimé (filtrer sans afficher révèle par inférence) et la
  recherche par nom est réservée à ADMIN/STAFF. Le PDF n'est servi qu'après
  une candidature sur une offre de cet employeur (`CV_NOT_SHARED`).
- Vérification employeur : SIRET (Luhn) + registre public ; **jamais VERIFIED
  par défaut** (registre muet = PENDING). Sans VERIFIED : ni deck candidats,
  ni intérêt, ni CVthèque, ni candidatures reçues.
- **Défaut trouvé en testant la sonde elle-même** : le gestionnaire d'auth est
  un singleton qui mémorise l'utilisateur entre sous-requêtes du selftest —
  sans `Auth::forgetGuards()`, l'employeur était vu comme le candidat
  précédent et le parcours « marchait » avec 0 match. Le selftest doit être
  testé, sinon il ment.
- `matchDebug` rebranché sur `MatchScorer` (les méthodes extraites de
  `JobOfferMatchService` auraient levé `ReflectionException` en production).
  Nouvelle route `/deploy/{token}/geocode-backfill` (compte par défaut,
  `?executer=1`, idempotente, relançable).
- Risque résiduel assumé : un SIRET public actif suffit à devenir VERIFIED,
  donc à voir des cartes de mineurs — email de domaine ou validation humaine à
  prévoir avant d'ouvrir au-delà d'IDA. Action humaine après déploiement :
  saisir le code postal de l'offre d'IDA (sinon `JOB_OFFER_NOT_LOCATED`).

**Lot 1 : mise en production (2026-09-22) — deux pièges qui ont coûté une panne**

- **L'API est tombée entièrement (500 sur toutes les routes)** après une
  synchronisation WinSCP lancée sur `apps/api` en entier. Cause réelle :
  le dossier **`bootstrap/cache/`**, gitignoré et propre au poste de dev, a
  été envoyé. Son `packages.php` liste les extensions découvertes **avec les
  paquets de `require-dev`** (`laravel/pail`, `laravel/pao`,
  `nunomaduro/collision`), absents d'un `composer install --no-dev`. Laravel
  tentait donc d'enregistrer `Laravel\Pail\PailServiceProvider` à chaque
  requête. Réparation : **supprimer `bootstrap/cache/packages.php` et
  `services.php` sur le serveur**, Laravel les régénère seul.
- Ce qui a permis de trancher sans deviner : `/api/...` renvoyait quand même
  l'enveloppe JSON `{success:false}` de `bootstrap/app.php`. Une app qui rend
  **sa propre** page d'erreur a démarré : ni `vendor` ni l'autoloader ne
  peuvent être en cause. Un autoloader cassé donne une erreur PHP brute.
- **Ne jamais synchroniser `apps/api` à la racine.** Masque d'exclusion à
  régler une fois pour toutes dans WinSCP :
  `vendor/; bootstrap/cache/; storage/; .env; tests/; .phpunit.result.cache`
- **`create_external_interests_table` échouait en production, pas en test** :
  MySQL limite les identifiants à 64 caractères, Laravel générait
  `external_interests_candidate_profile_id_external_job_offer_id_unique`
  (68). **SQLite n'a pas cette limite** — 807 tests verts, production en
  échec. Nom d'index explicite désormais
  (`external_interests_profile_offer_unique`). Leçon générale : toute
  migration créant un index composite sur des colonnes à nom long doit être
  jouée contre un vrai MySQL avant l'envoi ; les tests SQLite ne le voient pas.
- MySQL ne défait pas le DDL déjà exécuté : la table avait été créée avant
  l'échec de l'index, sans ligne dans `migrations`. `up()` commence donc par
  un `Schema::dropIfExists` commenté, pour se rattraper sans accès SQL direct
  au serveur (il n'y en a pas sur l'hébergement mutualisé).
- **État vérifié après déploiement** : 12/12 migrations appliquées, 0 en
  attente ; selftest **16/16 vert** ; parcours match complet exercé en
  production (deck candidat 1 offre Jeuncy + 20 partenaires à 30 km dans le
  66, deck employeur sans aucune clé interdite, double intérêt → match,
  matchs des deux côtés, 3 notifications, enum MySQL acceptée) ; site
  reconstruit et servi (empreinte du bundle identique au build local).
- **La CVthèque du site est tombée entre-temps** : l'API du lot 1 avait été
  déployée sans reconstruire `apps/web`. Prouvé en téléchargeant le bundle en
  ligne et en y comptant les clés de la nouvelle API (`last_name_initial`,
  `age_band`, `has_vehicle` : zéro occurrence). **Le lot 1 change la forme de
  la CVthèque : API et site doivent partir ensemble.** Ordre d'envoi des
  fichiers du site : les `assets/` d'abord, `index.html` en dernier.
- Restes connus : le code postal de l'offre d'IDA n'est toujours pas saisi
  (elle reste hors du deck, `offres_publiees_sans_coordonnees: 1`) ; 2 profils
  sur 115 ont un code postal que le géocodeur ne résout pas et restent sans
  coordonnées (passe idempotente, relançable).

**Modèle match — lot 2, deck entreprise, intérêts, match et dossier
(2026-09-23) : terminé, pas encore déployé**

- **Aucun fichier backend touché** : tout le lot 2 consomme le socle du lot 1
  tel quel. Seuls `apps/mobile` et `apps/web` changent, donc seul le site est
  à renvoyer (les `assets/` d'abord, `index.html` en dernier) ; l'application
  se recharge toute seule dans Expo Go.
- **Mobile, côté entreprise et CFA** : onglet « Découvrir » avec sélecteur
  d'offre et pile de cartes candidats (`SwipeDeck` réutilisé tel quel du
  prototype du 22), feuille « C'est un match ! », onglet « Matchs » des deux
  côtés, détail d'un match, « Candidatures reçues » offre par offre avec
  changement de statut, offre express, porte de vérification. Barre d'onglets
  ramenée à cinq entrées de chaque côté (Découvrir et Matchs communs).
- **Mobile, côté candidat** : écran « Ce que je cherche » (contrats, secteurs,
  les deux rayons, permis, disponibilité, phrase, autorisation de la photo).
  Tout y est facultatif, et c'est structurel : `DiscoverService::filtreContrat`
  traite une liste vide comme « éligible à tout », ce qui est l'état des 115
  profils existants. L'exiger aurait vidé le deck employeur le jour de son
  ouverture.
- **Site** : pages `/interesses` (candidat seulement — l'intérêt à sens unique
  n'est jamais exposé à l'employeur, il n'y a donc pas de page miroir) et
  `/mes-matchs` (les deux rôles, une seule route, le serveur choisit la forme
  de la réponse) ; offre express et éditeur de missions sur « Mes offres » ;
  champs de mise en relation dans le formulaire d'offre.

**Trois défauts que seule l'exécution a montrés (lot 2)**

- **Le bouton « Modifier » manquait sur toute offre en ligne.** Il n'était
  rendu que pour les brouillons, alors que le serveur accepte depuis le lot 1
  la modification d'une offre publiée gratuitement
  (`JobOfferService::requireOwnedEditableOffer`) et re-géocode quand le code
  postal change. **C'est la vraie raison pour laquelle le code postal d'IDA
  restait inaccessible** : ajouter le champ au formulaire ne suffisait pas,
  le formulaire lui-même était hors d'atteinte. Leçon : quand une garde
  serveur et une condition d'affichage disent la même chose, écrire la
  seconde comme le miroir explicite de la première, avec le nom de la méthode
  en commentaire.
- Une entreprise non vérifiée attendait **neuf secondes** devant
  « Chargement des matchs… » : TanStack Query rejouait trois fois un 403
  définitif. `retry: false` sur ces routes, et le 403 s'affiche comme la
  CVthèque le fait — un encadré avec une action, pas une ligne d'erreur.
- Le oui qui crée un match met une dizaine de secondes (le serveur envoie
  deux notifications et deux emails **dans** la requête, faute de file
  d'attente — décision assumée du lot 1). Les boutons portent donc un libellé
  d'attente, pas seulement un état grisé.

**Vérifié au navigateur** contre l'API locale et la base de dev (compte
`rh@nexatech.example.com` et `lea.girard@example.com`, mot de passe de démo) :
intérêt employeur posé à la main → page « Ils s'intéressent à toi » remplie →
« Ça m'intéresse » → 201 → bannière de match → le match apparaît dans « Mes
matchs » avec le bon statut et la bonne action ; offre express créée, publiée
et **géocodée** (66000 → 42.70, 2.90), puis modifiée alors qu'elle était en
ligne avec deux missions enregistrées. Données d'essai retirées de la base de
dev ensuite, sauf un match de démo Léa Girard ↔ Café des Lices laissé
volontairement pour voir les pages remplies. Production non touchée.

**Connu et à traiter plus tard (lot 2)**

- Rien n'est déployé : le site est à reconstruire et à envoyer quand Pierre le
  décidera.
- **Aucune entreprise VERIFIED en production**, donc aucune pile de candidats
  ne peut encore se charger. Le chemin est connu et ne demande pas de code :
  se connecter avec le compte CFA d'IDA, saisir son vrai SIRET sur
  `/organization`, la vérification part toute seule contre le registre public.
  Le refus « NAF enseignement » ne s'applique **qu'aux entreprises**, jamais à
  un CFA (`CompanyVerificationService`, `$refuseTrainingNaf`) — IDA peut donc
  passer. Le même SIRET manque dans `LBA_SIRET_WHITELIST` du `.env` de prod.
- Le deck candidat reste le prototype local du lot 0 (gestes dans
  AsyncStorage) : son branchement sur `discover/offers` est le lot 3.
- L'app mobile n'a pas d'écran de CVthèque (onglet masqué) ; prévu au lot 5.

**Profil candidat : la saisie ne se perd plus (2026-09-23) : corrige et en ligne**

- Signale par Pierre : un candidat remplit son profil, ouvre une autre page,
  la ferme, revient — tout etait vide. La page se remplit en plusieurs fois
  (14 champs d'identite, plus experiences, formations, langues, competences,
  logiciels) et tout vivait dans l'etat React, perdu au demontage.
- `apps/web/src/lib/profile-draft.ts` : brouillon local, ecrit a chaque frappe
  sans temporisation (le clic qui fait perdre la saisie arrive souvent juste
  apres la derniere lettre). Couvre aussi les sections saisies avant la
  creation du profil (`useStagedProfileSections`, qui prend desormais
  l'identifiant du compte). Bandeau de restauration avec un bouton pour
  repartir des donnees enregistrees.
- **Premier correctif insuffisant, a retenir** : `sessionStorage` meurt avec
  l'onglet. Il couvrait la navigation — et mes quatre tests avec — mais pas le
  geste reellement decrit (« ouvrir une autre page **puis la fermer** »).
  Retour de Pierre : « ca marche toujours pas, donc t'as rien change ». Un
  aller-retour FTP perdu. Passage a `localStorage`, avec contre-epreuve : le
  nouveau test echoue bien avec l'implementation precedente.
- Garde-fous, parce que le brouillon contient nom, date de naissance,
  telephone et adresse et que le public navigue depuis des postes partages :
  cle portant l'identifiant du compte, effacement a l'enregistrement, a
  l'import de CV et a la deconnexion (dans `clearSession`, un seul point pour
  cinq appelants), peremption a sept jours verifiee a la lecture. Le cookie de
  session vit deja sept jours : le brouillon n'ouvre pas une porte fermee.
- 9 tests dedies (29 au total cote web) : la page est reellement demontee puis
  remontee, l'onglet ferme simule en vidant `sessionStorage`, le brouillon
  perime jete, et deux comptes qui se succedent ne se voient jamais.
- **Piege de deploiement rencontre** : une seconde session Claude travaillait
  sur `apps/web` au meme moment (lot 2 web). Un build fait depuis le depot
  embarque forcement son travail en cours, commite ou non. Construire alors
  depuis un worktree git isolé sur son propre commit, ou attendre — et
  toujours donner a Pierre le **nom exact** du fichier attendu dans
  `index.html`, c'est ce qui a permis de voir qu'il avait envoye un autre
  build que le mien.

**Modèle match — lot 3, deck candidat complet, rayon et GPS (2026-09-23) :
terminé, pas encore déployé**

- La pile « Découvrir » du candidat quitte le prototype : elle lit
  `discover/offers`, les gestes partent au serveur, et un « Ça m'intéresse »
  peut créer un match. `src/store/swipe-store.ts` (AsyncStorage) et la feuille
  « Où ? » par numéro de département sont **supprimés** — le serveur sait où
  habite le candidat, le département n'est plus une saisie mais une
  conséquence.
- **Deux piles, deux régimes de pagination.** Les offres Jeuncy sont une
  « Sélection du jour » finie qui arrive **en entier dès la première page** ;
  les partenaires se paginent par vingt. Règle écrite en tête du hook : ne
  lire `jeuncy` que sur la première page, sinon les mêmes vingt offres
  reviennent à chaque page suivante.
- **Trois gestes, trois traitements.** « Ça m'intéresse » part seul (il peut
  déclencher notification et email chez l'employeur) ; « Passer » sur une
  offre Jeuncy s'accumule et part par lots ; les gestes sur une offre
  partenaire partent un par un, faute de route de lot. L'annulation vise la
  bonne pile — le serveur garde **un cran par pile** — et vide le tampon
  d'abord, sinon « le dernier geste » désignerait une autre carte.
- **Rayon et GPS** : feuille de réglages (paliers 5-100 km, « Autour de
  moi »). L'élargissement est toujours annoncé, en tête de pile et dans la
  feuille. Position demandée en précision basse, arrondie à ~1 km **côté app
  et côté serveur**, effaçable depuis « Confidentialité ». Elle ne sert qu'à
  la pile du candidat : le deck employeur lit d'autres colonnes, ce qui rend
  le texte de consentement vérifiable plutôt que promis.
- **Offres gardées** : la section de l'onglet Candidatures lit
  `GET external-interests`. L'écran dit que ce n'est pas une candidature et
  que « J'ai postulé » est une note que le candidat se laisse à lui-même —
  Jeuncy ne peut pas vérifier ce qui se passe sur le site de l'employeur.
- Nouvelle dépendance : **`expo-location` ~57.0.19** (fonctionne dans Expo
  Go), chaînes de permission dans `app.json`. Installée avec `npx expo
install` ; Pierre n'a pas besoin de lancer pnpm, `node_modules` est déjà à
  jour sur son poste.

**Drapeau d'ouverture (`JEUNCY_MATCH_ACTIF`) — deux fichiers backend**

- `services.jeuncy.match_actif`, **défaut vrai**. C'est un **frein
  d'urgence, pas un interrupteur de lancement** : le plan (§10) prévoyait
  d'ouvrir la pile candidat à dix offres Jeuncy dans les 30 km, mais la
  production n'en a qu'une, et fermer sur ce critère masquerait aussi les
  7 779 offres partenaires — qui sont précisément le remplissage prévu en
  attendant.
- Le refus (`MATCH_NOT_OPEN_YET`, 403) est posé **avant** la garde de profil :
  une pile fermée ne doit pas reprocher au candidat un profil incomplet pour
  un écran qui ne s'ouvrirait pas de toute façon.
- Deux fichiers modifiés, **tous deux déjà surveillés** par
  `DeployController::version()` :
  `app/Services/DiscoverService.php` (`90eaa24bd521d634`, 26 238 o) et
  `config/services.php` (`693037a439ecb8b0`, 9 887 o).

**Connu et à traiter plus tard (lot 3)**

- Rien n'est déployé. Le prochain envoi porte **l'API et le site** (les deux
  fichiers ci-dessus, plus `apps/web/dist`) : la leçon du 2026-09-22 vaut
  toujours, ils partent ensemble.
- **Non vérifié sur iPhone** : rien du lot 3 n'a été exercé sur un vrai
  téléphone, et la partie GPS ne peut pas l'être autrement (pas de
  localisation dans un bundle exporté). À faire en premier au prochain essai
  Expo Go.
- La base de dev n'a **aucune offre partenaire** (`partenaires: 0`), donc la
  pile partenaire, sa pagination et « Je garde » n'ont pas pu être exercées
  localement. En production il y en a 7 709.
- Le retrait d'une offre gardée n'existe pas : le serveur ne connaît que
  l'annulation du **dernier** geste, et l'offrir sur n'importe quelle ligne
  annulerait en réalité autre chose. Il faut une route dédiée.
