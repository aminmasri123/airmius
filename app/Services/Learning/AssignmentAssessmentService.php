<?php

namespace App\Services\Learning;

use App\Models\LearningAssignment;
use App\Models\LearningAssignmentSubmission;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Support\AppNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssignmentAssessmentService
{
    public function submit(Request $request, LearningCourse $course, LearningAssignment $assignment, LearningEnrollment $enrollment): LearningAssignmentSubmission
    {
        return DB::transaction(function () use ($request, $course, $assignment, $enrollment) {
            $enrollment = LearningEnrollment::whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            abort_unless($enrollment->status === 'active' && (int) $enrollment->user_id === (int) $request->user()->id, 403);
            $assignment = LearningAssignment::whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $data = $request->validate(['body' => ['nullable', 'string', 'max:10000'], 'attachment_url' => ['nullable', 'url', 'max:500']]);
            $schema = app(AssessmentSchema::class);
            $structured = $schema->assignmentSubmission($request, $assignment);
            abort_if(empty($structured) && blank($data['body'] ?? null) && blank($data['attachment_url'] ?? null), 422, __('learning.errors.submission_required'));
            $existing = LearningAssignmentSubmission::where('learning_assignment_id', $assignment->id)->where('learning_enrollment_id', $enrollment->id)->first();
            if ($existing && $existing->answers === ($structured['answers'] ?? null) && $existing->body === ($data['body'] ?? null) && $existing->attachment_url === ($data['attachment_url'] ?? null) && (int) data_get($existing->grading_snapshot, 'revision') === (int) $assignment->revision) {
                return $existing;
            }
            $automatic = $assignment->grading_mode === 'automatic';
            abort_if($automatic && (empty($assignment->questions) || collect($assignment->questions)->contains(fn ($q) => ($q['question_type'] ?? null) === 'long_text') || filled($data['attachment_url'] ?? null)), 422, __('learning_assessment.automatic_fixed_only'));
            $score = null;
            $status = 'submitted';
            if ($automatic) {
                $correct = collect($assignment->questions)->filter(fn ($q) => $schema->correct($q, $structured['answers'][$q['id']]))->count();
                $percent = $correct / count($assignment->questions) * 100;
                $score = (int) round($assignment->points * $percent / 100);
                $status = $percent >= $assignment->pass_percent ? 'passed' : 'needs_revision';
            }
            $submission = LearningAssignmentSubmission::updateOrCreate(['learning_assignment_id' => $assignment->id, 'learning_enrollment_id' => $enrollment->id], [
                'user_id' => $request->user()->id, 'body' => $data['body'] ?? null, ...$structured, 'attachment_url' => $data['attachment_url'] ?? null,
                'status' => $status, 'submitted_at' => now(), 'score' => $score, 'feedback' => $automatic ? __('learning_assessment.automatic_result', ['score' => $score, 'points' => $assignment->points]) : null,
                'graded_by' => null, 'graded_at' => $automatic ? now() : null,
                'grading_snapshot' => ['mode' => $automatic ? 'automatic' : 'manual', 'points' => $assignment->points, 'pass_percent' => $assignment->pass_percent, 'revision' => $assignment->revision],
            ]);
            app(LearningProgressService::class)->refreshCompletion($course, $enrollment);
            $replace = ['student' => $request->user()->name, 'assignment' => $assignment->title, 'course' => $course->title];
            $context = ['course_id' => $course->id, 'assignment_id' => $assignment->id, 'submission_id' => $submission->id, 'lesson_id' => $assignment->learning_lesson_id];
            if ((int) $course->user_id !== (int) $request->user()->id) {
                AppNotification::sendLocalized($course->user_id, 'learning.assignment.submitted', 'learning.notifications.assignment_submitted_title', 'learning.notifications.assignment_submitted_body', $replace, $context, ['after_commit' => true]);
            }
            if ($automatic) {
                AppNotification::sendLocalized($request->user(), 'learning.assignment.graded', 'learning.notifications.assignment_graded_title', 'learning.notifications.assignment_graded_body', $replace, [...$context, 'status' => $status], ['after_commit' => true]);
            }

            return $submission;
        });
    }
}
