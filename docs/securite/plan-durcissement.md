# Audit de sécurité Jeuncy — plan de durcissement

Audit réalisé le 1er octobre 2026, sur le code du dépôt uniquement.
Aucun fichier de code n'a été modifié. Aucun appel n'a été fait vers jeuncy.com
ni vers api.jeuncy.com. Aucune donnée personnelle réelle n'a été lue.

---

## Ce que j'ai trouvé, honnêtement

Ce projet est **nettement mieux protégé que la moyenne de ce qu'on voit à ce
stade**, et je préfère le dire d'emblée parce que la suite est une liste de
problèmes et qu'une liste de problèmes donne toujours une impression plus
sombre que la réalité.

Les bases sont bonnes et elles ont été réfléchies, pas copiées : le jeton de
session long vit dans un cookie que le JavaScript ne peut pas lire ; une
déconnexion ou un changement de mot de passe coupe _immédiatement_ toutes les
sessions déjà ouvertes, ce qui n'est pas automatique avec ce type de jeton et
demande du travail ; la connexion et l'inscription sont limitées en nombre
d'essais ; le CORS n'a pas de joker ; le site et l'API forcent tous deux le
HTTPS ; la signature des paiements Stripe est vérifiée ; le journal d'erreurs
exposé aux outils de déploiement masque les adresses email et les jetons ;
l'outil de déploiement refuse un envoi de masse si on ne confirme pas
explicitement ; et surtout, **il n'y a aucun secret dans l'historique Git** —
j'ai cherché sur les 198 commits, ligne par ligne.

J'ai cherché un oubli de contrôle d'accès — une page où l'on pourrait lire le
dossier de quelqu'un d'autre en changeant un numéro dans l'adresse. **Je n'en
ai trouvé aucun.** Les 15 ressources accessibles par identifiant sont toutes
gardées. C'est le résultat le plus rassurant de cet audit, et il n'est pas
fréquent.

Trois remarques supplémentaires, dans le bon sens, parce qu'un audit doit
aussi corriger la documentation :

- `CLAUDE.md` décrit comme un compromis à surveiller le fait que
  `/auth/callback` reçoive le jeton dans l'adresse. **Ce n'est plus vrai** : le
  code pose un cookie et ne met rien dans l'URL
  (`apps/api/app/Http/Controllers/Auth/AuthController.php:173-177`). Le point
  est réglé, on peut le rayer.
- `CLAUDE.md` dit « Pas de limite de durée de vie du lien d'invitation » pour
  la visio. **Ce n'est plus vrai non plus** : les liens expirent
  (`apps/api/app/Services/VideoRoomService.php:56-61` et `88-96`).
- La règle « consentement avant enregistrement d'une visio » (`CLAUDE.md` §10)
  est aujourd'hui **sans objet** : rien n'enregistre les visios, et c'est dit
  aux usagers. Ce n'est donc pas un manquement.

Ce qui reste est réel, et une partie touche directement des documents
d'identité de jeunes gens. C'est l'objet de la suite.

---

## À faire en premier

Cinq points. Ce sont ceux qui exposent réellement des données de mineurs ou la
production. Le reste peut attendre une semaine ; ceux-là, non.

### 1. Vérifier si le compte de démonstration `admin@jeuncy.com` existe en production

**Ce qui peut arriver :** ce compte est créé par le jeu de données de
démonstration avec le mot de passe `Password123!` et le rôle administrateur.
S'il existe en production, n'importe qui l'ayant deviné dispose du back-office
complet : liste de tous les comptes, suspension, modération, CVthèque entière,
coordonnées de tous les candidats mineurs inclus.

**La vérification exacte, à faire toi-même, en une minute :** connecte-toi sur
`jeuncy.com/admin` avec ton propre compte, onglet **Utilisateurs**, filtre
**ADMIN** — si `admin@jeuncy.com` apparaît et que ce n'est pas le compte que tu
utilises, le problème est là.

**Si le compte existe :** clique **Suspendre** sur cette ligne immédiatement.
C'est suffisant et immédiat : une suspension coupe les sessions déjà ouvertes
sans attendre l'expiration du jeton
(`apps/api/app/Auth/JwtGuard.php:45`). Ensuite, `admin@jeuncy.com` étant une
adresse de ton domaine, fais un « mot de passe oublié » depuis cette adresse
pour reprendre le compte proprement, ou laisse-le suspendu définitivement.

### 2. Les CV et les photos sont lisibles par n'importe qui, sans compte, et le lien est donné aux recruteurs

**Ce qui peut arriver :** un recruteur reçoit, dans la réponse de l'API,
l'adresse web directe du PDF du CV. Cette adresse fonctionne sans compte, sans
mot de passe, pour toujours. S'il la transfère par mail, la colle dans un
tableur partagé, ou si sa boîte mail est un jour compromise, le CV complet
d'un jeune de 16 ans — nom, adresse, téléphone, date de naissance, photo — est
téléchargeable par le monde entier, et Jeuncy ne peut plus rien révoquer.

Détail en section **A1**.

### 3. Les CV déposés ne sont pas effacés quand un candidat supprime son compte

**Ce qui peut arriver :** un jeune exerce son droit à l'effacement, l'écran lui
confirme que ses fichiers sont supprimés, et son CV en PDF reste en réalité sur
le serveur, toujours téléchargeable par son adresse. C'est à la fois une
exposition de données et un manquement RGPD caractérisé, parce qu'on lui a dit
le contraire.

Et, du côté entreprise : **un recruteur qui a téléchargé au moins un CV ne peut
plus supprimer son compte du tout** — la suppression échoue sur une erreur
serveur.

Détail en sections **A2** et **L1**.

### 4. Un numéro SIRET public suffit pour voir les fiches de candidats de 15 ans

**Ce qui peut arriver :** le SIRET de n'importe quelle entreprise française est
public et consultable en ligne. Quelqu'un qui en recopie un obtient un compte
entreprise « vérifié », donc l'accès à la CVthèque — c'est-à-dire aux fiches de
tous les candidats, avec un filtre par âge qui n'a pas de plancher. Rien ne
prouve que cette personne a le moindre rapport avec l'entreprise dont elle a
pris le numéro.

C'est déjà écrit comme risque assumé dans `docs/mobile/lot-1-backend.md` §10.
Il n'a pas changé depuis. Détail en section **B1**.

### 5. Un seul jeton de déploiement, jamais expiré, qui voyage dans l'adresse

**Ce qui peut arriver :** ce secret unique ouvre les migrations de base, le
journal d'erreurs de production, l'outil de diagnostic candidat par candidat et
l'envoi d'une lettre à tous les inscrits. Il circule dans le _chemin_ des
adresses, donc il se retrouve dans les journaux d'accès d'OVH, dans ton
historique de navigateur et dans toute capture d'écran de ta barre d'adresse.
Il n'a aucune date d'expiration et il n'existe aucune procédure pour le
remplacer.

Détail en section **F**.

---

# Le détail, par thème

Chaque constat indique : ce que j'ai vérifié et où, ce que ça permet
concrètement, la correction, et l'effort.

---

## A. Fichiers et données personnelles — le point le plus important

### A1. Les CV, photos et logos sont servis directement par Apache, sans aucun contrôle

**Vérifié :**

| Fichier produit            | Où le chemin est construit                                  | Emplacement                             |
| -------------------------- | ----------------------------------------------------------- | --------------------------------------- |
| CV généré par Jeuncy       | `apps/api/app/Services/CvService.php:28`                    | `generated-cvs/{idProfil}/{uuid}.pdf`   |
| CV déposé par le candidat  | `apps/api/app/Services/CandidateProfileService.php:278-279` | `uploaded-cvs/{idProfil}-{uuid}.pdf`    |
| CV joint à une candidature | `apps/api/app/Services/ApplicationService.php:247-248`      | `application-cvs/{idProfil}-{uuid}.pdf` |
| Photo de profil            | `apps/api/app/Services/CandidateProfileService.php:244-245` | `photos/{idProfil}-{uuid}.ext`          |
| Logo d'entreprise          | `apps/api/app/Services/CompanyService.php:192-193`          | `company-logos/{id}-{uuid}.ext`         |

Tous sur le disque `public`, configuré en `'visibility' => 'public'` avec une
adresse publique (`apps/api/config/filesystems.php:41-58`). Autrement dit : le
serveur web les sert lui-même, Laravel n'est jamais consulté, donc **aucune
règle d'autorisation ne peut s'appliquer**.

**Bonne nouvelle, et c'est important : les noms de fichiers ne sont pas
devinables.** Chacun porte un UUID tiré au hasard. On ne peut pas les énumérer
en essayant des numéros. Le listage de répertoire est par ailleurs désactivé
(`apps/api/public/.htaccess:21`). Donc : **pas de fuite de masse possible.**

**Mais l'adresse est remise aux recruteurs.** Dans
`apps/api/app/Services/ApplicationService.php:201`, la liste des candidatures
est renvoyée avec `->with([..., 'generatedCv'])`, et le modèle `GeneratedCv` ne
masque rien (`apps/api/app/Models/GeneratedCv.php:9`) : le champ `file_url`
part dans la réponse. Même chose pour le profil, dont `cv_file_url` n'est pas
dans la liste des champs masqués
(`apps/api/app/Models/CandidateProfile.php:51`), et dans la fiche d'un match
(`apps/api/app/Services/MatchService.php:273`).

**Ce que ça permet concrètement :** une fois qu'un candidat a postulé, le
recruteur détient un lien permanent, anonyme et non révocable vers son CV.
Même après un blocage, même après le retrait de la candidature, même après
que le candidat a supprimé son compte (voir A2), même après la fin de
l'abonnement. Ce lien n'a pas besoin de Jeuncy pour fonctionner.

**L'incohérence est interne au projet, et c'est ce qui rend le constat
indiscutable.** Le téléchargement depuis la CVthèque, lui, sert les octets du
PDF à travers une route gardée précisément pour que l'adresse ne circule pas —
le commentaire le dit mot pour mot
(`apps/api/app/Http/Controllers/CvthequeController.php:32-35` et
`apps/api/app/Services/CvthequeService.php:38-41`). La règle existe et elle est
tenue d'un côté ; elle ne l'est pas de l'autre.

**Correction, en deux temps :**

_Tout de suite, petit effort_ — empêcher que de nouvelles adresses sortent :

1. ajouter `cv_file_url` à `$hidden` dans `apps/api/app/Models/CandidateProfile.php:51` ;
2. ajouter `protected $hidden = ['file_url'];` dans `apps/api/app/Models/GeneratedCv.php` ;
3. servir ces deux documents au recruteur par une route gardée, sur le modèle
   exact de `CvthequeController::downloadCv` qui existe déjà et fonctionne.

_Ensuite, effort moyen_ — déplacer les nouveaux dépôts vers le disque privé
(`storage/app/private`, déjà configuré dans
`apps/api/config/filesystems.php:33-39`) et ne les servir qu'à travers Laravel.
Les fichiers déjà en ligne restent où ils sont : leur nom n'étant pas
devinable, le risque résiduel est la circulation des liens déjà distribués, pas
une fuite nouvelle.

_Mesure d'appoint immédiate, petit effort_ — déposer en FTP un fichier
`.htaccess` dans `public/storage/` sur le serveur :

```apache
Options -Indexes
# Un PDF ouvert dans l'onglet s'exécute sous le domaine api.jeuncy.com.
# Forcé en téléchargement, il ne s'exécute pas.
<FilesMatch "\.pdf$">
    Header always set Content-Disposition "attachment"
</FilesMatch>
# Ceinture et bretelles : aucun script ne doit jamais s'exécuter ici.
<FilesMatch "\.(php|phtml|phar|cgi|pl|py|sh|htaccess)$">
    Require all denied
</FilesMatch>
Header always set X-Content-Type-Options "nosniff"
```

**Effort : petit** pour les trois mesures immédiates, **moyen** pour le passage
au disque privé.

### A2. Le CV déposé et le CV de candidature survivent à la suppression du compte

**Vérifié :** `apps/api/app/Services/AccountService.php:202-224`
(`storedFilePaths`) relève, pour les effacer, la photo de profil, les CV
générés, le logo d'entreprise et le logo de CFA. **Il ne relève ni
`candidate_profiles.cv_file_url`** (le CV que le jeune a déposé lui-même)
**ni les `applications.cv_file_url`** (le CV joint à chaque candidature).

Les lignes en base partent bien en cascade. Les fichiers, eux, restent sur le
disque public — et restent téléchargeables par leur adresse, qui a pu être
distribuée à plusieurs recruteurs (voir A1).

**Ce que ça permet concrètement :** le document le plus sensible du lot — celui
qui porte nom, adresse, téléphone, date de naissance et souvent une photo —
survit à une demande d'effacement. Et l'écran de suppression affirme le
contraire au candidat : _« tes fichiers (photo, CV générés, logo) sont
supprimés »_ (`apps/web/src/pages/AccountPrivacy.tsx:95-96`). La phrase est
littéralement exacte — le CV **déposé** n'y figure pas — ce qui la rend
d'autant plus trompeuse.

**Correction :** ajouter dans `storedFilePaths()` le `cv_file_url` du profil et
ceux de toutes les candidatures du candidat, puis corriger le texte de
`AccountPrivacy.tsx`. Ajouter un test qui vérifie que le fichier a réellement
disparu du disque — il n'en existe aucun aujourd'hui pour ce chemin.

**Effort : petit.**

### A3. Le rôle STAFF ouvre le CV complet de tous les candidats

**Vérifié :** `apps/api/app/Services/CvthequeService.php:295-298` —
`isCvSharedWith()` commence par `if ($this->isInternal($user)) return true;`,
ce qui court-circuite la règle « le CV ne s'ouvre que si le candidat a postulé
à l'une de tes offres ». `isInternal` couvre ADMIN et STAFF
(`CvthequeService.php:314-317`). Et STAFF s'attribue en un clic depuis le
back-office, à partir d'un compte candidat
(`apps/api/routes/api/admin.php:20` → `apps/api/app/Services/AdminService.php:113-130`).

**Ce que ça permet concrètement :** un compte promu STAFF lit le CV intégral de
n'importe quel candidat visible, sans qu'aucun d'eux ait postulé chez lui.
C'est **voulu et documenté** (`apps/api/routes/api/cvtheque.php:12-16`) : STAFF
désigne aujourd'hui toi et moi, et Jeuncy est déjà responsable de ces données.
Je le signale parce que la porte est large et qu'elle s'ouvre d'un bouton : le
jour où STAFF désignera un stagiaire ou un commercial, la décision aura changé
de nature sans que personne ne l'ait rouverte.

**Correction :** rien d'urgent. Écrire noir sur blanc, dans `CLAUDE.md`, qui a
droit à STAFF et selon quelle règle. Les téléchargements internes sont déjà
journalisés (`CvthequeService.php:245-249`), ce qui est le bon réflexe.

**Effort : petit** (c'est une décision écrite, pas du code).

### A4. Les consultations de fiches ne sont pas journalisées, seuls les téléchargements le sont

**Vérifié :** la ligne de journal `cv_downloads` est écrite dans
`apps/api/app/Services/CvthequeService.php:245-249`, uniquement au
téléchargement du PDF. L'ouverture d'une fiche candidat
(`CvthequeService.php:208-217`) n'écrit rien.

**Ce que ça permet concrètement :** un recruteur peut parcourir des dizaines de
fiches sans laisser de trace. La migration revendique pourtant que la
fréquence des accès « révèle un usage anormal (moissonnage) »
(`apps/api/database/migrations/2026_09_02_100001_create_cv_downloads_table.php:37-39`) :
cette détection est aveugle au parcours le plus simple.

Nuance en faveur du projet : la fiche ne montre les coordonnées qu'après
candidature, et le présentateur de carte est une liste blanche stricte. Le
volume d'information consultable sans trace est donc limité.

**Correction :** écrire une ligne de journal aussi à la consultation d'une
fiche, avec un type distinct.

**Effort : petit.**

---

## B. Mineurs

Rappel de cadrage : 15 ans minimum sur le site, 16 ans sur l'application.

### B1. Un SIRET public actif suffit pour accéder à des fiches de mineurs

**Vérifié :** `apps/api/app/Services/CompanyVerificationService.php:100-104` —
si le registre public répond, si le numéro correspond bien à l'établissement
demandé et si celui-ci est actif, le statut passe à `VERIFIED`, sans aucune
autre condition. Et depuis la gratuité du 15 septembre, toute entreprise ou
CFA a l'accès payant d'office
(`apps/api/app/Services/SubscriptionService.php:185-187`). La vérification est
donc la **seule** porte.

**Le lot 4 a-t-il bouché le trou ?** Non — il l'a rétréci d'un côté qui n'était
pas le bon. La file de vérification manuelle ne liste que les organisations
restées `PENDING`
(`apps/api/app/Services/AdminModerationService.php:93` et `:99`), c'est-à-dire
celles que le registre n'a pas su confirmer. Une inscription frauduleuse avec
un vrai SIRET passe en `VERIFIED` automatiquement et **n'apparaît dans aucune
file de relecture**. Le chemin par lequel entrerait un imposteur est
exactement celui qui n'est pas relu.

Le code le dit lui-même, et c'est à son honneur :
_« C'est une preuve d'existence, pas une preuve d'identité »_
(`CompanyVerificationService.php:23-26`).

Effet de bord à connaître : le SIRET est unique en base
(`apps/api/app/Http/Requests/Company/StoreCompanyRequest.php:35`). Quelqu'un
qui prend le numéro d'une vraie entreprise **l'empêche de s'inscrire ensuite**.

**Ce que ça permet concrètement :** voir les cartes de tous les candidats du
périmètre, dont des mineurs ; marquer un intérêt ; déclencher des notifications
et des emails chez eux ; et, dès qu'un candidat postule, recevoir son dossier
complet avec coordonnées et CV.

**Correction, par ordre de coût :**

1. _Petit_ — faire passer en file de relecture manuelle **toute** nouvelle
   organisation, même auto-vérifiée, avant qu'elle voie une première fiche
   candidat. Tant qu'il n'y a qu'IDA et une poignée d'entreprises, le coût
   humain est nul.
2. _Moyen_ — exiger une adresse email dont le domaine correspond au site web
   déclaré, avec validation par lien.
3. _Moyen_ — n'ouvrir l'accès aux fiches qu'après la publication d'une
   première offre réelle, ce qui élève le coût d'une inscription de façade.

### B2. La CVthèque du site n'a aucun plancher d'âge, contrairement au deck de l'application

**Vérifié :** le deck employeur de l'application impose 16 ans révolus
directement en base de données
(`apps/api/app/Services/DiscoverService.php:208` et `:220-221`) — c'est solide,
c'est structurel, on ne peut pas l'oublier. La CVthèque du site, elle, n'a
aucun filtre équivalent : les seuls filtres d'âge sont ceux que le recruteur
choisit (`apps/api/app/Services/CvthequeService.php:143-151`), **sans borne
basse**. Un recruteur peut demander « âge maximum : 16 » et obtenir la liste
des plus jeunes.

Le filtre par âge est légitime en apprentissage (la rémunération dépend de la
tranche d'âge). Ce n'est donc pas le filtre qui pose problème, c'est l'absence
de plancher combinée à B1.

**Ce que ça permet concrètement :** à un compte dont l'identité n'a jamais été
vérifiée, de dresser la liste des candidats de 15 et 16 ans.

**Correction :** décider une règle explicite et l'écrire une fois, au même
endroit que celle du deck. Par exemple : les profils de moins de 16 ans ne sont
pas interrogeables par un employeur qui n'a pas été relu à la main.

**Effort : petit** (une condition), **la décision est à toi.**

### B3. La case « j'ai 15 ans ou plus » est facultative côté serveur

**Vérifié :** `apps/api/app/Http/Requests/Auth/RegisterRequest.php:33` —
`['sometimes', 'accepted']`. Si le client ne l'envoie pas, le compte se crée
avec `age_confirmed_at` à vide. Une inscription par Google ne la pose jamais.

C'est **assumé et commenté** (le client mobile ne l'envoyait pas encore), et la
vraie barrière est ailleurs : la date de naissance du profil, qui impose 15 ans
révolus (`apps/api/app/Http/Requests/CandidateProfile/StoreCandidateProfileRequest.php:33`).
La protection existe donc. Ce qui manque, c'est la **preuve datée** du
consentement, que la CNIL ou Apple demanderont.

Au passage : `CLAUDE.md` affirme encore que la case « n'est pas enregistrée
côté serveur ». C'est périmé — la colonne existe depuis le 22 septembre.

**Correction :** rendre la case obligatoire une fois que l'application mobile
l'envoie (elle le fait depuis le lot A), et la poser aussi au retour de Google.

**Effort : petit.**

### B4. Le lien d'invitation à une visio ne demande aucun compte

**Vérifié :** `apps/api/routes/api/video-rooms.php:9` — route publique.
Elle ne renvoie que trois champs, sans aucune identité
(`apps/api/app/Http/Controllers/PublicVideoRoomController.php:18-22`), et le
lien **expire** désormais
(`apps/api/app/Services/VideoRoomService.php:56-61`, `:88-96`). C'est bien
fait.

Le point qui reste, et qui est une décision plus qu'un défaut : toute personne
à qui le lien est transmis entre dans une salle où peut se trouver un mineur.
C'est le propre d'un lien d'invitation ; je le signale pour qu'il soit décidé,
pas découvert.

**Correction possible :** demander un prénom à l'entrée côté Jeuncy, et
afficher à l'hôte qui attend. **Effort : moyen.**
À défaut, ne rien changer mais l'écrire.

---

## C. Authentification et session

Le socle est sain. Algorithme de signature **fixé** à HS256 des deux côtés de
l'émission et de la vérification
(`apps/api/app/Services/JwtService.php:30`, `:42`, `:107`) : l'attaque
classique du jeton « sans signature » ne s'applique pas. Secrets séparés pour
le jeton court et le jeton long, sans valeur par défaut
(`apps/api/config/jwt.php:4-7`) — une configuration incomplète fait échouer
l'application au lieu de la rendre silencieusement vulnérable.

### C1. Le jeton de rafraîchissement n'est pas réellement « tourné »

**Vérifié :** `apps/api/app/Services/AuthService.php:102-124`. À chaque
rafraîchissement, un nouveau couple de jetons est émis — mais **l'ancien jeton
reste valable jusqu'à son terme naturel de 7 jours**. Rien ne l'invalide, et
rien ne détecte qu'il a été rejoué.

`CLAUDE.md` parle de « rotation à chaque refresh ». Il y a bien remplacement,
mais pas rotation au sens de la sécurité, qui suppose que l'ancien meure.

**Ce que ça permet concrètement :** si un jeton de 7 jours est volé une fois —
ordinateur partagé en CFA, sauvegarde de navigateur, poste public — le voleur
garde l'accès pendant une semaine entière, en parallèle du vrai titulaire, et
personne ne s'en aperçoit. Aujourd'hui, seule une déconnexion volontaire ou un
changement de mot de passe coupe le lien (ces deux-là fonctionnent bien, voir
`AuthService.php:130-133` et `:208-209`).

**Correction :** stocker une empreinte du dernier jeton de rafraîchissement émis
par appareil, et refuser — en révoquant tout — la présentation d'un jeton déjà
consommé. C'est la détection de rejeu standard.
Compliqué par un comportement déjà connu : `logout` incrémente `token_version`,
donc déconnecte _tous_ les appareils. Le faire proprement suppose une table de
sessions par appareil.

**Effort : moyen.** À prévoir avant le pilote, pas avant demain.

### C2. La garde anti-XSS du mode mobile est correcte, et j'ai vérifié pourquoi

**Vérifié :** `apps/api/app/Http/Controllers/Auth/AuthController.php:72-84`. En
mode mobile, le jeton est lu **uniquement dans le corps** de la requête, et le
cookie est ignoré. Sans cela, un script injecté dans le navigateur appellerait
cette route avec l'en-tête mobile, le navigateur joindrait automatiquement le
cookie, et le serveur répondrait en clair avec un jeton de 7 jours.

**Rien à signaler.** C'est la bonne construction, elle est testée
(`apps/api/tests/Feature/MobileAuthTest.php`), et la note de `CLAUDE.md` sur le
piège du harnais de test montre qu'elle a été vérifiée pour la bonne raison.

### C3. `/auth/callback` ne reçoit plus de jeton dans l'adresse

**Vérifié :** `AuthController.php:173-177` côté serveur et
`apps/web/src/pages/AuthCallback.tsx:13-20` côté site. Le compromis documenté
dans `CLAUDE.md` a été corrigé. **Rien à signaler, le point est à rayer de la
documentation.**

### C4. Le mot de passe n'exige que 8 caractères, sans autre contrôle

**Vérifié :** `apps/api/app/Http/Requests/Auth/RegisterRequest.php:25` et
`ResetPasswordRequest.php:18` — `min:8`, rien d'autre.

**Ce que ça permet concrètement :** `password`, `jeuncy66`, `12345678` sont
acceptés. Combinés au fait que la limite d'essais se réinitialise après deux
minutes (voir D1), ces mots de passe tombent.

**Correction :** Laravel sait le faire en une ligne —
`Password::min(10)->uncompromised()` vérifie en plus que le mot de passe ne
figure pas dans les fuites publiques connues, sans jamais l'envoyer nulle part
en clair.

**Effort : petit.**

---

## D. Limitation de débit

### D1. Trois routes sont limitées. Toutes les autres ne le sont pas.

**Vérifié, exhaustivement :**

| Route                                         | Limite                  | Où                                   |
| --------------------------------------------- | ----------------------- | ------------------------------------ |
| `POST auth/login`, `POST auth/register`       | 7 essais / 2 min par IP | `apps/api/routes/api/auth.php:13`    |
| `POST auth/forgot-password`, `reset-password` | 10 / min par IP         | `apps/api/routes/api/auth.php:17`    |
| `POST contact`                                | 5 / 10 min par IP       | `apps/api/routes/api/contact.php:14` |

Et c'est tout. `apps/api/bootstrap/app.php:137-139` n'ajoute aucune limite
générale au groupe API — Laravel n'en met pas par défaut.

**Contrairement à l'hypothèse de départ de cet audit : la connexion EST
limitée.** Le bourrage d'identifiants brutal est donc contré. La limite reste
tolérante (7 essais, puis 2 minutes d'attente, puis on recommence : environ
200 essais par heure et par adresse IP), ce qui suffit pour un mot de passe
faible à 8 caractères mais pas pour un mot de passe correct. C'est le point C4
qui est le vrai sujet, pas celui-ci.

**Les routes réellement exposées, par ordre de gravité :**

1. **`POST auth/refresh`** (`apps/api/routes/api/auth.php:21`) — aucune limite.
   Permet d'essayer des jetons à volonté. Risque faible (il faudrait deviner
   une signature), mais c'est gratuit à corriger.
2. **`POST candidate-profile/cv`** (`apps/api/routes/api/candidate-profile.php:44`)
   — aucune limite. Chaque appel fabrique un PDF, et le moteur recommence
   jusqu'à **onze fois** pour faire tenir le CV sur une page
   (`apps/api/app/Services/CvService.php:186`). Un compte candidat qui appelle
   cette route en boucle met l'hébergement mutualisé à genoux et remplit le
   disque. **C'est le déni de service le moins cher à monter du projet.**
3. **`POST candidate-profile/cv/import`** — lit un PDF fourni par l'utilisateur
   avec une bibliothèque d'analyse. Même sujet, avec en prime une surface
   d'attaque sur le format.
4. **`POST video-rooms`** (`apps/api/routes/api/video-rooms.php:13`) — aucune
   limite. `CONVENTIONS.md` §11 exige pourtant explicitement une limite sur la
   création de salle vidéo : **le code diverge de la règle écrite du projet**.
5. **`GET job-offers/search`** et `job-offers/external/search` — publiques et
   sans limite. Gênant surtout pour la charge, les réponses étant paginées.

**Correction :** poser une limite générale sur tout le groupe API dans
`bootstrap/app.php`, puis resserrer les trois routes coûteuses :

```php
$middleware->api(append: [WrapApiResponse::class]);
$middleware->throttleApi('120,1');   // 120 requêtes par minute, par compte ou par IP
```

puis, sur les routes concernées, `->middleware('throttle:5,10')` pour la
génération de CV et l'import, et `->middleware('throttle:20,60')` pour la
création de salle.

**Effort : petit.** C'est le meilleur rapport effet/coût de tout ce rapport.

---

## E. Autorisation

### E1. Je n'ai trouvé aucune garde manquante

J'ai suivi, une par une, les **15 ressources désignées par un identifiant dans
l'adresse** (`{jobOffer}`, `{application}`, `{experience}`, `{education}`,
`{language}`, `{notification}`, `{videoRoom}`, `{offerInterest}`,
`{externalInterest}`, `{userBlock}`, `{payment}`, `{candidateProfile}`,
`{report}`, `{user}`, `{externalJobOffer}`), depuis la route jusqu'au service.
**Toutes sont gardées**, soit par une vérification de propriétaire explicite,
soit par une requête déjà restreinte à l'appelant.

J'ai aussi vérifié les 16 endroits où un modèle est chargé directement par son
numéro : aucun n'aboutit à une lecture ou une écriture sans filtre.

Et trois points que je cherchais spécifiquement, tous corrects :

- **impossible de s'inscrire en ADMIN ou STAFF** (`RegisterRequest.php:27`) ;
- un employeur ne peut pas marquer son intérêt pour un candidat hors de sa
  portée en devinant un numéro : la cible est rejouée contre la requête du deck
  (`apps/api/app/Services/InterestService.php:346-355`) ;
- les routes littérales (`search`, `count`, `express`, `batch`, `last`,
  `access`) sont toutes déclarées **avant** la route à paramètre
  correspondante, avec `whereNumber` en renfort. Aucune ne peut être avalée.

### E2. Une garde d'âge qui laisse passer si personne n'est connecté

**Vérifié :** `apps/api/app/Http/Middleware/EnsureMatchAge.php:32-36` — si
`$user` est vide, la requête **passe**. À comparer avec la garde de rôle, qui
refuse (`apps/api/app/Http/Middleware/EnsureUserHasRole.php:20-22`).

**Ce que ça permet concrètement : rien aujourd'hui.** Les quatre groupes de
routes qui utilisent `match.age` portent tous `auth:api`, donc l'utilisateur ne
peut pas être vide.

**Pourquoi je le signale quand même :** le commentaire du fichier revendique
exactement la propriété qu'il n'a pas — _« elle doit tenir sur une route
ajoutée demain par quelqu'un qui n'aura pas lu MOBILE.md »_
(`EnsureMatchAge.php:16-20`). Si cette personne oublie `auth:api`, la règle des
16 ans ne s'applique plus à personne, **sans le moindre message**.

**Correction :** remplacer le passage silencieux par un refus en 401 quand
l'utilisateur est vide. Une ligne.

**Effort : petit.**

### E3. Les données publiques sont propres

**Vérifié champ par champ :**

- fiches entreprise et CFA : liste de champs masqués explicite, aucun email,
  aucun téléphone, aucune coordonnée GPS, aucun `user_id` — donc pas
  d'énumération de comptes. Le filtre `is_public` est appliqué **y compris** en
  accès direct par numéro (`apps/api/app/Services/CompanyService.php:70-73`) ;
- offres partenaires : liste blanche de colonnes
  (`apps/api/app/Models/ExternalJobOffer.php:34-40`) ;
- offres Jeuncy : statut `PUBLISHED` exigé, aucune donnée candidat ;
- salles de visio : trois champs, aucune identité ;
- désinscription à la lettre : adresse signée, rien d'affiché, et le GET
  n'agit pas.

Un test automatisé couvre déjà ce périmètre
(`apps/api/tests/Feature/PublicDataExposureTest.php`). **Rien à signaler.**

### E4. Aucune injection SQL

J'ai relu les 20 requêtes écrites en SQL brut. Les seules valeurs interpolées
sont des noms de colonnes internes et une formule de distance constante ; les
valeurs venant de l'utilisateur passent toutes par des paramètres liés
(par exemple `apps/api/app/Services/DiscoverService.php:229-230`). **Rien à
signaler.**

---

## F. Outillage de déploiement

### F1. La garde est présente partout — c'est le transport qui pose problème

**Vérifié :** les 18 routes de `apps/api/routes/web.php:55-100` appellent
`assertAuthorized($token)` en première instruction. La garde elle-même est
correctement écrite (`apps/api/app/Http/Controllers/DeployController.php:131-138`) :
comparaison à temps constant (`hash_equals`, qui ne laisse pas deviner le jeton
caractère par caractère) et 404 si la variable n'est pas définie. **Aucun
contrôle manquant.**

Les trois problèmes sont ailleurs :

**a) Le secret voyage dans le chemin de l'adresse.** Il atterrit donc dans les
journaux d'accès d'OVH (conservés et consultables), dans ton historique de
navigateur, et dans l'en-tête `Referer` de toute ressource externe que
chargerait une des pages HTML servies par ces routes. Aujourd'hui la page de
dépôt de la lettre ne charge rien d'externe — j'ai vérifié
(`apps/api/resources/views/deploy/newsletter-editions.blade.php`) — mais ça
tient à un lien près.

**b) Un seul jeton pour tout.** Le même secret ouvre `migrate` (modifie la base
de données), `logs` (journal de production), `match/{id}` (diagnostic candidat
par candidat), `newsletter` (envoi à tous les inscrits) et `geocode-backfill`.
Celui qui a besoin du moins puissant les obtient tous.

**c) Il n'expire jamais et aucune procédure ne le remplace.**

**Ce que ça permet concrètement :** quiconque met la main sur ce jeton — par
un journal, une capture d'écran, un historique — dispose du journal d'erreurs
de production et peut envoyer un email à tous tes inscrits en ton nom.

**Correction, en trois étapes :**

1. _Petit_ — **passer le jeton en en-tête HTTP** plutôt qu'en segment
   d'adresse. Les routes deviennent `/deploy/status`, et `assertAuthorized()`
   lit `$request->header('X-Jeuncy-Deploy')`. Un en-tête n'apparaît ni dans les
   journaux d'accès, ni dans l'historique, ni dans le `Referer`. Attention :
   cela te oblige à appeler ces outils avec `curl.exe -H` au lieu du
   navigateur. Les deux pages HTML (dépôt de la lettre) doivent rester
   atteignables au navigateur : pour celles-là, garde le chemin mais avec un
   **jeton distinct**, qui n'ouvre que la lettre.
2. _Petit_ — **deux jetons séparés** : `DEPLOY_TOKEN` pour l'exploitation
   (migrations, journaux, diagnostics) et `NEWSLETTER_TOKEN` pour la lettre.
   C'est de loin le meilleur gain : le geste hebdomadaire, celui qui se fait au
   navigateur et laisse le plus de traces, cesse de donner accès à la base.
3. _Petit_ — **procédure de rotation**, à écrire dans
   `docs/exploitation/journal-verification.md` : générer une nouvelle valeur
   (`openssl rand -hex 32`), l'écrire dans le `.env` du serveur par FTP,
   appeler `/deploy/{ancien}/clear-cache` une dernière fois, vérifier que
   l'ancien répond 404. À faire tous les six mois et à chaque fois qu'un jeton
   a pu être vu par quelqu'un.

### F2. `migrate` s'exécute sur une simple visite d'adresse

**Vérifié :** `apps/api/routes/web.php:56` et `DeployController.php:531-538` —
une requête GET déclenche `php artisan migrate --force` sans aucun paramètre
de confirmation.

**Ce que ça permet concrètement :** un aperçu de lien dans une messagerie, un
préchargement de navigateur ou un antivirus qui suit les adresses d'un mail
suffit à lancer des migrations en production. Le risque n'est pas qu'elles
tournent — elles sont idempotentes — mais qu'elles tournent **au mauvais
moment**, par exemple pendant que tu téléverses des fichiers.

Les routes à effet de masse, elles, sont bien gardées — j'ai vérifié :
l'envoi de la lettre refuse si on ne met pas `&tous=1`
(`DeployController.php:2062-2068`), les relances de match ne partent qu'avec
`?executer=1` (`DeployController.php:1930`), le rattrapage de géocodage et
l'import LBA comptent à blanc par défaut. **La leçon du 8 septembre a bien été
appliquée.**

**Correction :** exiger `?executer=1` sur `migrate` aussi, comme partout
ailleurs. **Effort : petit.**

---

## G. Téléversements

**Vérifié, pour les cinq points d'entrée :**

| Entrée                 | Types acceptés                       | Taille max | Où                                                                             |
| ---------------------- | ------------------------------------ | ---------- | ------------------------------------------------------------------------------ |
| Photo de profil        | jpeg, jpg, png, webp + règle `image` | 2 Mo       | `apps/api/app/Http/Requests/CandidateProfile/UploadProfilePhotoRequest.php:17` |
| CV déposé              | pdf                                  | 5 Mo       | `.../UploadCvRequest.php:21`                                                   |
| CV de candidature      | pdf                                  | 5 Mo       | `.../Application/StoreApplicationRequest.php:25`                               |
| CV importé (lecture)   | pdf                                  | 5 Mo       | `.../CandidateProfile/ImportCvRequest.php:17`                                  |
| Logos entreprise / CFA | jpeg, jpg, png, webp + `image`       | 2 Mo       | `.../Company/UploadCompanyLogoRequest.php:17`                                  |

**C'est bien fait, et sur trois points précis :**

- la règle `mimes` de Laravel vérifie le **contenu** du fichier, pas
  l'extension que le navigateur annonce. Renommer un script en `.png` ne passe
  pas ;
- le **SVG est exclu** des images acceptées. C'est le bon choix et il est rare :
  un SVG peut contenir du JavaScript ;
- le nom de fichier enregistré est **entièrement reconstruit** par le serveur
  (`{id}-{uuid}.{extension déduite du contenu}`). Le nom choisi par
  l'utilisateur ne sert qu'à l'affichage, et il est nettoyé de tout séparateur
  de chemin et de tout retour à la ligne
  (`apps/api/app/Services/CandidateProfileService.php:323-334`).

**Je n'ai pas trouvé de faille sur ce thème.** Les deux seules remarques :
l'absence de limite de débit sur ces routes (voir D1), et le fait qu'aucun
`.htaccess` n'interdise l'exécution de scripts dans le dossier de stockage —
défense en profondeur, proposée en A1.

---

## H. En-têtes et CORS

### H1. Ce qui est déjà posé

**CORS** (`apps/api/config/cors.php`) : origines explicites, **pas de joker**,
limité aux chemins `api/*`, en-tête `Content-Disposition` exposé
volontairement. Correct.

**En-têtes du site** (`apps/web/public/.htaccess:37-54`) : HTTPS forcé,
redirection de `www` vers le domaine canonique, `Strict-Transport-Security`,
`X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`. Le fichier est copié dans
le build à chaque fois, donc il ne peut pas être oublié.

**En-têtes de l'API** (`apps/api/public/.htaccess:13-17`) : HTTPS forcé, HSTS,
nosniff, Referrer-Policy. **Il manque l'équivalent de `X-Frame-Options` côté
API.**

### H2. Ce qui manque : Content-Security-Policy et Permissions-Policy

**Ce que ça permet concrètement :** sans politique de contenu, si une faille
d'injection apparaissait un jour dans le site (aujourd'hui je n'en vois pas —
voir H3), le navigateur exécuterait sans broncher un script venu de n'importe
où et lui laisserait envoyer les données vers n'importe quel serveur. La
politique de contenu est le filet qui limite les dégâts d'une faille qu'on n'a
pas vue.

**Proposition réaliste pour le site**, à ajouter dans
`apps/web/public/.htaccess`, dans le bloc `mod_headers` existant :

```apache
Header always set Content-Security-Policy "default-src 'self'; \
script-src 'self' 'unsafe-inline' https://meet.jit.si; \
style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; \
font-src 'self' data: https://fonts.gstatic.com; \
img-src 'self' data: blob: https://api.jeuncy.com; \
connect-src 'self' https://api.jeuncy.com; \
frame-src https://meet.jit.si https://www.youtube.com https://www.openstreetmap.org; \
frame-ancestors 'none'; base-uri 'self'; form-action 'self'; object-src 'none'"

Header always set Permissions-Policy "camera=(self \"https://meet.jit.si\"), microphone=(self \"https://meet.jit.si\"), display-capture=(self \"https://meet.jit.si\"), geolocation=(), payment=(), usb=(), interest-cohort=()"
```

Trois précisions pour que ce ne soit pas un idéal inapplicable :

- **`'unsafe-inline'` dans `script-src` est assumé.** Le fichier `index.html`
  contient un petit script qui applique le thème avant le premier affichage
  (`apps/web/index.html:71-80`) et un bloc de données structurées. Sans cette
  tolérance, le site s'afficherait en blanc puis clignoterait. On pourra la
  retirer plus tard en déplaçant ce script dans un fichier.
- **Jitsi, YouTube et OpenStreetMap sont nécessaires** : ce sont les trois
  cadres réellement utilisés (`JitsiRoom.tsx:14`, la vidéo de profil, la carte
  de la page À propos). Les oublier casse la visio.
- **La `Permissions-Policy` doit nommer Jitsi explicitement**, sinon la caméra
  et le partage d'écran sont bloqués dans la salle de démo. Écrite comme
  ci-dessus, elle **interdit la géolocalisation sur le site** — c'est voulu,
  seule l'application mobile l'utilise.
- Si les paiements Stripe sont réactivés, il faudra ajouter
  `https://js.stripe.com` à `script-src` et `https://checkout.stripe.com` à
  `form-action`.

**Pour l'API** (`apps/api/public/.htaccess`), plus simple — elle ne sert que du
JSON, des PDF et des images :

```apache
Header always set Content-Security-Policy "default-src 'none'; frame-ancestors 'none'; base-uri 'none'"
Header always set X-Frame-Options "DENY"
Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"
```

**À faire dans cet ordre :** poser d'abord la politique du site, puis parcourir
le site entier (accueil, offres, profil, CV, visio, admin) en gardant la
console du navigateur ouverte. Toute ressource bloquée s'y affiche en clair.
Corriger, puis seulement ensuite passer à l'API.

**Effort : moyen** — l'écriture est courte, la vérification demande une heure.

### H3. Pas d'injection côté site

J'ai cherché les endroits où du contenu non échappé pourrait être injecté dans
la page : **aucun `dangerouslySetInnerHTML`, aucun `innerHTML`, aucun `eval`**
dans `apps/web/src`. Côté emails, toutes les chaînes venant de l'utilisateur
passent par la fonction d'échappement `e()` avant d'entrer dans le HTML
(`apps/api/app/Services/MailService.php:423-430`), y compris le message du
formulaire de contact. **Rien à signaler.**

---

## I. Comptes de démonstration

### I1. Le jeu de données crée un administrateur avec un mot de passe connu

**Vérifié :** `apps/api/database/seeders/DatabaseSeeder.php:22` définit le mot
de passe commun, et `:227-231` crée `admin@jeuncy.com` avec le rôle ADMIN.
**Aucune garde d'environnement** n'empêche ce fichier de s'exécuter en
production.

Deux éléments qui rendent l'incident peu probable : l'adresse des cinq autres
comptes se termine par `example.com`, donc eux ne gênent pas ; et il n'existe
**aucune route de déploiement capable de lancer le `seed`** — j'ai vérifié les
18, seule `migrate` existe. Il faudrait donc que le seed ait été lancé
manuellement, un jour, contre la base de production.

Mais c'est précisément le genre de chose qui arrive une fois et qu'on oublie.
Et la conséquence serait totale.

**La vérification, en une phrase :** connecte-toi sur `jeuncy.com/admin`,
onglet **Utilisateurs**, filtre **ADMIN**, et regarde si `admin@jeuncy.com`
figure dans la liste alors que ce n'est pas ton compte.

**Si oui :** clique **Suspendre** sur cette ligne tout de suite — l'effet est
immédiat, y compris sur une session déjà ouverte
(`apps/api/app/Auth/JwtGuard.php:45`). Puis reprends le compte par « mot de
passe oublié » depuis la boîte `admin@jeuncy.com`, qui est sur ton domaine, ou
laisse-le suspendu.

**Correction côté code, dans tous les cas :** faire échouer le seeder si
l'environnement est `production`, et faire de l'adresse de l'administrateur de
démonstration une adresse en `example.com` comme les autres.

**Effort : petit.**

---

## J. Secrets

### J1. Aucun secret n'a jamais été commité

**Vérifié :** recherche sur les **198 commits** de toutes les branches, en
cherchant les formes de clés Stripe (`sk_live_`, `sk_test_`, `whsec_`), Resend
(`re_`), Google (`GOCSPX-`), AWS (`AKIA…`), les clés privées
(`BEGIN … PRIVATE KEY`), les mots de passe de base et les URL de connexion
MySQL. **Zéro résultat.** (J'ai validé ma méthode de recherche sur un motif que
je savais présent, pour ne pas conclure sur un outil muet.)

Le seul résultat est `mysql://user:password@localhost:3306/jeuncy` dans un
`.env.example` du 16 juillet 2026 — c'est un exemple générique, pas un secret.

Les fichiers `.env` sont correctement ignorés, à la racine comme dans
`apps/api`, et `bootstrap/cache/` l'est aussi (le dossier qui avait fait tomber
l'API le 22 septembre est bien hors de Git — l'incident venait d'une
synchronisation FTP, pas du dépôt).

**Rien à signaler. C'est une bonne tenue sur 198 commits.**

### J2. Une remarque d'hygiène, pas de sécurité

**Vérifié :** 582 fichiers sont versionnés sous
`.claude/worktrees/agent-adeb1211dfc8f4373/` et
`.claude/worktrees/agent-ae074fe179b708006/` — des copies complètes du projet,
figées au 16 juillet 2026, laissées par d'anciennes sessions de travail.

Elles ne contiennent aucun secret (je les ai incluses dans la recherche
ci-dessus). Mais elles contiennent des copies périmées de fichiers de sécurité
— un `JwtGuard.php` d'avant la gestion des comptes suspendus, par exemple — et
elles répondront à n'importe quelle recherche dans le dépôt, ce qui peut faire
lire et corriger le mauvais fichier.

**Correction :** `git rm -r --cached .claude/worktrees` et ajouter
`.claude/worktrees/` au `.gitignore`.

**Effort : petit.**

---

## K. Dépendances

### K1. Côté site — une vulnérabilité réelle, les autres sont des outils de développement

**Vérifié par `pnpm audit` :** 1 critique, 14 élevées, 14 moyennes. Mais
**presque toutes concernent `vitest`, `vite`, `eslint` et leurs dépendances** —
des outils qui tournent sur ta machine, jamais sur le serveur. Elles ne sont
pas à ignorer, mais elles ne mettent aucune donnée en jeu.

**Une seule concerne le code réellement livré aux visiteurs :**

> **`react-router-dom` 6.30.4** — _Open redirect leading to XSS_ (gravité
> moyenne). Versions touchées : 6.30.2 à 6.30.5. Installé :
> `apps/web/node_modules/react-router-dom` = **6.30.4**, déclaré en
> `^6.28.1` dans `apps/web/package.json`.

**Ce que ça permet concrètement :** un lien piégé, envoyé à un candidat, peut
le faire quitter jeuncy.com vers un site contrôlé par un tiers — typiquement
une fausse page de connexion Jeuncy — ou, selon le chemin, faire exécuter du
code dans la page.

**Correction :** `pnpm --filter web up react-router-dom@^6.30.6`, puis
reconstruire et redéployer le site. Comme la contrainte est déjà `^6.28.1`,
aucun changement de code n'est nécessaire.

**Effort : petit** — mais rappel de la leçon du 22 septembre : les `assets/`
partent en premier, `index.html` en dernier.

### K2. Côté API — rien d'exploitable à distance, mais des mises à jour à faire

**Vérifié par `composer audit` :** 26 avis sur 6 paquets.

| Paquet                | Installé | À viser   | Ce que ça change concrètement                                                                                                                                                                                                                                                                             |
| --------------------- | -------- | --------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `guzzlehttp/guzzle`   | 7.14.2   | ≥ 7.15.2  | Une élevée : un nom d'hôte écrit de façon non canonique contourne les contrôles d'hôte. Guzzle sert ici à appeler Resend, Stripe, le registre des entreprises et La bonne alternance — des adresses que tu fixes toi-même, donc exploitation très improbable. À jour quand même.                          |
| `dompdf/dompdf`       | 3.1.5    | ≥ 3.1.6   | Six avis, dont une **lecture de fichier local par une image SVG en data-URI**. Le moteur ne reçoit que le gabarit de CV, dont les images sont produites par le serveur — pas de contenu hostile aujourd'hui. Mais c'est le composant qui traite les données des candidats, et la mise à jour est mineure. |
| `league/commonmark`   | 2.8.3    | ≥ 2.10.2  | Huit avis de déni de service sur du Markdown piégé, et un contournement de filtre XSS. **Dépendance indirecte, non utilisée directement par Jeuncy** — je n'ai trouvé aucun appel. Faible priorité.                                                                                                       |
| `laravel/framework`   | 13.20.0  | ≥ 13.30.0 | Une faible : XSS dans la page de débogage. Sans effet ici, `APP_DEBUG` valant `false` par défaut (`apps/api/config/app.php:42`).                                                                                                                                                                          |
| `league/flysystem`    | 3.35.2   | > 3.35.2  | Faible, contournement d'un contrôle de caractères dans un chemin. Les chemins sont fabriqués par le serveur.                                                                                                                                                                                              |
| `phpseclib/phpseclib` | 3.0.55   | ≥ 3.0.57  | Moyenne, cryptographie. Dépendance indirecte non utilisée.                                                                                                                                                                                                                                                |

**Correction :** `composer update` dans `apps/api`, puis `php artisan test`
avant d'envoyer. Attention : `composer.json` déclare `firebase/php-jwt`,
`laravel/socialite` et `stripe/stripe-php` en `"*"` — c'est-à-dire « n'importe
quelle version », ce qui rend une mise à jour imprévisible. Les épingler à une
version majeure (`^7.1`, `^5.28`, `^21.0`) avant de lancer la commande.

**Effort : moyen** — la mise à jour elle-même est rapide, c'est le
redéploiement de `vendor/` par FTP sur l'hébergement mutualisé qui coûte.

---

## L. RGPD

### L1. Un recruteur ayant téléchargé un CV ne peut plus supprimer son compte

**Vérifié :** la table du journal de téléchargements attache l'utilisateur en
`restrictOnDelete`
(`apps/api/database/migrations/2026_09_02_100001_create_cv_downloads_table.php:33`).
Le commentaire de la migration justifie ce choix en supposant que _« les
comptes supprimés sont de toute façon anonymisés et non effacés »_ — or
`apps/api/app/Services/AccountService.php:124` ne déclenche l'anonymisation
**que s'il existe un paiement ou un abonnement**. Depuis la gratuité du
15 septembre, une entreprise peut parfaitement utiliser la CVthèque sans jamais
rien payer. Dans ce cas, `AccountService.php:153` appelle `$user->delete()`,
qui se heurte à la contrainte.

**Ce que ça permet concrètement :** ce compte ne peut pas être supprimé. La
demande échoue sur une erreur serveur, sans message compréhensible. Le droit à
l'effacement est inexploitable pour cette population — et c'est exactement la
population des entreprises actives.

**Correction :** ajouter `|| $user->cvDownloads()->exists()` à la condition de
la ligne 124, ce qui bascule ces comptes sur le chemin d'anonymisation déjà
écrit et déjà testé. Et ajouter le test correspondant : il n'en existe aucun
pour ce cas.

**Effort : petit.**

### L2. Aucune purge des comptes inactifs, alors que les documents commerciaux l'annoncent

**Vérifié :** il n'existe aucune commande, aucune tâche planifiée qui supprime
un compte après une période d'inactivité. Les deux purges existantes font autre
chose : `cvs:archive-inactive` efface le PDF d'un CV après **15 jours** sans
connexion (`apps/api/app/Console/Commands/ArchiveInactiveCvs.php:15`), et
`cv-downloads:purge` efface les lignes du journal après **3 ans**
(`apps/api/app/Console/Commands/PurgeCvDownloads.php:24`) — celle-là est
correctement câblée et conforme à ce qui est annoncé.

Or la durée de **3 ans** est écrite noir sur blanc dans
`docs/commercial/guide-commercial-jeuncy.src.html:149` et `:356`, et dans
`docs/commercial/plaquette-candidats.src.html:158`. Elle n'est ni implémentée,
ni même reprise dans la politique de confidentialité, qui se contente de
« tant que le compte est actif » (`apps/web/src/pages/PrivacyPolicy.tsx:277-279`)
— une formulation sans plafond, que le RGPD n'accepte pas (art. 13.2.a : il
faut une durée, ou un critère permettant de la déterminer).

**Ce que ça permet concrètement :** les CV de jeunes inscrits en 2026 seront
toujours en base en 2032, et une plaquette commerciale affirme le contraire à
leurs CFA.

**Correction :** écrire une commande `accounts:purge-inactive` qui anonymise
(et non supprime, pour éviter le problème L1) les comptes sans connexion depuis
3 ans, en prévenant par email 30 jours avant. La planifier comme les autres
dans `bootstrap/app.php`. Et aligner la politique de confidentialité sur la
règle choisie.

**Effort : moyen.**

### L3. La politique de confidentialité décrit l'archivage des CV de façon fausse

**Vérifié :** `apps/web/src/pages/PrivacyPolicy.tsx:290-293` affirme qu'un CV
non régénéré depuis 15 jours est archivé et **« reste consultable »**.

Deux inexactitudes : le déclencheur n'est pas la non-régénération du CV mais
l'inactivité du **compte** (`ArchiveInactiveCvs.php:23-28`) ; et le PDF est
**réellement effacé du disque** (`apps/api/app/Services/CvService.php:241-248`),
donc il n'est plus consultable du tout.

**Ce que ça permet concrètement :** un candidat qui revient après seize jours
trouve un CV inaccessible, après avoir lu l'inverse. C'est un support client
garanti, et une inexactitude dans un document qui engage juridiquement.

**Correction :** réécrire ces trois lignes pour dire ce que le code fait.
**Effort : petit.**

### L4. La lettre d'information — à traiter avant le premier envoi réel

Une autre session travaille en ce moment sur cette fonctionnalité. Je n'ai donc
**pas jugé son état d'avancement** : il manquait un fichier de commande à un
moment de mon audit et il n'y était plus l'instant d'après, ce qui est normal
pour du travail en cours. Ce qui suit porte sur les choix de conception
visibles, pas sur l'inachevé.

**Ce qui est déjà très bien fait — et c'est rare :**

- le lien de désinscription est un **paramètre obligatoire** de la fonction de
  rendu (`apps/api/app/Services/NewsletterService.php:207`) : il ne _peut pas_
  être oublié dans un envoi ;
- il est présent dans la version HTML **et** dans la version texte ;
- les en-têtes `List-Unsubscribe` et `List-Unsubscribe-Post` (RFC 8058) sont
  posés (`apps/api/app/Services/MailService.php:508-509`), ce qui donne le
  bouton « se désabonner » natif de Gmail et d'Outlook ;
- le lien est **signé cryptographiquement** (`NewsletterService.php:255-260`) :
  on ne peut pas désinscrire quelqu'un d'autre en changeant un numéro ;
- le GET n'agit pas, seul le POST désinscrit — ce qui évite qu'un antivirus ou
  un aperçu de lien désabonne les gens à leur insu ;
- la table de suivi ne stocke **pas les adresses email**, seulement un numéro
  de compte.

**Les deux points à trancher avant le premier envoi :**

**a) Le destinataire est abonné par défaut.** Il n'existe aucune colonne de
consentement, seulement `newsletter_unsubscribed_at` — vide signifiant
« abonné » (`apps/api/database/migrations/2026_10_01_100000_add_newsletter_unsubscribe_to_users_table.php:18-27`).
La base légale qui ressort du code est donc l'intérêt légitime, pas le
consentement. **C'est défendable** pour une lettre adressée à des gens inscrits
sur la plateforme et portant sur le service lui-même. Ça ne l'est plus si la
lettre devient promotionnelle.

**b) Aucun traitement particulier pour les mineurs.** La sélection des
destinataires (`apps/api/app/Services/NewsletterService.php:275-287`) n'a aucun
filtre d'âge. Un jeune de 15 ans reçoit la lettre comme un majeur, sur la seule
base d'un abonnement par défaut. Le projet sait pourtant filtrer par âge
ailleurs. La CNIL attend une vigilance renforcée sur la prospection visant des
mineurs — et c'est le point qui se verrait le plus en cas de contrôle.

**c) La lettre n'est pas mentionnée dans la politique de confidentialité.**
Ni dans les finalités, ni dans les bases légales, ni dans les destinataires —
où Resend n'est listé que « pour les emails **transactionnels** »
(`apps/web/src/pages/PrivacyPolicy.tsx:239-254`). Un traitement qui existe sans
information préalable de la personne, c'est l'art. 13.

**Correction avant le premier envoi :** ajouter un paragraphe à la politique de
confidentialité, et décider explicitement si les comptes de moins de 18 ans
reçoivent la lettre. **Effort : petit** pour les deux.

### L5. L'export de données est incomplet sur deux points

**Vérifié :** `apps/api/app/Services/AccountService.php:36-94`.

- Il ne contient pas le **journal des accès à son CV**, alors que la politique
  promet explicitement _« il nous permet de vous indiquer, si vous le demandez,
  qui a accédé à votre CV »_ (`PrivacyPolicy.tsx:224-228`). Tenir cette
  promesse demande aujourd'hui une intervention manuelle en base.
- Il ne contient pas `age_confirmed_at` ni `newsletter_unsubscribed_at`
  (`AccountService.php:37-42`) — c'est-à-dire précisément les preuves datées de
  consentement et d'opposition, celles que la personne a le plus de raisons de
  vouloir vérifier.

**Correction :** ajouter ces trois éléments au tableau d'export.
**Effort : petit.**

### L6. Ce qui est déjà conforme, et bien construit

Pour que la liste ci-dessus ne donne pas une image fausse :

- l'**anonymisation** plutôt que la suppression quand il existe des pièces
  comptables est correctement implémentée, avec les deux chemins séparés
  (`AccountService.php:124`, `:133-155`) ;
- l'adresse anonymisée porte un **suffixe aléatoire**, ce qui empêche un tiers
  de pré-enregistrer l'adresse d'une victime pour bloquer sa suppression à
  jamais (`AccountService.php:140-145`) — c'est une attaque subtile, anticipée ;
- les fichiers sont effacés **après** validation de la transaction, pas pendant
  (`AccountService.php:157-166`) : un échec SQL ne détruit plus des fichiers ;
- la suppression **exige une confirmation par l'email du compte** ;
- la **photo n'est visible des recruteurs que si le candidat l'a acceptée**,
  avec un défaut à « non » en base
  (`apps/api/database/migrations/2026_09_22_100000_add_match_fields_to_candidate_profiles_table.php:57`) :
  un vrai opt-in, pour la donnée la plus identifiante d'un mineur ;
- la **position GPS est arrondie à environ 1 km côté serveur**, pas seulement
  côté application (`apps/api/app/Services/CandidateProfileService.php:112-113`) :
  la promesse faite à l'usager est vérifiable, pas déclarative ;
- le journal de téléchargements ne garde **ni adresse IP ni navigateur**, et il
  part en cascade quand le candidat supprime son profil : le droit du candidat
  prime sur le confort de traçabilité. C'est le bon arbitrage.

---

# Ce que je n'ai pas pu vérifier

Un audit qui tait ses angles morts ment. Voici les miens.

**1. L'état réel de la production.** Je n'ai touché ni jeuncy.com ni
api.jeuncy.com, comme demandé. Je ne sais donc pas :

- si `admin@jeuncy.com` existe (→ vérification en section I1) ;
- si `APP_DEBUG` vaut bien `false` sur le serveur. Le défaut du code est
  `false`, mais le `.env` du serveur peut dire autre chose. **À vérifier :**
  `/deploy/{jeton}/env-check` affiche la configuration effective ;
- si les en-têtes sont réellement servis. OVH peut ignorer un `.htaccess` si
  `mod_headers` n'est pas chargé. **À vérifier :** ouvrir jeuncy.com avec
  l'onglet Réseau du navigateur, cliquer sur la première requête, lire les
  en-têtes de réponse ;
- si le listage de répertoire est bien désactivé sur `/storage`. La directive
  existe mais elle est enfermée dans une condition sur deux modules Apache
  (`apps/api/public/.htaccess:19-22`). **À vérifier :** ouvrir
  `https://api.jeuncy.com/storage/photos/` dans un navigateur. Attendu : une
  erreur 403 ou 404. Si une liste de fichiers s'affiche, c'est à traiter en
  urgence, avant tout le reste de ce document.

**2. Si le jeu de données de démonstration a été exécuté en production.** Seule
la liste des utilisateurs le dira.

**3. Si le jeton de déploiement a déjà fuité.** Les journaux d'accès d'OVH le
contiennent nécessairement (il est dans l'adresse) ; savoir qui les a lus est
hors de portée. C'est une raison de plus de le remplacer une fois, par
principe, en même temps que la séparation en deux jetons.

**4. Le comportement réel sous charge.** Je n'ai lancé aucun test de
performance. Mon constat sur la génération de CV (D1) est déduit du code —
onze rendus PDF possibles par appel — et pas mesuré. Avant de corriger, on peut
le mesurer : appeler `POST candidate-profile/cv` dix fois de suite sur
l'API locale et regarder le temps de réponse.

**5. Les trois constats RGPD sur les fichiers orphelins** (A2, L1) sont lus
dans le code et confirmés par l'absence de test couvrant ces chemins. Ils
gagneraient à être confirmés par un test de bout en bout avant correction,
plutôt que corrigés sur ma parole.

**6. Le travail en cours sur la lettre d'information.** Une autre session
modifie `MailService`, `DeployController`, `routes/web.php` et des migrations
en ce moment même. Ce que j'ai lu de ces fichiers était vrai à l'instant où je
l'ai lu. Les constats de la section L4 portent sur des choix de conception, qui
ne devraient pas bouger, mais il faut les relire une fois ce travail terminé.

**7. Je n'ai pas audité l'application mobile en profondeur.** J'ai vérifié que
le jeton long est bien rangé dans le coffre du téléphone, avec la bonne option
(`apps/mobile/src/lib/secure-store.ts:29-33`), et qu'aucune adresse en clair ni
aucun secret n'est codé en dur. Je n'ai pas examiné le reste : elle n'est
publiée sur aucun magasin, le risque est donc nul aujourd'hui, mais il faudra
le faire avant le lot 6.

---

# Ce qui est déjà bien

À relire le jour où cette liste de problèmes donnera l'impression que tout est
à refaire.

**Session et authentification**

- Jeton long dans un cookie `httpOnly`, inaccessible au JavaScript, avec un
  chemin restreint à `/api/auth` et `secure` en production.
- **Révocation immédiate** par `token_version` : une déconnexion ou un
  changement de mot de passe coupe toutes les sessions sur-le-champ, sans
  attendre l'expiration. Ce n'est pas gratuit avec ce type de jeton.
- Un compte suspendu est coupé **en cours de session**, pas à la prochaine
  connexion (`JwtGuard.php:45`).
- Algorithme de signature fixé, pas de valeur par défaut pour les secrets.
- Le jeton de réinitialisation de mot de passe est à **usage unique sans état
  serveur**, par une empreinte du mot de passe en cours
  (`JwtService.php:62-67`) : élégant et correct.
- Réponse identique que l'email existe ou non, sur le « mot de passe oublié »
  comme sur la connexion.
- La garde anti-XSS du mode mobile, et surtout le fait qu'elle ait été testée
  pour la bonne raison après qu'un premier test l'eut validée par accident.

**Autorisation**

- 15 ressources sur 15 correctement gardées. Aucun trou.
- Les fiches de match répondent **404 et non 403** pour une ligne qui n'est pas
  à vous : dire « interdit » confirmerait l'existence.
- Un employeur ne peut pas marquer un candidat hors de sa portée en devinant un
  numéro : la cible est rejouée contre la requête du deck.
- Le présentateur de carte candidat est une **liste blanche**, pas une liste
  noire : une colonne ajoutée demain n'apparaît pas toute seule. C'est le bon
  sens de l'oubli, et c'est explicitement raisonné dans le fichier.
- Le deck employeur ne lit **structurellement jamais** la position GPS du
  téléphone : la décision « aucune distance côté employeur » est garantie par
  le schéma de la base, pas par une règle qu'on peut oublier.
- Impossible de s'inscrire en ADMIN ou STAFF.

**Données et vie privée**

- La CVthèque ne **cherche pas** dans ce qu'elle ne montre pas : le filtre par
  ville a été supprimé parce que filtrer sans afficher révèle par inférence.
  C'est un raisonnement de spécialiste.
- Le téléchargement depuis la CVthèque sert les octets, pas l'adresse, pour que
  l'URL ne circule pas. La bonne construction — qu'il reste à appliquer aux
  candidatures (A1).
- Opt-in strict sur la photo visible des recruteurs.
- Position GPS arrondie côté serveur.
- Les sondes de déploiement masquent les petits effectifs par département
  (règle du secret statistique de l'INSEE) et les emails dans les journaux.

**Entrées et sorties**

- Validation d'upload stricte, SVG exclu, nom de fichier reconstruit par le
  serveur.
- Signature des webhooks Stripe vérifiée, et la vérification délibérément
  séparée du client Stripe pour rester testable sans réseau.
- Échappement systématique dans les emails.
- Aucun `dangerouslySetInnerHTML` dans tout le site.
- Aucune injection SQL possible : les 20 requêtes brutes sont correctement
  paramétrées.
- CORS sans joker, HTTPS forcé des deux côtés, `www` redirigé vers le domaine
  canonique.

**Exploitation**

- Garde de déploiement en comparaison à temps constant, inerte par défaut.
- Toutes les actions à effet de masse exigent un paramètre explicite de
  confirmation — la leçon des 37 notifications du 8 septembre a bien été
  apprise et généralisée.
- Le journal d'erreurs exposé masque les emails et les jetons.
- Aucun secret dans 198 commits.
- 72 fichiers de tests fonctionnels, dont un dédié à l'exposition des données
  publiques et un à la garde d'âge.

Et, plus largement : les décisions de sécurité de ce projet sont **écrites dans
le code, avec leur raison**. C'est ce qui a rendu cet audit rapide et c'est ce
qui empêchera quelqu'un de défaire par inadvertance une protection dont il
n'aurait pas compris l'objet. C'est plus précieux que n'importe lequel des
correctifs listés plus haut.
