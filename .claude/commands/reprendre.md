---
description: Reprendre le travail là où la dernière session l'a laissé
---

Reprendre, c'est retrouver ce qui restait en cours — pas relire tout
`CLAUDE.md` comme une liste à cocher (il est tenu à la main et prend du retard
de plusieurs sessions, voir §11). Dans l'ordre :

1. `git status` et `git diff` sur tout le repo (pas seulement `apps/mobile`) :
   des fichiers modifiés non commités sont le signal le plus fiable d'un
   travail interrompu en plein milieu.
2. `git log --oneline -20` : le dernier commit donne la date de « hier »
   réelle et le sujet du dernier chantier fini.
3. Si des fichiers sont modifiés : lire le diff en entier, comprendre ce qui
   manque pour que ce soit fini (test qui couvre le changement, suite
   correspondante qui passe), finir, puis **demander avant de commit** — ne
   jamais commit sans confirmation explicite.
4. Si rien n'est en cours : chercher le dernier point ouvert non résolu —
   dans l'ordre de fiabilité, un memory `project`/`feedback` récent, puis les
   dernières sections « Connu et à traiter plus tard » de `CLAUDE.md` (en se
   rappelant qu'elles peuvent déjà être résolues par un commit plus récent :
   vérifier dans le code, jamais prendre la note pour un fait actuel).
5. Annoncer en une phrase ce qui semble être « où on s'est arrêté » et
   pourquoi (quel fichier, quel commit, quelle note), avant de continuer —
   si deux pistes sont également plausibles, les nommer toutes les deux et
   demander laquelle plutôt que de deviner.

Ne jamais supposer l'état de la production à partir d'une note : une note dit
ce qui était vrai quand elle a été écrite, pas ce qui est vrai maintenant
(voir memory [[diagnostiquer-avant-de-supposer]]).
