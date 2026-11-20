<?php

namespace App\Services\Learning;

use App\Models\LearningAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssessmentSchema
{
    public const TYPES = ['short_text', 'long_text', 'multiple_choice', 'single_choice', 'ordering'];

    public function questions(Request $request, bool $solutions = true, bool $preserveIds = false): array
    {
        $data = $request->validate([
            'questions' => [$solutions ? 'required' : 'present', 'array', 'min:'.($solutions ? 1 : 0), 'max:50'],
            'questions.*.id' => ['nullable', 'regex:/^[a-zA-Z0-9_-]{1,64}$/'],
            'questions.*.question' => ['nullable', 'string', 'max:3000'],
            'questions.*.image_url' => ['nullable', 'url:http,https', 'max:500'],
            'questions.*.question_type' => ['required', Rule::in(self::TYPES)],
            'questions.*.explanation' => ['nullable', 'string', 'max:3000'],
            'questions.*.options' => ['nullable', 'array', 'max:20'],
            'questions.*.options.*.id' => ['required', 'string', 'regex:/^[a-zA-Z0-9_-]{1,64}$/'],
            'questions.*.options.*.text' => ['nullable', 'string', 'max:1000'],
            'questions.*.options.*.image_url' => ['nullable', 'url:http,https', 'max:500'],
            'questions.*.correct_options' => ['nullable', 'array', 'max:20'],
            'questions.*.correct_options.*' => ['string', 'max:1000'],
        ])['questions'];
        foreach ($data as $i => &$q) {
            $q['question'] = trim($q['question'] ?? '');
            $q['image_url'] = $q['image_url'] ?? null;
            if ($q['question'] === '' && blank($q['image_url'])) {
                $this->invalid("questions.$i.question", 'text_or_image');
            }
            $q['options'] = $q['options'] ?? [];
            $q['correct_options'] = $q['correct_options'] ?? [];
            if (in_array($q['question_type'], ['multiple_choice', 'single_choice', 'ordering'], true)) {
                if (count($q['options']) < 2) {
                    $this->invalid("questions.$i.options", 'two_options');
                }
                $ids = array_column($q['options'], 'id');
                if (count($ids) !== count(array_unique($ids))) {
                    $this->invalid("questions.$i.options", 'unique_options');
                }
                foreach ($q['options'] as $j => $option) {
                    if (blank($option['text'] ?? null) && blank($option['image_url'] ?? null)) {
                        $this->invalid("questions.$i.options.$j.text", 'text_or_image');
                    }
                }
                if ($q['question_type'] === 'ordering') {
                    $q['correct_options'] = $ids;
                }
                if ($solutions && ($q['correct_options'] === [] || array_diff($q['correct_options'], $ids) || count($q['correct_options']) !== count(array_unique($q['correct_options'])))) {
                    $this->invalid("questions.$i.correct_options", 'correct_required');
                }
                if ($solutions && $q['question_type'] === 'single_choice' && count($q['correct_options']) !== 1) {
                    $this->invalid("questions.$i.correct_options", 'one_correct');
                }
            } else {
                $q['options'] = [];
                $q['correct_options'] = array_values(array_filter(array_map('trim', $q['correct_options']), fn ($v) => $v !== ''));
                if ($solutions && $q['question_type'] === 'short_text' && $q['correct_options'] === []) {
                    $this->invalid("questions.$i.correct_options", 'correct_required');
                }
                if ($q['question_type'] === 'long_text') {
                    $q['correct_options'] = [];
                }
            }
            if (! $solutions || $preserveIds) {
                $q['id'] = is_scalar($q['id'] ?? null) ? (string) $q['id'] : (string) Str::uuid();
                if (! preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $q['id'])) {
                    $this->invalid("questions.$i.id", 'unique_options');
                }
                if (! $solutions) {
                    $q['correct_options'] = [];
                }
            }
        }
        unset($q);
        $ids = array_filter(array_column($data, 'id'), fn ($id) => $id !== null);
        if (count($ids) !== count(array_unique($ids))) {
            $this->invalid('questions', 'unique_options');
        }

        return $data;
    }

    public function assignmentQuestions(Request $request, bool $automatic): array
    {
        $questions = $this->questions($request, $automatic, true);
        if ($automatic && collect($questions)->contains(fn ($q) => $q['question_type'] === 'long_text')) {
            $this->invalid('questions', 'automatic_fixed_only');
        }

        return $questions;
    }

    public function publicQuestion(array $q): array
    {
        $safe = array_intersect_key($q, array_flip(['id', 'question', 'question_type', 'image_url', 'options', 'position']));
        $safe['options'] = $q['options'] ?? [];
        if (($q['question_type'] ?? '') === 'ordering') {
            $safe['options'] = collect($safe['options'] ?? [])->shuffle()->values()->all();
        }

        return $safe;
    }

    public function answers(array $questions, array $answers): array
    {
        if (array_diff(array_keys($answers), array_column($questions, 'id'))) {
            $this->invalid('answers', 'invalid_answer');
        }
        $result = [];
        foreach ($questions as $q) {
            $id = $q['id'];
            $value = $answers[$id] ?? null;
            if (in_array($q['question_type'], ['short_text', 'long_text'], true)) {
                if (! is_string($value) || trim($value) === '' || mb_strlen($value) > ($q['question_type'] === 'short_text' ? 1000 : 10000)) {
                    $this->invalid("answers.$id", 'answer_required');
                }
                $result[$id] = trim($value);
            } else {
                if (! is_array($value) || $value === [] || count($value) > 20 || count($value) !== count(array_unique(array_filter($value, 'is_string')))) {
                    $this->invalid("answers.$id", 'invalid_answer');
                }
                $ids = array_column($q['options'], 'id');
                if (array_diff($value, $ids)) {
                    $this->invalid("answers.$id", 'invalid_answer');
                }
                if ($q['question_type'] === 'single_choice' && count($value) !== 1) {
                    $this->invalid("answers.$id", 'invalid_answer');
                }
                if ($q['question_type'] === 'ordering' && count($value) !== count($ids)) {
                    $this->invalid("answers.$id", 'invalid_answer');
                }
                $result[$id] = array_values($value);
            }
        }

        return $result;
    }

    public function correct(array $q, mixed $answer): bool
    {
        if ($q['question_type'] === 'long_text') {
            return false;
        }
        if ($q['question_type'] === 'short_text') {
            $normalize = fn ($text) => mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $text)));

            return in_array($normalize($answer), array_map($normalize, $q['correct_options']), true);
        }
        $expected = $q['correct_options'];
        if ($q['question_type'] !== 'ordering') {
            sort($expected);
            sort($answer);
        }

        return $expected === $answer;
    }

    public function assignmentSubmission(Request $request, LearningAssignment $assignment): array
    {
        if (empty($assignment->questions)) {
            return [];
        }
        $data = $request->validate(['assignment_revision' => ['required', 'integer'], 'answers' => ['required', 'array']]);
        if ((int) $data['assignment_revision'] !== $assignment->revision) {
            $this->invalid('assignment_revision', 'changed');
        }

        return ['answers' => $this->answers($assignment->questions, $data['answers']), 'question_snapshot' => $assignment->questions];
    }

    private function invalid(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('learning_assessment.'.$key)]);
    }
}
