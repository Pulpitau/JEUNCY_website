<?php

namespace App\Models;

use App\Enums\CvSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Une ligne = un CV de candidat recupere par un recruteur depuis la CVtheque.
// Voir la migration create_cv_downloads_table pour la justification RGPD.
#[Fillable(['candidate_profile_id', 'user_id', 'source'])]
class CvDownload extends Model
{
    protected $table = 'cv_downloads';

    // Le seul horodatage utile est celui du telechargement lui-meme, rempli
    // par la base (useCurrent) : created_at/updated_at feraient doublon.
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'source' => CvSource::class,
            'downloaded_at' => 'datetime',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
