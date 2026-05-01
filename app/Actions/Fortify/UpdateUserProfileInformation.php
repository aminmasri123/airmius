<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\ImageService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:2048'],
            'profile_visibility' => ['required', Rule::in(['public', 'private'])],
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
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'profile_visibility' => $input['profile_visibility'],
                'bio' => $input['bio'] ?? null,
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'profile_visibility' => $input['profile_visibility'],
            'bio' => $input['bio'] ?? null,
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
