# Documents commerciaux Jeuncy

Sources HTML des documents de prospection, et leur rendu PDF dans `pdf/`.

| Document                     | Source                               | Pages     | Usage                                                            |
| ---------------------------- | ------------------------------------ | --------- | ---------------------------------------------------------------- |
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

## Ce qui doit rester à jour

- **Le nombre de candidats** (deck diapo 10, guide page 2, plaquette entreprises
  page 8) : lire le chiffre exact dans `/admin` avant un rendez-vous.
- **Les places fondateur restantes** : compteur public sur `/tarifs`.
- **Les tarifs** : source de vérité dans `apps/api/config/services.php`.

## L'histoire de Jeuncy

Le récit fondateur (deck diapo 3, plaquettes, guide page 3) est une mise en
récit à **faire valider par la direction** avant tout usage externe. Les détails
biographiques doivent correspondre à ce que les fondateurs ont réellement vécu
et acceptent de raconter.
