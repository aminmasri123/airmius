<?php

namespace Tests\Feature;

use App\Models\LearningAssignment;
use App\Models\LearningAssignmentSubmission;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningAutomaticAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $owner = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::create(['user_id' => $owner->id, 'title' => 'Automatic tasks', 'category' => 'training', 'level' => 'beginner', 'status' => 'published', 'is_public' => true]);
        $enrollment = LearningEnrollment::create(['learning_course_id' => $course->id, 'user_id' => $student->id, 'status' => 'active']);

        return [$owner, $student, $course, $enrollment];
    }

    private function createTask(User $owner, LearningCourse $course, array $extra = []): array
    {
        Sanctum::actingAs($owner);

        return $this->postJson('/api/v1/learning-studio/courses/'.$course->id.'/assignments', array_replace([
            'title' => 'Fixed answers', 'grading_mode' => 'automatic', 'points' => 80, 'pass_percent' => 80, 'is_required' => true,
            'questions' => [
                ['id' => 'single', 'question' => 'Pick one', 'question_type' => 'single_choice', 'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']], 'correct_options' => ['a']],
                ['id' => 'multi', 'question' => 'Pick both', 'question_type' => 'multiple_choice', 'options' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']], 'correct_options' => ['a', 'b']],
                ['id' => 'order', 'question' => 'Order', 'question_type' => 'ordering', 'options' => [['id' => 'a', 'text' => 'First'], ['id' => 'b', 'text' => 'Second']]],
                ['id' => 'text', 'question' => 'Fixed word', 'question_type' => 'short_text', 'correct_options' => ['secret solution']],
            ],
        ], $extra))->assertCreated()->json('data');
    }

    public function test_automatic_grades_points_completion_notifications_and_hides_solutions(): void
    {
        [$owner,$student,$course,$enrollment] = $this->fixture();
        $task = $this->createTask($owner, $course);
        Sanctum::actingAs($student);
        $response = $this->getJson('/api/v1/learning/courses/'.$course->id)->assertOk();
        $this->assertStringNotContainsString('correct_options', $response->getContent());
        $this->assertStringNotContainsString('secret solution', $response->getContent());
        $payload = ['assignment_revision' => 1, 'answers' => ['single' => ['a'], 'multi' => ['b', 'a'], 'order' => ['a', 'b'], 'text' => '  SECRET   solution  '], 'score' => 9999, 'status' => 'rejected'];
        $url = '/api/v1/learning/courses/'.$course->id.'/assignments/'.$task['id'].'/submissions';
        $result = $this->postJson($url, $payload)->assertCreated()->assertJsonPath('data.status', 'passed')->assertJsonPath('data.score', 80)->assertJsonMissingPath('data.question_snapshot');
        $this->assertNotNull($enrollment->fresh()->completed_at);
        $this->postJson($url, $payload)->assertCreated();
        $this->assertSame(1, LearningAssignmentSubmission::count());
        $alerts = Notification::where('type', 'like', 'learning.assignment.%')->get();
        $this->assertCount(2, $alerts);
        $authorAlert = $alerts->firstWhere('user_id', $owner->id);
        $this->assertStringContainsString('learning/studio', $authorAlert->data['mobile_url']);
        $this->assertStringContainsString('submission=', $authorAlert->data['mobile_url']);
        $this->assertStringContainsString('learning/courses/'.$course->id, $alerts->firstWhere('user_id', $student->id)->data['mobile_url']);
        $submission = LearningAssignmentSubmission::sole();
        $this->assertNull($submission->graded_by);
        $this->assertNotNull($submission->graded_at);
        $this->assertSame('automatic', $submission->grading_snapshot['mode']);
    }

    public function test_failed_answers_and_order_are_not_passed_and_revisions_are_checked(): void
    {
        [$owner,$student,$course] = $this->fixture();
        $task = $this->createTask($owner, $course);
        Sanctum::actingAs($student);
        $url = '/api/v1/learning/courses/'.$course->id.'/assignments/'.$task['id'].'/submissions';
        $answers = ['single' => ['a'], 'multi' => ['a', 'b'], 'order' => ['b', 'a'], 'text' => 'secret solution'];
        $this->postJson($url, ['assignment_revision' => 1, 'answers' => $answers])->assertCreated()->assertJsonPath('data.status', 'needs_revision')->assertJsonPath('data.score', 60);
        $this->postJson($url, ['assignment_revision' => 0, 'answers' => $answers])->assertUnprocessable();
        $this->postJson($url, ['assignment_revision' => 1, 'answers' => [...$answers, 'forged' => 'x']])->assertUnprocessable();
        $this->postJson($url, ['assignment_revision' => 1, 'answers' => $answers, 'attachment_url' => 'https://example.test/file.pdf'])->assertUnprocessable();
        Sanctum::actingAs($owner);
        $this->putJson('/api/v1/learning-studio/courses/'.$course->id.'/assignments/'.$task['id'], ['title' => 'Revised task', 'points' => 80, 'grading_mode' => 'automatic', 'pass_percent' => 80, 'questions' => $task['questions']])->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertSame(1, LearningAssignmentSubmission::sole()->grading_snapshot['revision']);
        Sanctum::actingAs($student);
        $this->postJson($url, ['assignment_revision' => 1, 'answers' => $answers])->assertUnprocessable();
    }

    public function test_automatic_mode_requires_fixed_solutions_but_legacy_manual_tasks_still_work(): void
    {
        [$owner,$student,$course] = $this->fixture();
        Sanctum::actingAs($owner);
        $base = '/api/v1/learning-studio/courses/'.$course->id.'/assignments';
        $this->postJson($base, ['title' => 'Unsupported', 'grading_mode' => 'automatic', 'questions' => [['question' => 'Essay', 'question_type' => 'long_text']]])->assertUnprocessable();
        $this->postJson($base, ['title' => 'No answers', 'grading_mode' => 'automatic', 'questions' => [['question' => 'Word', 'question_type' => 'short_text']]])->assertUnprocessable();
        $id = $this->postJson($base, ['title' => 'Legacy essay', 'points' => 100])->assertCreated()->assertJsonPath('data.grading_mode', 'manual')->json('data.id');
        Sanctum::actingAs($student);
        $this->postJson('/api/v1/learning/courses/'.$course->id.'/assignments/'.$id.'/submissions', ['body' => 'Manual answer'])->assertCreated()->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.score', null);
    }

    public function test_web_submission_auto_grades_and_foreign_or_revoked_access_is_rejected(): void
    {
        [$owner,$student,$course,$enrollment] = $this->fixture();
        $task = $this->createTask($owner, $course);
        $assignment = LearningAssignment::findOrFail($task['id']);
        $answers = ['single' => ['a'], 'multi' => ['a', 'b'], 'order' => ['a', 'b'], 'text' => 'secret solution'];
        $this->actingAs($student)->post(route('auth.learning.assignments.submissions.store', [$course, $assignment]), ['assignment_revision' => 1, 'answers' => $answers])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('passed', LearningAssignmentSubmission::sole()->status);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/learning/courses/'.$course->id.'/assignments/'.$assignment->id.'/submissions', ['assignment_revision' => 1, 'answers' => $answers])->assertForbidden();
        $this->getJson('/api/v1/learning-studio?course='.$course->id)->assertNotFound();
        Sanctum::actingAs($student);
        $enrollment->update(['status' => 'revoked']);
        $this->postJson('/api/v1/learning/courses/'.$course->id.'/assignments/'.$assignment->id.'/submissions',['assignment_revision' => 1, 'answers' => $answers])->assertForbidden();
    }
}
