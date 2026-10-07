# Journal des envois de la lettre Jeuncy

> Une ligne par édition envoyée. Tenu à la main après chaque envoi, à partir du
> relevé `GET /deploy/{token}/newsletter?edition=<slug>` (mode à blanc, il
> n'écrit rien).
>
> **À quoi il sert.** Les chiffres d'un envoi ne se reconstituent pas après
> coup : la base garde l'état courant, pas l'historique. Sans ce fichier, on ne
> saura plus dans trois mois combien de candidats ont reçu quoi, ni si le taux
> de désinscription monte. Et un taux qui monte est le seul signal précoce
> qu'une lettre agace plutôt qu'elle n'aide.

## Deux pièges de lecture, à connaître avant d'interpréter une ligne

**Le chiffre de l'objet n'est pas celui qu'on relit.** L'édition stockée en
base ne contient pas de nombre : elle porte `[[offres_total]]`, et le serveur
le remplace au moment d'expédier. Donc la route de relecture affiche le
compteur **d'aujourd'hui**, pas celui qui est réellement parti dans la boîte
des candidats. La colonne « Annoncé » ci-dessous conserve la valeur du jour de
l'envoi — c'est la seule trace fiable.

**`destinataires: 0` après un envoi est normal**, et ne veut pas dire que
personne n'a reçu la lettre. Le compteur signale ceux qui restent à servir ;
une fois l'édition expédiée, il tombe à zéro. Lire `envois_enregistres` à la
place.

## Envois

| Édition                | Envoyée le         | Objet (chiffre annoncé)                                    | Candidats | Envoyés | Échecs | Désinscriptions (cumul) | Remarques                                                                                                                                                                                                                                                                           |
| ---------------------- | ------------------ | ---------------------------------------------------------- | --------- | ------- | ------ | ----------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `2026-10-01-lettre-01` | 2026-10-01, ~14h30 | « **6 824** offres d'alternance t'attendent sur Jeuncy »   | 125       | **125** | 0      | **1** (0,8 %)           | Première lettre. Envoi en deux temps : 5 destinataires, vérification, puis les 120 restants en 89 s. Contenu : volume d'offres, application mobile en chantier (sans date), rendez-vous hebdomadaire.                                                                               |
| `2026-10-06-lettre-02` | 2026-10-06, ~10h40 | « **6 405** offres ce matin, et de nouvelles chaque nuit » | 124       | **124** | 0      | **1** (0,8 %)           | Destinataires = 125 − 1 désinscrit, exclu automatiquement. Envoyée un lundi+1 : le lundi 5 est passé sans lettre, faute d'envoi automatique. Contenu : le stock change chaque nuit (donc revenir souvent), comment filtrer, et la photo de profil désormais acceptée jusqu'à 12 Mo. |

## Ce qu'on surveille d'une lettre à l'autre

- **Les échecs.** Un échec n'est pas réessayé automatiquement, à dessein :
  renvoyer en boucle à une adresse qui refuse abîme la réputation du domaine
  pour tous les autres. Plusieurs échecs sur la même édition méritent de
  regarder le journal d'erreurs avant la lettre suivante.
- **Les désinscriptions.** Sous 1 %, c'est sain. Au-delà de 3 %, c'est que la
  lettre ne tient pas sa promesse — fréquence, longueur ou contenu.
- **Le nombre de candidats**, qui doit monter. S'il stagne alors que des
  inscriptions ont lieu, c'est que des comptes sortent par ailleurs
  (suppression, suspension) et ça vaut un coup d'œil.
