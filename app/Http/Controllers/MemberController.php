<?php

namespace App\Http\Controllers;

use App\Models\AccountWarning;
use App\Models\MailDelivery;
use App\Models\User;
use App\Notifications\AccountSuspendedNotification;
use App\Notifications\InactiveAccountNotice;
use App\Services\AdminCreatedUserProvisioner;
use App\Support\TransactionalMail;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MemberController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $canManageInactivity = (bool) $request->user()?->can('system.manage');
        $tab = (string) $request->input('tab', 'users');
        if ($tab === 'inactivity' && ! $canManageInactivity) {
            $tab = 'users';
        }
        $inactiveSearch = trim((string) $request->input('inactive_search', ''));
        $inactiveStage = (string) $request->input('inactive_stage', 'all');

        $users = User::query()
            ->select(['id', 'name', 'email', 'profile_visibility', 'account_status', 'suspended_until', 'suspension_reason', 'last_login_at', 'last_seen_at', 'created_at'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($status === 'suspended', fn ($query) => $query->where('account_status', 'suspended'))
            ->when($status === 'active', fn ($query) => $query->where(function ($query) {
                $query->whereNull('account_status')
                    ->orWhere('account_status', 'active');
            }))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $inactiveUsers = $canManageInactivity
            ? $this->inactiveUsers($inactiveSearch, $inactiveStage)
            : ['data' => [], 'links' => [], 'from' => null, 'to' => null, 'total' => 0];

        $payload = [
            'users' => $users,
            'inactiveUsers' => $inactiveUsers,
            'inactiveSummary' => $canManageInactivity ? $this->inactiveSummary() : [],
            'inactiveRules' => $canManageInactivity ? $this->inactiveRules() : [],
            'canManageInactivity' => $canManageInactivity,
            'warnings' => AccountWarning::query()
                ->with(['user:id,name,email,account_status,suspended_until', 'flag'])
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (AccountWarning $warning) => [
                    'id' => $warning->id,
                    'severity' => $warning->severity,
                    'points' => $warning->points,
                    'reason' => $warning->reason,
                    'created_at' => $warning->created_at,
                    'user' => $warning->user,
                    'flag' => $warning->flag ? [
                        'categories' => $warning->flag->categories ?: [],
                        'matched_terms' => $warning->flag->matched_terms ?: [],
                        'status' => $warning->flag->status,
                    ] : null,
                ]),
            'filters' => [
                'search' => $search,
                'status' => in_array($status, ['all', 'active', 'suspended'], true) ? $status : 'all',
                'tab' => in_array($tab, ['users', 'warnings', 'inactivity'], true) ? $tab : 'users',
                'inactive_search' => $inactiveSearch,
                'inactive_stage' => in_array($inactiveStage, ['all', '12', '18', '24', '36', 'mail_failed'], true) ? $inactiveStage : 'all',
            ],
        ];

        return $request->expectsJson()
            ? response()->json(['data' => $payload])
            : Inertia::render('Auth/Dashboard/Users/Index', $payload);
    }

    public function sendInactivityNotice(Request $request, User $user, TransactionalMail $mail)
    {
        $this->authorize('viewAny', User::class);

        $data = $request->validate([
            'stage' => ['required', Rule::in(['first', 'second', 'scheduled'])],
        ]);

        abort_if($user->privacy_status === 'anonymized', 422, 'Anonymisierte Nutzer können nicht mehr angeschrieben werden.');

        $scheduledAt = $data['stage'] === 'scheduled'
            ? $this->anonymizationDateFor($user)
            : null;

        $sent = $mail->notifyWithFallback(
            $user,
            fn (array $transport) => new InactiveAccountNotice(
                $data['stage'],
                $scheduledAt?->format('d.m.Y'),
                $transport['mailer'],
                $transport['address'],
                $transport['name'],
            ),
            'support',
            'billing',
            'inactive-account:manual:'.$data['stage'].':'.$user->id.':'.now()->timestamp,
            10,
            [
                'mail_type' => 'inactive_account.'.$data['stage'],
                'manual' => true,
                'user_id' => $user->id,
                'sent_by' => $request->user()?->id,
                'scheduled_at' => $scheduledAt?->toDateString(),
            ],
        );

        if (! $sent) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Inaktivitäts-Mail konnte nicht gesendet werden.'], 422);
            }

            return back()->with('error', 'Inaktivitäts-Mail konnte nicht gesendet werden. Details stehen in der Mail-Zentrale.');
        }

        $this->markInactivityNoticeSent($user, $data['stage'], $scheduledAt);

        if ($request->expectsJson()) {
            return response()->json(['data' => ['sent' => true]]);
        }

        return back()->with('success', 'Inaktivitäts-Mail wurde gesendet und protokolliert.');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create', User::class);

        return Inertia::render('Auth/Dashboard/Users/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, AdminCreatedUserProvisioner $provisioner)
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'generate_password' => ['sometimes', 'boolean'],
            'send_credentials' => ['sometimes', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', Rule::requiredIf(! $request->boolean('generate_password'))],
            'password_confirmation' => ['nullable', 'same:password', Rule::requiredIf(! $request->boolean('generate_password'))],
            'profile_visibility' => ['nullable', Rule::in(['public', 'private', 'friends'])],
        ]);

        $result = $provisioner->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'profile_visibility' => $data['profile_visibility'] ?? 'public',
            'generate_password' => $request->boolean('generate_password'),
            'send_credentials' => $request->boolean('send_credentials'),
        ]);

        $message = $result['credentials_sent']
            ? 'Nutzer wurde erstellt. Die Zugangsdaten wurden per E-Mail versendet.'
            : ($request->boolean('generate_password')
                ? 'Nutzer wurde erstellt. Das generierte Kennwort wurde nicht per E-Mail versendet.'
                : 'Nutzer wurde erstellt.');

        return redirect()->route('members.index')->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        $canManageRoles = request()->user()->can('assignRoles', $user);

        $payload = [
            'user' => array_merge($user->only([
                'id',
                'name',
                'first_name',
                'last_name',
                'email',
                'birth_date',
                'profile_visibility',
                'bio',
                'account_status',
                'suspended_until',
                'suspension_reason',
            ]), [
                'birth_date' => $user->birth_date?->toDateString(),
                'suspended_until' => $user->suspended_until?->toDateTimeString(),
                'roles' => $user->getRoleNames()->values()->all(),
                'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            ]),
            'availableRoles' => $canManageRoles
                ? Role::query()->orderBy('name')->pluck('name')->values()
                : [],
            'availablePermissions' => $canManageRoles
                ? Permission::query()->orderBy('name')->pluck('name')->values()
                : [],
            'canManageRoles' => $canManageRoles,
        ];

        return request()->expectsJson()
            ? response()->json(['data' => $payload])
            : Inertia::render('Auth/Dashboard/Users/Edit', $payload);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $canManageRoles = $request->user()->can('assignRoles', $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'profile_visibility' => ['required', Rule::in(['public', 'private', 'friends'])],
            'bio' => ['nullable', 'string', 'max:1000'],
            'suspension_action' => ['nullable', Rule::in(['', 'lift', '1', '3', '7', '10', '14', '30', '60', '90'])],
            'suspension_reason' => ['nullable', 'string', 'max:500'],
            'roles' => [$canManageRoles ? 'array' : 'prohibited'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $displayName = trim($firstName.' '.$lastName);

        $user->update([
            'name' => $displayName !== '' ? $displayName : $data['name'],
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $data['email'],
            'birth_date' => isset($data['birth_date']) && $data['birth_date'] !== ''
                ? Carbon::parse($data['birth_date'])->toDateString()
                : null,
            'profile_visibility' => $data['profile_visibility'],
            'bio' => $data['bio'] ?? null,
        ]);

        if (($data['suspension_action'] ?? '') === 'lift') {
            $user->forceFill([
                'account_status' => 'active',
                'suspended_until' => null,
                'suspension_reason' => null,
            ])->save();
        } elseif (in_array((string) ($data['suspension_action'] ?? ''), ['1', '3', '7', '10', '14', '30', '60', '90'], true)) {
            $suspendedUntil = now()->addDays((int) $data['suspension_action']);
            $reason = $data['suspension_reason'] ?? null;

            $user->forceFill([
                'account_status' => 'suspended',
                'suspended_until' => $suspendedUntil,
                'suspension_reason' => $reason,
            ])->save();

            try {
                $user->notify(new AccountSuspendedNotification($reason, $suspendedUntil->format('d.m.Y H:i')));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        if ($canManageRoles && array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles']);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => ['id' => $user->id]]);
        }

        return redirect()->route('members.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        abort_if($user->is(request()->user()), 403);
        $this->authorize('delete', $user);

        $user->delete();

        if (request()->expectsJson()) {
            return response()->json(['data' => ['deleted' => true]]);
        }

        return redirect()->route('members.index')->with('success', 'User deleted successfully.');
    }

    private function inactiveUsers(string $search, string $stage)
    {
        $failedRecipientIds = $stage === 'mail_failed'
            ? MailDelivery::query()
                ->where('status', 'failed')
                ->where('mail_type', 'like', 'inactive_account.%')
                ->pluck('recipient_id')
                ->filter()
                ->unique()
                ->values()
                ->all()
            : [];

        $users = User::query()
            ->select([
                'id',
                'name',
                'email',
                'profile_photo_path',
                'profile_visibility',
                'account_status',
                'privacy_status',
                'last_login_at',
                'last_seen_at',
                'created_at',
                'updated_at',
                'inactivity_first_warning_sent_at',
                'inactivity_second_warning_sent_at',
                'deletion_scheduled_at',
                'anonymized_at',
            ])
            ->where(function ($query) {
                $query->whereNull('privacy_status')
                    ->orWhere('privacy_status', '!=', 'anonymized');
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(in_array($stage, ['12', '18', '24', '36'], true), function ($query) use ($stage) {
                $query->whereRaw('COALESCE(last_login_at, last_seen_at, updated_at, created_at) <= ?', [
                    now()->subMonthsNoOverflow((int) $stage)->toDateTimeString(),
                ]);
            })
            ->when($stage === 'mail_failed', fn ($query) => $query->whereIn('id', $failedRecipientIds ?: [0]))
            ->orderByRaw('COALESCE(last_login_at, last_seen_at, updated_at, created_at) asc')
            ->paginate(25, ['*'], 'inactive_page')
            ->withQueryString();

        $ids = collect($users->items())->pluck('id')->all();
        $deliveries = MailDelivery::query()
            ->whereIn('recipient_id', $ids ?: [0])
            ->where('mail_type', 'like', 'inactive_account.%')
            ->latest()
            ->get()
            ->groupBy('recipient_id');

        return $users->through(fn (User $user) => $this->inactiveUserPayload($user, $deliveries->get($user->id, collect())));
    }

    private function inactiveUserPayload(User $user, $deliveries): array
    {
        $basis = $this->activityBasis($user);
        $lastDelivery = $deliveries->first();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profile_photo_url' => $user->profile_photo_thumb ?: $user->profile_photo_url,
            'profile_visibility' => $user->profile_visibility,
            'account_status' => $user->account_status,
            'privacy_status' => $user->privacy_status ?: 'active',
            'last_login_at' => $user->last_login_at?->format('d.m.Y H:i'),
            'last_seen_at' => $user->last_seen_at?->format('d.m.Y H:i'),
            'inactive_days' => $basis ? (int) $basis->diffInDays(now()) : null,
            'first_warning_sent_at' => $user->inactivity_first_warning_sent_at?->format('d.m.Y H:i'),
            'second_warning_sent_at' => $user->inactivity_second_warning_sent_at?->format('d.m.Y H:i'),
            'deletion_scheduled_at' => $user->deletion_scheduled_at?->format('d.m.Y H:i'),
            'recommended_stage' => $this->recommendedInactivityStage($user, $basis),
            'last_mail' => $lastDelivery ? [
                'id' => $lastDelivery->id,
                'type' => $lastDelivery->mail_type,
                'status' => $lastDelivery->status,
                'used_category' => $lastDelivery->used_category,
                'from_address' => $lastDelivery->from_address,
                'error_message' => $lastDelivery->error_message,
                'created_at' => $lastDelivery->created_at?->format('d.m.Y H:i'),
            ] : null,
        ];
    }

    private function inactiveSummary(): array
    {
        return [
            'inactive_12' => $this->countInactiveSince(12),
            'inactive_18' => $this->countInactiveSince(18),
            'inactive_24' => $this->countInactiveSince(24),
            'inactive_36' => $this->countInactiveSince(36),
            'mail_failed' => MailDelivery::query()
                ->where('status', 'failed')
                ->where('mail_type', 'like', 'inactive_account.%')
                ->count(),
        ];
    }

    private function inactiveRules(): array
    {
        return collect(['inactive', 'reactivation', 'hidden', 'anonymized', 'archived'])
            ->map(fn (string $rule) => [
                'month' => __("member_lifecycle.rules.{$rule}.month"),
                'title' => __("member_lifecycle.rules.{$rule}.title"),
                'description' => __("member_lifecycle.rules.{$rule}.description"),
            ])
            ->all();
    }

    private function countInactiveSince(int $months): int
    {
        return User::query()
            ->where(function ($query) {
                $query->whereNull('privacy_status')
                    ->orWhere('privacy_status', '!=', 'anonymized');
            })
            ->whereRaw('COALESCE(last_login_at, last_seen_at, updated_at, created_at) <= ?', [
                now()->subMonthsNoOverflow($months)->toDateTimeString(),
            ])
            ->count();
    }

    private function recommendedInactivityStage(User $user, ?Carbon $basis): string
    {
        if (! $basis) {
            return 'check';
        }

        $months = (int) $basis->diffInMonths(now());

        if ($months >= 36) {
            return 'anonymize';
        }

        if ($months >= 24) {
            return 'scheduled';
        }

        if ($months >= 18) {
            return $user->inactivity_second_warning_sent_at ? 'waiting' : 'second';
        }

        if ($months >= 12) {
            return $user->inactivity_first_warning_sent_at ? 'waiting' : 'first';
        }

        return 'active';
    }

    private function markInactivityNoticeSent(User $user, string $stage, ?Carbon $scheduledAt): void
    {
        $changes = match ($stage) {
            'first' => [
                'privacy_status' => 'inactive_warning_sent',
                'inactivity_first_warning_sent_at' => now(),
            ],
            'second' => [
                'privacy_status' => 'inactive_warning_sent',
                'inactivity_second_warning_sent_at' => now(),
            ],
            'scheduled' => [
                'privacy_status' => 'scheduled_for_anonymization',
                'profile_visibility' => 'private',
                'deletion_scheduled_at' => $scheduledAt,
            ],
        };

        $user->forceFill($changes)->save();
    }

    private function anonymizationDateFor(User $user): Carbon
    {
        $basis = $this->activityBasis($user) ?: now();
        $date = $basis->copy()->addMonthsNoOverflow(36);

        return $date->isPast() ? now()->addDays(30) : $date;
    }

    private function activityBasis(User $user): ?Carbon
    {
        return $user->last_login_at
            ?? $user->last_seen_at
            ?? $user->updated_at
            ?? $user->created_at;
    }
}
