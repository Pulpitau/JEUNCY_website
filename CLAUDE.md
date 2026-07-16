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

- NestJS (Node.js + TypeScript), architecture modulaire (un module par domaine métier)
- Prisma ORM + MySQL 8
- Passport.js (stratégies local + JWT + Google OAuth) intégré à NestJS
- Guards NestJS pour la vérification de rôle (`RolesGuard`)
- Zod (ou class-validator, natif à NestJS) pour la validation des DTO
- `@react-pdf/renderer` côté serveur pour générer le PDF final du CV
- Stripe SDK (Checkout + webhooks) pour les offres payantes
- Resend (ou Brevo) pour les emails transactionnels
- Génération de noms de salle Jitsi uniques et non devinables (UUID) côté API

### Repo

- Monorepo avec pnpm workspaces (`apps/web`, `apps/api`, `packages/shared` pour les types
  partagés front/back — DTO, enums de statut, etc.)
- Turborepo pour orchestrer les scripts (dev, build, lint) sur les deux apps

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
  /api                      # NestJS
    /src
      /modules
        /auth
        /users
        /candidate-profiles
        /companies
        /job-offers
        /applications
        /payments
        /cv-generator
        /video-rooms        # module visioconférence (Jitsi Meet)
        /notifications
      /prisma
      /common               # guards, decorators, filters
/packages
  /shared                   # types/DTO/enums partagés front-back
/prisma
  schema.prisma
  seed.ts
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
pnpm install                      # installer les deps du monorepo
pnpm dev                          # lance web + api en parallèle (turbo)
pnpm --filter api prisma migrate dev
pnpm --filter api prisma studio
pnpm --filter api prisma db seed
pnpm lint                         # ESLint sur tout le monorepo
pnpm test                         # tests web + api
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

- Monorepo pnpm workspaces + Turborepo (`apps/web`, `apps/api`, `packages/shared`), lié
  à `origin/main` (github.com/Pulpitau/JEUNCY_website)
- `apps/web` : Vite + React 18 + TS, Tailwind, React Router, TanStack Query, Zustand,
  RHF + Zod, bases shadcn/ui
- `apps/api` : NestJS + TS, `ConfigModule`, `ValidationPipe` global, CORS ; Prisma
  configuré (schema à la racine `/prisma`, client généré dans `apps/api/generated`,
  aucun modèle métier encore — voir phase 2)
- `packages/shared` : enums de statut (`UserRole`, `JobOfferStatus`, `ApplicationStatus`,
  `VideoRoomStatus`) et type `ApiResponse`
- ESLint (flat config) + Prettier + husky/lint-staged fonctionnels sur tout le monorepo
- Design system : tokens Tailwind Jeuncy (couleurs, polices, dégradé signature),
  `ThemeProvider` dark/light (Zustand + `data-theme`, persistant, anti-FOUC), composants
  de base (`Button`, `Card`, `Input`, `Badge`, `Navbar`, `Footer`), page d'accueil de
  démonstration vérifiée en navigateur (build/lint/test OK)
- Workflow : une branche par étape, mergée dans `main` (voir `CONVENTIONS.md` §8)

**Modèle de données : terminé** (couvre les entités des phases 2 à 5, pas encore la
logique métier — controllers/services/DTO à écrire phase par phase)

- `prisma/schema.prisma` complet : `User`, `CandidateProfile`, `Experience`, `Education`,
  `Skill`/`CandidateSkill`, `GeneratedCV`, `Company`, `CfaOrganization`, `JobOffer`,
  `Application`, `Payment`, `Notification`, `VideoRoom`, avec enums (`UserRole`,
  `JobOfferStatus`, `ContractType`, `ApplicationStatus`, `PaymentStatus`,
  `NotificationType`, `VideoRoomStatus`) — snake_case en base via `@map`/`@@map`,
  cascades justifiées en commentaire
- Migration initiale générée (`prisma/migrations/20260716000000_init`) via
  `prisma migrate diff --from-empty` : **non testée contre un vrai moteur MySQL**
  (ni Docker ni MySQL disponibles dans l'environnement de dev utilisé) — à valider avec
  `pnpm --filter api prisma migrate dev` dès qu'une vraie base est accessible
- `prisma/seed.ts` : données de démo réalistes (2 candidats, 2 entreprises, 1 CFA, 3
  offres, candidatures, paiement, notification, salle de visio) — exécuté et vérifié
  jusqu'à la tentative de connexion DB, jamais contre une vraie base
- `packages/shared` : enums `ContractType`, `PaymentStatus`, `NotificationType` ajoutés,
  synchronisés avec le schema Prisma
- Choix de modélisation faits sans validation préalable (à relire) : `JobOffer` rattachée
  à `Company` OU `CfaOrganization` via deux FK nullables (invariant validé côté DTO, pas
  en base) ; `ContractType` (ALTERNANCE/SAISONNIER/BENEVOLAT) déduit du positionnement
  produit mais non explicitement listé dans ce fichier ; `Payment` non cascade-supprimé
  avec l'utilisateur (obligation légale de conservation des pièces comptables)

**Authentification : terminée**

- `apps/api` module `auth` : register/login/refresh/logout/me, mot de passe oublié
  (token JWT signé, email via Resend), Google OAuth (création ou association de compte
  par email) — strategies Passport local + JWT + Google, `JwtAuthGuard`/`RolesGuard` +
  `@Roles()`/`@CurrentUser()` dans `common/`
- Access token courte durée (15 min, réponse JSON) + refresh token longue durée (7 jours,
  cookie httpOnly `jeuncy_refresh_token`, jamais exposé au JS) — rotation à chaque refresh
- Format de réponse standard `{ success, data }` / `{ success, error }` appliqué
  globalement (`ResponseInterceptor` + `HttpExceptionFilter`)
- `apps/web` : store Zustand de session **non persisté** (accessToken en mémoire
  uniquement), `AuthProvider` qui restaure la session via `/auth/refresh` au chargement,
  client API typé avec retry automatique sur 401, pages login/register/mot de passe
  oublié/réinitialisation + callback Google OAuth (RHF + Zod, erreurs `role="alert"`)
- 14 tests unitaires sur `AuthService`
- Correctif de fond : `packages/shared` repassé en CommonJS (était `"type": "module"`,
  incompatible avec la compilation CJS de NestJS — `require()` d'un module ESM échoue au
  runtime, pas seulement au typecheck) ; extensions `.js` explicites dans ses exports
  internes (requis par la résolution `nodenext` d'`apps/api`)
- Choix faits sans validation préalable (à relire) : inscription Google sans sélection de
  rôle → `CANDIDATE` par défaut ; page `/auth/callback` reçoit l'access token en query
  string (pas idéal niveau exposition — historique navigateur — mais le refresh token
  reste protégé en cookie httpOnly, seul l'access token courte durée est concerné)
- **Non testé en conditions réelles** : ni la connexion Google OAuth (`GOOGLE_CLIENT_ID`
  placeholder), ni l'envoi d'email (`RESEND_API_KEY` absent → log au lieu d'envoyer), ni
  le flux complet register/login (pas de base MySQL — voir point suivant)

**Connu et à traiter plus tard**

- Prisma reste en v6.19 (v7 supprime le support de `package.json#prisma`, utilisé ici
  pour pointer vers `/prisma/schema.prisma` — migration vers `prisma.config.ts` à
  prévoir si besoin)
- Pas de base MySQL locale configurée : `apps/api` ne démarre pas (`PrismaService`
  échoue à se connecter au démarrage) et la migration n'a pas pu être appliquée tant que
  `DATABASE_URL` ne pointe pas vers une instance réelle (Docker à installer, ou instance
  MySQL locale/managée à fournir) — bloquant pour tester register/login/refresh en vrai
- Logos `apps/web/public/logo/logo-light.png` et `logo-dark.png` sont les versions
  circulaires (badge) redimensionnées à 128×128 ; la version pleine avec tagline
  (`logo_jeuncy.png` à la racine, hors repo web) n'a pas encore d'usage assigné

**Phase 2 — profil + CV : à faire**
