<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Employeur ecarte a la main des offres importees (voir ExternalOfferFilter).
#[Fillable(['siret', 'normalized_name', 'display_name', 'reason', 'created_by'])]
class ExternalEmployerBlock extends Model
{
    protected $table = 'external_employer_blocks';
}
