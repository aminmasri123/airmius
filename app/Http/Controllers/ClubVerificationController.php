<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Notifications\ClubVerificationStatusUpdated;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClubVerificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->can('system.manage'), 403);

        return Inertia::render('Auth/Dashboard/Admin/ClubVerifications/Index', [
            'clubs' => Club::query()
                ->with('owner:id,name,email')
                ->latest('verification_requested_at')
                ->latest('id')
                ->limit(100)
                ->get([
                    'id',
                    'name',
                    'sport_type',
                    'country',
                    'city',
                    'owner_id',
                    'is_official',
                    'official_club_number',
                    'requested_official_club_number',
                    'verification_status',
                    'verification_notes',
                    'verification_requested_at',
                    'verified_at',
                    'rejected_at',
                ]),
        ]);
    }

    public function approve(Request $request, Club $club)
    {
        abort_unless($request->user()->can('system.manage'), 403);
        abort_if((int) $club->owner_id === (int) $request->user()->id, 422, __('validation.approval_second_person'));

        $data = $request->validate([
            'official_club_number' => ['nullable', 'string', 'max:120'],
            'verification_notes' => ['nullable', 'string', 'max:2000'],
            'mark_official' => ['boolean'],
        ]);

        $number = $data['official_club_number'] ?? $club->requested_official_club_number;
        $markOfficial = (bool) ($data['mark_official'] ?? filled($number));

        $club->forceFill([
            'verification_status' => 'verified',
            'verification_notes' => $data['verification_notes'] ?? null,
            'is_official' => $markOfficial,
            'official_club_number' => $markOfficial ? $number : null,
            'verified_at' => now(),
            'rejected_at' => null,
            'verified_by' => $request->user()->id,
        ])->save();

        $club->owner?->notify(new ClubVerificationStatusUpdated($club));
        if ($club->owner) {
            AppNotification::sendLocalized(
                $club->owner,
                'club.verification_status_updated',
                'organization.notifications.verification_approved_title',
                'organization.notifications.verification_approved_body',
                ['club' => $club->name],
                [
                    'url' => route('auth.clubs.show', $club->id),
                    'club_id' => $club->id,
                    'verification_status' => $club->verification_status,
                ],
            );
        }

        return back()->with('success', __('organization.club.verification_approved'));
    }

    public function reject(Request $request, Club $club)
    {
        abort_unless($request->user()->can('system.manage'), 403);

        $data = $request->validate([
            'verification_notes' => ['required', 'string', 'max:2000'],
        ]);

        $club->forceFill([
            'verification_status' => 'rejected',
            'verification_notes' => $data['verification_notes'],
            'is_official' => false,
            'official_club_number' => null,
            'verified_at' => null,
            'rejected_at' => now(),
            'verified_by' => $request->user()->id,
        ])->save();

        $club->owner?->notify(new ClubVerificationStatusUpdated($club));
        if ($club->owner) {
            AppNotification::sendLocalized(
                $club->owner,
                'club.verification_status_updated',
                'organization.notifications.verification_rejected_title',
                'organization.notifications.verification_rejected_body',
                ['club' => $club->name],
                [
                    'url' => route('auth.clubs.show', $club->id),
                    'club_id' => $club->id,
                    'verification_status' => $club->verification_status,
                    'verification_notes' => $club->verification_notes,
                ],
            );
        }

        return back()->with('success', __('organization.club.verification_rejected'));
    }
}
