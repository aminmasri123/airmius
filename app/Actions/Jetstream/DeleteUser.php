<?php

namespace App\Actions\Jetstream;

use App\Models\Club;
use App\Models\User;
use App\Services\DeletionRecoveryLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
            if (Club::query()->where('owner_id', $lockedUser->id)->exists()) {
                throw ValidationException::withMessages([
                    'account' => __('account_deletion.club_owner_blocked'),
                ]);
            }

            $ledger = app(DeletionRecoveryLedger::class);
            $operationId = $ledger->prepare('user', (int) $lockedUser->id, $lockedUser->getRawOriginal('created_at'));
            if ($operationId !== null) {
                DB::afterCommit(fn () => $ledger->complete($operationId));
            }

            $lockedUser->tokens()->delete();
            $lockedUser->delete();
        });

        $user->deleteProfilePhoto();
    }
}
