<?php

namespace App\Support;

use App\Models\User;

class AccountAccess
{
    public static function isSuspended(User $user): bool
    {
        if ($user->account_status !== 'suspended') {
            return false;
        }

        if ($user->suspended_until && $user->suspended_until->isPast()) {
            $user->forceFill([
                'account_status' => 'active',
                'suspended_until' => null,
                'suspension_reason' => null,
            ])->save();

            return false;
        }

        return true;
    }

    public static function ensureAllowed(User $user): void
    {
        abort_if($user->privacy_status === 'anonymized', 401);
        abort_if(self::isSuspended($user), 423, __('account_access.suspended'));
    }
}
