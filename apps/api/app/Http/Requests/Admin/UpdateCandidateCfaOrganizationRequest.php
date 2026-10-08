<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

// Rattache un candidat a un CFA partenaire (badge « JEUNCY x <ecole> »,
// feuille de route CFA du 2026-10-07) ou le detache (null).
class UpdateCandidateCfaOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cfa_organization_id' => ['nullable', 'integer', 'exists:cfa_organizations,id'],
        ];
    }
}
