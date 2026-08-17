<?php

namespace Tests\Feature;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Enums\VideoRoomStatus;
use App\Models\User;
use App\Services\MailService;
use App\Services\VideoRoomService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendVideoRoomRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    private VideoRoomService $service;

    private $mailServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailServiceMock = Mockery::mock(MailService::class);
        $this->app->instance(MailService::class, $this->mailServiceMock);
        $this->service = $this->app->make(VideoRoomService::class);
    }

    private function makeHost(): User
    {
        return User::create(['email' => 'rh@nexatech.example.com', 'password_hash' => 'x', 'role' => UserRole::COMPANY]);
    }

    private function makeParticipant(): User
    {
        return User::create(['email' => 'lea.girard@example.com', 'password_hash' => 'x', 'role' => UserRole::CANDIDATE]);
    }

    public function test_sends_reminder_to_host_and_participant_within_the_window(): void
    {
        $host = $this->makeHost();
        $this->makeParticipant();
        $room = $this->service->createForUser($host, 'lea.girard@example.com', now()->addMinutes(30)->toDateTimeString());

        $this->mailServiceMock->shouldReceive('sendVideoRoomReminderEmail')
            ->twice()
            ->with(Mockery::anyOf('rh@nexatech.example.com', 'lea.girard@example.com'), Mockery::any(), Mockery::any());

        $this->artisan('video-rooms:send-reminders')->assertSuccessful();

        $this->assertNotNull($room->fresh()->reminder_sent_at);
        $this->assertSame(1, $host->fresh()->notifications()->where('type', NotificationType::VIDEO_ROOM_REMINDER)->count());
    }

    public function test_sends_reminder_to_host_only_when_no_participant(): void
    {
        $host = $this->makeHost();
        $this->service->createForUser($host, null, now()->addMinutes(30)->toDateTimeString());

        $this->mailServiceMock->shouldReceive('sendVideoRoomReminderEmail')->once()->with('rh@nexatech.example.com', Mockery::any(), Mockery::any());

        $this->artisan('video-rooms:send-reminders')->assertSuccessful();
    }

    public function test_does_not_send_reminder_twice_for_the_same_room(): void
    {
        $host = $this->makeHost();
        $room = $this->service->createForUser($host, null, now()->addMinutes(30)->toDateTimeString());

        $this->mailServiceMock->shouldReceive('sendVideoRoomReminderEmail')->once();
        $this->artisan('video-rooms:send-reminders')->assertSuccessful();

        $this->mailServiceMock->shouldNotReceive('sendVideoRoomReminderEmail');
        $this->artisan('video-rooms:send-reminders')->assertSuccessful();

        $this->assertSame(1, $host->fresh()->notifications()->where('type', NotificationType::VIDEO_ROOM_REMINDER)->count());
    }

    public function test_does_not_send_reminder_for_room_scheduled_far_in_the_future(): void
    {
        $host = $this->makeHost();
        $this->service->createForUser($host, null, now()->addDays(2)->toDateTimeString());

        $this->mailServiceMock->shouldNotReceive('sendVideoRoomReminderEmail');

        $this->artisan('video-rooms:send-reminders')->assertSuccessful();
    }

    public function test_does_not_send_reminder_for_already_started_room(): void
    {
        $host = $this->makeHost();
        $room = $this->service->createForUser($host, null, now()->addMinutes(30)->toDateTimeString());
        $room->update(['status' => VideoRoomStatus::LIVE, 'started_at' => now()]);

        $this->mailServiceMock->shouldNotReceive('sendVideoRoomReminderEmail');

        $this->artisan('video-rooms:send-reminders')->assertSuccessful();
    }
}
