<?php

namespace App\Http\Controllers;

use App\Models\SportSkill;
use App\Models\User;
use App\Models\UserRoleApplication;
use App\Notifications\TrainerApplicationStatusUpdated;
use App\Services\AccountRoleApplicationService;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccountRoleApplicationController extends Controller
{
    public function store(Request $request, AccountRoleApplicationService $applications)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([UserRoleApplication::TYPE_TRAINER, UserRoleApplication::TYPE_SPONSOR])],
            'message' => ['nullable', 'string', 'max:2000'],
            'application_data' => ['nullable', 'array'],
            'application_data.specialties' => ['nullable', 'string', 'max:500'],
            'application_data.sports' => ['nullable', 'string', 'max:500'],
            'application_data.sport_ids' => ['nullable', 'array', 'max:20'],
            'application_data.sport_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('sports', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'application_data.sport_skill_ids' => ['nullable', 'array', 'max:40'],
            'application_data.sport_skill_ids.*' => [
                'integer',
                'distinct',
                'exists:sport_skills,id',
            ],
            'application_data.experience' => ['nullable', 'string', 'max:2000'],
            'application_data.certification' => ['nullable', 'string', 'max:500'],
            'application_data.company_name' => ['required_if:type,sponsor', 'nullable', 'string', 'max:255'],
            'application_data.legal_name' => ['nullable', 'string', 'max:255'],
            'application_data.website' => ['nullable', 'url:http,https', 'max:2048'],
            'application_data.country_code' => ['required_if:type,sponsor', 'nullable', 'string', 'size:2'],
            'application_data.contact_name' => ['required_if:type,sponsor', 'nullable', 'string', 'max:255'],
            'application_data.contact_email' => ['required_if:type,sponsor', 'nullable', 'email', 'max:255'],
            'application_data.phone' => ['nullable', 'string', 'max:80'],
            'application_data.interests' => ['nullable', 'string', 'max:1000'],
            'application_data.goals' => ['nullable', 'string', 'max:2000'],
            'application_data.legal_accuracy' => ['required_if:type,sponsor', 'nullable', 'accepted'],
            'application_data.data_privacy' => ['required_if:type,sponsor', 'nullable', 'accepted'],
        ]);

        if ($data['type'] === UserRoleApplication::TYPE_SPONSOR) {
            $result = $applications->submitSponsor(
                $request->user(),
                $data['message'] ?? null,
                $data['application_data'] ?? [],
            );

            return $this->response($request, [
                'message' => $result['created']
                    ? 'Dein Sponsor-Antrag wurde eingereicht.'
                    : 'Dein offener Sponsor-Antrag wurde aktualisiert.',
                'application' => $this->applicationPayload($result['application']),
            ], $result['created'] ? 201 : 200);
        }

        $applicationData = $this->normalizeTrainerApplicationData($data['application_data'] ?? []);

        $result = $applications->submitTrainer(
            $request->user(),
            $data['message'] ?? null,
            null,
            $applicationData,
        );
        $application = $result['application'];

        return $this->response($request, [
            'message' => $result['created']
                ? 'Dein Trainerzugang wurde aktiviert. Der Antrag wird jetzt von Airmius geprüft.'
                : 'Dein Trainerantrag wird bereits von Airmius geprüft.',
            'application' => $this->applicationPayload($application),
        ], $result['created'] ? 201 : 200);
    }

    public function index(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'data' => $request->user()
                    ->roleApplications()
                    ->latest('requested_at')
                    ->get()
                    ->map(fn (UserRoleApplication $application) => $this->applicationPayload($application))
                    ->values(),
            ]);
        }

        $applications = UserRoleApplication::query()
            ->with('user:id,name,email')
            ->where('type', UserRoleApplication::TYPE_TRAINER)
            ->latest('requested_at')
            ->limit(100)
            ->get();

        return Inertia::render('Auth/Dashboard/Admin/TrainerApplications/Index', [
            'applications' => $applications->map(fn (UserRoleApplication $application) => $this->applicationPayload($application, true))->values(),
        ]);
    }

    public function createSponsor(Request $request)
    {
        return Inertia::render('Auth/Dashboard/SponsorApplications/Create', [
            'application' => $request->user()->roleApplications()
                ->where('type', UserRoleApplication::TYPE_SPONSOR)
                ->latest('requested_at')
                ->first()
                ?->only(['id', 'status', 'message', 'application_data', 'review_notes', 'requested_at', 'reviewed_at']),
            'hasSponsorRole' => $request->user()->hasRole('sponsor'),
        ]);
    }

    public function withdraw(Request $request, UserRoleApplication $application)
    {
        abort_unless((int) $application->user_id === (int) $request->user()->id, 403);
        abort_unless($application->type === UserRoleApplication::TYPE_SPONSOR, 404);
        abort_unless($application->status === UserRoleApplication::STATUS_PENDING, 409, 'Nur ein laufender Sponsor-Antrag kann zurückgezogen werden.');

        $application->update([
            'status' => UserRoleApplication::STATUS_WITHDRAWN,
            'reviewed_at' => now(),
            'reviewed_by' => null,
        ]);

        User::permission('system.manage')->get()->each(function (User $reviewer) use ($application, $request) {
            AppNotification::send($reviewer, 'role.application_withdrawn', [
                'title' => 'Sponsor-Antrag zurückgezogen',
                'body' => $request->user()->name.' hat den Sponsor-Antrag zurückgezogen.',
                'url' => route('admin.sponsor-applications.index'),
                'action_url' => route('admin.sponsor-applications.index'),
                'mobile_url' => 'airmius://admin/sponsor-applications',
                'deep_link' => 'airmius://admin/sponsor-applications',
                'application_id' => $application->id,
                'application_type' => $application->type,
                'application_status' => $application->status,
            ]);
        });

        return $this->response($request, [
            'message' => 'Dein Sponsor-Antrag wurde zurückgezogen.',
            'application' => $this->applicationPayload($application),
        ]);
    }

    public function approve(Request $request, UserRoleApplication $application)
    {
        abort_unless($request->user()->can('system.manage'), 403);
        abort_unless(in_array($application->type, [UserRoleApplication::TYPE_TRAINER, UserRoleApplication::TYPE_SPONSOR], true), 404);
        abort_unless($application->status === UserRoleApplication::STATUS_PENDING, 409, 'Dieser Antrag wurde bereits bearbeitet.');
        abort_if((int) $application->user_id === (int) $request->user()->id, 422, __('validation.approval_second_person'));

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->loadMissing('user');
        $application->user->assignRole($application->type === UserRoleApplication::TYPE_SPONSOR ? 'sponsor' : 'coach');
        $application->update([
            'status' => UserRoleApplication::STATUS_APPROVED,
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        $this->notifyApplicant($application);

        return $this->response($request, ['message' => $application->type === UserRoleApplication::TYPE_SPONSOR ? 'Sponsor-Antrag wurde freigegeben.' : 'Trainerantrag wurde freigegeben.', 'application' => $this->applicationPayload($application)]);
    }

    public function reject(Request $request, UserRoleApplication $application)
    {
        abort_unless($request->user()->can('system.manage'), 403);
        abort_unless(in_array($application->type, [UserRoleApplication::TYPE_TRAINER, UserRoleApplication::TYPE_SPONSOR], true), 404);
        abort_unless($application->status === UserRoleApplication::STATUS_PENDING, 409, 'Dieser Antrag wurde bereits bearbeitet.');

        $data = $request->validate([
            'review_notes' => ['required', 'string', 'max:2000'],
        ]);

        $application->loadMissing('user');
        $application->update([
            'status' => UserRoleApplication::STATUS_REJECTED,
            'review_notes' => $data['review_notes'],
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        if ($application->role_activated && ! $application->user->roleApplications()
            ->where('type', UserRoleApplication::TYPE_TRAINER)
            ->where('status', UserRoleApplication::STATUS_APPROVED)
            ->exists()) {
            $application->user->removeRole('coach');
        }

        $this->notifyApplicant($application);

        return $this->response($request, ['message' => $application->type === UserRoleApplication::TYPE_SPONSOR ? 'Sponsor-Antrag wurde abgelehnt.' : 'Trainerantrag wurde abgelehnt.', 'application' => $this->applicationPayload($application)]);
    }

    public function adminIndex(Request $request)
    {
        abort_unless($request->user()->can('system.manage'), 403);
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected'])]]);
        $applications = UserRoleApplication::query()->with('user:id,name,email')
            ->where('type', UserRoleApplication::TYPE_TRAINER)
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('requested_at')->paginate(30);
        $applications->through(fn ($application) => $this->applicationPayload($application, true));

        return response()->json($applications);
    }

    public function adminSponsorIndex(Request $request)
    {
        abort_unless($request->user()->can('system.manage'), 403);
        $data = $request->validate(['status' => ['nullable', Rule::in(['pending', 'approved', 'rejected', 'withdrawn'])]]);
        $applications = UserRoleApplication::query()->with('user:id,name,email')
            ->where('type', UserRoleApplication::TYPE_SPONSOR)
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('requested_at')->paginate(30);
        $applications->through(fn ($application) => $this->applicationPayload($application, true));

        return response()->json($applications);
    }

    public function sponsorAdminIndex(Request $request)
    {
        abort_unless($request->user()->can('system.manage'), 403);

        $applications = UserRoleApplication::query()
            ->with('user:id,name,email')
            ->where('type', UserRoleApplication::TYPE_SPONSOR)
            ->latest('requested_at')
            ->limit(100)
            ->get();

        return Inertia::render('Auth/Dashboard/Admin/TrainerApplications/Index', [
            'applications' => $applications->map(fn (UserRoleApplication $application) => $this->applicationPayload($application, true))->values(),
            'applicationType' => UserRoleApplication::TYPE_SPONSOR,
        ]);
    }

    private function notifyApplicant(UserRoleApplication $application): void
    {
        $approved = $application->status === UserRoleApplication::STATUS_APPROVED;
        $isSponsor = $application->type === UserRoleApplication::TYPE_SPONSOR;
        $applicationUrl = $isSponsor
            ? ($approved ? route('auth.sponsor-workspace.index') : route('auth.sponsor-applications.create'))
            : ($approved ? route('auth.trainer-cockpit.index') : route('auth.settings', ['tab' => 'roles']));
        $mobileUrl = $isSponsor
            ? ($approved ? 'airmius://sponsors' : 'airmius://sponsors/application')
            : ($approved ? 'airmius://training' : 'airmius://settings');

        AppNotification::send($application->user, 'role.application_status_updated', [
            'title' => $isSponsor
                ? ($approved ? 'Sponsor-Antrag freigegeben' : 'Sponsor-Antrag abgelehnt')
                : ($approved ? 'Trainerantrag freigegeben' : 'Trainerantrag abgelehnt'),
            'body' => $isSponsor
                ? ($approved ? 'Dein Sponsor-Cockpit ist jetzt freigeschaltet.' : 'Dein Sponsor-Antrag wurde abgelehnt. Öffne den Antrag für weitere Hinweise.')
                : ($approved ? 'Airmius hat deinen Trainerantrag freigegeben.' : 'Airmius hat deinen Trainerantrag abgelehnt. Die Trainerfunktion wurde deaktiviert.'),
            'url' => $applicationUrl,
            'action_url' => $applicationUrl,
            'mobile_url' => $mobileUrl,
            'deep_link' => $mobileUrl,
            'application_id' => $application->id,
            'application_type' => $application->type,
            'application_status' => $application->status,
            'review_notes' => $application->review_notes,
        ]);

        if (! $isSponsor) {
            $application->user->notify(new TrainerApplicationStatusUpdated($application));
        }
    }

    private function response(Request $request, array $data, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['data' => $data], $status);
        }

        return back()->with('success', $data['message']);
    }

    private function applicationPayload(UserRoleApplication $application, bool $includeApplicant = false): array
    {
        return [
            'id' => $application->id,
            'type' => $application->type,
            'status' => $application->status,
            'message' => $application->message,
            'application_data' => $application->application_data ?? [],
            'review_notes' => $application->review_notes,
            'role_activated' => (bool) $application->role_activated,
            'requested_at' => $application->requested_at?->toJSON(),
            'reviewed_at' => $application->reviewed_at?->toJSON(),
            'updated_at' => $application->updated_at?->toJSON(),
            ...($includeApplicant ? ['user' => $application->user?->only(['id', 'name', 'email'])] : []),
        ];
    }

    private function normalizeTrainerApplicationData(array $applicationData): array
    {
        $sportIds = collect($applicationData['sport_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $skillIds = collect($applicationData['sport_skill_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($sportIds !== [] && $skillIds !== []) {
            $validSkillCount = SportSkill::query()
                ->whereIn('id', $skillIds)
                ->whereIn('sport_id', $sportIds)
                ->count();

            abort_if(
                $validSkillCount !== count($skillIds),
                422,
                'Die gewählten Schwerpunkte passen nicht zu den ausgewählten Sportarten.',
            );
        }

        if ($sportIds !== []) {
            $applicationData['sport_ids'] = $sportIds;
        }

        if ($skillIds !== []) {
            $applicationData['sport_skill_ids'] = $skillIds;
        }

        return $applicationData;
    }
}
