<?php

namespace App\Services;

use App\Models\Club;
use App\Models\User;
use App\Notifications\ClubDeletionCodeRequested;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class ClubDeletionIdentityService
{
    public function requiresPassword(User $user): bool
    {
        return ! $user->socialAccounts()->exists();
    }

    public function sendCode(Club $club, User $actor, string $identity): void
    {
        Gate::forUser($actor)->authorize('delete', $club);
        abort_unless((int) $club->owner_id === (int) $actor->id, 403);

        $valid = $this->requiresPassword($actor)
            ? Hash::check($identity, (string) $actor->password)
            : hash_equals(mb_strtolower($actor->email), mb_strtolower(trim($identity)));

        if (! $valid) {
            throw ValidationException::withMessages(['identity' => __('club_deletion.wrong_identity')]);
        }

        $code = (string) random_int(100000, 999999);
        $hash = Hash::make($code);
        try {
            $actor->notify(new ClubDeletionCodeRequested($code, $club->name));
        } catch (TransportExceptionInterface $exception) {
            report($exception);
            abort(503, __('club_deletion.code_delivery_failed'));
        }
        Cache::put($this->key($club, $actor), $hash, now()->addMinutes(15));
    }

    public function consumeCode(Club $club, User $actor, string $code): void
    {
        $key = $this->key($club, $actor);
        $hash = Cache::get($key);
        if (! is_string($hash) || ! Hash::check($code, $hash)) {
            throw ValidationException::withMessages(['code' => __('club_deletion.wrong_code')]);
        }
        Cache::forget($key);
    }

    private function key(Club $club, User $actor): string
    {
        return "club_deletion_code:{$club->id}:{$actor->id}";
    }
}
