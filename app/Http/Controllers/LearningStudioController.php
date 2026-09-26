<?php

namespace App\Http\Controllers;

use App\Models\CommerceOrderItem;
use App\Models\LearningAssignment;
use App\Models\LearningAssignmentSubmission;
use App\Models\LearningCourse;
use App\Models\LearningCourseSection;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningLessonComment;
use App\Models\LearningQuiz;
use App\Models\LearningQuizQuestion;
use App\Models\LearningSecurityEvent;
use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Services\Learning\LearningEnrollmentService;
use App\Services\Learning\LearningOfferEvaluationService;
use App\Services\Learning\LearningProgressService;
use App\Services\LearningCourseTranslationService;
use App\Services\MediaOptimizer;
use App\Support\AppNotification;
use App\Support\ClubAuditLog;
use App\Support\UploadStorage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

class LearningStudioController extends Controller
{
    public function __construct(
        private readonly LearningEnrollmentService $learningEnrollment,
        private readonly LearningProgressService $learningProgress,
        private readonly LearningCourseTranslationService $courseTranslations,
        private readonly LearningOfferEvaluationService $offerEvaluation,
    ) {}

    public function index(Request $request)
    {
        $language = in_array($request->query('language'), $this->courseTranslations->locales(), true)
            ? $request->query('language')
            : null;
        $courses = LearningCourse::query()
            ->with('translationVariants:id,user_id,title,language,status,is_public,published_at,translation_group')
            ->withCount(['sections', 'lessons', 'enrollments'])
            ->where('user_id', $request->user()->id)
            ->when($language, fn ($query, string $locale) => $query->where('language', $locale))
            ->latest('updated_at')
            ->get();
        $courses->each(fn (LearningCourse $course) => $course->setRelation(
            'translationVariants',
            $course->translationVariants->where('user_id', $course->user_id)->values(),
        ));

        $selectedCourse = $courses->firstWhere('id', (int) $request->integer('course')) ?: $courses->first();

        $payload = [
            'courses' => $courses,
            'supportedLocales' => $this->courseTranslations->locales(),
            'filters' => ['language' => $language],
            'selectedCourse' => $selectedCourse
                ? $this->courseResource(
                    LearningCourse::query()
                        ->where('user_id', $request->user()->id)
                        ->with([
                            'sections.lessons.comments.user:id,name,email,profile_photo_path',
                            'sections.lessons.comments.replies.user:id,name,email,profile_photo_path',
                            'sections.lessons.assignments.submissions.user:id,name,email,profile_photo_path',
                            'quizzes.questions',
                            'enrollments.user:id,name,email,profile_photo_path',
                            'enrollments.lessonProgress',
                            'enrollments.quizAttempts',
                            'enrollments.certificate',
                            'enrollments.assignmentSubmissions.assignment',
                            'reviews.user:id,name,profile_photo_path',
                            'coupons',
                            'assignments.submissions.user:id,name,email,profile_photo_path',
                            'translationVariants:id,user_id,title,language,status,is_public,published_at,translation_group',
                        ])
                        ->findOrFail($selectedCourse->id)
                )
                : null,
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $payload]);
        }

        return Inertia::render('Auth/Dashboard/Learning/Studio', $payload);
    }

    public function storeCourse(Request $request)
    {
        $data = $this->courseData($request);
        $data = $this->courseTranslations->prepareForCreate(
            $data,
            $data['translation_of_id'] ?? null,
            $request->user()->id,
        );

        try {
            $course = LearningCourse::create([
                ...$this->courseAttributes($data),
                'user_id' => $request->user()->id,
                'slug' => LearningCourse::uniqueSlug($data['title']),
                'learning_goals' => $this->lines($request->input('learning_goals_text')),
                'requirements' => $this->lines($request->input('requirements_text')),
                'target_groups' => $this->lines($request->input('target_groups_text')),
                'sales_points' => $this->lines($request->input('sales_points_text')),
                'faq_items' => $this->faqItems($request->input('faq_items_text')),
                'tags' => $this->tags($request->input('tags_text')),
                'is_free' => (bool) ($data['is_free'] ?? true),
                'price_cents' => (int) ($data['price_cents'] ?? 0),
                'published_at' => ($data['status'] ?? 'draft') === 'published' ? now() : null,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $this->courseTranslations->rethrowWriteConflict($exception);
        }

        $course->sections()->create([
            'title' => __('learning.studio.default_section_title', locale: $course->language),
            'description' => __('learning.studio.default_section_description', locale: $course->language),
            'position' => 1,
        ]);

        if ($request->expectsJson()) {
            return $this->courseJsonResponse($course, __('learning.responses.course_created'), 201);
        }

        return redirect()
            ->route('auth.learning.studio.index', ['course' => $course->id])
            ->with('success', __('learning.responses.course_created'));
    }

    public function updateCourse(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $this->courseTranslations->prepareForUpdate(
            $course,
            $this->courseData($request, $course),
        );

        try {
            $course->update([
                ...$this->courseAttributes($data),
                'slug' => $course->title !== $data['title'] ? LearningCourse::uniqueSlug($data['title'], $course->id) : $course->slug,
                'learning_goals' => $this->lines($request->input('learning_goals_text')),
                'requirements' => $this->lines($request->input('requirements_text')),
                'target_groups' => $this->lines($request->input('target_groups_text')),
                'sales_points' => $this->lines($request->input('sales_points_text')),
                'faq_items' => $this->faqItems($request->input('faq_items_text')),
                'tags' => $this->tags($request->input('tags_text')),
                'published_at' => ($data['status'] ?? $course->status) === 'published' && ! $course->published_at ? now() : $course->published_at,
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            $this->courseTranslations->rethrowWriteConflict($exception);
        }

        if ($request->expectsJson()) {
            return $this->courseJsonResponse($course, __('learning.responses.course_saved'));
        }

        return back()->with('success', __('learning.responses.course_saved'));
    }

    public function storeSection(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $section = $course->sections()->create([
            ...$data,
            'position' => $course->sections()->max('position') + 1,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('learning.responses.section_created'),
                'data' => $section,
            ], 201);
        }

        return back()->with('success', __('learning.responses.section_created'));
    }

    public function storeLesson(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $this->lessonData($request, $course);

        $lesson = $course->lessons()->create([
            ...$data,
            'attachments' => $this->attachments($request->input('attachments_text')),
            'position' => $data['position'] ?? ($course->lessons()->where('learning_course_section_id', $data['learning_course_section_id'])->max('position') + 1),
        ]);

        $this->recalculateCourseDuration($course);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('learning.responses.lesson_created'),
                'data' => $lesson->fresh(),
            ], 201);
        }

        return back()->with('success', __('learning.responses.lesson_created'));
    }

    public function updateLesson(Request $request, LearningCourse $course, LearningLesson $lesson)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($lesson->learning_course_id === $course->id, 404);

        $lesson->update([
            ...$this->lessonData($request, $course),
            'attachments' => $this->attachments($request->input('attachments_text')),
        ]);

        $this->recalculateCourseDuration($course);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('learning.responses.lesson_saved'),
                'data' => $lesson->fresh(),
            ]);
        }

        return back()->with('success', __('learning.responses.lesson_saved'));
    }

    public function destroyLesson(Request $request, LearningCourse $course, LearningLesson $lesson)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($lesson->learning_course_id === $course->id, 404);

        $sectionId = $lesson->learning_course_section_id;
        $lesson->delete();
        $this->normalizeLessonPositions($course, $sectionId);
        $this->recalculateCourseDuration($course);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('learning.responses.lesson_deleted')]);
        }

        return back()->with('success', __('learning.responses.lesson_deleted'));
    }

    public function reorderLessons(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'lessons' => ['required', 'array', 'min:1'],
            'lessons.*.id' => ['required', Rule::exists('learning_lessons', 'id')->where('learning_course_id', $course->id)],
            'lessons.*.position' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        foreach ($data['lessons'] as $row) {
            LearningLesson::query()
                ->where('learning_course_id', $course->id)
                ->whereKey($row['id'])
                ->update(['position' => (int) $row['position']]);
        }

        if ($request->expectsJson()) {
            return $this->courseJsonResponse($course, __('learning.responses.lesson_order_saved'));
        }

        return back()->with('success', __('learning.responses.lesson_order_saved'));
    }

    public function storeQuiz(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'learning_lesson_id' => ['nullable', Rule::exists('learning_lessons', 'id')->where('learning_course_id', $course->id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pass_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'question' => ['nullable', 'string', 'max:1000'],
            'options_text' => ['nullable', 'string', 'max:2000'],
            'correct_options_text' => ['nullable', 'string', 'max:1000'],
            'explanation' => ['nullable', 'string', 'max:1000'],
        ]);

        $quiz = LearningQuiz::create([
            'learning_course_id' => $course->id,
            'learning_lesson_id' => $data['learning_lesson_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'pass_percent' => $data['pass_percent'],
        ]);

        if (filled($data['question'] ?? null)) {
            LearningQuizQuestion::create([
                'learning_quiz_id' => $quiz->id,
                'question' => $data['question'],
                'options' => $this->lines($data['options_text'] ?? null),
                'correct_options' => $this->lines($data['correct_options_text'] ?? null),
                'explanation' => $data['explanation'] ?? null,
                'position' => 1,
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('learning.responses.quiz_created'),
                'data' => $quiz->fresh('questions'),
            ], 201);
        }

        return back()->with('success', __('learning.responses.quiz_created'));
    }

    public function destroyQuiz(Request $request, LearningCourse $course, LearningQuiz $quiz)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($quiz->learning_course_id === $course->id, 404);

        $quiz->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => __('learning.responses.quiz_deleted')]);
        }

        return back()->with('success', __('learning.responses.quiz_deleted'));
    }

    public function uploadAsset(Request $request, LearningCourse $course, MediaOptimizer $mediaOptimizer)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:512000'],
            'purpose' => ['required', Rule::in(['cover', 'lesson_video', 'lesson_attachment'])],
        ]);

        $stored = $mediaOptimizer->store(
            $data['file'],
            'learning/courses/'.$course->id.'/'.$data['purpose']
        );

        return response()->json([
            'url' => UploadStorage::url($stored['path']),
            'path' => $stored['path'],
            'thumbnail_url' => UploadStorage::url($stored['thumbnail_path'] ?? null),
            'mime_type' => $stored['type'] ?? $data['file']->getClientMimeType(),
            'size' => $stored['size'] ?? $data['file']->getSize(),
            'name' => $data['file']->getClientOriginalName(),
        ]);
    }

    public function resolveComment(Request $request, LearningCourse $course, LearningLessonComment $comment)
    {
        $this->authorizeCourse($request, $course);
        $comment->load('lesson');
        abort_unless($comment->lesson?->learning_course_id === $course->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'answered', 'resolved'])],
        ]);

        $comment->update([
            'status' => $data['status'],
            'resolved_at' => $data['status'] === 'resolved' ? now() : null,
        ]);

        if ($request->expectsJson()) {
            return $this->courseJsonResponse($course, __('learning.responses.question_status_saved'));
        }

        return back()->with('success', __('learning.responses.question_status_saved'));
    }

    public function replyComment(Request $request, LearningCourse $course, LearningLessonComment $comment)
    {
        $this->authorizeCourse($request, $course);
        $comment->load(['lesson', 'user']);
        abort_unless($comment->lesson?->learning_course_id === $course->id, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        LearningLessonComment::query()->create([
            'learning_lesson_id' => $comment->learning_lesson_id,
            'user_id' => $request->user()->id,
            'parent_id' => $comment->id,
            'body' => $data['body'],
            'visibility' => 'course',
            'status' => 'answered',
        ]);

        $comment->update([
            'status' => 'answered',
            'resolved_at' => null,
        ]);

        if ((int) $comment->user_id !== (int) $request->user()->id) {
            AppNotification::sendLocalized(
                $comment->user_id,
                'learning.question.answered',
                'learning.notifications.question_answered_title',
                'learning.notifications.question_answered_body',
                ['course' => $course->title],
                [
                    'course_id' => $course->id,
                    'lesson_id' => $comment->learning_lesson_id,
                    'comment_id' => $comment->id,
                    'reply_by' => $request->user()->name,
                    'url' => route('guest.learning.courses.show', $course),
                ],
                ['dedupe_key' => 'learning-question:'.$comment->id.':answered'],
            );
            $this->sendLearningMail($comment->user, 'Antwort auf deine Kursfrage: '.$course->title, [
                'Hallo '.$comment->user?->name.',',
                $request->user()->name.' hat auf deine Frage im Kurs "'.$course->title.'" geantwortet.',
                '"'.$data['body'].'"',
                'Kurs ?ffnen: '.route('guest.learning.courses.show', $course),
            ]);
        }

        if ($request->expectsJson()) {
            return $this->courseJsonResponse($course, __('learning.responses.reply_sent'), 201);
        }

        return back()->with('success', __('learning.responses.reply_sent'));
    }

    public function storeCoupon(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['required', 'integer', 'min:1', 'max:1000000'],
            'max_redemptions' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'expires_at' => ['nullable', 'date'],
            'is_active' => ['boolean'],
        ]);

        $coupon = $course->coupons()->updateOrCreate(
            ['code' => strtoupper(trim($data['code']))],
            [
                ...$data,
                'code' => strtoupper(trim($data['code'])),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ],
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('learning.responses.coupon_saved'),
                'data' => $coupon->fresh(),
            ], 201);
        }

        return back()->with('success', __('learning.responses.coupon_saved'));
    }

    public function storeAssignment(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'learning_lesson_id' => ['nullable', Rule::exists('learning_lessons', 'id')->where('learning_course_id', $course->id)],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'points' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'due_after_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'is_required' => ['boolean'],
        ]);

        $assignment = $course->assignments()->create([
            ...$data,
            'points' => (int) ($data['points'] ?? 100),
            'is_required' => (bool) ($data['is_required'] ?? true),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('learning.responses.assignment_created'),
                'data' => $assignment,
            ], 201);
        }

        return back()->with('success', __('learning.responses.assignment_created'));
    }

    public function gradeAssignment(Request $request, LearningCourse $course, LearningAssignmentSubmission $submission)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($submission->assignment?->learning_course_id === $course->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['passed', 'needs_revision', 'rejected'])],
            'score' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $submission->update([
            ...$data,
            'graded_by' => $request->user()->id,
            'graded_at' => now(),
        ]);
        $submission->loadMissing(['assignment', 'enrollment.user']);
        if ($submission->enrollment) {
            $this->learningProgress->refreshCompletion($course, $submission->enrollment);
        }

        AppNotification::sendLocalized(
            $submission->user,
            'learning.assignment.graded',
            'learning.notifications.assignment_graded_title',
            'learning.notifications.assignment_graded_body',
            ['assignment' => $submission->assignment->title, 'course' => $course->title],
            [
                'course_id' => $course->id,
                'assignment_id' => $submission->assignment->id,
                'status' => $submission->status,
                'url' => route('guest.learning.courses.show', $course),
            ],
            ['dedupe_key' => 'learning-assignment-submission:'.$submission->id.':graded:'.$submission->graded_at?->format('Uu')],
        );

        $this->sendLearningMail($submission->user, 'Deine Aufgabe wurde bewertet', [
            "Deine Aufgabe \"{$submission->assignment->title}\" im Kurs \"{$course->title}\" wurde bewertet.",
            $data['feedback'] ?? '',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('learning.responses.grade_saved'),
                'data' => $submission->fresh(),
            ]);
        }

        return back()->with('success', __('learning.responses.grade_saved'));
    }

    public function grantEnrollment(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()->where('email', strtolower($data['email']))->first();
        abort_unless($user, 422, __('learning.errors.user_not_found'));

        $result = $this->learningEnrollment->activate($user, $course, 'manual', true);
        $enrollment = $result['enrollment'];
        if ($result['activated']) {
            $this->sendLearningMail($user, 'Dein Kurszugang ist freigeschaltet', [
                "Du hast jetzt Zugriff auf \"{$course->title}\".",
                route('guest.learning.courses.show', $course),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('learning.responses.access_granted'),
                'data' => $enrollment->fresh('user:id,name,email,profile_photo_path'),
            ], 201);
        }

        return back()->with('success', __('learning.responses.access_granted'));
    }

    public function revokeEnrollment(Request $request, LearningCourse $course, LearningEnrollment $enrollment)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($enrollment->learning_course_id === $course->id, 404);

        $enrollment->update(['status' => 'cancelled']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('learning.responses.access_revoked'),
                'data' => $enrollment->fresh(),
            ]);
        }

        return back()->with('success', __('learning.responses.access_revoked'));
    }

    public function participationConfirmation(Request $request, LearningCourse $course, LearningEnrollment $enrollment)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($enrollment->learning_course_id === $course->id, 404);

        $enrollment->loadMissing(['user:id,name,email', 'certificate']);

        $payload = [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'offer_type' => $course->offer_type ?: 'course',
                'starts_at' => optional($course->starts_at)->toIso8601String(),
                'ends_at' => optional($course->ends_at)->toIso8601String(),
            ],
            'participant' => [
                'id' => $enrollment->user?->id,
                'name' => $enrollment->user?->name,
                'email' => $enrollment->user?->email,
            ],
            'participation' => [
                'status' => $enrollment->status,
                'started_at' => optional($enrollment->started_at)->toIso8601String(),
                'completed_at' => optional($enrollment->completed_at)->toIso8601String(),
                'progress_percent' => (int) $enrollment->progress_percent,
                'confirmed' => in_array($enrollment->status, ['active', 'completed'], true),
            ],
            'certificate' => $enrollment->certificate ? [
                'id' => $enrollment->certificate->id,
                'code' => $enrollment->certificate->code,
                'issued_at' => optional($enrollment->certificate->issued_at)->toIso8601String(),
                'verify_url' => route('guest.learning.certificates.verify', $enrollment->certificate->code),
            ] : null,
        ];

        if ($course->club_id && $course->club) {
            ClubAuditLog::record($course->club, $request->user(), 'club.learning.participation_confirmation.viewed', $enrollment, [
                'entity_type' => 'learning_enrollment',
                'course_id' => $course->id,
                'has_certificate' => (bool) $enrollment->certificate,
            ]);
        }

        return response()->json(['data' => $payload]);
    }

    public function offerEvaluation(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        return response()->json(['data' => $this->offerEvaluation->evaluate($course)]);
    }

    public function exportOfferEvaluation(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $evaluation = $this->offerEvaluation->evaluate($course);
        $rows = [
            ['Bereich', 'Kennzahl', 'Wert'],
            ['Teilnahme', 'Teilnahmen aktiv/abgeschlossen', $evaluation['participation']['active_or_completed']],
            ['Teilnahme', 'Abschluesse', $evaluation['participation']['completed']],
            ['Teilnahme', 'Zertifikate', $evaluation['participation']['certificates_issued']],
            ['Auslastung', 'Kapazitaet', $evaluation['utilization']['capacity'] ?? ''],
            ['Auslastung', 'Belegt', $evaluation['utilization']['occupied']],
            ['Auslastung', 'Auslastung Prozent', $evaluation['utilization']['occupancy_rate_percent'] ?? ''],
            ['Warteliste', 'Wartende', $evaluation['waitlist']['count']],
            ['Finanzen', 'Einnahmen Cent', $evaluation['finance']['revenue_cents']],
            ['Finanzen', 'Ausgaben Cent', $evaluation['finance']['expenses_cents']],
            ['Finanzen', 'Netto Cent', $evaluation['finance']['net_cents']],
            ['Datenschutz', 'Personenbezogene Zeilen', 'nein'],
        ];

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);

        return Response::make(stream_get_contents($stream), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="learning-offer-evaluation-'.$course->id.'.csv"',
        ]);
    }

    public function exportReport(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $course->load(['enrollments.user', 'enrollments.lessonProgress', 'enrollments.quizAttempts', 'enrollments.certificate']);
        $rows = [[
            'Name',
            'E-Mail',
            'Status',
            'Fortschritt',
            'Lektionen abgeschlossen',
            'Quiz bestanden',
            'Zertifikat',
            'Start',
            'Abschluss',
        ]];

        foreach ($course->enrollments as $enrollment) {
            $rows[] = [
                $enrollment->user?->name,
                $enrollment->user?->email,
                $enrollment->status,
                $enrollment->progress_percent.'%',
                $enrollment->lessonProgress->where('completed', true)->count(),
                $enrollment->quizAttempts->where('passed', true)->pluck('learning_quiz_id')->unique()->count(),
                $enrollment->certificate?->code,
                optional($enrollment->started_at)->toDateString(),
                optional($enrollment->completed_at)->toDateString(),
            ];
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);

        return Response::make(stream_get_contents($stream), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="learning-report-'.$course->id.'.csv"',
        ]);
    }

    public function updateQuality(Request $request, LearningCourse $course)
    {
        $data = $request->validate([
            'quality_status' => ['required', Rule::in(['pending', 'approved', 'changes_requested', 'rejected'])],
            'quality_note' => ['nullable', 'string', 'max:5000'],
            'featured' => ['boolean'],
        ]);

        $course->update([
            'quality_status' => $data['quality_status'],
            'quality_note' => $data['quality_note'] ?? null,
            'featured_at' => ($data['featured'] ?? false) ? now() : null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', __('learning.responses.quality_saved'));
    }

    private function courseData(Request $request, ?LearningCourse $course = null): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(['training', 'nutrition', 'mindset', 'tactics', 'rehab', 'coaching', 'club_management'])],
            'offer_type' => ['nullable', Rule::in(['course', 'block', 'single_session', 'multi_pass', 'camp', 'training_camp'])],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'pro'])],
            'language' => ['required', Rule::in($this->courseTranslations->locales())],
            'cover_image' => ['nullable', 'url', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'review', 'published', 'archived'])],
            'is_public' => ['boolean'],
            'is_free' => ['boolean'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'registration_deadline_at' => ['nullable', 'date'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'sales_points_text' => ['nullable', 'string', 'max:5000'],
            'faq_items_text' => ['nullable', 'string', 'max:5000'],
            'guarantee_text' => ['nullable', 'string', 'max:2000'],
            'certificate_logo_url' => ['nullable', 'url', 'max:500'],
            'certificate_signature_name' => ['nullable', 'string', 'max:255'],
            'certificate_footer_text' => ['nullable', 'string', 'max:1000'],
        ];

        if (! $course) {
            $rules['translation_of_id'] = [
                'nullable',
                'integer',
                Rule::exists('learning_courses', 'id')->where('user_id', $request->user()->id),
            ];
        }

        return $request->validate($rules);
    }

    private function lessonData(Request $request, LearningCourse $course): array
    {
        $data = $request->validate([
            'learning_course_section_id' => ['required', Rule::exists('learning_course_sections', 'id')->where('learning_course_id', $course->id)],
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['lesson', 'video', 'exercise', 'assignment', 'live_session'])],
            'summary' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string', 'max:20000'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'position' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'is_preview' => ['boolean'],
            'unlock_after_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        // Empty optional number fields are converted to null by Laravel. The
        // database columns intentionally use zero as their safe default, so do
        // not explicitly insert null and bypass that default.
        $data['duration_minutes'] ??= 0;
        $data['unlock_after_days'] ??= 0;

        return $data;
    }

    private function courseResource(LearningCourse $course): array
    {
        if ($course->relationLoaded('translationVariants')) {
            $course->setRelation(
                'translationVariants',
                $course->translationVariants->where('user_id', $course->user_id)->values(),
            );
        }

        return [
            ...$course->toArray(),
            'learning_goals_text' => implode("\n", $course->learning_goals ?: []),
            'requirements_text' => implode("\n", $course->requirements ?: []),
            'target_groups_text' => implode("\n", $course->target_groups ?: []),
            'sales_points_text' => implode("\n", $course->sales_points ?: []),
            'faq_items_text' => collect($course->faq_items ?: [])
                ->map(fn (array $item) => trim(($item['question'] ?? '').' | '.($item['answer'] ?? '')))
                ->filter()
                ->implode("\n"),
            'tags_text' => implode(', ', $course->tags ?: []),
            'sections' => $course->sections->map(fn (LearningCourseSection $section) => [
                ...$section->toArray(),
                'lessons' => $section->lessons->map(fn (LearningLesson $lesson) => [
                    ...$lesson->toArray(),
                    'assignments' => $lesson->assignments->values(),
                ])->values(),
            ])->values(),
            'quizzes' => $course->quizzes->values(),
            'coupons' => $course->coupons->values(),
            'assignments' => $course->assignments->map(fn (LearningAssignment $assignment) => [
                ...$assignment->toArray(),
                'submissions' => $assignment->submissions->values(),
            ])->values(),
            'preview_url' => $course->status === 'published' && $course->is_public
                ? $this->courseTranslations->canonicalUrl($course)
                : null,
            'translations' => $this->courseTranslations->variants($course)->all(),
            'publish_checklist' => $this->publishChecklist($course),
            'enrollments' => $course->enrollments
                ->map(fn ($enrollment) => [
                    ...$enrollment->toArray(),
                    'completed_lessons_count' => $enrollment->lessonProgress->where('completed', true)->count(),
                    'quiz_attempts_count' => $enrollment->quizAttempts->count(),
                    'passed_quizzes_count' => $enrollment->quizAttempts->where('passed', true)->pluck('learning_quiz_id')->unique()->count(),
                    'assignments_submitted_count' => $enrollment->assignmentSubmissions->count(),
                    'assignments_passed_count' => $enrollment->assignmentSubmissions->where('status', 'passed')->count(),
                    'completion_requirements' => $this->learningProgress->requirements($course, $enrollment),
                    'certificate' => $enrollment->certificate,
                ])
                ->values(),
            'questions' => $course->sections
                ->flatMap(fn (LearningCourseSection $section) => $section->lessons)
                ->flatMap(fn (LearningLesson $lesson) => $lesson->comments
                    ->whereNull('parent_id')
                    ->map(fn (LearningLessonComment $comment) => [
                        'id' => $comment->id,
                        'lesson_id' => $lesson->id,
                        'lesson_title' => $lesson->title,
                        'body' => $comment->body,
                        'status' => $comment->status ?: 'open',
                        'created_at' => optional($comment->created_at)->toIso8601String(),
                        'resolved_at' => optional($comment->resolved_at)->toIso8601String(),
                        'user' => $comment->user,
                        'replies' => $comment->replies
                            ->sortBy('created_at')
                            ->map(fn (LearningLessonComment $reply) => [
                                'id' => $reply->id,
                                'body' => $reply->body,
                                'created_at' => optional($reply->created_at)->toIso8601String(),
                                'user' => $reply->user,
                            ])
                            ->values(),
                    ]))
                ->sortByDesc('created_at')
                ->values(),
            'reviews' => $course->reviews->values(),
            'analytics' => [
                'average_progress' => (int) round($course->enrollments->avg('progress_percent') ?: 0),
                'completed_enrollments' => $course->enrollments->whereNotNull('completed_at')->count(),
                'open_questions' => $course->sections
                    ->flatMap(fn (LearningCourseSection $section) => $section->lessons)
                    ->flatMap(fn (LearningLesson $lesson) => $lesson->comments)
                    ->whereNull('parent_id')
                    ->where('status', 'open')
                    ->count(),
                'average_rating' => $course->reviews->where('status', 'published')->avg('rating')
                    ? round((float) $course->reviews->where('status', 'published')->avg('rating'), 1)
                    : null,
                'security_events_24h' => LearningSecurityEvent::query()
                    ->where('learning_course_id', $course->id)
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
                'critical_security_events_24h' => LearningSecurityEvent::query()
                    ->where('learning_course_id', $course->id)
                    ->where('severity', 'critical')
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
                'blocked_video_attempts_24h' => LearningSecurityEvent::query()
                    ->where('learning_course_id', $course->id)
                    ->whereIn('type', ['video_signature_invalid', 'video_access_denied', 'video_drip_locked'])
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
                ...$this->courseSalesAnalytics($course),
            ],
            'security_events' => LearningSecurityEvent::query()
                ->with(['user:id,name,email', 'lesson:id,title'])
                ->where('learning_course_id', $course->id)
                ->latest()
                ->limit(12)
                ->get()
                ->map(fn (LearningSecurityEvent $event) => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'severity' => $event->severity,
                    'created_at' => optional($event->created_at)->toIso8601String(),
                    'lesson_title' => $event->lesson?->title,
                    'user' => $event->user ? [
                        'name' => $event->user->name,
                        'email' => $event->user->email,
                    ] : null,
                ])
                ->values(),
        ];
    }

    private function courseJsonResponse(
        LearningCourse $course,
        string $message,
        int $status = 200,
    ) {
        $course = LearningCourse::query()
            ->where('user_id', $course->user_id)
            ->with([
                'sections.lessons.comments.user:id,name,email,profile_photo_path',
                'sections.lessons.comments.replies.user:id,name,email,profile_photo_path',
                'sections.lessons.assignments.submissions.user:id,name,email,profile_photo_path',
                'quizzes.questions',
                'enrollments.user:id,name,email,profile_photo_path',
                'enrollments.lessonProgress',
                'enrollments.quizAttempts',
                'enrollments.certificate',
                'enrollments.assignmentSubmissions.assignment',
                'reviews.user:id,name,profile_photo_path',
                'coupons',
                'assignments.submissions.user:id,name,email,profile_photo_path',
                'translationVariants:id,user_id,title,language,status,is_public,published_at,translation_group',
            ])
            ->findOrFail($course->id);

        return response()->json([
            'message' => $message,
            'data' => $this->courseResource($course),
        ], $status);
    }

    private function authorizeCourse(Request $request, LearningCourse $course): void
    {
        abort_unless($course->user_id === $request->user()->id, 403);
    }

    private function recalculateCourseDuration(LearningCourse $course): void
    {
        $course->update(['estimated_minutes' => (int) $course->lessons()->sum('duration_minutes')]);
    }

    private function normalizeLessonPositions(LearningCourse $course, ?int $sectionId): void
    {
        if (! $sectionId) {
            return;
        }

        $course->lessons()
            ->where('learning_course_section_id', $sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->values()
            ->each(fn (LearningLesson $lesson, int $index) => $lesson->update(['position' => $index + 1]));
    }

    private function lines(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function courseAttributes(array $data): array
    {
        unset($data['sales_points_text'], $data['faq_items_text']);

        if (blank($data['offer_type'] ?? null)) {
            $data['offer_type'] = 'course';
        }

        foreach (['capacity', 'registration_deadline_at', 'starts_at', 'ends_at'] as $field) {
            if (array_key_exists($field, $data) && blank($data[$field])) {
                $data[$field] = null;
            }
        }

        return $data;
    }

    private function faqItems(?string $value): array
    {
        return collect($this->lines($value))
            ->map(function (string $line) {
                [$question, $answer] = array_pad(preg_split('/\s+\|\s+/', $line, 2), 2, '');

                return [
                    'question' => trim($question),
                    'answer' => trim($answer),
                ];
            })
            ->filter(fn (array $item) => filled($item['question']) && filled($item['answer']))
            ->values()
            ->all();
    }

    private function tags(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn ($tag) => trim($tag))
            ->filter()
            ->values()
            ->all();
    }

    private function attachments(?string $value): array
    {
        return collect($this->lines($value))
            ->map(fn ($url) => ['name' => basename(parse_url($url, PHP_URL_PATH) ?: $url), 'url' => $url])
            ->values()
            ->all();
    }

    private function publishChecklist(LearningCourse $course): array
    {
        $checks = [
            ['key' => 'description', 'label' => __('learning.studio.checklist.description'), 'done' => filled($course->description)],
            ['key' => 'cover', 'label' => __('learning.studio.checklist.cover'), 'done' => filled($course->cover_image)],
            ['key' => 'goals', 'label' => __('learning.studio.checklist.goals'), 'done' => ! empty($course->learning_goals)],
            ['key' => 'lessons', 'label' => __('learning.studio.checklist.lessons'), 'done' => $course->lessons->isNotEmpty()],
            ['key' => 'structure', 'label' => __('learning.studio.checklist.structure'), 'done' => $course->sections->isNotEmpty()],
            ['key' => 'completion', 'label' => __('learning.studio.checklist.completion'), 'done' => $course->quizzes->isNotEmpty() || $course->assignments->where('is_required', true)->isNotEmpty()],
            ['key' => 'sales', 'label' => __('learning.studio.checklist.sales'), 'done' => ! empty($course->sales_points)],
            ['key' => 'faq', 'label' => __('learning.studio.checklist.faq'), 'done' => ! empty($course->faq_items)],
            ['key' => 'price', 'label' => __('learning.studio.checklist.price'), 'done' => $course->is_free || $course->price_cents > 0],
            ['key' => 'public', 'label' => __('learning.studio.checklist.public'), 'done' => (bool) $course->is_public],
        ];
        $doneCount = collect($checks)->where('done', true)->count();

        return [
            'items' => $checks,
            'done_count' => $doneCount,
            'total_count' => count($checks),
            'score' => count($checks) > 0 ? (int) round(($doneCount / count($checks)) * 100) : 0,
            'ready' => collect($checks)->every(fn (array $check) => $check['done']),
        ];
    }

    private function courseSalesAnalytics(LearningCourse $course): array
    {
        $productIds = MarketplaceProduct::query()
            ->where('learning_course_id', $course->id)
            ->pluck('id');

        if ($productIds->isEmpty()) {
            return [
                'sales_count' => 0,
                'pending_sales_count' => 0,
                'cancelled_sales_count' => 0,
                'gross_sales_cents' => 0,
                'refunded_cents' => 0,
                'net_revenue_cents' => 0,
            ];
        }

        $items = CommerceOrderItem::query()
            ->with('order:id,status,refunded_cents,currency')
            ->where('orderable_type', MarketplaceProduct::class)
            ->whereIn('orderable_id', $productIds)
            ->get()
            ->filter(fn (CommerceOrderItem $item) => (bool) $item->order);
        $completed = $items->filter(fn (CommerceOrderItem $item) => $item->order->status === 'completed');
        $refundedOrders = $items
            ->filter(fn (CommerceOrderItem $item) => in_array($item->order->status, ['refunded', 'cancelled'], true) || (int) $item->order->refunded_cents > 0)
            ->pluck('order')
            ->unique('id');
        $refundedCents = (int) $refundedOrders->sum(fn ($order) => (int) $order->refunded_cents);
        $grossSalesCents = (int) $completed->sum('total_cents');

        return [
            'sales_count' => $completed->pluck('commerce_order_id')->unique()->count(),
            'pending_sales_count' => $items
                ->filter(fn (CommerceOrderItem $item) => in_array($item->order->status, ['pending', 'awaiting_transfer'], true))
                ->pluck('commerce_order_id')
                ->unique()
                ->count(),
            'cancelled_sales_count' => $items
                ->filter(fn (CommerceOrderItem $item) => in_array($item->order->status, ['cancelled', 'refunded'], true))
                ->pluck('commerce_order_id')
                ->unique()
                ->count(),
            'gross_sales_cents' => $grossSalesCents,
            'refunded_cents' => $refundedCents,
            'net_revenue_cents' => max(0, $grossSalesCents - $refundedCents),
        ];
    }

    private function sendLearningMail($user, string $subject, array $lines): void
    {
        if (! $user?->email) {
            return;
        }

        try {
            Mail::raw(implode("\n\n", [...$lines, 'Viele Grüße', 'Airmius']), function ($message) use ($user, $subject) {
                $message->to($user->email)->subject($subject);
            });
        } catch (Throwable) {
            // Mail delivery must not block studio workflows.
        }
    }
}
