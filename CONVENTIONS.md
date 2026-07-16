# CONVENTIONS.md — Règles de code du projet Jeuncy

## 1. Langue

- **Code** (variables, fonctions, composants, modules NestJS) : anglais.
- **Contenu utilisateur** (UI, textes, messages d'erreur affichés, emails) : français.
- **Commentaires** : français ou anglais, cohérent dans un même fichier.

## 2. Naming

- Composants React : `PascalCase.tsx` (ex: `JobOfferCard.tsx`)
- Hooks : `useCamelCase.ts` (ex: `useCandidateProfile.ts`)
- Dossiers : `kebab-case` (ex: `job-offers/`)
- Variables/fonctions : `camelCase`
- Types/interfaces : `PascalCase`, pas de préfixe `T`/`I`
- Modules NestJS : `xxx.module.ts`, `xxx.controller.ts`, `xxx.service.ts`, `xxx.dto.ts`
- Tables/colonnes MySQL (via Prisma) : `snake_case` en base, mappé en `camelCase` côté modèle
  Prisma via `@map()` / `@@map()`
- Routes API : REST, pluriel, kebab-case (`/job-offers/:id/applications`)
- Constantes globales : `SCREAMING_SNAKE_CASE`

## 3. Frontend (React + Vite)

- Un composant = un fichier. Props typées via une interface `XxxProps`, jamais `any`.
- Composants "présentation" (UI pure) séparés des composants "container" (logique/fetch)
  quand la complexité le justifie.
- Appels API centralisés dans `/src/lib/api` (client typé, pas de `fetch()` éparpillé dans
  les composants) ; TanStack Query pour le cache et la gestion des états loading/error.
- État global (Zustand) réservé aux données vraiment transverses (thème, session) — le reste
  reste en state local ou en cache TanStack Query.
- Le thème dark/light passe par un attribut `data-theme` sur `<html>`, jamais de logique de
  couleur dupliquée dans chaque composant : tout passe par les tokens Tailwind.

## 4. Backend (NestJS)

- Un module par domaine métier (`auth`, `job-offers`, `applications`, `video-rooms`, etc.),
  chacun avec son controller, service, DTOs, et tests.
- Toute route protégée utilise les decorators `@UseGuards(JwtAuthGuard, RolesGuard)` +
  `@Roles(...)`.
- Validation des entrées via DTO + `class-validator` (ou Zod si le module le justifie),
  jamais de validation manuelle ad hoc dans le controller.
- Logique métier dans les services, jamais dans les controllers (le controller ne fait que
  router + valider + appeler le service).
- Accès BDD via Prisma, requêtes complexes isolées dans des méthodes de repository dédiées,
  pas de requête Prisma brute dans un controller.

## 5. Style et formatage

- TypeScript strict (`strict: true`) partout, pas de `any` sauf cas documenté en commentaire.
- ESLint + Prettier obligatoires (husky + lint-staged avant chaque commit).
- Tailwind : utiliser les tokens du thème custom (`bg-jeuncy-navy`, `text-jeuncy-coral`, etc.)
  définis dans `tailwind.config.ts`, jamais de couleur hexadécimale en dur dans le JSX.
- Types partagés front/back (statuts, enums, formes de DTO) centralisés dans
  `packages/shared`, importés des deux côtés — pas de duplication de types.

## 6. API — format de réponse

```json
{ "success": true, "data": {...} }
{ "success": false, "error": { "code": "INVALID_INPUT", "message": "..." } }
```
Codes HTTP corrects (400 validation, 401 non authentifié, 403 non autorisé, 404, 409 conflit,
500 erreur serveur).

## 7. Base de données / Prisma

- Une migration = un changement logique cohérent, nommée explicitement.
- Jamais de modification manuelle du schéma en prod sans migration versionnée.
- `onDelete: Cascade` justifié en commentaire dans `schema.prisma`.
- Mots de passe hashés (bcrypt/argon2), jamais en clair, jamais retournés dans une API.

## 8. Git / Commits

- Convention **Conventional Commits** :
  `feat(candidate): ajoute le générateur de CV PDF`
  `feat(video): intègre Jitsi Meet pour les salles de démo`
  `fix(auth): corrige la redirection après login`
- Une branche par feature (`feature/cv-generator`, `feature/video-rooms`).
- Pas de commit direct sur `main` ; passage par PR même en solo.

## 9. Tests

- `apps/api` : tests unitaires (Jest, natif NestJS) sur les services (logique métier), tests
  e2e sur les endpoints critiques (auth, candidature, paiement).
- `apps/web` : tests de composants (Vitest + Testing Library) sur les éléments interactifs
  critiques (formulaire de candidature, toggle dark mode, CV builder, salle vidéo).
- Tout bug corrigé s'accompagne d'un test de non-régression.

## 10. Accessibilité et responsive

- Mobile-first : layout mobile d'abord, puis extension `md:`/`lg:`.
- Contraste minimum AA (vérifier notamment le rose corail sur fond clair).
- Labels de formulaire associés, erreurs annoncées (`aria-live`).
- La salle de visio doit rester utilisable au clavier (mute/unmute, quitter la salle).

## 11. Sécurité

- Aucun secret en dur dans le code (clés Stripe, DB) — uniquement `.env`, jamais commité.
- CORS strict entre `apps/web` et `apps/api` en production.
- Rate limiting sur les routes sensibles (login, inscription, création de salle vidéo).
- Upload de fichiers (photo, CV importé) : validation type MIME + taille max, stockage hors repo.
- Webhooks Stripe : vérification systématique de la signature.
- Consentement explicite avant tout enregistrement d'une session de visioconférence.

## 12. Documentation

- Chaque module significatif (générateur de CV, paiement, visioconférence) a un court README
  expliquant le fonctionnement et les décisions non évidentes.
- `CLAUDE.md` mis à jour à la fin de chaque session avec l'état d'avancement réel.
