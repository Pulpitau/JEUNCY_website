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
Signature : « Ton alternance commence ici. »

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
  sessions. Si ces besoins deviennent prioritaires, migration possible vers un
  **self-hosting Jitsi via Docker** (même API front, juste changer le domaine du serveur),
  à évaluer en V2 si le besoin se confirme.

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

**Connu et à traiter plus tard**

- Déploiement réel sur l'hébergement OVH mutualisée PRO pas encore fait/documenté (accès
  FTP disponibles, mais process de déploiement — build du frontend, upload PHP, config
  `.env` prod, cron si besoin d'une queue — à définir en phase de mise en prod)
- Le PDF final du CV (génération côté serveur) devra utiliser une lib PHP
  (`barryvdh/laravel-dompdf` ou `spatie/browsershot` pressenties) — `@react-pdf/renderer`
  ne fonctionne qu'en JS, reste utilisable uniquement côté client pour la prévisualisation
- Logos `apps/web/public/logo/logo-light.png` et `logo-dark.png` sont les versions
  circulaires (badge) redimensionnées à 128×128 ; la version pleine avec tagline
  (`logo_jeuncy.png` à la racine, hors repo web) n'a pas encore d'usage assigné

**Phase 2 — profil + CV : à faire**
