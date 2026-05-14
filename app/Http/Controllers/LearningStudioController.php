<?php

namespace App\Http\Controllers;

use App\Models\LearningCourse;
use App\Models\LearningCourseSection;
use App\Models\LearningLesson;
use App\Models\LearningQuiz;
use App\Models\LearningQuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

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

        return Inertia::render('Auth/Dashboard/Learning/Studio', [
            'courses' => $courses,
            'selectedCourse' => $selectedCourse
                ? $this->courseResource(
                    LearningCourse::query()
                        ->where('user_id', $request->user()->id)
                        ->with(['sections.lessons', 'quizzes.questions', 'enrollments.user:id,name,email,profile_photo_path'])
                        ->findOrFail($selectedCourse->id)
                )
                : null,
        ]);
    }

    public function storeCourse(Request $request)
    {
        $data = $this->courseData($request);

        $course = LearningCourse::create([
            ...$data,
            'user_id' => $request->user()->id,
            'slug' => LearningCourse::uniqueSlug($data['title']),
            'learning_goals' => $this->lines($request->input('learning_goals_text')),
            'requirements' => $this->lines($request->input('requirements_text')),
            'target_groups' => $this->lines($request->input('target_groups_text')),
            'tags' => $this->tags($request->input('tags_text')),
            'is_free' => (bool) ($data['is_free'] ?? true),
            'price_cents' => (int) ($data['price_cents'] ?? 0),
        ]);

        $course->sections()->create([
            'title' => 'Start',
            'description' => 'Einleitung und Orientierung',
            'position' => 1,
        ]);

        return redirect()
            ->route('auth.learning.studio.index', ['course' => $course->id])
            ->with('success', 'Kurs wurde angelegt. Du kannst jetzt Kapitel und Lektionen planen.');
    }

    public function updateCourse(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $this->courseData($request, $course);
        $course->update([
            ...$data,
            'slug' => $course->title !== $data['title'] ? LearningCourse::uniqueSlug($data['title'], $course->id) : $course->slug,
            'learning_goals' => $this->lines($request->input('learning_goals_text')),
            'requirements' => $this->lines($request->input('requirements_text')),
            'target_groups' => $this->lines($request->input('target_groups_text')),
            'tags' => $this->tags($request->input('tags_text')),
            'published_at' => ($data['status'] ?? $course->status) === 'published' && ! $course->published_at ? now() : $course->published_at,
        ]);

        return back()->with('success', 'Kurs wurde gespeichert.');
    }

    public function storeSection(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $course->sections()->create([
            ...$data,
            'position' => $course->sections()->max('position') + 1,
        ]);

        return back()->with('success', 'Kapitel wurde hinzugefuegt.');
    }

    public function storeLesson(Request $request, LearningCourse $course)
    {
        $this->authorizeCourse($request, $course);

        $data = $this->lessonData($request, $course);

        $course->lessons()->create([
            ...$data,
            'attachments' => $this->attachments($request->input('attachments_text')),
            'position' => $course->lessons()->where('learning_course_section_id', $data['learning_course_section_id'])->max('position') + 1,
        ]);

        $this->recalculateCourseDuration($course);

        return back()->with('success', 'Lektion wurde hinzugefuegt.');
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

        return back()->with('success', 'Lektion wurde gespeichert.');
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

        return back()->with('success', 'Quiz wurde angelegt.');
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
            'is_preview' => ['boolean'],
        ]);
    }

    private function courseResource(LearningCourse $course): array
    {
        return [
            ...$course->toArray(),
            'learning_goals_text' => implode("\n", $course->learning_goals ?: []),
            'requirements_text' => implode("\n", $course->requirements ?: []),
            'target_groups_text' => implode("\n", $course->target_groups ?: []),
            'tags_text' => implode(', ', $course->tags ?: []),
            'sections' => $course->sections->map(fn (LearningCourseSection $section) => [
                ...$section->toArray(),
                'lessons' => $section->lessons->values(),
            ])->values(),
            'quizzes' => $course->quizzes->values(),
            'enrollments' => $course->enrollments->values(),
        ];
    }

    private function authorizeCourse(Request $request, LearningCourse $course): void
    {
        abort_unless($course->user_id === $request->user()->id, 403);
    }

    private function recalculateCourseDuration(LearningCourse $course): void
    {
        $course->update(['estimated_minutes' => (int) $course->lessons()->sum('duration_minutes')]);
    }

    private function lines(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value))
            ->map(fn ($line) => trim($line))
            ->filter()
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
}
