<?php

namespace Tests\Feature;

use App\Models\LearningAssignmentSubmission;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\LearningQuizAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningAssessmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_quiz_editing_manual_review_snapshots_and_permissions(): void
    {
        $owner = User::factory()->create();
        $student = User::factory()->create();
        $course = LearningCourse::create(['user_id' => $owner->id, 'title' => 'Assessments', 'category' => 'training', 'level' => 'beginner', 'status' => 'published', 'is_public' => true]);
        $enrollment = LearningEnrollment::create(['learning_course_id' => $course->id, 'user_id' => $student->id, 'status' => 'active']);
        Sanctum::actingAs($owner);
        $base = '/api/v1/learning-studio/courses/'.$course->id;
        $payload = ['title' => 'Five types', 'pass_percent' => 80, 'questions' => $this->questions()];
        $quiz = $this->postJson($base.'/quizzes', $payload)->assertCreated()->assertJsonCount(5, 'data.questions')->json('data');
        $questions = $quiz['questions'];
        $answers = [$questions[0]['id'] => ['a'], $questions[1]['id'] => ['b', 'a'], $questions[2]['id'] => ['a', 'b'], $questions[3]['id'] => '  accepted   ANSWER ', $questions[4]['id'] => 'My long answer'];
        Sanctum::actingAs($student);
        $detail = $this->getJson('/api/v1/learning/courses/'.$course->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('correct_options', $detail);
        $this->assertStringNotContainsString('accepted answer', $detail);
        $url = '/api/v1/learning/courses/'.$course->id.'/quizzes/'.$quiz['id'].'/attempts';
        $this->postJson($url, ['answers' => $answers])->assertUnprocessable();
        $attemptId = $this->postJson($url, ['answers' => $answers, 'quiz_revision' => 1])->assertCreated()->assertJsonPath('data.passed', false)->assertJsonPath('data.review_status', 'pending')->json('data.id');
        $this->assertNull($enrollment->fresh()->completed_at);
        $gradeUrl = $base.'/quizzes/'.$quiz['id'].'/attempts/'.$attemptId;
        $this->putJson($gradeUrl, ['manual_grades' => [$questions[4]['id'] => true]])->assertForbidden();
        Sanctum::actingAs($owner);
        $changed = $payload;
        $changed['questions'] = $questions;
        $changed['questions'][4]['question'] = 'New wording';
        $this->putJson($base.'/quizzes/'.$quiz['id'], $changed)->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertSame('Long question', LearningQuizAttempt::findOrFail($attemptId)->question_snapshot[4]['question']);
        $this->putJson($gradeUrl, ['manual_grades' => [$questions[4]['id'] => true], 'feedback' => 'Well done'])->assertOk()->assertJsonPath('data.passed', true)->assertJsonPath('data.score_percent', 100);
        $this->assertNotNull($enrollment->fresh()->completed_at);
        Sanctum::actingAs($student);
        $this->postJson($url, ['answers' => $answers, 'quiz_revision' => 1])->assertUnprocessable();
        $reversed = $answers;
        $reversed[$questions[2]['id']] = ['b', 'a'];
        $id = $this->postJson($url, ['answers' => $reversed, 'quiz_revision' => 2])->assertCreated()->json('data.id');
        Sanctum::actingAs($owner);
        $this->putJson($base.'/quizzes/'.$quiz['id'].'/attempts/'.$id, ['manual_grades' => [$questions[4]['id'] => false]])->assertOk()->assertJsonPath('data.score_percent', 60)->assertJsonPath('data.passed', false);
        Sanctum::actingAs(User::factory()->create());
        $this->putJson($base.'/quizzes/'.$quiz['id'], $changed)->assertForbidden();
        $this->putJson($gradeUrl, ['manual_grades' => [$questions[4]['id'] => true]])->assertForbidden();
    }

    public function test_question_validation_and_image_only_options(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        $course = LearningCourse::create(['user_id' => $owner->id, 'title' => 'Validation', 'category' => 'training', 'level' => 'beginner']);
        $url = '/api/v1/learning-studio/courses/'.$course->id.'/quizzes';
        $q = $this->questions()[0];
        $q['question'] = '';
        $q['image_url'] = 'https://example.test/question.png';
        $q['options'][0] = ['id' => 'a', 'image_url' => 'https://example.test/a.png'];
        $this->postJson($url, ['title' => 'Images', 'pass_percent' => 70, 'questions' => [$q]])->assertCreated();
        unset($q['image_url']);
        $this->postJson($url, ['title' => 'Missing question', 'pass_percent' => 70, 'questions' => [$q]])->assertUnprocessable();
        $q = $this->questions()[0];
        $q['correct_options'] = ['a', 'b'];
        $this->postJson($url, ['title' => 'Single', 'pass_percent' => 70, 'questions' => [$q]])->assertUnprocessable();
        $q['correct_options'] = ['foreign'];
        $this->postJson($url, ['title' => 'Foreign option', 'pass_percent' => 70, 'questions' => [$q]])->assertUnprocessable();
    }

    public function test_assignments_accept_structured_answers_and_are_editable_without_changing_old_submissions(): void
    {
        $owner = User::factory()->create();
        $student = User::factory()->create();
        Sanctum::actingAs($owner);
        $course = LearningCourse::create(['user_id' => $owner->id, 'title' => 'Task', 'category' => 'training', 'level' => 'beginner', 'status' => 'published', 'is_public' => true]);
        LearningEnrollment::create(['learning_course_id' => $course->id, 'user_id' => $student->id, 'status' => 'active']);
        $base = '/api/v1/learning-studio/courses/'.$course->id.'/assignments';
        $data = ['title' => 'Task', 'points' => 100, 'questions' => [$this->questions()[4]]];
        $task = $this->postJson($base, $data)->assertCreated()->json('data');
        Sanctum::actingAs($student);
        $url = '/api/v1/learning/courses/'.$course->id.'/assignments/'.$task['id'].'/submissions';
        $this->postJson($url, ['assignment_revision' => 1, 'answers' => [$task['questions'][0]['id'] => 'My response']])->assertCreated();
        Sanctum::actingAs($owner);
        $data['questions'] = $task['questions'];
        $data['questions'][0]['question'] = 'Revised task';
        $this->putJson($base.'/'.$task['id'], $data)->assertOk()->assertJsonPath('data.revision', 2);
        $this->assertDatabaseHas('learning_assignment_submissions', ['body' => null, 'status' => 'submitted']);
        $snapshot = LearningAssignmentSubmission::firstOrFail()->question_snapshot;
        $this->assertSame('Long question', $snapshot[0]['question']);
        Sanctum::actingAs($student);
        $this->putJson($base.'/'.$task['id'], $data)->assertForbidden();
        $this->postJson($url, ['assignment_revision' => 1, 'answers' => [$task['questions'][0]['id'] => 'Old response']])->assertUnprocessable();
    }

    public function test_web_creator_can_save_edit_and_reject_foreign_question_ids(): void
    {
        $owner = User::factory()->create();
        $course = LearningCourse::create(['user_id' => $owner->id, 'title' => 'Web quiz', 'category' => 'training', 'level' => 'beginner']);
        $this->actingAs($owner);
        $payload = ['title' => 'Web mixed quiz', 'pass_percent' => 70, 'questions' => $this->questions()];
        $this->from('/learning/studio')->post(route('auth.learning.studio.quizzes.store', $course), $payload)->assertRedirect('/learning/studio')->assertSessionHasNoErrors();
        $quiz = $course->quizzes()->firstOrFail()->load('questions');
        $payload['questions'] = $quiz->questions->toArray();
        $payload['title'] = 'Edited quiz';
        $this->from('/learning/studio')->put(route('auth.learning.studio.quizzes.update', [$course, $quiz]), $payload)->assertRedirect('/learning/studio')->assertSessionHasNoErrors();
        $this->assertSame(2, $quiz->fresh()->revision);
        $other = $this->postJson(route('auth.learning.studio.quizzes.store', $course), ['title' => 'Second', 'pass_percent' => 70, 'questions' => [$this->questions()[0]]])->assertCreated()->json('data');
        $payload['questions'][0]['id'] = $other['questions'][0]['id'];
        $this->putJson(route('auth.learning.studio.quizzes.update', [$course, $quiz]), $payload)->assertUnprocessable();
        $this->assertSame(2, $quiz->fresh()->revision);
        $task = ['title' => 'Web assignment', 'points' => 100, 'questions' => [$this->questions()[4]]];
        $this->from('/learning/studio')->post(route('auth.learning.studio.assignments.store', $course), $task)->assertRedirect('/learning/studio')->assertSessionHasNoErrors();
        $assignment = $course->assignments()->firstOrFail();
        $this->from('/learning/studio')->put(route('auth.learning.studio.assignments.update', [$course, $assignment]), [...$task, 'title' => 'Edited task'])->assertRedirect('/learning/studio')->assertSessionHasNoErrors();
        $this->assertSame(2, $assignment->fresh()->revision);
        $this->actingAs(User::factory()->create())->putJson(route('auth.learning.studio.quizzes.update', [$course, $quiz]), $payload)->assertForbidden();
    }

    private function questions(): array
    {
        $options = [['id' => 'a', 'text' => 'First'], ['id' => 'b', 'text' => 'Second']];

        return [
            ['question' => 'Single question', 'question_type' => 'single_choice', 'options' => $options, 'correct_options' => ['a']],
            ['question' => 'Multi question', 'question_type' => 'multiple_choice', 'options' => $options, 'correct_options' => ['a', 'b']],
            ['question' => 'Order question', 'question_type' => 'ordering', 'options' => $options],
            ['question' => 'Short question', 'question_type' => 'short_text', 'correct_options' => ['accepted answer']],
            ['question' => 'Long question', 'question_type' => 'long_text'],
        ];
    }
}
