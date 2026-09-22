<?php

namespace App\Models;

use App\Enums\ContractType;
use App\Enums\DrivingLicenseCategory;
use App\Enums\OfferSector;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// latitude / longitude et device_* sont volontairement absents de ce
// tableau : les premieres sont geocodees par GeocodingService a partir de
// la commune + code postal, les secondes posees par
// CandidateProfileService::setDeviceLocation apres arrondi cote serveur —
// jamais par mass-assignment depuis une requete cliente (meme raison que
// trial_* sur Company).
#[Fillable([
    'user_id', 'first_name', 'last_name', 'headline', 'phone', 'birth_date',
    'address', 'city', 'postal_code', 'bio', 'photo_url', 'hobbies', 'driving_license',
    'video_url', 'portfolio_url', 'linkedin_url', 'is_visible_in_cvtheque',
    'cv_file_url', 'cv_original_filename', 'cv_uploaded_at',
    'search_radius_km', 'mobility_radius_km', 'wanted_contract_types', 'wanted_sectors',
    'has_driving_license', 'driving_license_categories', 'has_vehicle', 'available_from',
    'pitch', 'show_photo_to_employers',
])]
class CandidateProfile extends Model
{
    use HasFactory;

    protected $table = 'candidate_profiles';

    // L'age est ajoute a chaque serialisation du profil. C'est l'AGE qui
    // interesse un recruteur — le cout d'un alternant depend de sa tranche
    // d'age — pas la date exacte : exposer l'un permet de garder l'autre
    // privee (voir CvthequeService, qui masque birth_date).
    protected $appends = ['age'];

    // Masques par defaut : le profil est serialise tel quel dans la
    // candidature complete (ApplicationService::listForOffer) une fois le
    // dossier envoye, et une coordonnee — a fortiori la position GPS du
    // telephone — ne doit jamais y passer. Le deck employeur ne lit que
    // latitude / longitude (PROFILE) en SQL, sans jamais les afficher.
    //
    // Le proprietaire les recupere via makeVisible(OWNER_VISIBLE) dans
    // CandidateProfileService et dans l'export RGPD (AccountService) : un
    // export sans la position GPS stockee serait incomplet.
    protected $hidden = ['latitude', 'longitude', 'device_latitude', 'device_longitude', 'device_located_at'];

    public const OWNER_VISIBLE = ['latitude', 'longitude', 'device_latitude', 'device_longitude', 'device_located_at'];

    // Tranches d'age montrees a un employeur avant le dossier (MOBILE.md
    // §4.1) : jamais l'age exact. Bornes : 17 -> '<18', 18-20, 21-25, 26+.
    public const AGE_BANDS = ['<18', '18-20', '21-25', '26+'];

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date?->age;
    }

    // Pas dans $appends, a dessein : c'est le presenteur de carte
    // (CandidateCardPresenter) qui decide de l'exposer, pas la serialisation
    // par defaut.
    public function getAgeBandAttribute(): ?string
    {
        $age = $this->age;

        if ($age === null) {
            return null;
        }

        return match (true) {
            $age < 18 => '<18',
            $age <= 20 => '18-20',
            $age <= 25 => '21-25',
            default => '26+',
        };
    }

    public function hasProfileCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function hasDeviceCoordinates(): bool
    {
        return $this->device_latitude !== null && $this->device_longitude !== null;
    }

    /**
     * Types de contrat souhaites, en enums. Liste vide = pas de preference
     * (eligible a tout).
     *
     * @return list<ContractType>
     */
    public function wantedContractTypes(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => ContractType::tryFrom((string) $value),
            $this->wanted_contract_types ?? [],
        )));
    }

    /**
     * @return list<OfferSector>
     */
    public function wantedSectors(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => OfferSector::tryFrom((string) $value),
            $this->wanted_sectors ?? [],
        )));
    }

    /**
     * @return list<DrivingLicenseCategory>
     */
    public function drivingLicenseCategories(): array
    {
        return array_values(array_filter(array_map(
            fn ($value) => DrivingLicenseCategory::tryFrom((string) $value),
            $this->driving_license_categories ?? [],
        )));
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_visible_in_cvtheque' => 'boolean',
            'cv_uploaded_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'device_latitude' => 'float',
            'device_longitude' => 'float',
            'device_located_at' => 'datetime',
            'search_radius_km' => 'integer',
            'mobility_radius_km' => 'integer',
            'wanted_contract_types' => 'array',
            'wanted_sectors' => 'array',
            'has_driving_license' => 'boolean',
            'driving_license_categories' => 'array',
            'has_vehicle' => 'boolean',
            'available_from' => 'date',
            'show_photo_to_employers' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(Experience::class);
    }

    public function educations(): HasMany
    {
        return $this->hasMany(Education::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(Language::class);
    }

    public function generatedCvs(): HasMany
    {
        return $this->hasMany(GeneratedCv::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function offerInterests(): HasMany
    {
        return $this->hasMany(OfferInterest::class);
    }

    public function externalInterests(): HasMany
    {
        return $this->hasMany(ExternalInterest::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'candidate_skills');
    }

    public function software(): BelongsToMany
    {
        return $this->belongsToMany(Software::class, 'candidate_software');
    }
}
