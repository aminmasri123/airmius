<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubBusinessPartner;
use Illuminate\Validation\ValidationException;

class ClubDonationDonorService
{
    public const TYPES = ['member', 'external_member', 'partner', 'sponsor', 'other'];

    public function options(Club $club): array
    {
        return $club->users()->orderBy('name')->get()->map(fn ($member) => [
            'key' => 'member:'.$member->id, 'type' => 'member', 'id' => $member->id,
            'name' => $member->name, 'email' => $member->email,
        ])->concat($club->externalMembers()->orderBy('name')->get()->map(fn ($member) => [
            'key' => 'external_member:'.$member->id, 'type' => 'external_member', 'id' => $member->id,
            'name' => $member->name, 'email' => $member->email,
        ]))->concat(ClubBusinessPartner::where('club_id', $club->id)->where('is_active', true)->orderBy('name')->get()->map(fn ($partner) => [
            'key' => 'partner:'.$partner->id, 'type' => 'partner', 'id' => $partner->id,
            'name' => $partner->name, 'email' => $partner->contact['email'] ?? null,
        ]))->concat($club->sponsors()->orderBy('name')->get()->map(fn ($sponsor) => [
            'key' => 'sponsor:'.$sponsor->id, 'type' => 'sponsor', 'id' => $sponsor->id,
            'name' => $sponsor->name, 'email' => $sponsor->email,
        ]))->values()->all();
    }

    public function resolve(Club $club, array $data): array
    {
        $type = $data['donor_type'] ?? 'member';
        $fields = ['member' => 'user_id', 'external_member' => 'club_external_member_id',
            'partner' => 'club_business_partner_id', 'sponsor' => 'sponsor_id'];
        $field = $fields[$type] ?? null;
        foreach ($fields as $candidate) {
            if ($candidate !== $field && filled($data[$candidate] ?? null)) {
                throw ValidationException::withMessages(['donor_type' => __('validation.prohibited', ['attribute' => $candidate])]);
            }
        }
        $attributes = ['user_id' => null, 'club_external_member_id' => null,
            'club_business_partner_id' => null, 'sponsor_id' => null, 'donor_type' => $type];
        $member = null;
        if ($type === 'other') {
            $name = trim((string) ($data['donor_name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(['donor_name' => __('validation.required', ['attribute' => 'Name'])]);
            }
            $snapshot = ['name' => $name, 'email' => $data['donor_email'] ?? null,
                'address' => $data['donor_address'] ?? null];
        } else {
            if (! filled($data[$field] ?? null)) {
                throw ValidationException::withMessages([$field => __('validation.required', ['attribute' => $field])]);
            }
            $source = match ($type) {
                'member' => $club->users()->where('users.id', $data[$field])->firstOrFail(),
                'external_member' => $club->externalMembers()->findOrFail($data[$field]),
                'partner' => ClubBusinessPartner::where('club_id', $club->id)->where('is_active', true)->findOrFail($data[$field]),
                'sponsor' => $club->sponsors()->findOrFail($data[$field]),
            };
            $attributes[$field] = $source->id;
            $snapshot = ['name' => $source->name,
                'email' => $type === 'partner' ? ($source->contact['email'] ?? null) : $source->email];
            if ($type === 'member') {
                $member = $source;
            }
        }

        return ['attributes' => [...$attributes, 'donor_snapshot' => $snapshot], 'member' => $member];
    }
}
