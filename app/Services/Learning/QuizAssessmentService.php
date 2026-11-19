<?php

namespace App\Services\Learning;

use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningQuiz;
use App\Models\LearningQuizAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuizAssessmentService
{
    public function __construct(private readonly AssessmentSchema $schema) {}

    public function save(Request $request, LearningCourse $course, ?LearningQuiz $quiz = null): LearningQuiz
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:1000'],
            'pass_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'learning_lesson_id' => ['nullable', Rule::exists('learning_lessons', 'id')->where('learning_course_id', $course->id)],
        ]);
        $questions = $this->schema->questions($request);

        return DB::transaction(function () use ($request, $course, $quiz, $data, $questions) {
            $owner = LearningCourse::whereKey($course->id)->lockForUpdate()->firstOrFail();
            abort_unless($owner->user_id === $request->user()->id, 403);
            $target = $quiz ? $owner->quizzes()->whereKey($quiz->id)->lockForUpdate()->firstOrFail() : $owner->quizzes()->make();
            $existing = $quiz ? $target->questions()->get()->keyBy('id') : collect();
            foreach ($questions as $i => $q) {
                if (isset($q['id']) && ! $existing->has($q['id'])) {
                    throw ValidationException::withMessages(["questions.$i.id" => __('learning_assessment.changed')]);
                }
            }
            $target->fill([...$data, 'schema_version' => 2, 'revision' => $quiz ? $target->revision + 1 : 1])->save();
            $keep = [];
            foreach ($questions as $i => $q) {
                $id = $q['id'] ?? null;
                unset($q['id']);
                $question = $id ? $existing->get($id) : $target->questions()->make();
                $question->fill([...$q, 'position' => $i + 1])->save();
                $keep[] = $question->id;
            }
            $target->questions()->whereNotIn('id', $keep)->delete();

            return $target->fresh('questions');
        });
    }

    public function attempt(LearningQuiz $quiz, LearningEnrollment $enrollment, User $user, array $answers, ?int $revision): LearningQuizAttempt
    {
        return DB::transaction(function () use ($quiz, $enrollment, $user, $answers, $revision) {
            $quiz = LearningQuiz::whereKey($quiz->id)->lockForUpdate()->firstOrFail()->load('questions');
            if (($quiz->schema_version === 2 || $revision !== null) && $revision !== $quiz->revision) {
                throw ValidationException::withMessages(['quiz_revision' => __('learning_assessment.changed')]);
            }
            $questions = $quiz->questions->toArray();
            $manual = false;
            if ($quiz->schema_version === 2) {
                $answers = $this->schema->answers($questions, $answers);
                $correct = [];
                foreach ($questions as $q) {
                    if ($q['question_type'] === 'long_text') {
                        $manual = true;
                    } elseif ($this->schema->correct($q, $answers[$q['id']])) {
                        $correct[] = $q['id'];
                    }
                }
            } else {
                $normalize = function ($value) {
                    $items = is_array($value) ? $value : [$value];
                    $items = array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? mb_strtolower(trim((string) $v)) : '', $items)));
                    sort($items);

                    return $items;
                };
                $answers = array_map($normalize, $answers);
                $correct = array_column(array_filter($questions, fn ($q) => $normalize($q['correct_options'] ?? []) === ($answers[$q['id']] ?? [])), 'id');
            }
            $score = (int) round(count($correct) / max(1, count($questions)) * 100);

            return $quiz->attempts()->create([
                'learning_enrollment_id' => $enrollment->id, 'user_id' => $user->id, 'answers' => $answers,
                'correct_question_ids' => $correct, 'score_percent' => $score, 'passed' => ! $manual && $score >= $quiz->pass_percent,
                'review_status' => $manual ? 'pending' : 'automatic', 'question_snapshot' => $questions,
                'pass_percent_snapshot' => $quiz->pass_percent, 'quiz_revision' => $quiz->revision, 'submitted_at' => now(),
            ]);
        });
    }

    public function grade(Request $request, LearningQuizAttempt $attempt): void
    {
        $data = $request->validate(['manual_grades' => ['required', 'array'], 'manual_grades.*' => ['required', 'boolean'], 'feedback' => ['nullable', 'string', 'max:5000']]);
        DB::transaction(function () use ($attempt, $data) {
            $target = LearningQuizAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $questions = $target->question_snapshot ?? [];
            $ids = array_column(array_filter($questions, fn ($q) => $q['question_type'] === 'long_text'), 'id');
            if ($ids === [] || array_diff($ids, array_keys($data['manual_grades'])) || array_diff(array_keys($data['manual_grades']), $ids)) {
                throw ValidationException::withMessages(['manual_grades' => __('learning_assessment.answer_required')]);
            }
            $correct = [];
            foreach ($questions as $q) {
                $ok = $q['question_type'] === 'long_text' ? (bool) $data['manual_grades'][$q['id']] : $this->schema->correct($q, $target->answers[$q['id']]);
                if ($ok) {
                    $correct[] = $q['id'];
                }
            }
            $score = (int) round(count($correct) / max(1, count($questions)) * 100);
            $target->update(['manual_grades' => $data['manual_grades'], 'feedback' => $data['feedback'] ?? null, 'correct_question_ids' => $correct, 'score_percent' => $score, 'passed' => $score >= $target->pass_percent_snapshot, 'review_status' => 'reviewed']);
        });
    }
}
