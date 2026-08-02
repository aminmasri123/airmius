<?php

namespace App\Http\Controllers;

use App\Models\UserRoleApplication;
use App\Services\AccountRoleApplicationService;
use App\Notifications\TrainerApplicationStatusUpdated;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountRoleApplicationController extends Controller
{
    public function store(Request $request, AccountRoleApplicationService $applications)
    {
        $data = $request->validate([
            'type' => ['required', 'in:trainer'],
            'message' => ['nullable', 'string', 'max:2000'],
            'application_data' => ['nullable', 'array'],
            'application_data.specialties' => ['nullable', 'string', 'max:500'],
            'application_data.experience' => ['nullable', 'string', 'max:2000'],
            'application_data.certification' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $applications->submitTrainer(
            $request->user(),
            $data['message'] ?? null,
            null,
            $data['application_data'] ?? null,
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

    public function approve(Request $request, UserRoleApplication $application)
    {
        abort_unless($request->user()->can('system.manage'), 403);
        abort_unless($application->type === UserRoleApplication::TYPE_TRAINER, 404);

        $data = $request->validate([
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $application->loadMissing('user');
        $application->user->assignRole('coach');
        $application->update([
            'status' => UserRoleApplication::STATUS_APPROVED,
            'review_notes' => $data['review_notes'] ?? null,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        $this->notifyApplicant($application);

        return back()->with('success', 'Trainerantrag wurde freigegeben.');
    }

    public function reject(Request $request, UserRoleApplication $application)
    {
        abort_unless($request->user()->can('system.manage'), 403);
        abort_unless($application->type === UserRoleApplication::TYPE_TRAINER, 404);

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

        return back()->with('success', 'Trainerantrag wurde abgelehnt.');
    }

    private function notifyApplicant(UserRoleApplication $application): void
    {
        $approved = $application->status === UserRoleApplication::STATUS_APPROVED;
        $applicationUrl = $approved
            ? route('auth.trainer-cockpit.index')
            : route('auth.settings', ['tab' => 'roles']);

        AppNotification::send($application->user, 'role.application_status_updated', [
            'title' => $approved ? 'Trainerantrag freigegeben' : 'Trainerantrag abgelehnt',
            'body' => $approved
                ? 'Airmius hat deinen Trainerantrag freigegeben.'
                : 'Airmius hat deinen Trainerantrag abgelehnt. Die Trainerfunktion wurde deaktiviert.',
            'url' => $applicationUrl,
            'application_id' => $application->id,
            'application_type' => $application->type,
            'application_status' => $application->status,
            'review_notes' => $application->review_notes,
        ]);

        $application->user->notify(new TrainerApplicationStatusUpdated($application));
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
            ...($includeApplicant ? ['user' => $application->user?->only(['id', 'name', 'email'])] : []),
        ];
    }
}
