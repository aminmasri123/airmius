<?php

namespace App\Http\Controllers;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningLesson;
use App\Models\LearningLessonComment;
use App\Models\LearningLessonNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PublicLearningController extends Controller
{
    public function index()
    {
        $courses = LearningCourse::query()
            ->with(['tutor:id,name,profile_photo_path', 'sections.lessons'])
            ->withCount(['sections', 'lessons', 'enrollments'])
            ->where('status', 'published')
            ->where('is_public', true)
            ->latest('published_at')
            ->latest('id')
            ->get()
            ->map(fn (LearningCourse $course) => $this->courseCard($course));

        return Inertia::render('Guest/E-Learning', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'learningProducts' => [],
            'learningCourses' => $courses,
        ]);
    }

    public function show(Request $request, LearningCourse $course)
    {
        abort_unless($course->status === 'published' && $course->is_public, 404);

        $course->load(['tutor:id,name,profile_photo_path', 'sections.lessons', 'quizzes.questions']);
        $enrollment = $request->user()
            ? LearningEnrollment::query()->where('learning_course_id', $course->id)->where('user_id', $request->user()->id)->first()
            : null;

        return Inertia::render('Guest/LearningCourseShow', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'course' => $this->courseDetail($course),
            'enrollment' => $enrollment,
        ]);
    }

    public function enroll(Request $request, LearningCourse $course)
    {
        abort_unless($course->status === 'published' && $course->is_public, 404);

        LearningEnrollment::query()->firstOrCreate([
            'learning_course_id' => $course->id,
            'user_id' => $request->user()->id,
        ], [
            'status' => 'active',
            'started_at' => now(),
        ]);

        return back()->with('success', 'Du bist im Kurs eingeschrieben.');
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

        return back()->with('success', 'Notiz wurde gespeichert.');
    }

    public function storeComment(Request $request, LearningCourse $course, LearningLesson $lesson)
    {
        $this->authorizeParticipant($request, $course, $lesson);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        LearningLessonComment::query()->create([
            'learning_lesson_id' => $lesson->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
            'visibility' => 'course',
        ]);

        return back()->with('success', 'Frage wurde im Kurs gepostet.');
    }

    private function authorizeParticipant(Request $request, LearningCourse $course, LearningLesson $lesson): void
    {
        abort_unless($lesson->learning_course_id === $course->id, 404);
        abort_unless($request->user(), 403);
        abort_unless(
            $course->user_id === $request->user()->id
            || LearningEnrollment::query()->where('learning_course_id', $course->id)->where('user_id', $request->user()->id)->exists(),
            403
        );
    }

    private function courseCard(LearningCourse $course): array
    {
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
            'estimated_minutes' => $course->estimated_minutes,
            'sections_count' => $course->sections_count,
            'lessons_count' => $course->lessons_count,
            'enrollments_count' => $course->enrollments_count,
            'tutor' => $course->tutor,
            'show_url' => route('guest.learning.courses.show', $course),
        ];
    }

    private function courseDetail(LearningCourse $course): array
    {
        return [
            ...$this->courseCard($course),
            'requirements' => $course->requirements ?: [],
            'target_groups' => $course->target_groups ?: [],
            'tags' => $course->tags ?: [],
            'sections' => $course->sections->map(fn ($section) => [
                ...$section->toArray(),
                'lessons' => $section->lessons->values(),
            ])->values(),
            'quizzes' => $course->quizzes->values(),
        ];
    }
}
