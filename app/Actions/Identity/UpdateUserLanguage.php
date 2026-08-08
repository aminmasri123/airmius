<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Services\DomainEventPublisher;
use Illuminate\Support\Facades\DB;

class UpdateUserLanguage
{
    public function __construct(private readonly DomainEventPublisher $events) {}

    public function execute(User $user, string $language): User
    {
        if ($user->language === $language) {
            return $user;
        }

        return DB::transaction(function () use ($user, $language) {
            $previousLanguage = $user->language;

            $user->forceFill(['language' => $language])->save();

            $this->events->record(
                'identity.user.language_updated.v1',
                $user,
                payload: [
                    'previous_language' => $previousLanguage,
                    'language' => $language,
                ],
                audience: ['users' => [$user->id]],
            );

            return $user->refresh();
        });
    }
}
