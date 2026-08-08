<?php

namespace App\Http\Controllers;

use App\Models\LearningAssignment;
use App\Models\LearningAssignmentSubmission;
use App\Models\LearningCertificate;
use App\Models\LearningCourse;
use App\Models\LearningCourseReview;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningLessonComment;
use App\Models\LearningLessonNote;
use App\Models\LearningLessonProgress;
use App\Models\LearningQuiz;
use App\Models\LearningQuizAttempt;
use App\Models\LearningSecurityEvent;
use App\Services\AirmiusPdfDocument;
use App\Services\Learning\LearningEnrollmentService;
use App\Services\Learning\LearningProgressService;
use App\Support\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

class PublicLearningController extends Controller
{
    private const CERTIFICATE_NOT_FOUND = 'Certificate not found.';

    public function __construct(
        private readonly LearningEnrollmentService $learningEnrollment,
        private readonly LearningProgressService $learningProgress,
    ) {}

    /**
     * Public learning catalogue for native clients. The response deliberately
     * contains only published course-card data; enrollment and lesson actions
     * remain protected by the existing authenticated routes.
     */
    public function indexJson(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'level' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', Rule::in(['free', 'paid'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $courses = LearningCourse::query()
            ->with(['tutor:id,name'])
            ->withCount(['sections', 'lessons'])
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', 'published')])
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
            ->where('status', 'published')
            ->where('is_public', true)
            ->when($filters['q'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('sport_type', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['level'] ?? null, fn ($query, string $level) => $query->where('level', $level))
            ->when(($filters['price'] ?? null) === 'free', fn ($query) => $query->where('is_free', true))
            ->when(($filters['price'] ?? null) === 'paid', fn ($query) => $query->where('is_free', false))
            ->orderByDesc('featured_at')
            ->latest('published_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? 24, ['*'], 'page', $filters['page'] ?? 1);

        $data = collect($courses->items())->map(function (LearningCourse $course): array {
            return [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'subtitle' => $course->subtitle,
                'description' => $course->description,
                'category' => $course->category,
                'sport_type' => $course->sport_type,
                'level' => $course->level,
                'cover_image' => $course->cover_image,
                'is_free' => (bool) $course->is_free,
                'price_cents' => $course->price_cents,
                'currency' => $course->currency,
                'average_rating' => $course->average_rating ? round((float) $course->average_rating, 1) : null,
                'reviews_count' => (int) ($course->reviews_count ?? 0),
                'estimated_minutes' => $course->estimated_minutes,
                'sections_count' => (int) ($course->sections_count ?? 0),
                'lessons_count' => (int) ($course->lessons_count ?? 0),
                'tutor' => $course->tutor ? [
                    'id' => $course->tutor->id,
                    'name' => $course->tutor->name,
                ] : null,
            ];
        })->values();

        return response()->json([
            'data' => $data,
            'facets' => [
                'categories' => LearningCourse::query()
                    ->where('status', 'published')
                    ->where('is_public', true)
                    ->distinct()
                    ->pluck('category')
                    ->filter()
                    ->values(),
                'levels' => LearningCourse::query()
                    ->where('status', 'published')
                    ->where('is_public', true)
                    ->distinct()
                    ->pluck('level')
                    ->filter()
                    ->values(),
            ],
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
            ],
        ]);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'level' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', Rule::in(['free', 'paid'])],
        ]);

        $courses = LearningCourse::query()
            ->with(['tutor:id,name,profile_photo_path', 'marketplaceProducts' => fn ($query) => $query->where('status', 'published')->latest('id')])
            ->withCount(['sections', 'lessons', 'enrollments'])
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', 'published')])
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
            ->where('status', 'published')
            ->where('is_public', true)
            ->when($filters['q'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('sport_type', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $category) => $query->where('category', $category))
            ->when($filters['level'] ?? null, fn ($query, string $level) => $query->where('level', $level))
            ->when(($filters['price'] ?? null) === 'free', fn ($query) => $query->where('is_free', true))
            ->when(($filters['price'] ?? null) === 'paid', fn ($query) => $query->where('is_free', false))
            ->orderByDesc('featured_at')
            ->latest('published_at')
            ->latest('id')
            ->get()
            ->map(fn (LearningCourse $course) => $this->courseCard($course));

        return Inertia::render('Guest/E-Learning', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'learningProducts' => [],
            'learningCourses' => $courses,
            'filters' => $filters,
            'facets' => [
                'categories' => LearningCourse::query()->where('status', 'published')->where('is_public', true)->distinct()->pluck('category')->filter()->values(),
                'levels' => LearningCourse::query()->where('status', 'published')->where('is_public', true)->distinct()->pluck('level')->filter()->values(),
            ],
        ]);
    }

    public function show(Request $request, LearningCourse $course)
    {
        abort_unless($course->status === 'published' && $course->is_public, 404);

        $course->load([
            'tutor:id,name,profile_photo_path',
            'sections.lessons.comments.user:id,name,profile_photo_path',
            'sections.lessons.comments.replies.user:id,name,profile_photo_path',
            'sections.lessons.assignments.submissions' => fn ($query) => $request->user()
                ? $query->where('user_id', $request->user()->id)
                : $query->whereRaw('1 = 0'),
            'quizzes.questions',
            'quizzes.lesson',
            'marketplaceProducts' => fn ($query) => $query->where('status', 'published')->latest('id'),
            'reviews' => fn ($query) => $query->with('user:id,name,profile_photo_path')->where('status', 'published')->latest()->limit(8),
            'assignments.submissions' => fn ($query) => $request->user()
                ? $query->where('user_id', $request->user()->id)
                : $query->whereRaw('1 = 0'),
        ]);
        $course->loadCount(['sections', 'lessons', 'enrollments']);
        $course->loadCount(['reviews as reviews_count' => fn ($query) => $query->where('status', 'published')]);
        $course->loadAvg(['reviews as average_rating' => fn ($query) => $query->where('status', 'published')], 'rating');
        $enrollment = $request->user()
            ? LearningEnrollment::query()
                ->with('certificate')
                ->where('learning_course_id', $course->id)
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->first()
            : null;
        $canUseLearningRoom = $this->canUseLearningRoom($request, $course, $enrollment);
        $lessonProgress = $enrollment
            ? LearningLessonProgress::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->get()
                ->keyBy('learning_lesson_id')
            : collect();
        $completedLessonIds = $lessonProgress
            ->where('completed', true)
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->all();
        $quizAttempts = $enrollment
            ? LearningQuizAttempt::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->latest('id')
                ->get()
                ->unique('learning_quiz_id')
                ->keyBy('learning_quiz_id')
            : collect();
        $myReview = $request->user()
            ? LearningCourseReview::query()
                ->where('learning_course_id', $course->id)
                ->where('user_id', $request->user()->id)
                ->first()
            : null;
        $isTutor = $request->user() && (int) $request->user()->id === (int) $course->user_id;

        return Inertia::render('Guest/LearningCourseShow', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'course' => $this->courseDetail($course, $canUseLearningRoom, $completedLessonIds, $quizAttempts, $lessonProgress, $enrollment, $isTutor),
            'enrollment' => $enrollment ? $this->enrollmentResource($enrollment) : null,
            'canUseLearningRoom' => $canUseLearningRoom,
            'myReview' => $myReview,
        ]);
    }

    public function enroll(Request $request, LearningCourse $course)
    {
        abort_unless($course->status === 'published' && $course->is_public, 404);
        abort_if(! $course->is_free, 403, __('learning.errors.paid_course'));
        abort_if((int) $course->user_id === (int) $request->user()->id, 422, __('learning.errors.tutor_self_enroll'));

        $result = $this->learningEnrollment->activate($request->user(), $course);

        if ($result['activated']) {
            $this->sendLearningMail($course->tutor, 'Neue Einschreibung: '.$course->title, [
                'Hallo '.$course->tutor?->name.',',
                $request->user()->name.' hat sich in deinen Kurs "'.$course->title.'" eingeschrieben.',
                'Studio: '.route('auth.learning.studio.index', ['course' => $course->id]),
            ]);
        }

        return back()->with('success', __('learning.responses.enrolled'));
    }

    public function completeLesson(Request $request, LearningCourse $course, LearningLesson $lesson)
    {
        $this->authorizeParticipant($request, $course, $lesson);

        $enrollment = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        abort_unless($enrollment, 403);

        $this->learningProgress->completeLesson($course, $enrollment, $lesson);

        return back()->with('success', __('learning.responses.lesson_completed'));
    }

    public function trackLessonProgress(Request $request, LearningCourse $course, LearningLesson $lesson)
    {
        $this->authorizeParticipant($request, $course, $lesson);

        $enrollment = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        abort_unless($enrollment, 403);

        $data = $request->validate([
            'watch_seconds' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);

        $progress = $this->learningProgress->trackLesson(
            $course,
            $enrollment,
            $lesson,
            $data['watch_seconds'],
        );

        return response()->json([
            'completed' => (bool) $progress->completed,
            'watch_seconds' => (int) $progress->watch_seconds,
            'watch_percent' => $this->watchPercent($lesson, $progress),
            'progress_percent' => (int) $enrollment->fresh()->progress_percent,
        ]);
    }

    public function streamLessonVideo(Request $request, LearningCourse $course, LearningLesson $lesson)
    {
        abort_unless($lesson->learning_course_id === $course->id, 404);

        if (! $request->hasValidSignature()) {
            $this->logLearningSecurityEvent($request, $course, $lesson, 'video_signature_invalid', 'warning');
            abort(403, 'Dieser Video-Link ist abgelaufen. Bitte lade die Kursseite neu.');
        }

        $enrollment = null;

        if ((int) $course->user_id !== (int) $request->user()?->id) {
            $enrollment = LearningEnrollment::query()
                ->where('learning_course_id', $course->id)
                ->where('user_id', $request->user()?->id)
                ->where('status', 'active')
                ->first();

            if (! $enrollment) {
                $this->logLearningSecurityEvent($request, $course, $lesson, 'video_access_denied', 'warning');
                abort(403);
            }

            if ($this->learningProgress->dripLocked($lesson, $enrollment)) {
                $this->logLearningSecurityEvent($request, $course, $lesson, 'video_drip_locked', 'warning');
                abort(403, 'Diese Lektion wird später freigeschaltet.');
            }
        }

        $path = $this->publicStoragePathFromUrl($lesson->video_url);

        if (! $path || ! Storage::disk('public')->exists($path)) {
            $this->logLearningSecurityEvent($request, $course, $lesson, 'video_file_missing', 'critical', [
                'video_url' => $lesson->video_url,
                'storage_path' => $path,
            ]);
            abort(404);
        }

        return Storage::disk('public')->response($path, basename($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Content-Disposition' => 'inline; filename="'.basename($path).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function submitAssignment(Request $request, LearningCourse $course, LearningAssignment $assignment)
    {
        abort_unless($assignment->learning_course_id === $course->id, 404);
        abort_unless($request->user(), 403);

        $enrollment = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        abort_unless($enrollment, 403);

        if ($assignment->learning_lesson_id) {
            $lesson = LearningLesson::query()->whereKey($assignment->learning_lesson_id)->firstOrFail();
            abort_unless($lesson->learning_course_id === $course->id, 404);
            abort_if($this->learningProgress->dripLocked($lesson, $enrollment), 403, __('learning.errors.assignment_locked'));
        }

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:10000'],
            'attachment_url' => ['nullable', 'url', 'max:500'],
        ]);
        abort_if(blank($data['body'] ?? null) && blank($data['attachment_url'] ?? null), 422, __('learning.errors.submission_required'));

        $submission = LearningAssignmentSubmission::query()->updateOrCreate(
            [
                'learning_assignment_id' => $assignment->id,
                'learning_enrollment_id' => $enrollment->id,
            ],
            [
                'user_id' => $request->user()->id,
                'body' => $data['body'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'status' => 'submitted',
                'submitted_at' => now(),
                'score' => null,
                'feedback' => null,
                'graded_by' => null,
                'graded_at' => null,
            ],
        );

        if ((int) $course->user_id !== (int) $request->user()->id) {
            AppNotification::sendLocalized(
                $course->user_id,
                'learning.assignment.submitted',
                'learning.notifications.assignment_submitted_title',
                'learning.notifications.assignment_submitted_body',
                [
                    'student' => $request->user()->name,
                    'assignment' => $assignment->title,
                    'course' => $course->title,
                ],
                [
                    'course_id' => $course->id,
                    'assignment_id' => $assignment->id,
                    'submission_id' => $submission->id,
                    'url' => route('auth.learning.studio.index', ['course' => $course->id]),
                ],
                ['dedupe_key' => sprintf(
                    'learning-assignment-submission:%d:%s',
                    $submission->id,
                    $submission->updated_at?->format('Uu') ?: 'created',
                )],
            );
        }

        return back()->with('success', __('learning.responses.assignment_submitted'));
    }

    public function submitQuiz(Request $request, LearningCourse $course, LearningQuiz $quiz)
    {
        abort_unless($quiz->learning_course_id === $course->id, 404);
        abort_unless($request->user(), 403);

        $enrollment = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        abort_unless($enrollment, 403);

        if ($quiz->learning_lesson_id) {
            $lesson = LearningLesson::query()->whereKey($quiz->learning_lesson_id)->firstOrFail();
            abort_unless($lesson->learning_course_id === $course->id, 404);
            abort_if($this->learningProgress->dripLocked($lesson, $enrollment), 403, __('learning.errors.quiz_locked'));
        }

        $data = $request->validate([
            'answers' => ['required', 'array'],
        ]);

        $attempt = $this->learningProgress->submitQuiz(
            $course,
            $enrollment,
            $quiz,
            $request->user(),
            $data['answers'],
        );

        return back()->with(
            'success',
            $attempt->passed ? __('learning.responses.quiz_passed') : __('learning.responses.quiz_failed'),
        );
    }

    public function storeReview(Request $request, LearningCourse $course)
    {
        abort_unless($course->status === 'published' && $course->is_public, 404);
        abort_unless($request->user(), 403);
        abort_unless(
            LearningEnrollment::query()->where('learning_course_id', $course->id)->where('user_id', $request->user()->id)->where('status', 'active')->exists(),
            403
        );

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:1500'],
        ]);

        $review = LearningCourseReview::query()->updateOrCreate([
            'learning_course_id' => $course->id,
            'user_id' => $request->user()->id,
        ], [
            'rating' => $data['rating'],
            'body' => $data['body'] ?? null,
            'status' => 'published',
        ]);

        if ((int) $course->user_id !== (int) $request->user()->id) {
            AppNotification::sendLocalized(
                $course->user_id,
                'learning.review.created',
                'learning.notifications.review_created_title',
                'learning.notifications.review_created_body',
                [
                    'student' => $request->user()->name,
                    'course' => $course->title,
                    'rating' => (int) $data['rating'],
                ],
                ['course_id' => $course->id, 'review_id' => $review->id],
                ['dedupe_key' => sprintf(
                    'learning-review:%d:%s',
                    $review->id,
                    $review->updated_at?->format('Uu') ?: 'created',
                )],
            );
            $this->sendLearningMail($course->tutor, 'Neue Kursbewertung: '.$course->title, [
                'Hallo '.$course->tutor?->name.',',
                $request->user()->name.' hat deinen Kurs "'.$course->title.'" mit '.(int) $data['rating'].' von 5 bewertet.',
                'Studio: '.route('auth.learning.studio.index', ['course' => $course->id]),
            ]);
        }

        return back()->with('success', __('learning.responses.review_saved'));
    }

    public function storeNote(Request $request, LearningCourse $course, LearningLesson $lesson)
    {
        $this->authorizeParticipant($request, $course, $lesson);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        LearningLessonNote::query()->create([
            'learning_lesson_id' => $lesson->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('success', __('learning.responses.note_saved'));
    }

    public function storeComment(Request $request, LearningCourse $course, LearningLesson $lesson)
    {
        $this->authorizeParticipant($request, $course, $lesson);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        $comment = LearningLessonComment::query()->create([
            'learning_lesson_id' => $lesson->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'visibility' => 'course',
        ]);

        if ((int) $course->user_id !== (int) $request->user()->id) {
            AppNotification::sendLocalized(
                $course->user_id,
                'learning.question.created',
                'learning.notifications.question_created_title',
                'learning.notifications.question_created_body',
                ['student' => $request->user()->name, 'course' => $course->title],
                [
                    'course_id' => $course->id,
                    'lesson_id' => $lesson->id,
                    'comment_id' => $comment->id,
                    'url' => route('auth.learning.studio.index', ['course' => $course->id]),
                ],
                ['dedupe_key' => 'learning-question:'.$comment->id.':created'],
            );
            $this->sendLearningMail($course->tutor, 'Neue Kursfrage: '.$course->title, [
                'Hallo '.$course->tutor?->name.',',
                $request->user()->name.' hat eine Frage zur Lektion "'.$lesson->title.'" gestellt.',
                '"'.$data['body'].'"',
                'Antworten: '.route('auth.learning.studio.index', ['course' => $course->id]),
            ]);
        }

        return back()->with('success', __('learning.responses.question_published'));
    }

    public function myCourses(Request $request)
    {
        $enrollments = LearningEnrollment::query()
            ->with([
                'course.tutor:id,name,profile_photo_path',
                'course.sections',
                'course.lessons',
                'course.marketplaceProducts' => fn ($query) => $query->where('status', 'published')->latest('id'),
                'certificate',
            ])
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->get()
            ->map(fn (LearningEnrollment $enrollment) => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'progress_percent' => $enrollment->progress_percent,
                'started_at' => optional($enrollment->started_at)->toIso8601String(),
                'completed_at' => optional($enrollment->completed_at)->toIso8601String(),
                'course' => $enrollment->course ? $this->courseCard($enrollment->course) : null,
                'certificate' => $enrollment->certificate ? [
                    'code' => $enrollment->certificate->code,
                    'issued_at' => optional($enrollment->certificate->issued_at)->toIso8601String(),
                    'download_url' => route('auth.learning.certificates.show', $enrollment->certificate),
                    'verify_url' => route('guest.learning.certificates.verify', $enrollment->certificate->code),
                ] : null,
            ])
            ->filter(fn (array $enrollment) => (bool) $enrollment['course'])
            ->values();

        return Inertia::render('Auth/Dashboard/Learning/MyCourses', [
            'enrollments' => $enrollments,
        ]);
    }

    public function downloadCertificate(Request $request, LearningCertificate $certificate)
    {
        if ((int) $certificate->user_id !== (int) $request->user()->id) {
            $this->logLearningSecurityEvent($request, $certificate->course, null, 'certificate_access_denied', 'warning', [
                'certificate_id' => $certificate->id,
            ]);
            abort(403);
        }

        $certificate->load(['course.tutor:id,name', 'user:id,name', 'enrollment']);
        abort_unless($certificate->enrollment?->status === 'active', 403);
        if (! $certificate->enrollment?->completed_at) {
            $this->logLearningSecurityEvent($request, $certificate->course, null, 'certificate_incomplete_denied', 'warning', [
                'certificate_id' => $certificate->id,
                'enrollment_id' => $certificate->learning_enrollment_id,
            ]);
            abort(403);
        }

        $pdf = new AirmiusPdfDocument;
        $pdf->header('ZERTIFIKAT', $certificate->code, 'Airmius Sportschule');
        $pdf->card(48, 632, 150, 54, 'Ausgestellt', $certificate->issued_at->format('d.m.Y'));
        $pdf->card(222, 632, 150, 54, 'Fortschritt', (string) ($certificate->enrollment?->progress_percent ?: 100).'%');
        $pdf->card(396, 632, 150, 54, 'Status', 'Abgeschlossen', true);
        $pdf->sectionTitle('Teilnehmer', 48, 570);
        $pdf->text($certificate->user?->name ?: 'Teilnehmer', 48, 540, 24, true, AirmiusPdfDocument::NAVY, 42);
        $pdf->sectionTitle('Kurs', 48, 490);
        $pdf->text($certificate->course?->title ?: 'Airmius Kurs', 48, 458, 22, true, AirmiusPdfDocument::BLUE, 48);
        $pdf->text($certificate->course?->subtitle ?: ($certificate->course?->description ?: 'Erfolgreich abgeschlossen.'), 48, 430, 10, false, AirmiusPdfDocument::SLATE, 95);
        $pdf->sectionTitle('Kursleitung', 48, 365);
        $pdf->text($certificate->course?->certificate_signature_name ?: ($certificate->course?->tutor?->name ?: 'Airmius Tutor'), 48, 338, 13, true);
        $pdf->strokeColor(...AirmiusPdfDocument::BORDER)->line(48, 285, 546, 285);
        $pdf->text($certificate->course?->certificate_footer_text ?: 'Dieses Zertifikat bestätigt, dass der Kurs mit den erforderlichen Lektionen und Wissenschecks abgeschlossen wurde.', 48, 250, 10, false, AirmiusPdfDocument::SLATE, 115);
        $pdf->text('Zertifikat-ID: '.$certificate->code, 48, 224, 9, true, AirmiusPdfDocument::MUTED, 80);

        return response($pdf->legalFooter([], 'Airmius gratuliert zum erfolgreichen Abschluss.')->render(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$certificate->code.'.pdf"',
        ]);
    }

    public function verifyCertificate(string $code)
    {
        $certificate = LearningCertificate::query()
            ->with(['course.tutor:id,name,profile_photo_path', 'user:id,name', 'enrollment'])
            ->where('code', Str::upper($code))
            ->firstOrFail();

        abort_unless($certificate->enrollment?->status === 'active' && $certificate->enrollment?->completed_at, 404);

        return Inertia::render('Guest/LearningCertificateVerify', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'certificate' => [
                'code' => $certificate->code,
                'issued_at' => optional($certificate->issued_at)->toIso8601String(),
                'student_name' => $certificate->user?->name,
                'course_title' => $certificate->course?->title,
                'course_subtitle' => $certificate->course?->subtitle,
                'course_url' => $certificate->course ? route('guest.learning.courses.show', $certificate->course) : null,
                'progress_percent' => $certificate->enrollment?->progress_percent ?: 100,
                'tutor' => $certificate->course?->tutor,
            ],
        ]);
    }

    /**
     * Return the same public certificate verification data for native clients.
     * Only completed, active certificates are exposed; private account fields
     * and download permissions remain protected by the existing routes.
     */
    public function verifyCertificateJson(string $code)
    {
        $certificate = LearningCertificate::query()
            ->with(['course.tutor:id,name', 'user:id,name', 'enrollment'])
            ->where('code', Str::upper($code))
            ->first();

        if (! $certificate ||
            $certificate->enrollment?->status !== 'active' ||
            ! $certificate->enrollment?->completed_at) {
            return response()->json([
                'message' => self::CERTIFICATE_NOT_FOUND,
                'message_text' => __('platform.learning.certificate_not_found'),
            ], 404);
        }

        return response()->json([
            'data' => [
                'code' => $certificate->code,
                'issued_at' => optional($certificate->issued_at)->toIso8601String(),
                'student_name' => $certificate->user?->name,
                'course_title' => $certificate->course?->title,
                'course_subtitle' => $certificate->course?->subtitle,
                'progress_percent' => $certificate->enrollment?->progress_percent ?: 100,
                'tutor' => $certificate->course?->tutor,
            ],
        ]);
    }

    private function authorizeParticipant(Request $request, LearningCourse $course, LearningLesson $lesson): void
    {
        abort_unless($lesson->learning_course_id === $course->id, 404);
        abort_unless($request->user(), 403);

        if ((int) $course->user_id === (int) $request->user()->id) {
            return;
        }

        $enrollment = LearningEnrollment::query()
            ->where('learning_course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();

        abort_unless($enrollment, 403);
        abort_if($this->learningProgress->dripLocked($lesson, $enrollment), 403, __('learning.errors.lesson_locked'));
    }

    private function canUseLearningRoom(Request $request, LearningCourse $course, ?LearningEnrollment $enrollment): bool
    {
        if (! $request->user()) {
            return false;
        }

        return $course->user_id === $request->user()->id || (bool) $enrollment;
    }

    private function courseCard(LearningCourse $course): array
    {
        $marketplaceProduct = $course->marketplaceProducts->first();

        return [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'subtitle' => $course->subtitle,
            'description' => $course->description,
            'category' => $course->category,
            'sport_type' => $course->sport_type,
            'level' => $course->level,
            'cover_image' => $course->cover_image,
            'learning_goals' => $course->learning_goals ?: [],
            'sales_points' => $course->sales_points ?: [],
            'faq_items' => $course->faq_items ?: [],
            'guarantee_text' => $course->guarantee_text,
            'quality_status' => $course->quality_status,
            'featured_at' => optional($course->featured_at)->toIso8601String(),
            'is_free' => $course->is_free,
            'price_cents' => $course->price_cents,
            'currency' => $course->currency,
            'average_rating' => $course->average_rating ? round((float) $course->average_rating, 1) : null,
            'reviews_count' => (int) ($course->reviews_count ?? 0),
            'purchase_url' => ! $course->is_free && $marketplaceProduct
                ? route('guest.marketplace.products.show', $marketplaceProduct)
                : null,
            'estimated_minutes' => $course->estimated_minutes,
            'sections_count' => $course->sections_count ?? ($course->relationLoaded('sections') ? $course->sections->count() : 0),
            'lessons_count' => $course->lessons_count ?? ($course->relationLoaded('lessons') ? $course->lessons->count() : 0),
            'enrollments_count' => $course->enrollments_count ?? 0,
            'tutor' => $course->tutor,
            'show_url' => route('guest.learning.courses.show', $course),
        ];
    }

    private function courseDetail(LearningCourse $course, bool $canUseLearningRoom, array $completedLessonIds = [], $quizAttempts = null, $lessonProgress = null, ?LearningEnrollment $enrollment = null, bool $bypassDrip = false): array
    {
        $quizAttempts = $quizAttempts ?: collect();
        $lessonProgress = $lessonProgress ?: collect();

        return [
            ...$this->courseCard($course),
            'requirements' => $course->requirements ?: [],
            'target_groups' => $course->target_groups ?: [],
            'tags' => $course->tags ?: [],
            'assignments' => $course->assignments
                ->map(fn (LearningAssignment $assignment) => $this->assignmentResource($assignment, $canUseLearningRoom, $enrollment))
                ->values(),
            'completion_requirements' => $enrollment
                ? $this->learningProgress->requirements($course, $enrollment)
                : null,
            'sections' => $course->sections->map(fn ($section) => [
                'id' => $section->id,
                'title' => $section->title,
                'description' => $section->description,
                'position' => $section->position,
                'lessons' => $section->lessons
                    ->map(fn (LearningLesson $lesson) => $this->lessonResource($lesson, $canUseLearningRoom || $lesson->is_preview, $completedLessonIds, $canUseLearningRoom, $lessonProgress->get($lesson->id), $enrollment, $bypassDrip))
                    ->values(),
            ])->values(),
            'quizzes' => $course->quizzes
                ->map(function ($quiz) use ($canUseLearningRoom, $quizAttempts, $enrollment, $bypassDrip) {
                    $lesson = $quiz->lesson;
                    $dripLocked = $lesson && ! $lesson->is_preview && ! $bypassDrip && $this->learningProgress->dripLocked($lesson, $enrollment);

                    return [
                        'id' => $quiz->id,
                        'learning_course_id' => $quiz->learning_course_id,
                        'learning_lesson_id' => $quiz->learning_lesson_id,
                        'title' => $quiz->title,
                        'description' => $quiz->description,
                        'pass_percent' => $quiz->pass_percent,
                        'locked' => (bool) $dripLocked,
                        'available_at' => $lesson ? optional($this->learningProgress->dripAvailableAt($lesson, $enrollment))->toIso8601String() : null,
                        'attempt' => $this->quizAttemptResource($quizAttempts->get($quiz->id)),
                        'questions' => $canUseLearningRoom && ! $dripLocked
                            ? $quiz->questions->map(fn ($question) => [
                                'id' => $question->id,
                                'question' => $question->question,
                                'options' => $question->options ?: [],
                                'position' => $question->position,
                            ])->values()
                            : [],
                    ];
                })
                ->values(),
            'reviews' => $course->reviews
                ->map(fn (LearningCourseReview $review) => [
                    'id' => $review->id,
                    'rating' => $review->rating,
                    'body' => $review->body,
                    'created_at' => optional($review->created_at)->toIso8601String(),
                    'user' => [
                        'name' => $review->user?->name,
                        'profile_photo_path' => $review->user?->profile_photo_path,
                    ],
                ])
                ->values(),
        ];
    }

    private function lessonResource(LearningLesson $lesson, bool $includeContent, array $completedLessonIds = [], bool $includeCommunity = false, ?LearningLessonProgress $progress = null, ?LearningEnrollment $enrollment = null, bool $bypassDrip = false): array
    {
        $dripLocked = ! $lesson->is_preview && ! $bypassDrip && $this->learningProgress->dripLocked($lesson, $enrollment);
        $availableAt = $this->learningProgress->dripAvailableAt($lesson, $enrollment);
        $resource = [
            'id' => $lesson->id,
            'learning_course_id' => $lesson->learning_course_id,
            'learning_course_section_id' => $lesson->learning_course_section_id,
            'title' => $lesson->title,
            'type' => $lesson->type,
            'summary' => $lesson->summary,
            'duration_minutes' => $lesson->duration_minutes,
            'position' => $lesson->position,
            'is_preview' => $lesson->is_preview,
            'unlock_after_days' => (int) ($lesson->unlock_after_days ?: 0),
            'available_at' => optional($availableAt)->toIso8601String(),
            'drip_locked' => $dripLocked,
            'completed' => in_array((int) $lesson->id, $completedLessonIds, true),
            'watch_seconds' => (int) ($progress?->watch_seconds ?: 0),
            'watch_percent' => $this->watchPercent($lesson, $progress),
            'locked' => ! $includeContent || $dripLocked,
        ];

        if ($includeContent && ! $dripLocked) {
            $resource['content'] = $lesson->content;
            $resource['video_url'] = $lesson->video_url;
            $resource['secure_video_url'] = $this->publicStoragePathFromUrl($lesson->video_url)
                ? URL::temporarySignedRoute('auth.learning.lessons.video', now()->addMinutes(15), [$lesson->learning_course_id, $lesson->id])
                : null;
            $resource['attachments'] = $lesson->attachments ?: [];
            $resource['assignments'] = $lesson->assignments
                ->map(fn (LearningAssignment $assignment) => $this->assignmentResource($assignment, $includeCommunity, $enrollment))
                ->values();
        }

        if ($includeCommunity && ! $dripLocked) {
            $resource['comments'] = $lesson->comments
                ->whereNull('parent_id')
                ->sortByDesc('created_at')
                ->map(fn (LearningLessonComment $comment) => [
                    'id' => $comment->id,
                    'body' => $comment->body,
                    'status' => $comment->status ?: 'open',
                    'created_at' => optional($comment->created_at)->toIso8601String(),
                    'user' => [
                        'name' => $comment->user?->name,
                        'profile_photo_path' => $comment->user?->profile_photo_path,
                    ],
                    'replies' => $comment->replies
                        ->sortBy('created_at')
                        ->map(fn (LearningLessonComment $reply) => [
                            'id' => $reply->id,
                            'body' => $reply->body,
                            'created_at' => optional($reply->created_at)->toIso8601String(),
                            'user' => [
                                'name' => $reply->user?->name,
                                'profile_photo_path' => $reply->user?->profile_photo_path,
                            ],
                        ])
                        ->values(),
                ])
                ->values();
        }

        return $resource;
    }

    private function assignmentResource(LearningAssignment $assignment, bool $includeSubmission = false, ?LearningEnrollment $enrollment = null): array
    {
        $submission = $includeSubmission ? $assignment->submissions->first() : null;
        $dueAt = $enrollment?->started_at && $assignment->due_after_days !== null
            ? $enrollment->started_at->copy()->addDays((int) $assignment->due_after_days)
            : null;

        return [
            'id' => $assignment->id,
            'learning_lesson_id' => $assignment->learning_lesson_id,
            'title' => $assignment->title,
            'instructions' => $assignment->instructions,
            'points' => $assignment->points,
            'due_after_days' => $assignment->due_after_days,
            'due_at' => optional($dueAt)->toIso8601String(),
            'is_required' => $assignment->is_required,
            'submission' => $submission ? [
                'id' => $submission->id,
                'status' => $submission->status,
                'score' => $submission->score,
                'feedback' => $submission->feedback,
                'attachment_url' => $submission->attachment_url,
                'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
                'graded_at' => optional($submission->graded_at)->toIso8601String(),
            ] : null,
        ];
    }

    private function enrollmentResource(LearningEnrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'status' => $enrollment->status,
            'progress_percent' => $enrollment->progress_percent,
            'started_at' => optional($enrollment->started_at)->toIso8601String(),
            'completed_at' => optional($enrollment->completed_at)->toIso8601String(),
            'certificate' => $enrollment->certificate ? [
                'code' => $enrollment->certificate->code,
                'issued_at' => optional($enrollment->certificate->issued_at)->toIso8601String(),
                'download_url' => route('auth.learning.certificates.show', $enrollment->certificate),
                'verify_url' => route('guest.learning.certificates.verify', $enrollment->certificate->code),
            ] : null,
        ];
    }

    private function quizAttemptResource(?LearningQuizAttempt $attempt): ?array
    {
        if (! $attempt) {
            return null;
        }

        return [
            'id' => $attempt->id,
            'score_percent' => $attempt->score_percent,
            'passed' => $attempt->passed,
            'submitted_at' => optional($attempt->submitted_at)->toIso8601String(),
        ];
    }

    private function watchPercent(LearningLesson $lesson, ?LearningLessonProgress $progress): int
    {
        $durationSeconds = max(0, (int) $lesson->duration_minutes * 60);

        if (! $durationSeconds || ! $progress) {
            return 0;
        }

        return min(100, (int) round(((int) $progress->watch_seconds / $durationSeconds) * 100));
    }

    private function publicStoragePathFromUrl(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $marker = '/storage/';
        $position = strpos($path, $marker);

        if ($position === false) {
            return null;
        }

        return ltrim(substr($path, $position + strlen($marker)), '/');
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
            // Mail delivery must not block learning workflows.
        }
    }

    private function logLearningSecurityEvent(Request $request, ?LearningCourse $course, ?LearningLesson $lesson, string $type, string $severity = 'warning', array $context = []): void
    {
        try {
            LearningSecurityEvent::query()->create([
                'learning_course_id' => $course?->id,
                'learning_lesson_id' => $lesson?->id,
                'user_id' => $request->user()?->id,
                'type' => $type,
                'severity' => $severity,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
                'context' => $context,
            ]);
        } catch (Throwable) {
            // Monitoring must never block learning workflows.
        }
    }
}
