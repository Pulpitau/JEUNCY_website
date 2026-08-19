<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\VideoRoomStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\VideoRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VideoRoomServiceTest extends TestCase
{
    use RefreshDatabase;

    private VideoRoomService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(VideoRoomService::class);
    }

    private function makeHost(): User
    {
        return User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
    }

    public function test_create_generates_unique_room_name_without_participant(): void
    {
        $room = $this->service->createForUser($this->makeHost(), null, null);

        $this->assertNotEmpty($room->jitsi_room_name);
        $this->assertNull($room->participant_id);
        $this->assertSame(VideoRoomStatus::SCHEDULED, $room->status);
    }

    public function test_create_links_participant_found_by_email(): void
    {
        $participant = User::create(['email' => 'lea.girard@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);

        $room = $this->service->createForUser($this->makeHost(), 'lea.girard@example.com', null);

        $this->assertSame($participant->id, $room->participant_id);
    }

    public function test_create_throws_when_participant_email_unknown(): void
    {
        $this->expectException(ApiException::class);
        $this->service->createForUser($this->makeHost(), 'inconnu@example.com', null);
    }

    public function test_find_public_by_room_name_throws_for_unknown_room(): void
    {
        $this->expectException(ApiException::class);
        $this->service->findPublicByRoomName('does-not-exist');
    }

    public function test_find_public_by_room_name_returns_room(): void
    {
        $room = $this->service->createForUser($this->makeHost(), null, null);

        $found = $this->service->findPublicByRoomName($room->jitsi_room_name);

        $this->assertTrue($found->is($room));
    }

    public function test_mark_started_sets_live_and_started_at(): void
    {
        $host = $this->makeHost();
        $room = $this->service->createForUser($host, null, null);

        $updated = $this->service->markStarted($host->fresh(), $room);

        $this->assertSame(VideoRoomStatus::LIVE, $updated->status);
        $this->assertNotNull($updated->started_at);
    }

    public function test_mark_started_rejects_non_host(): void
    {
        $host = $this->makeHost();
        $room = $this->service->createForUser($host, null, null);
        $intruder = User::create(['email' => 'contact@cafedeslices.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);

        $this->expectException(ApiException::class);
        $this->service->markStarted($intruder, $room);
    }

    public function test_mark_ended_sets_ended_at(): void
    {
        $host = $this->makeHost();
        $room = $this->service->createForUser($host, null, null);

        $updated = $this->service->markEnded($host->fresh(), $room);

        $this->assertSame(VideoRoomStatus::ENDED, $updated->status);
        $this->assertNotNull($updated->ended_at);
    }

    public function test_list_for_user_includes_hosted_and_participated_rooms(): void
    {
        $host = $this->makeHost();
        $participant = User::create(['email' => 'lea.girard@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
        $this->service->createForUser($host, 'lea.girard@example.com', null);

        $hostRooms = $this->service->listForUser($host->fresh());
        $participantRooms = $this->service->listForUser($participant->fresh());

        $this->assertCount(1, $hostRooms);
        $this->assertCount(1, $participantRooms);
    }

    // Le nom de salle est le SEUL controle d'acces de la page publique : sans
    // echeance, un lien transfere ou retrouve dans un historique restait un
    // acces permanent.
    public function test_scheduled_room_link_expires_after_the_grace_period(): void
    {
        $room = $this->service->createForUser($this->makeHost(), null, now()->toDateTimeString());

        $this->assertNotNull($room->expires_at);
        $this->assertEqualsWithDelta(
            now()->addHours(VideoRoomService::LINK_GRACE_HOURS)->timestamp,
            $room->expires_at->timestamp,
            5,
        );
    }

    public function test_undated_room_link_gets_the_default_window(): void
    {
        $room = $this->service->createForUser($this->makeHost(), null, null);

        $this->assertEqualsWithDelta(
            now()->addDays(VideoRoomService::LINK_DEFAULT_DAYS)->timestamp,
            $room->expires_at->timestamp,
            5,
        );
    }

    public function test_expired_link_is_refused_publicly(): void
    {
        $room = $this->service->createForUser($this->makeHost(), null, null);
        $room->update(['expires_at' => now()->subMinute()]);

        try {
            $this->service->findPublicByRoomName($room->jitsi_room_name);
            $this->fail('Un lien expire devrait etre refuse.');
        } catch (ApiException $e) {
            // 410 et non 404 : le frontend doit pouvoir dire "ce lien a
            // expire" plutot que "cette salle n'existe pas".
            $this->assertSame('VIDEO_ROOM_EXPIRED', $e->errorCode);
            $this->assertSame(410, $e->getStatusCode());
        }
    }

    public function test_valid_link_still_works_before_expiry(): void
    {
        $room = $this->service->createForUser($this->makeHost(), null, null);

        $this->assertSame(
            $room->id,
            $this->service->findPublicByRoomName($room->jitsi_room_name)->id,
        );
    }

    // Les salles creees avant l'ajout de l'echeance ont expires_at a NULL :
    // le deploiement ne doit pas invalider d'un coup des liens deja envoyes.
    public function test_room_without_expiry_keeps_working(): void
    {
        $room = $this->service->createForUser($this->makeHost(), null, null);
        $room->update(['expires_at' => null]);

        $this->assertSame(
            $room->id,
            $this->service->findPublicByRoomName($room->jitsi_room_name)->id,
        );
    }
}
