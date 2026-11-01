<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPlan;
use App\Services\ClubService;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\ClubPermissions;
use App\Support\Validation\ClubProfileRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AdminClubWorkspaceController extends Controller
{
    public const FIELDS = [
        'name' => 'Vereinsname', 'sport_type' => 'Sportart', 'country' => 'Land (z. B. DE)',
        'street' => 'Straße', 'house_number' => 'Hausnummer', 'postal_code' => 'Postleitzahl',
        'city' => 'Ort', 'state' => 'Bundesland', 'contact_email' => 'Kontakt-E-Mail',
        'contact_phone' => 'Telefon', 'website_url' => 'Website',
        'registry_authority' => 'Registergericht', 'registry_number' => 'Registernummer',
        'tax_authority' => 'Finanzamt', 'tax_number' => 'Steuernummer', 'vat_id' => 'Umsatzsteuer-ID',
    ];

    public function show(Request $request, Club $club)
    {
        abort_unless($request->user()->can('system.manage'), 403);
        $request->validate(['page' => ['nullable', 'integer', 'min:1'], 'teams_page' => ['nullable', 'integer', 'min:1'], 'invoices_page' => ['nullable', 'integer', 'min:1']]);
        $club->load('owner:id,name,email', 'currentSubscription.plan')->loadCount(['users', 'externalMembers', 'teams']);
        $billing = $request->user()->can('subscriptions.manage');
        $payload = [
            'club' => $club->only([...array_keys(self::FIELDS), 'id', 'verification_status', 'verification_notes',
                'official_club_number', 'verified_at', 'is_listed', 'users_count', 'external_members_count', 'teams_count']),
            'owner' => $club->owner,
            'fields' => self::FIELDS,
            'canManageSubscription' => $billing,
            'canManageRoles' => ClubPermissions::editableBy($club, $request->user()),
            'subscription' => $billing ? $club->currentSubscription?->only(['id', 'subscription_plan_id', 'status', 'trial_ends_at', 'current_period_ends_at', 'cancel_at_period_end']) : null,
            'planName' => $club->currentSubscription?->plan?->name,
            'plans' => $billing ? SubscriptionPlan::query()->where(fn ($query) => $query
                ->where(fn ($available) => $available->where('is_active', true)->where('target_actor', 'verein'))
                ->orWhere('id', $club->currentSubscription?->subscription_plan_id))
                ->orderBy('name')->get(['id', 'name']) : [],
            'members' => $club->users()->orderBy('users.name')->paginate(25, ['users.id', 'users.name', 'users.email'])->through(fn ($user) => [
                'id' => $user->id, 'name' => $user->name, 'email' => $user->email,
                'role' => $user->pivot->role,
            ]),
            'teams' => $club->teams()->orderBy('name')->paginate(25, ['id', 'name', 'sport_type'], 'teams_page'),
            'invoices' => $billing ? SubscriptionInvoice::query()->where('club_id', $club->id)->latest('id')->paginate(25, ['id', 'number', 'status', 'amount_cents', 'currency', 'issued_at'], 'invoices_page') : null,
            'history' => array_map(fn ($entry) => array_intersect_key($entry, array_flip(['id', 'label', 'actor', 'created_at'])), ClubAuditLog::forClub($club)),
        ];

        return $request->expectsJson() ? response()->json(['data' => $payload])
            : Inertia::render('Auth/Dashboard/Admin/Clubs/Show', $payload);
    }

    public function update(Request $request, Club $club, ClubService $service)
    {
        abort_unless($request->user()->can('system.manage'), 403);
        $rules = array_intersect_key(ClubProfileRules::update(), self::FIELDS);
        $data = $request->validate($rules);
        DB::transaction(function () use ($request, $club, $service, $data) {
            $club = Club::query()->lockForUpdate()->findOrFail($club->id);
            $before = $club->only(array_keys($data));
            $service->update($club, $data, $request->user());
            $changed = array_keys(array_filter($data, fn ($value, $key) => $before[$key] != $club->getAttribute($key), ARRAY_FILTER_USE_BOTH));
            if ($changed === []) {
                return;
            }
            ClubAuditLog::record($club, $request->user(), 'club.platform.updated', $club, ['changed_fields' => $changed]);
            if ($club->owner) {
                AppNotification::send($club->owner, 'club.platform.updated', [
                    'title' => 'Vereinsdaten geändert',
                    'body' => 'Die Plattformverwaltung hat die Daten von '.$club->name.' geändert: '.implode(', ', array_map(fn ($key) => self::FIELDS[$key], $changed)).'.',
                    'club_id' => $club->id, 'url' => route('auth.clubs.show', $club),
                ], ['after_commit' => true]);
            }
        });

        return $request->expectsJson() ? response()->json(['data' => ['saved' => true]]) : back()->with('success', 'Vereinsdaten gespeichert.');
    }
}
