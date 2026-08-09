<?php

namespace App\Http\Requests;

use App\Models\Sponsor;
use Illuminate\Foundation\Http\FormRequest;

class SponsorProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->hasRole('sponsor')
            || Sponsor::query()->where('owner_user_id', $user->id)->exists()
        );
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'registration_number' => ['nullable', 'string', 'max:120'],
            'vat_id' => ['nullable', 'string', 'max:80'],
            'rule_legal_accuracy' => ['sometimes', 'accepted'],
            'rule_data_privacy' => ['sometimes', 'accepted'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'url', 'max:2048'],
            'logo_light' => ['nullable', 'url', 'max:2048'],
            'logo_dark' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
