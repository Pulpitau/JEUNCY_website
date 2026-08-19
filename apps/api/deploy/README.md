# Fichiers propres au déploiement OVH

Ce dossier n'est **pas** utilisé en développement. Il contient les fichiers qui
diffèrent entre le dépôt et le serveur de production, et qui n'existaient
jusqu'ici que sur le serveur — donc irrécupérables en cas d'écrasement.

## Pourquoi ils diffèrent

L'hébergement OVH mutualisé impose une racine web publique. L'application est
donc scindée en deux dossiers à la racine du FTP :

| Dossier    | Contenu                        | Accessible par URL |
| ---------- | ------------------------------ | ------------------ |
| `www/`     | frontend compilé (`dist/`)     | oui                |
| `api/`     | `apps/api/public/`             | oui                |
| `api-app/` | tout le reste de l'app Laravel | **non**            |

Garder `vendor/`, `.env`, `app/` et `storage/` hors de toute URL est ce qui
empêche de les télécharger. C'est une protection, pas une contrainte
d'organisation.

## `index.php`

Dans le dépôt, `apps/api/public/index.php` remonte d'un cran (`../vendor`,
`../bootstrap`) parce que `public/` y est un sous-dossier de l'application. En
production, ce n'est plus vrai : `api/` et `api-app/` sont côte à côte, donc
les chemins pointent vers `../api-app/`.

**Ne jamais envoyer `apps/api/public/index.php` sur le serveur.** C'est le
fichier de ce dossier qui va dans `api/index.php`.

### Symptôme d'une erreur sur ce point

Toutes les routes PHP répondent **500 avec un corps vide** (y compris
`/index.php` appelé directement), alors que les fichiers statiques
(`/robots.txt`, `/favicon.ico`) répondent 200. PHP s'arrête sur l'autoloader
introuvable, avant que Laravel n'ait pu installer son gestionnaire d'erreurs —
d'où l'absence totale de message.

Arrivé le 2026-08-19, en envoyant le dossier `public/` entier alors que seul
le `.htaccess` était concerné.

## `.htaccess`

Celui de `apps/api/public/.htaccess` est correct pour la production et peut
être envoyé tel quel dans `api/`. Il porte la redirection HTTPS et les
en-têtes de sécurité en plus des règles Laravel.

Attention : il est **différent** de `apps/web/public/.htaccess`, qui va dans
`www/` et contient en plus le routage de l'application monopage.
