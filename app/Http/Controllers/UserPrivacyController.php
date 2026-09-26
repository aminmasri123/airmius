<?php

namespace App\Http\Controllers;

use App\Services\UserPrivacyExportService;
use App\Services\UserPrivacyRightsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserPrivacyController extends Controller
{
    public function export(Request $request, UserPrivacyExportService $exports)
    {
        $payload = $exports->export($request->user());
        $filename = 'airmius-datenauskunft-'.$request->user()->id.'-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(
            function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            $filename,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public function correct(Request $request, UserPrivacyRightsService $privacy)
    {
        $data = $request->validate($this->correctionRules($request));

        $privacy->correct($request->user(), $data);

        return back()->with('success', __('privacy_center.flash.corrected'));
    }

    public function withdrawConsents(Request $request, UserPrivacyRightsService $privacy)
    {
        $data = $request->validate($this->consentRules());
        $withdrawn = $privacy->withdrawConsents($request->user(), $data['consents'] ?? []);

        if ($withdrawn === []) {
            return back()->with('error', __('privacy_center.flash.invalid_consent'));
        }

        return back()->with('success', __('privacy_center.flash.withdrawn'));
    }

    private function correctionRules(Request $request): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:120'],
            'last_name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'athlete_license_number' => ['sometimes', 'nullable', 'string', 'max:120'],
            'athlete_license_valid_until' => ['sometimes', 'nullable', 'date'],
            'country' => ['sometimes', 'required', 'string', 'size:2'],
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'house_number' => ['sometimes', 'nullable', 'string', 'max:40'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:30'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'max:255'],
            'birth_date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', 'required', Rule::in(['female', 'male', 'diverse', 'not_specified'])],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'guardian_email' => ['sometimes', 'nullable', 'email', 'max:255', 'different:email'],
            'profile_visibility' => ['sometimes', 'required', Rule::in(['public', 'private'])],
            'direct_message_privacy' => ['sometimes', 'required', Rule::in(['everyone', 'friends'])],
            'friend_request_privacy' => ['sometimes', 'required', Rule::in(['everyone', 'friends'])],
        ];
    }

    private function consentRules(): array
    {
        return [
            'consents' => ['nullable', 'array'],
            'consents.*' => ['string', Rule::in([...UserPrivacyRightsService::supportedConsents(), 'all'])],
        ];
    }
}
