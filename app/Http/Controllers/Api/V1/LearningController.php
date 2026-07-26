<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LearningController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'level' => ['nullable', 'string', 'max:80'],
            'price' => ['nullable', Rule::in(['free', 'paid'])],
        ]);
        $enrollments = LearningEnrollment::query()
            ->with(['course.tutor:id,name', 'course.sections', 'course.lessons', 'certificate'])
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->get();
        $enrolledByCourse = $enrollments->keyBy('learning_course_id');
        $courses = LearningCourse::query()
            ->with('tutor:id,name')
            ->withCount(['sections', 'lessons', 'enrollments'])
            ->withCount(['reviews as reviews_count' => fn ($query) => $query->where('status', 'published')])
            ->withAvg(['reviews as average_rating' => fn ($query) => $query->where('status', 'published')], 'rating')
            ->where('status', 'published')
            ->where('is_public', true)
            ->when($filters['q'] ?? null, function ($query, string $search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('title', 'like', "%{$search}%")
                        ->orWhere('subtitle', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['category'] ?? null, fn ($query, string $value) => $query->where('category', $value))
            ->when($filters['level'] ?? null, fn ($query, string $value) => $query->where('level', $value))
            ->when(($filters['price'] ?? null) === 'free', fn ($query) => $query->where('is_free', true))
            ->when(($filters['price'] ?? null) === 'paid', fn ($query) => $query->where('is_free', false))
            ->orderByDesc('featured_at')
            ->latest('published_at')
            ->latest('id')
            ->get()
            ->map(fn (LearningCourse $course) => $this->courseCard($course, $enrolledByCourse->get($course->id)));

        return response()->json([
            'data' => [
                'catalog' => $courses,
                'enrollments' => $enrollments
                    ->filter(fn (LearningEnrollment $enrollment) => (bool) $enrollment->course)
                    ->map(fn (LearningEnrollment $enrollment) => $this->enrollmentData($enrollment))
                    ->values(),
                'certificates' => $enrollments
                    ->pluck('certificate')
                    ->filter()
                    ->map(fn (LearningCertificate $certificate) => $this->certificateData($certificate))
                    ->values(),
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
            ],
        ]);
    }

    public function show(Request $request, LearningCourse $course): JsonResponse
    {
        $this->published($course);
        $enrollment = $this->enrollment($request, $course);
        $isTutor = (int) $course->user_id === (int) $request->user()->id;
        $course->load([
            'tutor:id,name',
            'sections.lessons.comments.user:id,name',
            'sections.lessons.comments.replies.user:id,name',
            'sections.lessons.assignments',
            'quizzes.questions',
            'quizzes.lesson',
            'assignments',
        ])->loadCount(['sections', 'lessons', 'enrollments']);
        $progress = $enrollment
            ? LearningLessonProgress::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->get()
                ->keyBy('learning_lesson_id')
            : collect();
        $quizAttempts = $enrollment
            ? LearningQuizAttempt::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->latest('id')
                ->get()
                ->unique('learning_quiz_id')
                ->keyBy('learning_quiz_id')
            : collect();
        $submissions = $enrollment
            ? LearningAssignmentSubmission::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->get()
                ->keyBy('learning_assignment_id')
            : collect();
        $notes = LearningLessonNote::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('lesson', fn ($query) => $query->where('learning_course_id', $course->id))
            ->latest('id')
            ->get()
            ->groupBy('learning_lesson_id');

        return response()->json([
            'data' => [
                'course' => [
                    ...$this->courseCard($course, $enrollment),
                    'learning_goals' => $course->learning_goals ?: [],
                    'requirements' => $course->requirements ?: [],
                    'target_groups' => $course->target_groups ?: [],
                ],
                'enrollment' => $enrollment ? $this->enrollmentData($enrollment) : null,
                'can_use_learning_room' => $isTutor || (bool) $enrollment,
                'is_tutor' => $isTutor,
                'sections' => $course->sections->map(fn ($section) => [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'position' => $section->position,
                    'lessons' => $section->lessons->map(function (LearningLesson $lesson) use ($enrollment, $isTutor, $progress, $notes) {
                        $lockedByDrip = ! $isTutor && $this->dripLocked($lesson, $enrollment);
                        $canOpen = ($isTutor || $enrollment || $lesson->is_preview) && ! $lockedByDrip;
                        $lessonProgress = $progress->get($lesson->id);

                        return [
                            'id' => $lesson->id,
                            'title' => $lesson->title,
                            'type' => $lesson->type,
                            'summary' => $lesson->summary,
                            'duration_minutes' => $lesson->duration_minutes,
                            'position' => $lesson->position,
                            'is_preview' => $lesson->is_preview,
                            'locked' => ! $canOpen,
                            'available_at' => optional($this->dripAvailableAt($lesson, $enrollment))->toIso8601String(),
                            'completed' => (bool) $lessonProgress?->completed,
                            'watch_seconds' => (int) ($lessonProgress?->watch_seconds ?: 0),
                            'content' => $canOpen ? $lesson->content : null,
                            'attachments' => $canOpen ? ($lesson->attachments ?: []) : [],
                            'notes' => $canOpen
                                ? collect($notes->get($lesson->id, collect()))->map(fn (LearningLessonNote $note) => [
                                    'id' => $note->id,
                                    'body' => $note->body,
                                    'created_at' => optional($note->created_at)->toIso8601String(),
                                ])->values()
                                : [],
                            'comments' => ($canOpen && ($isTutor || $enrollment))
                                ? $lesson->comments
                                    ->whereNull('parent_id')
                                    ->sortByDesc('created_at')
                                    ->map(fn (LearningLessonComment $comment) => $this->commentData($comment))
                                    ->values()
                                : [],
                        ];
                    })->values(),
                ])->values(),
                'quizzes' => $course->quizzes->map(function (LearningQuiz $quiz) use ($enrollment, $isTutor, $quizAttempts) {
                    $locked = ! ($isTutor || $enrollment)
                        || ($quiz->lesson && ! $isTutor && $this->dripLocked($quiz->lesson, $enrollment));

                    return [
                        'id' => $quiz->id,
                        'learning_lesson_id' => $quiz->learning_lesson_id,
                        'title' => $quiz->title,
                        'description' => $quiz->description,
                        'pass_percent' => $quiz->pass_percent,
                        'locked' => $locked,
                        'attempt' => $this->attemptData($quizAttempts->get($quiz->id)),
                        'questions' => $locked ? [] : $quiz->questions->map(fn ($question) => [
                            'id' => $question->id,
                            'question' => $question->question,
                            'options' => $question->options ?: [],
                            'position' => $question->position,
                        ])->values(),
                    ];
                })->values(),
                'assignments' => $course->assignments->map(function (LearningAssignment $assignment) use ($enrollment, $isTutor, $submissions) {
                    $lesson = $assignment->learning_lesson_id
                        ? LearningLesson::query()->find($assignment->learning_lesson_id)
                        : null;
                    $locked = ! ($isTutor || $enrollment)
                        || ($lesson && ! $isTutor && $this->dripLocked($lesson, $enrollment));
                    $submission = $submissions->get($assignment->id);

                    return [
                        'id' => $assignment->id,
                        'learning_lesson_id' => $assignment->learning_lesson_id,
                        'title' => $assignment->title,
                        'instructions' => $locked ? null : $assignment->instructions,
                        'points' => $assignment->points,
                        'is_required' => $assignment->is_required,
                        'locked' => $locked,
                        'submission' => $submission ? [
                            'id' => $submission->id,
                            'status' => $submission->status,
                            'score' => $submission->score,
                            'feedback' => $submission->feedback,
                            'submitted_at' => optional($submission->submitted_at)->toIso8601String(),
                        ] : null,
                    ];
                })->values(),
                'completion_requirements' => $enrollment
                    ? $this->completionRequirements($course, $enrollment)
                    : null,
            ],
        ]);
    }

    public function enroll(Request $request, LearningCourse $course): JsonResponse
    {
        $this->published($course);
        abort_unless($course->is_free, 403, 'Dieser Kurs ist kostenpflichtig.');
        abort_if((int) $course->user_id === (int) $request->user()->id, 422, 'Kursleitungen müssen sich nicht selbst einschreiben.');

        $enrollment = LearningEnrollment::query()->updateOrCreate([
            'learning_course_id' => $course->id,
            'user_id' => $request->user()->id,
        ], [
            'status' => 'active',
            'started_at' => now(),
            'completed_at' => null,
        ]);

        return response()->json([
            'data' => $this->enrollmentData($enrollment->load('course.tutor:id,name')),
            'message' => 'Du bist im Kurs eingeschrieben.',
        ], $enrollment->wasRecentlyCreated ? 201 : 200);
    }

    public function completeLesson(Request $request, LearningCourse $course, LearningLesson $lesson): JsonResponse
    {
        $enrollment = $this->participant($request, $course, $lesson);
        LearningLessonProgress::query()->updateOrCreate([
            'learning_enrollment_id' => $enrollment->id,
            'learning_lesson_id' => $lesson->id,
        ], [
            'completed' => true,
            'completed_at' => now(),
        ]);
        $this->refreshCompletion($course, $enrollment);

        return response()->json([
            'data' => $this->enrollmentData($enrollment->fresh()->load(['course.tutor:id,name', 'certificate'])),
            'message' => 'Lektion wurde abgeschlossen.',
        ]);
    }

    public function trackProgress(Request $request, LearningCourse $course, LearningLesson $lesson): JsonResponse
    {
        $enrollment = $this->participant($request, $course, $lesson);
        $data = $request->validate([
            'watch_seconds' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]);
        $progress = LearningLessonProgress::query()->firstOrNew([
            'learning_enrollment_id' => $enrollment->id,
            'learning_lesson_id' => $lesson->id,
        ]);
        $progress->watch_seconds = max((int) $progress->watch_seconds, $data['watch_seconds']);
        $duration = max(0, (int) $lesson->duration_minutes * 60);
        if ($duration > 0 && $progress->watch_seconds >= (int) ceil($duration * .8)) {
            $progress->completed = true;
            $progress->completed_at ??= now();
        }
        $progress->save();
        $this->refreshCompletion($course, $enrollment);

        return response()->json([
            'data' => [
                'completed' => (bool) $progress->completed,
                'watch_seconds' => (int) $progress->watch_seconds,
                'progress_percent' => (int) $enrollment->fresh()->progress_percent,
            ],
        ]);
    }

    public function storeNote(Request $request, LearningCourse $course, LearningLesson $lesson): JsonResponse
    {
        $this->participant($request, $course, $lesson);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $note = LearningLessonNote::query()->create([
            'learning_lesson_id' => $lesson->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json(['data' => $note, 'message' => 'Notiz wurde gespeichert.'], 201);
    }

    public function storeComment(Request $request, LearningCourse $course, LearningLesson $lesson): JsonResponse
    {
        $this->participant($request, $course, $lesson);
        $data = $request->validate(['body' => ['required', 'string', 'max:3000']]);
        $comment = LearningLessonComment::query()->create([
            'learning_lesson_id' => $lesson->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'visibility' => 'course',
        ])->load('user:id,name');

        return response()->json([
            'data' => $this->commentData($comment),
            'message' => 'Frage wurde veröffentlicht.',
        ], 201);
    }

    public function submitQuiz(Request $request, LearningCourse $course, LearningQuiz $quiz): JsonResponse
    {
        abort_unless((int) $quiz->learning_course_id === (int) $course->id, 404);
        $enrollment = $this->activeEnrollmentOrFail($request, $course);
        if ($quiz->learning_lesson_id) {
            $lesson = LearningLesson::query()->findOrFail($quiz->learning_lesson_id);
            abort_if($this->dripLocked($lesson, $enrollment), 403, 'Dieses Quiz ist noch gesperrt.');
        }
        $data = $request->validate(['answers' => ['required', 'array']]);
        $quiz->load('questions');
        $answers = collect($data['answers'])
            ->mapWithKeys(fn ($value, $key) => [(string) $key => $this->normalizeAnswer($value)])
            ->all();
        $correctIds = $quiz->questions
            ->filter(fn ($question) => $this->normalizeAnswer($question->correct_options ?: [])
                === ($answers[(string) $question->id] ?? []))
            ->pluck('id')
            ->all();
        $score = (int) round((count($correctIds) / max(1, $quiz->questions->count())) * 100);
        $attempt = LearningQuizAttempt::query()->create([
            'learning_quiz_id' => $quiz->id,
            'learning_enrollment_id' => $enrollment->id,
            'user_id' => $request->user()->id,
            'answers' => $answers,
            'correct_question_ids' => $correctIds,
            'score_percent' => $score,
            'passed' => $score >= (int) $quiz->pass_percent,
            'submitted_at' => now(),
        ]);
        $this->refreshCompletion($course, $enrollment);

        return response()->json([
            'data' => $this->attemptData($attempt),
            'message' => $attempt->passed ? 'Quiz bestanden.' : 'Quiz nicht bestanden. Du kannst es erneut versuchen.',
        ], 201);
    }

    public function submitAssignment(Request $request, LearningCourse $course, LearningAssignment $assignment): JsonResponse
    {
        abort_unless((int) $assignment->learning_course_id === (int) $course->id, 404);
        $enrollment = $this->activeEnrollmentOrFail($request, $course);
        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:10000'],
            'attachment_url' => ['nullable', 'url', 'max:500'],
        ]);
        abort_if(blank($data['body'] ?? null) && blank($data['attachment_url'] ?? null), 422, 'Bitte reiche Text oder einen Link ein.');
        $submission = LearningAssignmentSubmission::query()->updateOrCreate([
            'learning_assignment_id' => $assignment->id,
            'learning_enrollment_id' => $enrollment->id,
        ], [
            'user_id' => $request->user()->id,
            'body' => $data['body'] ?? null,
            'attachment_url' => $data['attachment_url'] ?? null,
            'status' => 'submitted',
            'submitted_at' => now(),
            'score' => null,
            'feedback' => null,
            'graded_by' => null,
            'graded_at' => null,
        ]);

        return response()->json([
            'data' => $submission,
            'message' => 'Aufgabe wurde eingereicht.',
        ], 201);
    }

    public function storeReview(Request $request, LearningCourse $course): JsonResponse
    {
        $this->activeEnrollmentOrFail($request, $course);
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

        return response()->json(['data' => $review, 'message' => 'Bewertung wurde gespeichert.']);
    }

    public function certificate(Request $request, LearningCertificate $certificate): JsonResponse
    {
        abort_unless((int) $certificate->user_id === (int) $request->user()->id, 403);
        $certificate->load(['course.tutor:id,name', 'enrollment']);
        abort_unless($certificate->enrollment?->status === 'active' && $certificate->enrollment?->completed_at, 403);

        return response()->json(['data' => $this->certificateData($certificate)]);
    }

    private function courseCard(LearningCourse $course, ?LearningEnrollment $enrollment = null): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'subtitle' => $course->subtitle,
            'description' => $course->description,
            'category' => $course->category,
            'sport_type' => $course->sport_type,
            'level' => $course->level,
            'language' => $course->language,
            'cover_image' => $course->cover_image,
            'is_free' => $course->is_free,
            'price_cents' => $course->price_cents,
            'currency' => $course->currency,
            'estimated_minutes' => $course->estimated_minutes,
            'sections_count' => (int) ($course->sections_count ?? $course->sections->count()),
            'lessons_count' => (int) ($course->lessons_count ?? $course->lessons->count()),
            'enrollments_count' => (int) ($course->enrollments_count ?? 0),
            'average_rating' => $course->average_rating ? round((float) $course->average_rating, 1) : null,
            'reviews_count' => (int) ($course->reviews_count ?? 0),
            'tutor' => $course->tutor ? ['id' => $course->tutor->id, 'name' => $course->tutor->name] : null,
            'is_enrolled' => (bool) $enrollment,
            'progress_percent' => (int) ($enrollment?->progress_percent ?: 0),
        ];
    }

    private function enrollmentData(LearningEnrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'status' => $enrollment->status,
            'progress_percent' => (int) $enrollment->progress_percent,
            'started_at' => optional($enrollment->started_at)->toIso8601String(),
            'completed_at' => optional($enrollment->completed_at)->toIso8601String(),
            'course' => $enrollment->course ? $this->courseCard($enrollment->course, $enrollment) : null,
            'certificate' => $enrollment->certificate ? $this->certificateData($enrollment->certificate) : null,
        ];
    }

    private function certificateData(LearningCertificate $certificate): array
    {
        return [
            'id' => $certificate->id,
            'code' => $certificate->code,
            'issued_at' => optional($certificate->issued_at)->toIso8601String(),
            'course_title' => $certificate->course?->title,
            'tutor_name' => $certificate->course?->tutor?->name,
            'progress_percent' => (int) ($certificate->enrollment?->progress_percent ?: 100),
            'verify_url' => route('guest.learning.certificates.verify', $certificate->code),
        ];
    }

    private function commentData(LearningLessonComment $comment): array
    {
        return [
            'id' => $comment->id,
            'body' => $comment->body,
            'status' => $comment->status ?: 'open',
            'created_at' => optional($comment->created_at)->toIso8601String(),
            'user' => ['id' => $comment->user?->id, 'name' => $comment->user?->name],
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies->map(fn (LearningLessonComment $reply) => [
                    'id' => $reply->id,
                    'body' => $reply->body,
                    'created_at' => optional($reply->created_at)->toIso8601String(),
                    'user' => ['id' => $reply->user?->id, 'name' => $reply->user?->name],
                ])->values()
                : [],
        ];
    }

    private function attemptData(?LearningQuizAttempt $attempt): ?array
    {
        return $attempt ? [
            'id' => $attempt->id,
            'score_percent' => $attempt->score_percent,
            'passed' => $attempt->passed,
            'submitted_at' => optional($attempt->submitted_at)->toIso8601String(),
        ] : null;
    }

    private function participant(Request $request, LearningCourse $course, LearningLesson $lesson): LearningEnrollment
    {
        abort_unless((int) $lesson->learning_course_id === (int) $course->id, 404);
        $enrollment = $this->activeEnrollmentOrFail($request, $course);
        abort_if($this->dripLocked($lesson, $enrollment), 403, 'Diese Lektion ist noch gesperrt.');

        return $enrollment;
    }

    private function activeEnrollmentOrFail(Request $request, LearningCourse $course): LearningEnrollment
    {
        $this->published($course);
        $enrollment = $this->enrollment($request, $course);
        abort_unless($enrollment, 403);

        return $enrollment;
    }

    private function enrollment(Request $request, LearningCourse $course): ?LearningEnrollment
    {
        return LearningEnrollment::query()
            ->with(['course.tutor:id,name', 'certificate.course.tutor:id,name', 'certificate.enrollment'])
            ->where('learning_course_id', $course->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->first();
    }

    private function published(LearningCourse $course): void
    {
        abort_unless($course->status === 'published' && $course->is_public, 404);
    }

    private function dripLocked(LearningLesson $lesson, ?LearningEnrollment $enrollment): bool
    {
        return $enrollment?->started_at
            && (int) $lesson->unlock_after_days > 0
            && now()->lt($this->dripAvailableAt($lesson, $enrollment));
    }

    private function dripAvailableAt(LearningLesson $lesson, ?LearningEnrollment $enrollment)
    {
        if (! $enrollment?->started_at || (int) $lesson->unlock_after_days <= 0) {
            return null;
        }

        return $enrollment->started_at->copy()->addDays((int) $lesson->unlock_after_days);
    }

    private function normalizeAnswer($value): array
    {
        return collect(is_array($value) ? $value : [$value])
            ->map(fn ($item) => Str::lower(trim((string) $item)))
            ->filter()
            ->sort()
            ->values()
            ->all();
    }

    private function refreshCompletion(LearningCourse $course, LearningEnrollment $enrollment): void
    {
        $requirements = $this->completionRequirements($course, $enrollment);
        $total = max(1, $requirements['lessons']['total'] + $requirements['quizzes']['total'] + $requirements['assignments']['total']);
        $done = $requirements['lessons']['completed'] + $requirements['quizzes']['completed'] + $requirements['assignments']['completed'];
        $enrollment->forceFill([
            'progress_percent' => min(100, (int) round(($done / $total) * 100)),
            'completed_at' => $requirements['complete'] ? ($enrollment->completed_at ?: now()) : null,
        ])->save();

        if ($enrollment->completed_at) {
            $enrollment->certificate()->firstOrCreate([], [
                'learning_course_id' => $course->id,
                'user_id' => $enrollment->user_id,
                'code' => 'AIR-LEARN-'.Str::upper(Str::random(10)),
                'issued_at' => now(),
            ]);
        }
    }

    private function completionRequirements(LearningCourse $course, LearningEnrollment $enrollment): array
    {
        $totalLessons = $course->lessons()->count();
        $completedLessons = LearningLessonProgress::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('completed', true)
            ->whereHas('lesson', fn ($query) => $query->where('learning_course_id', $course->id))
            ->count();
        $quizIds = $course->quizzes()->pluck('id');
        $passedQuizIds = LearningQuizAttempt::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('passed', true)
            ->whereIn('learning_quiz_id', $quizIds)
            ->distinct()
            ->pluck('learning_quiz_id');
        $assignmentIds = $course->assignments()->where('is_required', true)->pluck('id');
        $passedAssignmentIds = LearningAssignmentSubmission::query()
            ->where('learning_enrollment_id', $enrollment->id)
            ->where('status', 'passed')
            ->whereIn('learning_assignment_id', $assignmentIds)
            ->distinct()
            ->pluck('learning_assignment_id');

        return [
            'lessons' => ['total' => $totalLessons, 'completed' => $completedLessons],
            'quizzes' => ['total' => $quizIds->count(), 'completed' => $passedQuizIds->count()],
            'assignments' => ['total' => $assignmentIds->count(), 'completed' => $passedAssignmentIds->count()],
            'complete' => $completedLessons >= $totalLessons
                && $quizIds->diff($passedQuizIds)->isEmpty()
                && $assignmentIds->diff($passedAssignmentIds)->isEmpty(),
        ];
    }
}
