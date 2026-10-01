# Documents commerciaux Jeuncy

Sources HTML des documents de prospection, et leur rendu PDF dans `pdf/`.

| Document                     | Source                               | Pages     | Usage                                                            |
| ---------------------------- | ------------------------------------ | --------- | ---------------------------------------------------------------- |
| **Plaquette CFA**            | `plaquette-cfa.src.html`             | 4 (A4)    | **À jour (2026-09-28)** — prospection des CFA, modèle gratuit    |
| Pitch deck entreprises & CFA | `pitch-deck-jeuncy.src.html`         | 14 (16:9) | Présentation en rendez-vous                                      |
| Plaquette entreprises & CFA  | `plaquette-entreprises-cfa.src.html` | 8 (A4)    | À envoyer aux prospects                                          |
| Guide commercial             | `guide-commercial-jeuncy.src.html`   | 11 (A4)   | **Interne** : argumentaire, scripts, objections, règles de tarif |
| One-page candidats           | `onepage-candidats.src.html`         | 1 (A4)    | Distribution en CFA, affichage                                   |
| Plaquette candidats          | `plaquette-candidats.src.html`       | 6 (A4)    | À envoyer aux jeunes, aux CFA                                    |

## Régénérer un PDF après une modification

Modifier le fichier `*.src.html` concerné, puis :

```bash
php build.php pitch-deck-jeuncy
```

Le script injecte la charte (`base.css`), les icônes (`icons.html`) et les
logos, écrit `dist/<nom>.html` et produit `dist/<Nom>.pdf` avec Chrome en mode
headless. Il affiche le nombre de pages : il doit rester celui du tableau
ci-dessus. Copier ensuite le PDF dans `pdf/`.

Chaque page (`.page`) ou diapositive (`.slide`) a une taille fixe et coupe ce
qui déborde : après une modification de texte, vérifier que la page n'est pas
tronquée.

## ⚠ Les cinq anciens documents portent encore les tarifs

Le deck, le guide commercial et les trois plaquettes datent d'avant le passage
au **gratuit** (décision du 2026-09-15, `CLAUDE.md`). Ils annoncent 299 €/mois,
l'offre fondateur et l'essai de 15 jours — **ne pas les envoyer tels quels**.
Seule `plaquette-cfa` est à jour. Les autres sont à réécrire.

## Ce qui doit rester à jour

- **Le nombre d'offres en ligne** (plaquette CFA, couverture et page 2) : il
  bouge chaque nuit avec l'import. Le lire sur `GET /api/job-offers/count`
  avant un rendez-vous, et régénérer le PDF s'il a beaucoup changé.
- **Le nombre de candidats**, si un document l'affiche : chiffre exact dans
  `/admin`. Ne jamais l'arrondir vers le haut.
- **Ce qui n'est pas encore ouvert** : l'application mobile est présentée comme
  une **ouverture pilote sur les Pyrénées-Orientales**, pas comme une
  application téléchargeable — c'est l'état réel, et un prospect vérifie en dix
  secondes.

## L'histoire de Jeuncy

Le récit fondateur (deck diapo 3, plaquettes, guide page 3) est une mise en
récit à **faire valider par la direction** avant tout usage externe. Les détails
biographiques doivent correspondre à ce que les fondateurs ont réellement vécu
et acceptent de raconter.
