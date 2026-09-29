<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\PrivacyCenterService;
use App\Services\UserPrivacyExportService;
use App\Services\UserPrivacyRightsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PrivacyController extends Controller
{
    public function show(Request $request, PrivacyCenterService $privacyCenter)
    {
        return response()->json([
            'data' => $privacyCenter->payload($request->user()),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, UserPrivacyExportService $exports)
    {
        return response()->json([
            'data' => $exports->export($request->user()),
        ]);
    }

    public function rightsProcess(UserPrivacyRightsService $privacy)
    {
        return response()->json([
            'data' => $privacy->rightsProcessMatrix(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function processingActivities(UserPrivacyRightsService $privacy)
    {
        return response()->json([
            'data' => $privacy->processingActivityInventory(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function correct(Request $request, UserPrivacyRightsService $privacy)
    {
        $user = $privacy->correct($request->user(), $request->validate($this->correctionRules($request)));

        return new UserResource($user->loadMissing([
            'roles',
            'permissions',
            'clubs',
            'teams.club',
            'sportProfiles.sport',
        ]));
    }

    public function withdrawConsents(Request $request, UserPrivacyRightsService $privacy)
    {
        $data = $request->validate($this->consentRules());
        $withdrawn = $privacy->withdrawConsents($request->user(), $data['consents'] ?? []);
        $user = $request->user()->refresh();

        return response()->json([
            'data' => [
                'withdrawn_consents' => $withdrawn,
                'privacy_settings' => [
                    'ads_personalization_consent' => (bool) $user->ads_personalization_consent,
                    'ads_measurement_consent' => (bool) $user->ads_measurement_consent,
                    'product_analytics_consent' => (bool) $user->product_analytics_consent,
                    'profile_visibility' => $user->profile_visibility ?? 'public',
                    'direct_message_privacy' => $user->direct_message_privacy ?? 'everyone',
                    'friend_request_privacy' => $user->friend_request_privacy ?? 'everyone',
                ],
            ],
        ]);
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
            'profile_visibility' => ['sometimes', 'required', Rule::in(['public', 'private', 'friends'])],
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
