<?php

namespace App\Services;

use App\Models\Club;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ClubRegistrationReviewRequested;
use App\Notifications\ClubRegistrationSubmitted;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ClubService
{
    public function __construct(private GamificationService $gamification) {}

    public function create(User $user, $data)
    {
        return DB::transaction(function () use ($user, $data) {
            $club = Club::create([
                'name' => $data['name'],
                'sport_type' => $data['sport_type'] ?? null,
                'is_official' => false,
                'official_club_number' => null,
                'verification_status' => 'pending_verification',
                'requested_official_club_number' => $data['official_club_number'] ?? null,
                'verification_requested_at' => now(),
                'country' => strtoupper($data['country']),
                'street' => $data['street'] ?? null,
                'house_number' => $data['house_number'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'sepa_account_holder' => $data['sepa_account_holder'] ?? null,
                'sepa_iban' => $data['sepa_iban'] ?? null,
                'sepa_bic' => $data['sepa_bic'] ?? null,
                'is_listed' => $data['is_listed'] ?? true,
                'teams_are_listed' => $data['teams_are_listed'] ?? true,
                'members_can_post_to_club' => $data['members_can_post_to_club'] ?? true,
                'members_can_post_to_teams' => $data['members_can_post_to_teams'] ?? true,
                'owner_id' => $user->id,
            ]);

            $club->users()->syncWithoutDetaching([
                $user->id => ['role' => 'owner', 'roles' => ['owner']],
            ]);

            $this->assignClubOwnerRole($user);

            $this->gamification->grantToClub($user, $club, 'club_profile_completed', $club, [
                'created_by' => $user->id,
            ]);

            $this->sendRegistrationNotifications($club, $user);

            return $club;
        });
    }

    public function delete(Club $club): bool
    {
        if ($blocker = app(ClubDeletionService::class)->blocker($club)) {
            abort(422, $blocker);
        }
        $owner = $club->owner;
        $deleted = (bool) $club->delete();

        if ($deleted && $owner) {
            $this->refreshClubOwnerRole($owner);
        }

        return $deleted;
    }

    public function update($club, array $data, ?User $actor = null)
    {
        $legalFields = [
            'registry_authority', 'registry_number', 'federation_affiliations',
            'tax_authority', 'tax_number', 'vat_id', 'tax_status',
            'tax_exemption_valid_until',
        ];
        $submittedLegalFields = array_values(array_intersect($legalFields, array_keys($data)));
        $contactFields = [
            'contact_email', 'contact_phone', 'website_url',
            'contact_details_public', 'contact_persons',
        ];
        $submittedContactFields = array_values(array_intersect($contactFields, array_keys($data)));
        $brandingFields = [
            'brand_primary_color', 'brand_secondary_color', 'brand_accent_color',
            'letterhead_settings', 'document_templates',
        ];
        $submittedBrandingFields = array_values(array_intersect($brandingFields, array_keys($data)));
        $hasSubmittedClubNumber = array_key_exists('official_club_number', $data);
        $submittedClubNumber = $hasSubmittedClubNumber
            ? trim((string) ($data['official_club_number'] ?? ''))
            : null;

        if (isset($data['country'])) {
            $data['country'] = strtoupper($data['country']);
        }

        foreach (['registry_authority', 'registry_number', 'tax_authority', 'tax_number', 'vat_id'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = $value === '' ? null : ($field === 'vat_id' ? strtoupper(preg_replace('/\s+/', '', $value)) : $value);
            }
        }

        if (array_key_exists('federation_affiliations', $data)) {
            $data['federation_affiliations'] = collect($data['federation_affiliations'] ?? [])
                ->map(fn (array $affiliation) => [
                    'name' => trim($affiliation['name']),
                    'member_number' => filled($affiliation['member_number'] ?? null) ? trim($affiliation['member_number']) : null,
                    'valid_from' => $affiliation['valid_from'] ?? null,
                    'valid_until' => $affiliation['valid_until'] ?? null,
                ])->values()->all();
        }

        foreach (['contact_email', 'contact_phone', 'website_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) ($data[$field] ?? ''));
                $data[$field] = $value === '' ? null : $value;
            }
        }

        if (filled($data['contact_email'] ?? null)) {
            $data['contact_email'] = strtolower($data['contact_email']);
        }

        if (array_key_exists('contact_persons', $data)) {
            $data['contact_persons'] = collect($data['contact_persons'] ?? [])
                ->map(fn (array $person) => [
                    'name' => trim($person['name']),
                    'role' => filled($person['role'] ?? null) ? trim($person['role']) : null,
                    'email' => filled($person['email'] ?? null) ? strtolower(trim($person['email'])) : null,
                    'phone' => filled($person['phone'] ?? null) ? trim($person['phone']) : null,
                    'is_public' => (bool) $person['is_public'],
                ])->values()->all();
        }

        foreach (['brand_primary_color', 'brand_secondary_color', 'brand_accent_color'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = strtoupper(trim((string) ($data[$field] ?? '')));
                $data[$field] = $value === '' ? null : $value;
            }
        }

        if (array_key_exists('letterhead_settings', $data)) {
            $settings = $data['letterhead_settings'] ?? [];
            $data['letterhead_settings'] = [
                'show_logo' => (bool) ($settings['show_logo'] ?? true),
                'header' => filled($settings['header'] ?? null) ? trim($settings['header']) : null,
                'address_line' => filled($settings['address_line'] ?? null) ? trim($settings['address_line']) : null,
                'footer' => filled($settings['footer'] ?? null) ? trim($settings['footer']) : null,
            ];
        }

        if (array_key_exists('document_templates', $data)) {
            $data['document_templates'] = collect($data['document_templates'] ?? [])->map(fn (array $template) => [
                'name' => trim($template['name']),
                'type' => $template['type'],
                'header' => filled($template['header'] ?? null) ? trim($template['header']) : null,
                'footer' => filled($template['footer'] ?? null) ? trim($template['footer']) : null,
                'is_default' => (bool) $template['is_default'],
            ])->values()->all();
        }

        unset($data['is_official'], $data['official_club_number'], $data['verification_status']);

        if ($hasSubmittedClubNumber) {
            $data['requested_official_club_number'] = $submittedClubNumber !== '' ? $submittedClubNumber : null;

            if ($submittedClubNumber !== '' && $submittedClubNumber !== (string) $club->official_club_number) {
                $data['verification_status'] = 'pending_verification';
                $data['verification_requested_at'] = now();
                $data['verification_notes'] = null;
            }
        }

        DB::transaction(function () use ($club, $data, $actor, $submittedLegalFields, $submittedContactFields, $submittedBrandingFields) {
            $before = collect($club->only([...$submittedLegalFields, ...$submittedContactFields, ...$submittedBrandingFields]));
            $club->update($data);

            $changedLegal = collect($submittedLegalFields)
                ->filter(fn (string $field) => $before->get($field) != $club->getAttribute($field))
                ->values()->all();

            if ($changedLegal !== []) {
                ClubAuditLog::record($club, $actor, 'club.legal_master_data.updated', $club, [
                    'changed_fields' => $changedLegal,
                ]);
            }

            $changedContacts = collect($submittedContactFields)
                ->filter(fn (string $field) => $before->get($field) != $club->getAttribute($field))
                ->values()->all();

            if ($changedContacts !== []) {
                ClubAuditLog::record($club, $actor, 'club.contact_master_data.updated', $club, [
                    'changed_fields' => $changedContacts,
                ]);
            }

            $changedBranding = collect($submittedBrandingFields)
                ->filter(fn (string $field) => $before->get($field) != $club->getAttribute($field))
                ->values()->all();

            if ($changedBranding !== []) {
                ClubAuditLog::record($club, $actor, 'club.branding.updated', $club, [
                    'changed_fields' => $changedBranding,
                ]);
            }
        });

        return $club;
    }

    public function assignClubOwnerRole(User $user): void
    {
        if (Role::query()->where('name', 'club_owner')->exists() && ! $user->hasRole('club_owner')) {
            $user->assignRole('club_owner');
        }
    }

    public function refreshClubOwnerRole(User $user): void
    {
        if (! Role::query()->where('name', 'club_owner')->exists()) {
            return;
        }

        $ownsClub = Club::query()->where('owner_id', $user->id)->exists();

        if ($ownsClub && ! $user->hasRole('club_owner')) {
            $user->assignRole('club_owner');

            return;
        }

        if (! $ownsClub && $user->hasRole('club_owner')) {
            $user->removeRole('club_owner');
        }
    }

    private function sendRegistrationNotifications(Club $club, User $user): void
    {
        try {
            $user->notify(new ClubRegistrationSubmitted($club));

            AppNotification::send($user, 'club.registration_submitted', [
                'title' => 'Vereinsbereich aktiviert',
                'body' => 'Dein Vereinsbereich ist sofort aktiv. Airmius prüft den Vereinsantrag und informiert dich über das Ergebnis.',
                'url' => route('auth.clubs.show', $club->id),
                'club_id' => $club->id,
                'club_name' => $club->name,
                'verification_status' => $club->verification_status,
            ]);

            $reviewers = User::permission('system.manage')->get();
            if ($reviewers->isNotEmpty()) {
                Notification::send($reviewers, new ClubRegistrationReviewRequested($club, $user));
                $reviewers->each(fn (User $reviewer) => AppNotification::send($reviewer, 'club.registration_review_requested', [
                    'title' => 'Neuer Vereinsantrag',
                    'body' => $user->name.' hat den Verein „'.$club->name.'“ registriert.',
                    'url' => route('admin.club-verifications.index'),
                    'club_id' => $club->id,
                    'verification_status' => $club->verification_status,
                ]));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
