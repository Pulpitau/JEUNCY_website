<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Models\UserBlock;
use App\Services\BlockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Blocage d'un compte par un autre (contrat lot 1 §1.5, MOBILE.md §7).
 *
 * Le test qui compte le plus est celui des DEUX SENS : un blocage a sens
 * unique laisserait l'employeur bloque continuer a voir la carte du candidat
 * qui l'a ecarte — donc a le solliciter par un autre chemin, ce qui vide le
 * blocage de son sens. C'est aussi la seule regle que les cinq services
 * appelants (CVtheque, deux decks, matchs, candidatures) partagent sans la
 * reecrire.
 */
class BlockServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlockService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(BlockService::class);
    }

    private function compte(string $email): User
    {
        return User::factory()->candidate()->create(['email' => $email]);
    }

    public function test_blocked_user_ids_work_in_both_directions(): void
    {
        $moi = $this->compte('moi@example.test');
        $queJeBloque = $this->compte('bloque@example.test');
        $quiMeBloque = $this->compte('bloqueur@example.test');
        $tiers = $this->compte('tiers@example.test');

        $this->service->block($moi, $queJeBloque->id);
        $this->service->block($quiMeBloque, $moi->id);
        // Blocage entre deux autres comptes : ne me concerne pas.
        $this->service->block($tiers, $queJeBloque->id);

        $ids = $this->service->blockedUserIdsFor($moi);
        sort($ids);
        $attendus = [$queJeBloque->id, $quiMeBloque->id];
        sort($attendus);

        $this->assertSame($attendus, $ids);
        // Mon propre identifiant ne doit jamais s'y trouver : les appelants
        // s'en servent en whereNotIn, je me retirerais moi-meme des listes.
        $this->assertNotContains($moi->id, $ids);

        $this->assertSame([], $this->service->blockedUserIdsFor($this->compte('neutre@example.test')));
    }

    public function test_cannot_block_self(): void
    {
        $moi = $this->compte('moi@example.test');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Tu ne peux pas te bloquer toi-même.');

        $this->service->block($moi, $moi->id);
    }

    /**
     * Un client mobile sur un reseau instable rejoue ses requetes : un
     * « vous avez deja bloque ce compte » serait un message d'erreur pour un
     * geste qui a reussi.
     */
    public function test_blocking_twice_is_idempotent(): void
    {
        $moi = $this->compte('moi@example.test');
        $autre = $this->compte('autre@example.test');

        $premier = $this->service->block($moi, $autre->id);
        $second = $this->service->block($moi, $autre->id);

        $this->assertSame($premier->id, $second->id);
        $this->assertSame(1, UserBlock::count());
    }

    public function test_blocking_an_unknown_account_is_404(): void
    {
        $moi = $this->compte('moi@example.test');

        try {
            $this->service->block($moi, 999999);
            $this->fail('un compte inexistant ne doit pas pouvoir etre bloque');
        } catch (ApiException $e) {
            $this->assertSame('BLOCK_TARGET_NOT_FOUND', $e->errorCode);
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_unblock_requires_ownership(): void
    {
        $moi = $this->compte('moi@example.test');
        $autre = $this->compte('autre@example.test');
        $intrus = $this->compte('intrus@example.test');

        $blocage = $this->service->block($moi, $autre->id);

        try {
            $this->service->unblock($intrus, $blocage);
            $this->fail('seul l\'auteur d\'un blocage peut le lever');
        } catch (ApiException $e) {
            $this->assertSame('FORBIDDEN', $e->errorCode);
            $this->assertSame(403, $e->getStatusCode());
        }

        // La personne bloquee non plus ne peut pas se debloquer elle-meme.
        try {
            $this->service->unblock($autre, $blocage);
            $this->fail('la personne bloquee ne leve pas le blocage');
        } catch (ApiException $e) {
            $this->assertSame('FORBIDDEN', $e->errorCode);
        }

        $this->service->unblock($moi, $blocage);
        $this->assertSame(0, UserBlock::count());
    }
}
