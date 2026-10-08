---
description: Reprendre le travail là où la dernière session l'a laissé
---

Reprendre, c'est retrouver ce qui restait en cours, puis donner à Pierre **une
seule étape**, en réponse courte. Dans l'ordre :

1. Lire la **dernière section « Où on s'est arrêté »** à la fin de `CLAUDE.md` :
   c'est le point d'arrêt écrit en fin de session (prochaine étape exacte,
   environnement de test, travail non commité, décisions en attente).
2. `git status` et `git log --oneline -10` sur tout le repo : des fichiers
   modifiés non commités confirment le travail interrompu. Ne jamais commiter
   sans le feu vert de Pierre.
3. Vérifier, sans le croire sur parole, ce que la note affirme : un point
   « en attente » peut avoir été réglé depuis (voir mémoire
   [[diagnostiquer-avant-de-supposer]]).
4. Si la reprise demande l'environnement de test (API locale, Expo, base de
   démo), le relancer comme décrit dans la note, vérifier qu'il répond, puis
   seulement donner l'étape à Pierre.
5. Répondre à Pierre en **trois lignes au plus** : où on en est, puis **la
   seule prochaine étape** qu'il doit faire. Jamais la liste complète (mémoire
   [[une-etape-a-la-fois]]).

Ne jamais tester en production ce qui touche de vrais candidats : la base de
test et `MatchDemoSeeder` existent pour ça.
