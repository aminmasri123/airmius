<?php

namespace App\Support\Authorization;

use App\Models\Club;
use App\Models\User;
use App\Support\ClubPermissions;
use App\Support\Privacy\DataClassification;
use App\Support\Privacy\ProcessingPurpose;

final readonly class AuthorizationContext
{
    public const REQUEST_PURPOSE_ATTRIBUTE = 'airmius_processing_purpose';

    public function __construct(
        public User $actor,
        public ProcessingPurpose $purpose,
        public ?Club $club = null,
        public ?string $reasonCode = null,
    ) {}

    public function can(string $permission): bool
    {
        if ($this->club && in_array($permission, ClubPermissions::ALL, true)) {
            return ClubPermissions::allows($this->club, $this->actor, $permission);
        }

        return $this->actor->can($permission);
    }

    public function allowsData(
        DataClassification $classification,
        bool $isOwner = false,
        bool $explicitGrant = false,
        bool $pseudonymized = false,
    ): bool {
        if ($classification === DataClassification::Public) {
            return true;
        }

        if ($this->purpose === ProcessingPurpose::Marketing) {
            return $classification->level() <= DataClassification::Internal->level()
                || ($classification === DataClassification::Personal && $explicitGrant);
        }

        if ($this->purpose === ProcessingPurpose::Analytics) {
            return $pseudonymized
                && $classification->level() <= DataClassification::Sensitive->level();
        }

        if ($isOwner) {
            return true;
        }

        if (in_array($this->purpose, [ProcessingPurpose::Support, ProcessingPurpose::Security], true)) {
            if ($classification->level() >= DataClassification::Sensitive->level()) {
                return $explicitGrant && filled($this->reasonCode);
            }

            return $explicitGrant;
        }

        if ($classification->level() <= DataClassification::Personal->level()) {
            return $explicitGrant;
        }

        return $explicitGrant
            && in_array($this->purpose, [
                ProcessingPurpose::Training,
                ProcessingPurpose::Organization,
                ProcessingPurpose::Billing,
            ], true);
    }
}
