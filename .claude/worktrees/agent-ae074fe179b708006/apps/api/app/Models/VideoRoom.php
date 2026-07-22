<?php

namespace App\Models;

use App\Enums\VideoRoomStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['host_id', 'participant_id', 'jitsi_room_name', 'status', 'scheduled_at', 'started_at', 'ended_at'])]
class VideoRoom extends Model
{
    protected $table = 'video_rooms';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => VideoRoomStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'participant_id');
    }
}
