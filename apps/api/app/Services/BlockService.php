<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\UserBlock;

/**
 * Blocage d'un compte par un autre (MOBILE.md §7).
 *
 * Un blocage vaut DANS LES DEUX SENS : celui qui bloque ne voit plus, et
 * n'est plus vu. C'est le seul comportement défendable — un blocage à sens
 * unique laisserait l'employeur bloqué continuer à voir la carte du
 * candidat qui l'a écarté, donc à le solliciter par un autre chemin.
 *
 * L'exclusion est SILENCIEUSE partout où elle s'applique (les deux decks, la
 * CVthèque, la liste des candidatures, les matchs) : une ligne absente est
 * indiscernable d'une ligne qui n'existe pas. Dire « ce profil vous a
 * bloqué » révélerait un geste que personne n'a demandé à rendre public.
 *
 * Service partagé : il est appelé à l'exécution par CvthequeService,
 * DiscoverService, InterestService, MatchService et ApplicationService.
 */
class BlockService
{
    /**
     * Idempotent : rebloquer quelqu'un rend la ligne existante plutôt que de
     * lever un conflit. Un client mobile sur un réseau instable rejoue ses
     * requêtes ; un « vous avez déjà bloqué ce compte » serait un message
     * d'erreur pour un geste qui a réussi.
     */
    public function block(User $user, int $blockedUserId): UserBlock
    {
        if ($blockedUserId === $user->id) {
            throw new ApiException('CANNOT_BLOCK_SELF', 'Tu ne peux pas te bloquer toi-même.', 400);
        }

        if (! User::whereKey($blockedUserId)->exists()) {
            throw new ApiException('BLOCK_TARGET_NOT_FOUND', "Impossible d'identifier ce compte.", 404);
        }

        return UserBlock::firstOrCreate([
            'blocker_user_id' => $user->id,
            'blocked_user_id' => $blockedUserId,
        ]);
    }

    /**
     * Seul l'auteur du blocage peut le lever.
     *
     * 403 et non 404 ici, contrairement aux matchs : l'identifiant d'un
     * blocage n'est connu que de celui qui l'a posé (il lui est rendu à la
     * création), donc confirmer son existence n'apprend rien à un tiers qui
     * en devinerait un.
     */
    public function unblock(User $user, UserBlock $block): void
    {
        if ($block->blocker_user_id !== $user->id) {
            throw new ApiException('FORBIDDEN', "Ce blocage ne t'appartient pas.", 403);
        }

        $block->delete();
    }

    /**
     * Identifiants des comptes à ne plus croiser, dans les deux sens : ceux
     * que cet utilisateur a bloqués ET ceux qui l'ont bloqué.
     *
     * Une seule requête, et un tableau plutôt qu'une collection : les
     * appelants s'en servent en `whereNotIn`, et une liste vide doit pouvoir
     * être testée par `!== []` sans ajouter une clause inutile à la requête.
     *
     * @return list<int>
     */
    public function blockedUserIdsFor(User $user): array
    {
        $ids = UserBlock::query()
            ->where('blocker_user_id', $user->id)
            ->orWhere('blocked_user_id', $user->id)
            ->get(['blocker_user_id', 'blocked_user_id'])
            ->flatMap(fn (UserBlock $block) => [$block->blocker_user_id, $block->blocked_user_id])
            ->unique()
            ->reject(fn (int $id) => $id === $user->id)
            ->values()
            ->all();

        return array_map('intval', $ids);
    }
}
