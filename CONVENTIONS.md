# CONVENTIONS.md — Règles de code du projet Jeuncy

## 1. Langue

- **Code** (variables, fonctions, composants, classes PHP) : anglais.
- **Contenu utilisateur** (UI, textes, messages d'erreur affichés, emails) : français.
- **Commentaires** : français ou anglais, cohérent dans un même fichier.

## 2. Naming

- Composants React : `PascalCase.tsx` (ex: `JobOfferCard.tsx`)
- Hooks : `useCamelCase.ts` (ex: `useCandidateProfile.ts`)
- Dossiers (apps/web, packages/shared) : `kebab-case` (ex: `job-offers/`)
- Variables/fonctions TS : `camelCase`
- Types/interfaces TS : `PascalCase`, pas de préfixe `T`/`I`
- Classes PHP (Controllers, Services, Models, Requests) : `PascalCase`, un fichier par
  classe, nom de fichier = nom de classe (`AuthController.php`, `JobOffer.php`)
- Méthodes/variables PHP : `camelCase` ; propriétés/colonnes de modèle : `snake_case`
  (convention Eloquent, correspond directement aux colonnes MySQL — pas de mapping
  supplémentaire nécessaire)
- Tables/colonnes MySQL (via migrations Laravel) : `snake_case`, nom de table au pluriel
  explicite sur chaque modèle (`protected $table = 'job_offers';`) — ne pas compter sur la
  pluralisation automatique d'Eloquent (ex: `education` reste singulier par défaut)
- Routes API : REST, pluriel, kebab-case, préfixées `/api` (`/api/job-offers/:id/applications`)
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

## 4. Backend (Laravel)

- Un domaine métier (`auth`, `job-offers`, `applications`, `video-rooms`, etc.) = un
  Controller (`app/Http/Controllers/...`) + un Service (`app/Services/...`) + ses Form
  Requests (`app/Http/Requests/...`) + ses tests (`tests/Feature/...`), routes déclarées
  dans `routes/api/xxx.php` et incluses depuis `routes/api.php`.
- Toute route protégée passe par le middleware `auth:api` (garde JWT custom,
  `app/Auth/JwtGuard.php`) + `role:XXX` (`app/Http/Middleware/EnsureUserHasRole.php`) —
  équivalent du couple guards/decorators NestJS, appliqué en middleware de route plutôt
  qu'en attribut de méthode.
- Validation des entrées via des Form Requests dédiées (`XxxRequest extends FormRequest`,
  méthode `rules()`), jamais de validation manuelle ad hoc dans le controller.
- Logique métier dans les services, jamais dans les controllers (le controller ne fait que
  router + injecter le Form Request validé + appeler le service + shaper la réponse).
- Accès BDD via Eloquent, requêtes complexes isolées dans des méthodes de service dédiées,
  pas de query builder brut dans un controller. Un modèle = une table déclarée explicitement
  (`protected $table = '...';`), jamais de dépendance à la pluralisation automatique.
- Erreurs métier attendues (email déjà pris, mauvais mot de passe, ressource non trouvée...)
  levées via `App\Exceptions\ApiException` (code HTTP + code d'erreur explicites), jamais de
  `abort()` brut ni d'exception générique — c'est ce qui alimente le format de réponse
  uniforme de la section 6, géré centralement dans `bootstrap/app.php`.

## 5. Style et formatage

- TypeScript strict (`strict: true`) partout dans `apps/web`/`packages/shared`, pas de
  `any` sauf cas documenté en commentaire.
- ESLint + Prettier obligatoires côté JS (husky + lint-staged avant chaque commit).
- PHP strict types (`declare(strict_types=1);`) et types de retour explicites sur toute
  nouvelle méthode dans `apps/api`. Laravel Pint obligatoire avant chaque commit
  (`composer pint` / `vendor/bin/pint`), équivalent Prettier côté PHP.
- Tailwind : utiliser les tokens du thème custom (`bg-jeuncy-navy`, `text-jeuncy-coral`, etc.)
  définis dans `tailwind.config.ts`, jamais de couleur hexadécimale en dur dans le JSX.
- Statuts/enums partagés front/back : définis une fois dans `packages/shared` (TS, pour
  `apps/web`) et une fois dans `app/Enums` (PHP backed enum, pour `apps/api`) — les deux
  n'ont plus de génération commune depuis le passage à Laravel (pas de client TS généré à
  partir de PHP), donc toute modification de valeur d'enum doit être répercutée
  manuellement des deux côtés.

## 6. API — format de réponse

```json
{ "success": true, "data": {...} }
{ "success": false, "error": { "code": "INVALID_INPUT", "message": "..." } }
```

Codes HTTP corrects (400 validation, 401 non authentifié, 403 non autorisé, 404, 409 conflit,
500 erreur serveur).

## 7. Base de données / Eloquent

- Une migration Laravel (`database/migrations/`) = un changement logique cohérent, nommée
  explicitement (`create_xxx_table`, `add_xxx_to_yyy_table`...). Les migrations sont la
  seule source de vérité du schéma.
- Jamais de modification manuelle du schéma en prod sans migration versionnée
  (`php artisan migrate`).
- `onDelete('cascade')` justifié en commentaire dans la migration (ex : suppression en
  cascade du profil candidat avec le compte, au titre du droit à l'effacement RGPD).
- Mots de passe hashés via le cast Eloquent `'password_hash' => 'hashed'` (bcrypt), jamais
  en clair, jamais retournés dans une API (`#[Hidden(['password_hash'])]` sur le modèle).
- Un modèle = une classe dans `app/Models/`, table déclarée explicitement
  (`protected $table`), colonnes remplissables déclarées via l'attribut `#[Fillable([...])]`.

## 8. Git / Commits

- Convention **Conventional Commits** :
  `feat(candidate): ajoute le générateur de CV PDF`
  `feat(video): intègre Jitsi Meet pour les salles de démo`
  `fix(auth): corrige la redirection après login`
- Une branche par feature (`feature/cv-generator`, `feature/video-rooms`).
- Pas de commit direct sur `main` ; passage par PR même en solo.

## 9. Tests

- `apps/api` : tests PHPUnit (`tests/Feature/`, `RefreshDatabase` + SQLite en mémoire) sur
  les services (logique métier) et les endpoints critiques (auth, candidature, paiement) ;
  dépendances externes (email, paiement) mockées via Mockery, jamais appelées en vrai dans
  les tests.
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
