# Démo Jeuncy : le parcours pour la présentation

Préparé le 2026-10-14. Durée : environ 20 minutes, plus les questions.

**Règle d'or : tout se passe sur la base de TEST.** Les comptes sont fictifs et
aucun vrai candidat ne reçoit quoi que ce soit, pas même un email : les
adresses fictives sont bloquées avant l'envoi. Seule exception : la vitrine
publique `jeuncy.com` (acte 1), consultée en lecture seule.

Parcours rejoué de bout en bout le 2026-10-14 contre la base de test : tout
passe.

## Le matériel

- **PC** : il fait tourner le serveur et montre le site.
- **iPhone** : il montre l'app dans Expo Go.
- PC et iPhone doivent être **sur le même réseau**. Dans une salle de réunion,
  connecte le PC au **partage de connexion de l'iPhone**. Le Wi-Fi d'une
  entreprise bloque souvent les échanges entre deux appareils.
- Il faut internet : la base de test est hébergée en ligne.

## Lancer la démo (2 minutes)

1. Connecte le PC au réseau de l'iPhone.
2. Fais un clic droit sur `docs\demo\lancer-demo.ps1`, puis **Exécuter avec
   PowerShell**. Trois fenêtres s'ouvrent : API, Site et Expo. Le script remet
   aussi tout le jeu de démo à zéro.
3. Sur l'iPhone, scanne le QR code de la fenêtre **Expo** avec l'appareil
   photo, puis ouvre dans Expo Go.
4. Sur le PC, ouvre `http://localhost:5173` dans un onglet et
   `https://jeuncy.com` dans un autre.

5. **Tour de chauffe**, avant l'arrivée des participants : ouvre une fois les
   pages du site que tu vas montrer (`/mes-matchs`, `/organization`, `/admin`)
   et l'app sur l'iPhone. Le site local se compile au premier passage : la
   première ouverture d'une page prend une dizaine de secondes, les suivantes
   2 à 4 secondes. La démo est plus lente que la production (0,2 à 0,3 s), car
   le serveur tourne sur le PC et la base de test est distante.

**Comptes** : mot de passe `Password123!` pour tous, adresse
`<compte>@demo-match.example.com`.

| Compte            | Qui c'est                      | Sert à                  |
| ----------------- | ------------------------------ | ----------------------- |
| `candidat.test`   | Pierre Test, 20 ans, Perpignan | le côté jeune           |
| `cfa.test`        | CFA Démo Perpignan (vérifié)   | l'école qui recrute     |
| `entreprise.test` | Boulangerie Démo (vérifiée)    | l'entreprise partenaire |
| `admin.test`      | Administrateur                 | le back-office          |

Ce qui est déjà en place : 10 jeunes autour de Perpignan (deux de moins de
18 ans), 8 offres Jeuncy, 37 vraies offres partenaires du 66, 4 élèves
rattachés au CFA, une pizzeria en attente de vérification, et un match déjà
conclu entre Pierre Test et le CFA.

## La veille : une répétition complète

Déroule tout le parcours une fois, puis **relance le script** pour tout
remettre à zéro.

Pendant la répétition, vérifie aussi ce point, le seul jamais testé sur
iPhone : une fois le dossier envoyé (acte 3), connecte-toi en `cfa.test` dans
l'app, puis Matchs → Pierre T. → **Ouvrir le CV**. Le PDF doit s'ouvrir.

## Le déroulé

### Acte 1 : la vitrine (PC, jeuncy.com, 2 min)

- **Accueil** : la signature « Match ton alternance » et le compteur d'offres
  (environ 8 100, le vrai chiffre de la production).
- **Offres** : les offres Jeuncy d'abord, puis les offres partenaires de La
  bonne alternance, sur toute la France. Les écoles qui se font passer pour
  des employeurs sont filtrées : plus de 1 400 annonces écartées.
- **Gratuit** : la plateforme est gratuite pour les jeunes comme pour les
  entreprises.

À dire : « Ce que vous voyez là est en ligne, c'est la production. »

### Acte 2 : une école découvre des jeunes (iPhone, `cfa.test`, 4 min)

- Connecte-toi en `cfa.test`. Onglet **Découvrir**, offre « Vendeur·se en
  alternance ».
- Montre une carte : prénom et initiale, tranche d'âge, badge « JEUNCY x CFA
  Démo Perpignan ». **Ni nom, ni ville, ni téléphone** : c'est ce qui protège
  les mineurs (Yanis et Hugo ont moins de 18 ans).
- Glisse à gauche (Passer), puis annule le geste.
- **Les deux premières cartes, Yanis et Inès, ont déjà dit oui** à cette
  offre. Glisse à droite sur l'une des deux : « C'est un match ! ».
- Onglet **Matchs** : le match est là, mais toujours aucune coordonnée. Elles
  n'apparaissent que si le jeune envoie lui-même son dossier.
- Onglet **Compte**, puis **Se déconnecter**.

### Acte 3 : le jeune (iPhone, `candidat.test`, 6 min)

- Connecte-toi en `candidat.test`. Onglet **Découvrir** : 7 offres Jeuncy,
  puis 37 vraies offres partenaires autour de Perpignan.
- Glisse à droite sur une offre : « Ça m'intéresse ». Une feuille propose
  d'envoyer le dossier maintenant ou de marquer seulement son intérêt.
- Ouvre les réglages de la pile : rayon de 5 à 100 km et « Autour de moi »
  (GPS).
- Sur une offre partenaire, touche **Je garde** : elle se range dans
  Candidatures → Gardées.
- Onglet **Profil** : photo obligatoire pour accéder à Découvrir, puis « Ce que
  je cherche ». Génère le CV Jeuncy (environ 4 secondes).
- Onglet **Matchs** : « Vendeur·se en alternance » (CFA Démo), puis **Envoyer
  mon dossier** (CV, téléphone, petit mot).

### Acte 4 : l'école reçoit le dossier (PC, `localhost:5173`, `cfa.test`, 3 min)

- Connecte-toi en `cfa.test`, puis va sur `/mes-matchs`. Le dossier de Pierre
  Test montre maintenant son nom, son téléphone, son email et son CV.
- Clique sur **Ouvrir le CV**. Le PDF n'est servi qu'à l'employeur qui a reçu le
  dossier : son adresse n'apparaît nulle part (correctif de sécurité du
  14 octobre).
- Passe le statut à **Entretien**. Sur l'iPhone, onglet **Notifications**,
  tire vers le bas : le jeune est prévenu.

### Acte 5 : l'école recommande ses élèves (PC, `cfa.test`, 2 min)

- Va sur `/organization`, rubrique **Entreprises partenaires** : cherche
  « Boulangerie », puis ajoute-la.
- **Recommander un candidat** : Lucas, offre « Apprenti·e boulanger·e ».
- Déconnecte-toi, connecte-toi en `entreprise.test`, puis va sur
  `/recommandations` : Lucas y est. La **CVthèque** n'affiche aucun nom
  complet.

### Acte 6 : le contrôle (PC, `admin.test`, 3 min)

- Va sur `/admin`, onglet **Statistiques**.
- Onglet **Modération**, rubrique « Vérifications en attente » : Pizzeria
  Démo. « **Aucune entreprise ne voit un jeune avant qu'un humain de Jeuncy
  l'ait validée.** » Écris une note, puis **Valider**.
- Même onglet, rubrique « Signalements » : chaque signalement fait depuis
  l'app arrive ici. Engagement public : traitement sous 24 h.
- Onglet **Offres partenaires** : l'audit des écoles écartées.

Note : NexaTech et Café des Lices, dans la file, sont d'anciens comptes de
test. Tu peux les ignorer.

### Acte 7 : données et confiance (iPhone, `candidat.test`, 1 min)

- Profil → **Confidentialité et données** : retrait de la CVthèque, export de
  toutes ses données, suppression du compte.
- Âge minimum : 16 ans. Le site (`/mentions-legales`, §8) décrit le
  signalement, la tolérance zéro et le recours des parents.

## Les questions qu'on te posera

**« Ça rapporte comment, si c'est gratuit ? »** Chaque jeune inscrit est un
candidat potentiel pour l'école partenaire, et l'OPCO finance l'école à
l'inscription de chaque apprenti. Le paiement en ligne (Stripe, abonnements)
est déjà construit, puis désactivé : il se rallume par un réglage, sans
développement.

**« Et les mineurs ? »**

- 16 ans minimum.
- Aucune donnée qui identifie le jeune avant qu'il envoie lui-même son
  dossier.
- Chaque entreprise est validée par un humain.
- Signalement et blocage dans l'app, traités sous 24 h.
- Export et suppression des données par le jeune lui-même.
- Un audit de sécurité a été mené le 1er octobre ; ses correctifs prioritaires
  sont en ligne depuis le 14 octobre.

**« Quand sera-t-elle sur les stores ? »** L'app est prête : version Android
vérifiée, fiches et questionnaire de données rédigés. Il reste à payer
l'inscription Google Play (25 $, après votre accord) et à obtenir la
validation du compte Apple (en attente). Ensuite, chaque store relit l'app :
compter quelques jours, parfois plus pour une première publication.

**« Et quand elle se remplira ? »** Voir la section suivante.

## Tient-elle la charge ? La réponse honnête

**Mesuré le 14 octobre, sur la production :**

- Pages publiques (recherche, compteur, offres partenaires) : **0,2 à 0,3 s**
  (20 requêtes).
- Import nocturne des offres partenaires : un export de 550 Mo lu en 18 s,
  plus de 8 000 offres en ligne.
- Lettre d'information : 125 destinataires, 0 échec.
- Limite anti-abus active : 120 requêtes par minute et par utilisateur.

**Ce qui n'a pas été fait :** aucun test de charge sur la production. Le
serveur est partagé ; un test le ralentirait pour les vrais utilisateurs.

**Ce qui devra évoluer, et à quel signal :**

1. **Le « oui » qui crée un match prend environ 10 s en production** : les
   deux emails partent pendant la requête, car l'hébergement mutualisé d'OVH
   n'a pas de file d'attente. C'est acceptable pour le pilote. À fort trafic :
   envoyer les emails après la réponse, ou passer sur un serveur avec une
   tâche de fond. Ce serait un changement d'hébergement, pas une réécriture :
   Laravel le prévoit.
2. **Chaque nouvelle entreprise attend une validation humaine.** C'est voulu,
   pour les mineurs. Quand il y aura trop d'inscriptions, on pourra valider
   automatiquement les adresses email au domaine de l'entreprise.
3. **Les emails** dépendent de l'offre Resend. Vérifier son quota sur le
   compte : une lettre hebdomadaire à quelques milliers de jeunes demande une
   offre payante (quelques dizaines d'euros par mois).
4. **L'hébergement mutualisé** partage ses ressources et ne garantit aucun
   volume. Signal : des temps de réponse au-dessus d'une seconde. Il faudra
   alors passer à l'offre supérieure ou à un serveur dédié. La base de
   données, MySQL 8.4, n'a pas à changer.
5. **La visio** utilise l'instance publique gratuite de Jitsi, sans logo
   Jeuncy et sans enregistrement.

## Si quelque chose ne marche pas

- **Expo Go n'ouvre pas l'app** : le PC et l'iPhone ne sont pas sur le même
  réseau. Vérifie le réseau, puis relance le script.
- **L'app s'ouvre mais affiche « Connexion impossible »** : la fenêtre API est
  fermée, ou le pare-feu de Windows bloque. Relance le script. Si Windows
  demande d'autoriser PHP ou Node, clique sur **Autoriser**.
- **Une fenêtre affiche une erreur** : ferme les trois fenêtres et relance le
  script.
- **Rien ne marche** : montre le site en ligne (acte 1) et les captures de
  l'app dans `docs/mobile/captures-stores/`.

## Après la démo

Ferme les trois fenêtres. L'app sur l'iPhone vise toujours la base de test,
et c'est voulu : supprime `apps/mobile/.env` seulement pour revenir à la
production.
