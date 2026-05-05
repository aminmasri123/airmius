<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Notifications\ClubVerificationStatusUpdated;
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

        return back()->with('success', 'Verein wurde freigegeben.');
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

        return back()->with('success', 'Vereinsantrag wurde abgelehnt.');
    }
}
