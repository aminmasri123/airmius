<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Notifications\ClubVerificationStatusUpdated;
use App\Services\ClubDataErasureService;
use App\Support\AppNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

class AdminClubController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ClubDataErasureService $erasure) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'query' => ['nullable', 'string', 'max:120'],
            'verification' => ['nullable', Rule::in(['pending_verification', 'verified', 'rejected'])],
        ]);
        $search = trim((string) ($filters['query'] ?? ''));
        $verification = (string) ($filters['verification'] ?? '');

        $clubs = Club::query()
            ->with([
                'owner:id,name,email',
                'currentSubscription.plan:id,name',
            ])
            ->withCount(['users', 'externalMembers', 'teams'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('city', 'like', '%'.$search.'%')
                        ->orWhere('official_club_number', 'like', '%'.$search.'%')
                        ->orWhereHas('owner', fn ($ownerQuery) => $ownerQuery
                            ->where('name', 'like', '%'.$search.'%')
                            ->orWhere('email', 'like', '%'.$search.'%'));
                });
            })
            ->when($verification !== '', fn ($query) => $query->where('verification_status', $verification))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Club $club) => [
                'id' => $club->id,
                'name' => $club->name,
                'sport_type' => $club->sport_type,
                'city' => $club->city,
                'country' => $club->country,
                'official_club_number' => $club->official_club_number,
                'verification_status' => $club->verification_status,
                'is_listed' => (bool) $club->is_listed,
                'owner' => $club->owner,
                'users_count' => $club->users_count,
                'external_members_count' => $club->external_members_count,
                'members_count' => $club->users_count + $club->external_members_count,
                'teams_count' => $club->teams_count,
                'plan' => $club->currentSubscription?->plan,
                'created_at' => $club->created_at?->toDateString(),
            ]);

        $payload = [
            'clubs' => $clubs,
            'filters' => [
                'query' => $search,
                'verification' => $verification,
            ],
            'summary' => [
                'total' => Club::query()->count(),
                'verified' => Club::query()->where('verification_status', 'verified')->count(),
                'pending' => Club::query()->where('verification_status', 'pending_verification')->count(),
                'unlisted' => Club::query()->where('is_listed', false)->count(),
            ],
            'canDeleteClubs' => $request->user()?->hasRole('super_admin') ?? false,
        ];

        return $request->expectsJson()
            ? response()->json(['data' => $payload])
            : Inertia::render('Auth/Dashboard/Admin/Clubs/Index', $payload);
    }

    public function destroy(Request $request, Club $club)
    {
        abort_unless(
            $request->user()?->hasRole('super_admin'),
            403,
            __('organization.admin_clubs.super_admin_required'),
        );
        $this->authorize('delete', $club);

        $request->merge([
            'confirmation_name' => trim((string) $request->input('confirmation_name')),
        ]);
        $request->validate([
            'confirmation_name' => ['required', 'string', Rule::in([$club->name])],
        ], [
            'confirmation_name.in' => __('organization.admin_clubs.name_mismatch'),
        ]);

        $clubName = $club->name;
        $owner = $club->owner;

        DB::transaction(function () use ($request, $club): void {
            $lockedClub = Club::query()->lockForUpdate()->findOrFail($club->id);
            $this->erasure->eraseForAdministrator($lockedClub, $request->user());
            DB::afterCommit(function (): void {
                try {
                    $this->erasure->cleanupFiles();
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        });

        if ($owner) {
            AppNotification::sendLocalized(
                $owner,
                'club.deleted_by_platform',
                'organization.notifications.club_deleted_by_platform_title',
                'organization.notifications.club_deleted_by_platform_body',
                ['club' => $clubName],
                ['url' => route('auth.dashboard')],
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => ['deleted' => true]]);
        }

        return back()->with('success', __('organization.admin_clubs.deleted', ['club' => $clubName]));
    }

    public function updateVerificationStatus(Request $request, Club $club)
    {
        abort_unless($request->user()?->can('system.manage'), 403);

        $data = $request->validate([
            'verification_status' => ['required', Rule::in(['pending_verification', 'verified', 'rejected'])],
        ]);

        $status = $data['verification_status'];
        abort_if(
            $status === 'verified' && (int) $club->owner_id === (int) $request->user()->id,
            422,
            __('validation.approval_second_person'),
        );

        $attributes = [
            'verification_status' => $status,
        ];

        if ($status === 'verified') {
            $attributes += [
                'verified_at' => now(),
                'rejected_at' => null,
                'verified_by' => $request->user()->id,
            ];
        } elseif ($status === 'rejected') {
            $attributes += [
                'is_official' => false,
                'official_club_number' => null,
                'verified_at' => null,
                'rejected_at' => now(),
                'verified_by' => $request->user()->id,
            ];
        } else {
            $attributes += [
                'verified_at' => null,
                'rejected_at' => null,
                'verified_by' => null,
                'verification_requested_at' => $club->verification_requested_at ?? now(),
            ];
        }

        $club->forceFill($attributes)->save();

        if ($club->owner && $status !== 'pending_verification') {
            $club->owner->notify(new ClubVerificationStatusUpdated($club));

            $titleKey = match ($status) {
                'verified' => 'organization.notifications.verification_approved_title',
                'rejected' => 'organization.notifications.verification_rejected_title',
            };
            $bodyKey = match ($status) {
                'verified' => 'organization.notifications.verification_approved_body',
                'rejected' => 'organization.notifications.verification_rejected_body',
            };

            AppNotification::sendLocalized(
                $club->owner,
                'club.verification_status_updated',
                $titleKey,
                $bodyKey,
                ['club' => $club->name],
                [
                    'url' => route('auth.clubs.show', $club->id),
                    'club_id' => $club->id,
                    'verification_status' => $club->verification_status,
                ],
            );
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => [
                'id' => $club->id,
                'verification_status' => $club->verification_status,
            ]]);
        }

        return back()->with('success', __('organization.club.verification_status_updated'));
    }
}
