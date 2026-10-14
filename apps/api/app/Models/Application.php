<?php

namespace App\Models;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// interest_id, source et responded_at : rattachement au modele match (voir
// la migration 2026_09_22_100006). Poses par ApplicationService, jamais
// depuis le corps d'une requete cliente.
#[Fillable([
    'candidate_profile_id', 'job_offer_id', 'status', 'cover_letter', 'contact_phone',
    'generated_cv_id', 'cv_file_url', 'interest_id', 'source', 'responded_at',
])]
class Application extends Model
{
    protected $table = 'applications';

    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'source' => ApplicationSource::class,
            'responded_at' => 'datetime',
        ];
    }

    public function candidateProfile(): BelongsTo
    {
        return $this->belongsTo(CandidateProfile::class);
    }

    public function jobOffer(): BelongsTo
    {
        return $this->belongsTo(JobOffer::class);
    }

    public function generatedCv(): BelongsTo
    {
        return $this->belongsTo(GeneratedCv::class);
    }

    public function interest(): BelongsTo
    {
        return $this->belongsTo(OfferInterest::class, 'interest_id');
    }

    /**
     * Le dossier tel que l'employeur le recoit : sans aucune adresse de
     * fichier. Ces adresses fonctionnent sans compte et pour toujours — une
     * fois remises, Jeuncy ne peut plus les revoquer, ni apres un blocage ni
     * apres la suppression du compte (audit de securite du 2026-10-01, A1).
     * L'employeur recupere le PDF par GET applications/{id}/cv, garde a
     * chaque appel ; has_cv lui dit seulement s'il y en a un.
     */
    public function forEmployer(): static
    {
        $this->makeHidden('cv_file_url')->append('has_cv');
        $this->generatedCv?->makeHidden('file_url');
        $this->candidateProfile?->makeHidden('cv_file_url');

        return $this;
    }

    // Le PDF de ce dossier : celui joint a la candidature, sinon le CV Jeuncy
    // choisi — tant qu'il n'est pas archive (son fichier est alors efface).
    public function cvFileUrl(): ?string
    {
        if ($this->cv_file_url !== null) {
            return $this->cv_file_url;
        }

        $cv = $this->generatedCv;

        return $cv !== null && $cv->archived_at === null ? $cv->file_url : null;
    }

    protected function hasCv(): Attribute
    {
        return Attribute::get(fn () => $this->cvFileUrl() !== null);
    }
}
