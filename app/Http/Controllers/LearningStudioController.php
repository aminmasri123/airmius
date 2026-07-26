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
use App\Models\LearningLessonProgress;
use App\Models\LearningQuiz;
use App\Models\LearningQuizAttempt;
use App\Models\LearningQuizQuestion;
use App\Models\LearningSecurityEvent;
use App\Models\MarketplaceProduct;
use App\Models\User;
use App\Services\MediaOptimizer;
use App\Support\AppNotification;
use App\Support\UploadStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

class LearningStudioController extends Controller
{
    public function index(Request $request)
    {
        $courses = LearningCourse::query()
            ->withCount(['sections', 'lessons', 'enrollments'])
            ->where('user_id', $request->user()->id)
            ->latest('updated_at')
            ->get();

        $selectedCourse = $courses->firstWhere('id', (int) $request->integer('course')) ?: $courses->first();

        $payload = [
            'courses' => $courses,
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
        ]);

        $course->sections()->create([
            'title' => 'Start',
            'description' => 'Einleitung und Orientierung',
            'position' => 1,
        ]);

        if ($request->expectsJson()) {
            return $this->courseJsonResponse($course, 'Kurs wurde angelegt.', 201);
        }

        return redirect()
            ->route('auth.learning.studio.index', ['course' => $course->id])
            ->with('success', 'Kurs wurde angelegt. Du kannst jetzt Kapitel und Lektionen planen.');
    }

    public function updateCourse(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $this->courseData($request, $course);
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

        if ($request->expectsJson()) {
            return $this->courseJsonResponse($course, 'Kurs wurde gespeichert.');
        }

        return back()->with('success', 'Kurs wurde gespeichert.');
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
                'message' => 'Kapitel wurde hinzugefügt.',
                'data' => $section,
            ], 201);
        }

        return back()->with('success', 'Kapitel wurde hinzugefügt.');
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
                'message' => 'Lektion wurde hinzugefügt.',
                'data' => $lesson->fresh(),
            ], 201);
        }

        return back()->with('success', 'Lektion wurde hinzugefügt.');
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
                'message' => 'Lektion wurde gespeichert.',
                'data' => $lesson->fresh(),
            ]);
        }

        return back()->with('success', 'Lektion wurde gespeichert.');
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
            return response()->json(['message' => 'Lektion wurde gelöscht.']);
        }

        return back()->with('success', 'Lektion wurde gelöscht.');
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
            return $this->courseJsonResponse($course, 'Lektionsreihenfolge wurde gespeichert.');
        }

        return back()->with('success', 'Lektionsreihenfolge wurde gespeichert.');
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
                'message' => 'Quiz wurde angelegt.',
                'data' => $quiz->fresh('questions'),
            ], 201);
        }

        return back()->with('success', 'Quiz wurde angelegt.');
    }

    public function destroyQuiz(Request $request, LearningCourse $course, LearningQuiz $quiz)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($quiz->learning_course_id === $course->id, 404);

        $quiz->delete();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Quiz wurde gelöscht.']);
        }

        return back()->with('success', 'Quiz wurde gelöscht.');
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
            return $this->courseJsonResponse($course, 'Fragenstatus wurde aktualisiert.');
        }

        return back()->with('success', 'Fragenstatus wurde aktualisiert.');
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
            AppNotification::send($comment->user_id, 'learning.question.answered', [
                'course_id' => $course->id,
                'course_title' => $course->title,
                'lesson_id' => $comment->learning_lesson_id,
                'lesson_title' => $comment->lesson?->title,
                'comment_id' => $comment->id,
                'reply_by' => $request->user()->name,
                'url' => route('guest.learning.courses.show', $course),
            ]);
            $this->sendLearningMail($comment->user, 'Antwort auf deine Kursfrage: '.$course->title, [
                'Hallo '.$comment->user?->name.',',
                $request->user()->name.' hat auf deine Frage im Kurs "'.$course->title.'" geantwortet.',
                '"'.$data['body'].'"',
                'Kurs ?ffnen: '.route('guest.learning.courses.show', $course),
            ]);
        }

        if ($request->expectsJson()) {
            return $this->courseJsonResponse($course, 'Antwort wurde an den Teilnehmer gesendet.', 201);
        }

        return back()->with('success', 'Antwort wurde an den Teilnehmer gesendet.');
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
                'message' => 'Gutschein wurde gespeichert.',
                'data' => $coupon->fresh(),
            ], 201);
        }

        return back()->with('success', 'Gutschein wurde gespeichert.');
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
                'message' => 'Aufgabe wurde angelegt.',
                'data' => $assignment,
            ], 201);
        }

        return back()->with('success', 'Aufgabe wurde angelegt.');
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
        $this->refreshEnrollmentCompletion($course, $submission->enrollment);

        AppNotification::send($submission->user, 'learning.assignment.graded', [
            'course_id' => $course->id,
            'course_title' => $course->title,
            'assignment_id' => $submission->assignment->id,
            'assignment_title' => $submission->assignment->title,
            'status' => $submission->status,
            'url' => route('guest.learning.courses.show', $course),
        ]);

        $this->sendLearningMail($submission->user, 'Deine Aufgabe wurde bewertet', [
            "Deine Aufgabe \"{$submission->assignment->title}\" im Kurs \"{$course->title}\" wurde bewertet.",
            $data['feedback'] ?? '',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Bewertung wurde gespeichert.',
                'data' => $submission->fresh(),
            ]);
        }

        return back()->with('success', 'Bewertung wurde gespeichert.');
    }

    public function grantEnrollment(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()->where('email', strtolower($data['email']))->first();
        abort_unless($user, 422, 'Nutzer mit dieser E-Mail wurde nicht gefunden.');

        $enrollment = LearningEnrollment::query()->updateOrCreate(
            ['learning_course_id' => $course->id, 'user_id' => $user->id],
            ['status' => 'active', 'started_at' => now()],
        );

        AppNotification::send($user, 'learning.access.granted', [
            'course_id' => $course->id,
            'course_title' => $course->title,
            'url' => route('guest.learning.courses.show', $course),
        ]);
        $this->sendLearningMail($user, 'Dein Kurszugang ist freigeschaltet', [
            "Du hast jetzt Zugriff auf \"{$course->title}\".",
            route('guest.learning.courses.show', $course),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => "Zugang für {$user->name} wurde freigeschaltet.",
                'data' => $enrollment->fresh('user:id,name,email,profile_photo_path'),
            ], 201);
        }

        return back()->with('success', "Zugang für {$user->name} wurde freigeschaltet.");
    }

    public function revokeEnrollment(Request $request, LearningCourse $course, LearningEnrollment $enrollment)
    {
        $this->authorizeCourse($request, $course);
        abort_unless($enrollment->learning_course_id === $course->id, 404);

        $enrollment->update(['status' => 'cancelled']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Kurszugang wurde deaktiviert.',
                'data' => $enrollment->fresh(),
            ]);
        }

        return back()->with('success', 'Kurszugang wurde deaktiviert.');
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

        return back()->with('success', 'Kurs-Qualitaetsstatus wurde gespeichert.');
    }

    private function courseData(Request $request, ?LearningCourse $course = null): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['required', Rule::in(['training', 'nutrition', 'mindset', 'tactics', 'rehab', 'coaching', 'club_management'])],
            'sport_type' => ['nullable', 'string', 'max:80'],
            'level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced', 'pro'])],
            'language' => ['required', 'string', 'max:10'],
            'cover_image' => ['nullable', 'url', 'max:500'],
            'status' => ['required', Rule::in(['draft', 'review', 'published', 'archived'])],
            'is_public' => ['boolean'],
            'is_free' => ['boolean'],
            'price_cents' => ['nullable', 'integer', 'min:0'],
            'sales_points_text' => ['nullable', 'string', 'max:5000'],
            'faq_items_text' => ['nullable', 'string', 'max:5000'],
            'guarantee_text' => ['nullable', 'string', 'max:2000'],
            'certificate_logo_url' => ['nullable', 'url', 'max:500'],
            'certificate_signature_name' => ['nullable', 'string', 'max:255'],
            'certificate_footer_text' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function lessonData(Request $request, LearningCourse $course): array
    {
        return $request->validate([
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
    }

    private function courseResource(LearningCourse $course): array
    {
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
                ? route('guest.learning.courses.show', $course)
                : null,
            'publish_checklist' => $this->publishChecklist($course),
            'enrollments' => $course->enrollments
                ->map(fn ($enrollment) => [
                    ...$enrollment->toArray(),
                    'completed_lessons_count' => $enrollment->lessonProgress->where('completed', true)->count(),
                    'quiz_attempts_count' => $enrollment->quizAttempts->count(),
                    'passed_quizzes_count' => $enrollment->quizAttempts->where('passed', true)->pluck('learning_quiz_id')->unique()->count(),
                    'assignments_submitted_count' => $enrollment->assignmentSubmissions->count(),
                    'assignments_passed_count' => $enrollment->assignmentSubmissions->where('status', 'passed')->count(),
                    'completion_requirements' => $this->completionRequirements($course, $enrollment),
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
            ['key' => 'description', 'label' => 'Beschreibung vorhanden', 'done' => filled($course->description)],
            ['key' => 'cover', 'label' => 'Cover-Bild gesetzt', 'done' => filled($course->cover_image)],
            ['key' => 'goals', 'label' => 'Lernziele gepflegt', 'done' => ! empty($course->learning_goals)],
            ['key' => 'lessons', 'label' => 'Mindestens eine Lektion', 'done' => $course->lessons->isNotEmpty()],
            ['key' => 'structure', 'label' => 'Kapitelstruktur gepflegt', 'done' => $course->sections->isNotEmpty()],
            ['key' => 'completion', 'label' => 'Abschlusslogik vorhanden', 'done' => $course->quizzes->isNotEmpty() || $course->assignments->where('is_required', true)->isNotEmpty()],
            ['key' => 'sales', 'label' => 'Verkaufsargumente gepflegt', 'done' => ! empty($course->sales_points)],
            ['key' => 'faq', 'label' => 'FAQ für Einwände', 'done' => ! empty($course->faq_items)],
            ['key' => 'price', 'label' => 'Preislogik geklaert', 'done' => $course->is_free || $course->price_cents > 0],
            ['key' => 'public', 'label' => 'öffentlich sichtbar', 'done' => (bool) $course->is_public],
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

    private function refreshEnrollmentCompletion(LearningCourse $course, ?LearningEnrollment $enrollment): void
    {
        if (! $enrollment) {
            return;
        }

        $requirements = $this->completionRequirements($course, $enrollment);
        $totalMilestones = max(1, (int) ($requirements['lessons']['total'] + $requirements['quizzes']['total'] + $requirements['assignments']['total']));
        $completedMilestones = (int) ($requirements['lessons']['completed'] + $requirements['quizzes']['completed'] + $requirements['assignments']['completed']);
        $progressPercent = min(100, (int) round(($completedMilestones / $totalMilestones) * 100));
        $completedAt = $requirements['complete'] ? ($enrollment->completed_at ?: now()) : null;

        $enrollment->forceFill([
            'progress_percent' => $progressPercent,
            'completed_at' => $completedAt,
        ])->save();

        if (! $completedAt) {
            return;
        }

        $certificate = $enrollment->certificate()->firstOrCreate([], [
            'learning_course_id' => $course->id,
            'user_id' => $enrollment->user_id,
            'code' => 'AIR-LEARN-'.Str::upper(Str::random(10)),
            'issued_at' => now(),
        ]);

        if (! $certificate->wasRecentlyCreated) {
            return;
        }

        AppNotification::send($enrollment->user_id, 'learning.certificate.issued', [
            'course_id' => $course->id,
            'course_title' => $course->title,
            'certificate_code' => $certificate->code,
            'url' => route('guest.learning.courses.show', $course),
        ]);
        $this->sendLearningMail($enrollment->user, 'Dein Zertifikat ist bereit: '.$course->title, [
            'Hallo '.$enrollment->user?->name.'.',
            'du hast den Kurs "'.$course->title.'" abgeschlossen.',
            'Zertifikat prüfen: '.route('guest.learning.certificates.verify', $certificate->code),
            'Kurs: '.route('guest.learning.courses.show', $course),
        ]);
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
        $passedQuizIds = $quizIds->isEmpty()
            ? collect()
            : LearningQuizAttempt::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->where('passed', true)
                ->whereIn('learning_quiz_id', $quizIds)
                ->distinct()
                ->pluck('learning_quiz_id');
        $assignmentIds = $course->assignments()->where('is_required', true)->pluck('id');
        $passedAssignmentIds = $assignmentIds->isEmpty()
            ? collect()
            : LearningAssignmentSubmission::query()
                ->where('learning_enrollment_id', $enrollment->id)
                ->where('status', 'passed')
                ->whereIn('learning_assignment_id', $assignmentIds)
                ->distinct()
                ->pluck('learning_assignment_id');

        return [
            'lessons' => [
                'label' => 'Lektionen',
                'total' => (int) $totalLessons,
                'completed' => (int) $completedLessons,
            ],
            'quizzes' => [
                'label' => 'Quiz',
                'total' => $quizIds->count(),
                'completed' => $passedQuizIds->count(),
            ],
            'assignments' => [
                'label' => 'Pflicht-Aufgaben',
                'total' => $assignmentIds->count(),
                'completed' => $passedAssignmentIds->count(),
            ],
            'complete' => $completedLessons >= $totalLessons
                && $quizIds->diff($passedQuizIds)->isEmpty()
                && $assignmentIds->diff($passedAssignmentIds)->isEmpty(),
        ];
    }
}
