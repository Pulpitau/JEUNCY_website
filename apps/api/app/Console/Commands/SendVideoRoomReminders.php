<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\VideoRoomStatus;
use App\Models\VideoRoom;
use App\Services\MailService;
use Illuminate\Console\Command;

class SendVideoRoomReminders extends Command
{
    protected $signature = 'video-rooms:send-reminders';

    protected $description = "Envoie un rappel (notification + email) a l'hote et au participant d'une salle de visio programmee dans l'heure qui suit";

    // Doit correspondre a la frequence de planification dans bootstrap/app.php
    // (->hourly()) : une salle prevue au-dela de cette fenetre sera repassee
    // en revue au prochain appel, une fois qu'elle y entrera.
    private const REMINDER_WINDOW_MINUTES = 60;

    public function __construct(private readonly MailService $mailService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $rooms = VideoRoom::query()
            ->where('status', VideoRoomStatus::SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->whereNull('reminder_sent_at')
            ->whereBetween('scheduled_at', [now(), now()->addMinutes(self::REMINDER_WINDOW_MINUTES)])
            ->with(['host', 'participant'])
            ->get();

        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        foreach ($rooms as $room) {
            $joinUrl = "{$frontendUrl}/demo/{$room->jitsi_room_name}";

            foreach (array_filter([$room->host, $room->participant]) as $recipient) {
                $recipient->notifications()->create([
                    'type' => NotificationType::VIDEO_ROOM_REMINDER,
                    'message' => 'Ta visioconférence Jeuncy est prévue le '.$room->scheduled_at->format('d/m/Y à H:i').'.',
                    'link' => "/demo/{$room->jitsi_room_name}",
                ]);
                $this->mailService->sendVideoRoomReminderEmail($recipient->email, $room->scheduled_at, $joinUrl);
            }

            $room->update(['reminder_sent_at' => now()]);
        }

        $this->info(count($rooms).' rappel(s) de visio envoye(s).');

        return self::SUCCESS;
    }
}
