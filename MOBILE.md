# Cadrage — Application mobile Jeuncy

> Document de référence pour le développement de l'application iOS et Android.
> Rédigé le 2026-09-04. À relire et amender avant d'ouvrir le chantier.
>
> Le développement se fera dans une **conversation dédiée**, qui démarrera avec
> ce document. Le contexte de la plateforme web reste dans `CLAUDE.md`.

## 1. La demande

Le patron veut une application Jeuncy présente **dans l'App Store et le Google
Play Store**. L'objectif premier est la **visibilité et la crédibilité**, pas
seulement le confort d'usage mobile — c'est ce qui a fait écarter la PWA, qui
aurait couvert l'essentiel du besoin pour une fraction du coût mais sans
présence dans les stores.

L'application reprend le fonctionnement de la plateforme actuelle, **pour les
trois rôles** : candidats, entreprises et CFA.

## 2. Décisions actées

| Point              | Décision                                                   |
| ------------------ | ---------------------------------------------------------- |
| Périmètre          | Les trois rôles : candidat, entreprise, CFA                |
| Plateformes        | iOS **et** Android                                         |
| Technologie        | React Native + Expo                                        |
| Paiements          | Achats intégrés Apple/Google acceptés, commission comprise |
| Compte développeur | Au nom de la société Jeuncy (pas au nom d'une personne)    |
| Budget récurrent   | Validé : ~99 €/an Apple, ~25 € une fois Google             |

### Réponses obtenues le 2026-09-08

| Question            | Réponse                                                                                                                                               |
| ------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Nom dans les stores | **Jeuncy**                                                                                                                                            |
| Numéro D-U-N-S      | **Obtenu**, au nom de SAS JEUNCY — le compte Apple Organisation peut être ouvert                                                                      |
| Téléphone de test   | **iPhone** (Pierre). Expo Go pour voir l'app en direct pendant le développement                                                                       |
| Échéance visée      | Application **développée et testable fin octobre 2026**. La publication dans les stores dépend en plus de la validation Apple/Google (1 à 2 semaines) |

### Déblocage des candidatures : produit abandonné

Question tranchée le 2026-09-08. Payer la publication (9,99 € entreprise,
5,99 € CFA) donne accès aux candidatures **de cette offre**. La CVthèque
reste réservée à l'abonnement.

Le code faisait l'inverse : l'essai gratuit débloquait les candidatures, le
paiement non — une entreprise qui payait ne pouvait consulter aucune
candidature. Corrigé le 2026-09-08 (`PaymentService`, test de non-régression).

**Conséquence pour les stores : un seul produit à déclarer**, la publication
d'offre, en deux tarifs. Le type `APPLICATIONS_UNLOCK` reste inutilisé.

### Hypothèses retenues faute de réponse explicite

Ces deux points ont été proposés et recommandés, sans validation formelle du
patron. **Le travail est cadré ci-dessous sur cette base ; les remettre en
cause change le découpage, pas la faisabilité.**

1. **Sortie en deux temps.** La première version publiée contient les achats
   **à l'unité** (9,99 € et 5,99 €). Les **abonnements** arrivent juste après.
   Raison : les abonnements en achat intégré sont la partie la plus délicate
   (renouvellement automatique, notifications serveur, annulations), et les
   bloquer retarderait toute la sortie.
2. **Administration et visioconférence exclues de la V1.** L'administration n'a
   aucun intérêt sur mobile ; la visio est un outil de démonstration
   commerciale, pas un usage terrain. Les deux restent sur le web.

## 3. Ce qui ne change pas : le backend

**L'API existante est réutilisée telle quelle.** L'application est un nouveau
client HTTP sur le même serveur Laravel : authentification, offres,
candidatures, profils, CVthèque, notifications — tout existe déjà et tourne en
production.

C'est ce qui rend le projet abordable. Le travail porte sur l'interface, pas
sur la logique métier.

## 4. Technologie : React Native + Expo

Retenu parce que c'est **du React et du TypeScript**, exactement ce qu'il y a
dans `apps/web`. Même langage, mêmes formulaires (React Hook Form + Zod),
mêmes appels API, `packages/shared` réutilisable tel quel.

Écarté :

- **Flutter** — excellent, mais langage Dart : tout est à réapprendre, rien
  n'est réutilisable.
- **Swift + Kotlin natifs** — deux applications à écrire et à maintenir.
  Injustifiable ici.
- **Webview autour de jeuncy.com** — **rejeté par Apple** (règle 4.2,
  fonctionnalité minimale). Un simple emballage de site est refusé à la
  validation. Il faut de vrais écrans natifs.

**Expo permet de fabriquer l'application iOS sans posséder de Mac** : la
compilation se fait sur leurs serveurs. Économie d'une machine.

## 5. Modifications backend nécessaires

Modestes, mais réelles.

### 5.1 Authentification — le refresh token

Aujourd'hui le refresh token est un **cookie httpOnly**, invisible du
JavaScript. Une application native ne gère pas les cookies de la même façon :
il faudra le renvoyer dans la **réponse JSON** pour les clients mobiles, et le
stocker dans le coffre sécurisé du téléphone (`expo-secure-store` → Keychain
sur iOS, Keystore sur Android).

Point de vigilance : ne pas dégrader la sécurité du web au passage. Le cookie
httpOnly doit rester le mécanisme du navigateur ; le corps JSON est un ajout
réservé au client mobile, distingué par un en-tête.

### 5.2 Google OAuth

Configuration différente en natif : identifiants clients dédiés iOS et Android,
schémas de redirection propres à l'application.

### 5.3 Notifications push

C'est ce qui donne tout son sens à la notification « une offre te correspond »
construite le 2026-09-03 : aujourd'hui elle attend que le candidat revienne sur
le site, demain c'est une notification sur son téléphone dans la minute.

- Nouvelle table des **jetons d'appareil** par utilisateur (un utilisateur peut
  avoir plusieurs appareils).
- Envoi depuis PHP via l'**API Expo Push** : un simple appel HTTPS, qui
  fonctionne sans problème sur l'hébergement mutualisé OVH.
- Envoi **synchrone**, comme le reste : pas de worker possible sur cet
  hébergement (même contrainte que `JobOfferMatchService`).

### 5.4 Achats intégrés — la vraie nouveauté

C'est le seul morceau de logique métier réellement neuf.

**Grille tarifaire actuelle** (source : `config/services.php`) :

| Produit                          | Prix   |
| -------------------------------- | ------ |
| Publication d'offre — entreprise | 9,99 € |
| Publication d'offre — CFA        | 5,99 € |
| Abonnement entreprise / CFA      | 499 €  |
| Abonnement fondateur (50 places) | 299 €  |

Chaque produit devra être déclaré chez Apple **et** chez Google, en plus
d'exister chez Stripe pour le web. **Trois systèmes de paiement en parallèle
pour un seul catalogue.**

Conséquences concrètes :

- Le modèle `Payment` doit porter son **fournisseur** (`stripe`, `apple`,
  `google`). Aujourd'hui il suppose Stripe partout.
- Il faut **vérifier les reçus côté serveur** auprès d'Apple et de Google — ne
  jamais faire confiance au client sur la réalité d'un paiement.
- Une entreprise qui paie dans l'application doit être reconnue sur le site, et
  inversement. La publication de l'offre est déclenchée de la même manière quel
  que soit le fournisseur — `JobOfferMatchService::notifyMatchingCandidates()`
  est déjà branché sur les trois chemins de publication existants, il faudra
  brancher le quatrième.

**Commission : 15 %, pas 30 %.** Le _Small Business Program_ d'Apple s'applique
à toute société sous un million de dollars par an ; Google applique la même
logique sur la première tranche annuelle. Sur une offre à 9,99 €, cela
représente environ 1,50 € au lieu de 3 €. **L'inscription à ce programme n'est
pas automatique**, il faut la demander.

### 5.5 Non concerné

CORS ne s'applique pas au natif. Le reste de l'API ne bouge pas.

## 6. Périmètre fonctionnel

### V1 — candidat

Connexion, inscription, mot de passe oublié, Google · Profil (informations,
expériences, formations, compétences, logiciels, langues) · Photo de profil ·
Import de CV en PDF et génération de CV · Recherche d'offres, filtres, détail ·
Candidature · Suivi des candidatures · Notifications, **avec push** ·
Confidentialité et suppression du compte (**exigée par Apple**)

### V1 — entreprise et CFA

Profil de l'organisation · Mes offres : création, modification, archivage ·
Publication payante **à l'unité** · Candidatures reçues et changement de statut
· CVthèque · Historique des paiements · Tarifs

### V2

Abonnements en achat intégré · Visioconférence (à évaluer) · Administration
(probablement jamais : elle n'a pas sa place sur mobile)

## 7. Découpage du travail

| Phase                | Contenu                                                                                                           |
| -------------------- | ----------------------------------------------------------------------------------------------------------------- |
| 0 — Socle ✅          | **Terminée le 2026-09-09.** Projet Expo, navigation, thème Jeuncy, client API, auth, stockage sécurisé — détail dans `CLAUDE.md` |
| 1 — Candidat         | Tous les écrans candidat, y compris CV et candidatures                                                            |
| 2 — Push             | Jetons d'appareil, envoi depuis PHP, branchement sur les notifications existantes                                 |
| 3 — Entreprise / CFA | Profil, offres, candidatures reçues, CVthèque                                                                     |
| 4 — Achats à l'unité | Déclaration des produits, achat, vérification des reçus, réconciliation avec Stripe                               |
| 5 — Publication      | Icônes, captures, fiches des stores, politique de confidentialité, comptes de test, soumission                    |
| 6 — Après            | Abonnements en achat intégré                                                                                      |

**Ordre de grandeur : plusieurs mois**, pas plusieurs semaines. L'application
reprend l'équivalent des six phases construites sur le web. Le calendrier réel
dépend du rythme de travail et des allers-retours de validation des stores
(compter 1 à 2 semaines pour la première soumission).

## 8. Prérequis administratifs

**À lancer immédiatement — c'est le chemin critique :**

- **Numéro D-U-N-S** pour le compte développeur Apple au nom de la société.
  Gratuit, mais souvent **plusieurs semaines**. Jeuncy a peut-être déjà un
  D-U-N-S sans le savoir : Apple fournit un outil de recherche en ligne.

**Ensuite :**

- Compte **Apple Developer Program** — type **Organisation**, ~99 €/an
- Compte **Google Play Console** — type Organisation, ~25 € une fois
- **IBAN et informations fiscales de la société** (Apple et Google versent les
  recettes des achats intégrés au titulaire du compte)
- Inscription au **Small Business Program** d'Apple (commission à 15 %)

**Pourquoi le compte doit être au nom de la société :** c'est l'entité qui
possède l'application **et qui encaisse**. Avec un compte personnel, les
recettes des achats intégrés arriveraient sur un compte bancaire personnel,
avec les conséquences fiscales que cela implique, et le store afficherait un
nom de personne au lieu de « Jeuncy ». Un transfert ultérieur est une procédure
lourde.

**Un compte Organisation dispense aussi** d'une contrainte pénible de Google :
les comptes personnels récents doivent faire tester l'application par 12
personnes pendant 14 jours avant publication.

_Montants et règles à confirmer au moment de l'inscription : les stores les
font évoluer._

## 9. Contraintes de validation, mentions légales et RGPD

C'est le volet le plus facile à sous-estimer : une omission ici coûte un refus
de store, ou pire, une non-conformité sur des données de mineurs.

### 9.1 Ce qui existe déjà côté API — à exposer, pas à réécrire

| Besoin                            | Existant                                                                                                                     |
| --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| Export de ses données             | `GET account/export` (`AccountService::exportData`)                                                                          |
| Suppression du compte             | `DELETE account` — exige la saisie de l'email en confirmation                                                                |
| Anonymisation comptable           | Un compte ayant payé est **anonymisé**, pas supprimé : obligation légale de conservation des pièces comptables               |
| Conservation limitée              | 3 ans annoncés dans la politique de confidentialité, réellement appliqués par `cvs:archive-inactive` et `cv-downloads:purge` |
| Visibilité CVthèque               | `is_visible_in_cvtheque` — le candidat peut se retirer de la CVthèque                                                        |
| Journal des téléchargements de CV | Chaque téléchargement par un recruteur est journalisé                                                                        |

**Rien de tout cela n'est à redévelopper.** L'application doit rendre ces
fonctions accessibles depuis ses écrans, c'est tout.

### 9.2 Pages légales du site, à reprendre dans l'application

- `/mentions-legales`
- `/confidentialite` — politique de confidentialité
- `/mon-compte/confidentialite` — écran personnel : export, suppression,
  visibilité dans la CVthèque

Ces trois pages doivent avoir leur équivalent natif. Un lien vers le site ne
suffit pas pour Apple : la politique de confidentialité doit aussi être
déclarée par une **URL publique** dans la fiche du store.

### 9.3 Exigences propres aux stores

- **Suppression du compte depuis l'application — obligatoire chez Apple**
  (règle 5.1.1(v)). Une application qui permet de créer un compte doit
  permettre de le supprimer, sans passer par le site ni par un email.
- **Déclaration des données collectées** : « App Privacy » chez Apple,
  « Sécurité des données » chez Google. Il faut lister précisément ce qui est
  collecté (identité, email, téléphone, CV, ville, historique de candidatures)
  et à quoi ça sert. Toute déclaration inexacte est un motif de retrait.
- **Public jeune.** Jeuncy s'adresse à des jeunes, dont des **mineurs de 16-17
  ans en alternance**. Les deux stores ont des règles renforcées sur les
  applications touchant des mineurs, et c'est un motif de refus fréquent. En
  France, le consentement RGPD autonome est possible **à partir de 15 ans** ;
  en dessous, l'autorisation d'un titulaire de l'autorité parentale est requise.
  **Tranché le 2026-09-09 : âge minimum de 15 ans**, déclaré par une case à
  cocher à l'inscription. Un alternant de 16-17 ans reste éligible, et le
  mécanisme lourd de l'accord parental est évité. La case existe dans
  l'application depuis la phase 0, mais **rien n'est encore enregistré côté
  serveur** : suffisant pour Apple, insuffisant pour prouver le consentement.
  Une colonne en base reste à ajouter avant la soumission.
- **Comptes de test** à fournir aux validateurs, pour les **trois rôles** —
  avec des données réalistes, sinon la validation échoue faute de pouvoir
  tester.

### 9.4 Achats intégrés et RGPD

Point rassurant : avec les achats intégrés, **Jeuncy ne voit jamais les
données bancaires**. Apple et Google encaissent et ne transmettent qu'un
identifiant de transaction. La surface de responsabilité est plus petite
qu'avec Stripe sur le web.

En revanche, le **reçu doit être vérifié côté serveur** — ne jamais croire le
client sur la réalité d'un paiement.

### 9.5 Notifications push et consentement

- iOS exige une **autorisation explicite** de l'utilisateur avant tout envoi.
  Demander cette permission **au bon moment** (après que le candidat a compris
  ce qu'il y gagne), jamais au premier lancement.
- Prévoir un **réglage de désactivation dans l'application** : aujourd'hui
  aucune préférence de notification n'existe côté produit (voir CLAUDE.md,
  « Connu et à traiter plus tard »). L'app est l'occasion de le créer.

## 10. Le coût qui ne s'arrête jamais

Un site web se dépose et tourne. Une application, non :

- Google impose chaque année de viser une version Android récente, sinon
  l'application **disparaît du store**.
- Apple fait de même à son rythme.
- Chaque correctif passe par une nouvelle validation.

Compter quelques jours de maintenance par an, indéfiniment.

## 11. Points à clarifier avant de commencer

- **Validation formelle des deux hypothèses de la section 2.**
- **Déblocage payant des candidatures** : le type de paiement
  `APPLICATIONS_UNLOCK` existe et son webhook est implémenté, mais aucun tarif
  n'est configuré et aucun parcours d'achat ne le déclenche. Produit abandonné
  ou à finir ? La réponse change le catalogue à déclarer dans les stores.
- **Nom et icône** de l'application dans les stores.
- **Comptes de test** à créer pour les validateurs.
