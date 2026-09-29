<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\ImageService;
use App\Support\MinorSafety;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'athlete_license_number' => ['nullable', 'string', 'max:120'],
            'athlete_license_valid_until' => ['nullable', 'date'],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:2048'],
            'profile_visibility' => ['required', Rule::in(['public', 'private', 'friends'])],
            'bio' => ['nullable', 'string', 'max:1000'],
        ])->validateWithBag('updateProfileInformation');

        /* if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        } */

        if (isset($input['photo'])) {

            $imageService = new ImageService;

            $previousPath = $user->profile_photo_path;
            $path = "profile/avatars/{$user->id}/".Str::uuid().'.jpg';

            $imageService->upload(
                $input['photo'],
                $path,
                'avatar'
            );

            if ($previousPath && $previousPath !== $path) {
                $user->deleteProfilePhotoFiles($previousPath);
            }

            $user->profile_photo_path = $path;
            $user->save();
        }

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
        } else {
            $firstName = trim($input['first_name']);
            $lastName = trim($input['last_name']);

            $updates = [
                'name' => trim($firstName.' '.$lastName),
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $input['email'],
                'athlete_license_number' => $input['athlete_license_number'] ?? null,
                'athlete_license_valid_until' => $input['athlete_license_valid_until'] ?? null,
                'profile_visibility' => $input['profile_visibility'],
                'bio' => $input['bio'] ?? null,
            ];

            if (MinorSafety::isUnderConsentAge($user)) {
                $updates = array_merge($updates, MinorSafety::privacyDefaults());
            }

            $user->forceFill($updates)->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $firstName = trim($input['first_name']);
        $lastName = trim($input['last_name']);

        $updates = [
            'name' => trim($firstName.' '.$lastName),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $input['email'],
            'athlete_license_number' => $input['athlete_license_number'] ?? null,
            'athlete_license_valid_until' => $input['athlete_license_valid_until'] ?? null,
            'profile_visibility' => $input['profile_visibility'],
            'bio' => $input['bio'] ?? null,
            'email_verified_at' => null,
        ];

        if (MinorSafety::isUnderConsentAge($user)) {
            $updates = array_merge($updates, MinorSafety::privacyDefaults());
        }

        $user->forceFill($updates)->save();

        $user->sendEmailVerificationNotification();
    }
}
