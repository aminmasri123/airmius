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
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'url', 'max:2048'],
            'logo_light' => ['nullable', 'url', 'max:2048'],
            'logo_dark' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
