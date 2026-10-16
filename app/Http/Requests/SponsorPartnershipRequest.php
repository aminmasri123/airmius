<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SponsorPartnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'sponsor_id' => ['required', 'integer', 'exists:sponsors,id'],
            'club_id' => ['required', 'integer', 'exists:clubs,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'package_code' => ['nullable', 'string', 'max:120'],
            'rights_package' => ['nullable', 'array', 'max:30'],
            'rights_package.*' => ['string', 'max:120'],
            'individual_offer_terms' => ['nullable', 'string', 'max:5000'],
            'contract_version' => ['nullable', 'string', 'max:80'],
            'renewal_notice_days' => ['nullable', 'integer', 'min:0', 'max:730'],
            'renewal_deadline' => ['nullable', 'date'],
            'budget_cents' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }
}
