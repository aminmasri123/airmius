<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserPrivacyRetentionService
{
    public function anonymize(User $user): void
    {
        if ($user->privacy_status === 'anonymized') {
            return;
        }

        DB::transaction(function () use ($user) {
            $user->deleteProfilePhoto();
            $this->deletePostMedia($user);
            $this->deleteMessageMedia($user);
            $this->deleteLooseFiles($user);

            $user->posts()->update([
                'content' => '[Konto anonymisiert]',
                'image' => null,
                'moderation_status' => 'removed',
            ]);

            $user->messages()->update([
                'message' => '[Konto anonymisiert]',
                'status' => 'deleted',
                'deleted_at' => now(),
            ]);

            $user->socialAccounts()->delete();
            $user->connectedSportActivities()->delete();
            $user->connectedSportAccounts()->delete();
            $user->tokens()->delete();
            $user->folders()->delete();
            $user->following()->delete();
            $user->followers()->delete();
            $user->sentFriendInvitations()->delete();
            $user->receivedFriendInvitations()->delete();
            $user->friendships()->delete();
            $user->receivedFriendships()->delete();

            $user->forceFill([
                'name' => 'Gelöschter Nutzer',
                'first_name' => null,
                'last_name' => null,
                'email' => 'anonymized-user-'.$user->id.'-'.Str::uuid().'@example.invalid',
                'password' => Str::password(64),
                'country' => null,
                'athlete_license_number' => null,
                'athlete_license_valid_until' => null,
                'street' => null,
                'house_number' => null,
                'postal_code' => null,
                'city' => null,
                'state' => null,
                'status' => 'offline',
                'account_status' => 'anonymized',
                'privacy_status' => 'anonymized',
                'suspended_until' => null,
                'suspension_reason' => null,
                'birth_date' => null,
                'guardian_email' => null,
                'guardian_user_id' => null,
                'guardian_consent_requested_at' => null,
                'guardian_consent_at' => null,
                'guardian_consent_rejected_at' => null,
                'guardian_consent_revoked_at' => null,
                'guardian_consent_revoked_by_email' => null,
                'guardian_consent_token' => null,
                'profile_visibility' => 'private',
                'bio' => null,
                'profile_photo_path' => null,
                'remember_token' => null,
                'inactivity_first_warning_sent_at' => null,
                'inactivity_second_warning_sent_at' => null,
                'deletion_scheduled_at' => null,
                'anonymized_at' => now(),
            ])->save();
        });
    }

    private function deletePostMedia(User $user): void
    {
        $user->posts()->with('attachments.file')->chunkById(50, function ($posts) {
            foreach ($posts as $post) {
                if ($post->image) {
                    Storage::disk(UploadStorage::disk())->delete($post->image);
                }

                foreach ($post->attachments as $attachment) {
                    $this->deleteFileModel($attachment->file);
                    $attachment->delete();
                }
            }
        });
    }

    private function deleteMessageMedia(User $user): void
    {
        $user->messages()->with('attachments.file')->chunkById(50, function ($messages) {
            foreach ($messages as $message) {
                foreach ($message->attachments as $attachment) {
                    $this->deleteFileModel($attachment->file);
                    $attachment->delete();
                }
            }
        });
    }

    private function deleteLooseFiles(User $user): void
    {
        File::query()
            ->where('user_id', $user->id)
            ->whereDoesntHave('posts')
            ->whereDoesntHave('messages')
            ->chunkById(50, fn ($files) => $files->each(fn (File $file) => $this->deleteFileModel($file)));
    }

    private function deleteFileModel(?File $file): void
    {
        if (! $file) {
            return;
        }

        Storage::disk(UploadStorage::disk())->delete(array_filter([
            $file->path,
            $file->thumbnail_path,
        ]));

        $file->delete();
    }
}
