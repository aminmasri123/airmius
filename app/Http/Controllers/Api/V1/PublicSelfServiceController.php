<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClubMembershipRequestResource;
use App\Models\Activity;
use App\Models\Club;
use App\Models\ClubMembershipRequest;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Services\ClubMembershipLifecycleService;
use App\Services\Learning\LearningEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicSelfServiceController extends Controller
{
    private const FILE_RULES = ['nullable', 'array', 'max:3'];
    private const FILE_ITEM_RULES = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];

    public function storeMembershipApplication(
        Request $request,
        Club $club,
        ClubMembershipLifecycleService $memberships,
    ): JsonResponse {
        $this->ensureHuman($request);
        abort_unless($club->is_listed && $club->membership_requests_enabled, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'club_membership_type_id' => ['nullable', Rule::exists('club_membership_types', 'id')->where('club_id', $club->id)],
            'message' => ['nullable', 'string', 'max:2000'],
            'application_data' => ['nullable', 'array', 'max:30'],
            'accepted_documents' => ['nullable', 'array'],
            'accepted_documents.*' => ['boolean'],
            'preferred_payment_method' => ['nullable', 'string', 'max:100'],
            'requested_billing_interval' => ['nullable', 'string', 'max:100'],
            'consent_version' => ['nullable', 'string', 'max:80'],
            'consent_signature' => ['nullable', 'string', 'max:255'],
            'attachments' => self::FILE_RULES,
            'attachments.*' => self::FILE_ITEM_RULES,
        ]);

        $user = $this->publicUser($data['email'], $data['name'], $data['phone'] ?? null);
        $payload = [
            ...$data,
            'type' => 'membership',
            'application_data' => [
                ...($data['application_data'] ?? []),
                'public_form' => true,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'attachment_count' => count($request->file('attachments', [])),
            ],
        ];

        $membershipRequest = $memberships->submitMembership($club, $user, $payload, $request->ip(), $request->userAgent());
        $token = $this->ensureToken($membershipRequest);

        Activity::query()->create([
            'user_id' => $user->id,
            'club_id' => $club->id,
            'type' => 'public.membership_application.submitted',
            'subject_type' => ClubMembershipRequest::class,
            'subject_id' => $membershipRequest->id,
            'data' => [
                'source' => 'public_self_service',
                'attachment_count' => count($request->file('attachments', [])),
                'has_message' => filled($data['message'] ?? null),
            ],
        ]);

        return response()->json([
            'data' => [
                'id' => $membershipRequest->id,
                'status' => $membershipRequest->status,
                'status_token' => $token,
                'status_url' => route('api.v1.public.membership-applications.status', ['token' => $token]),
            ],
        ], 201);
    }

    public function membershipApplicationStatus(string $token): ClubMembershipRequestResource
    {
        $membershipRequest = ClubMembershipRequest::query()
            ->where('public_status_token', $token)
            ->with(['club', 'membershipType'])
            ->firstOrFail();

        return new ClubMembershipRequestResource($membershipRequest);
    }

    public function storeCourseBooking(
        Request $request,
        LearningCourse $course,
        LearningEnrollmentService $enrollments,
    ): JsonResponse {
        $this->ensureHuman($request);
        abort_unless($course->status === 'published' && $course->is_public, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'attachments' => self::FILE_RULES,
            'attachments.*' => self::FILE_ITEM_RULES,
        ]);

        $user = $this->publicUser($data['email'], $data['name'], $data['phone'] ?? null);
        abort_if((int) $course->user_id === (int) $user->id, 422, __('learning.errors.tutor_self_enroll'));

        $result = $enrollments->activate($user, $course, 'public_self_service', true);
        $enrollment = $result['enrollment'];
        $token = $this->ensureToken($enrollment);

        Activity::query()->create([
            'user_id' => $user->id,
            'club_id' => $course->club_id,
            'type' => 'public.learning_booking.submitted',
            'subject_type' => LearningEnrollment::class,
            'subject_id' => $enrollment->id,
            'data' => [
                'source' => 'public_self_service',
                'course_id' => $course->id,
                'attachment_count' => count($request->file('attachments', [])),
                'has_notes' => filled($data['notes'] ?? null),
            ],
        ]);

        return response()->json([
            'data' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'activated' => $result['activated'],
                'status_token' => $token,
                'status_url' => route('api.v1.public.learning.bookings.status', ['token' => $token]),
            ],
        ], 201);
    }

    public function courseBookingStatus(string $token): JsonResponse
    {
        $enrollment = LearningEnrollment::query()
            ->where('public_status_token', $token)
            ->with('course:id,title,slug,status,is_public,user_id,club_id')
            ->firstOrFail();

        return response()->json([
            'data' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'progress_percent' => $enrollment->progress_percent,
                'started_at' => $enrollment->started_at?->toJSON(),
                'completed_at' => $enrollment->completed_at?->toJSON(),
                'course' => [
                    'id' => $enrollment->course?->id,
                    'title' => $enrollment->course?->title,
                    'slug' => $enrollment->course?->slug,
                ],
            ],
        ]);
    }

    private function ensureHuman(Request $request): void
    {
        if (filled($request->input('website'))) {
            throw ValidationException::withMessages(['website' => __('validation.prohibited')]);
        }
    }

    private function publicUser(string $email, string $name, ?string $phone): User
    {
        return User::query()->firstOrCreate(
            ['email' => Str::lower($email)],
            [
                'name' => $name,
                'phone' => $phone,
                'password' => Hash::make(Str::random(40)),
            ],
        );
    }

    private function ensureToken(ClubMembershipRequest|LearningEnrollment $model): string
    {
        if (blank($model->public_status_token)) {
            $model->forceFill(['public_status_token' => Str::random(48)])->save();
        }

        return $model->public_status_token;
    }
}
