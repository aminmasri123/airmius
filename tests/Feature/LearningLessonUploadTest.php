<?php

namespace Tests\Feature;

use App\Models\LearningCourse;
use App\Models\User;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningLessonUploadTest extends TestCase
{
    use RefreshDatabase;

    public static function platforms(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('platforms')]
    public function test_creator_can_upload_files_and_save_them_in_new_and_existing_lessons(bool $api): void
    {
        Storage::fake(UploadStorage::disk());
        [$course, $section] = $this->course();
        $api ? Sanctum::actingAs($course->tutor) : $this->actingAs($course->tutor);
        $url = $api ? '/api/v1/learning-studio/courses/'.$course->id : route('auth.learning.studio.courses.update', $course);
        $files = [];
        foreach ([UploadedFile::fake()->image('image.png'), UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('clip.mp4', 10, 'video/mp4')] as $file) {
            $response = $this->postJson($url.'/uploads', ['purpose' => 'lesson_attachment', 'file' => $file])->assertOk();
            Storage::disk(UploadStorage::disk())->assertExists($response->json('path'));
            $files[] = $response->json('url');
        }
        $video = $this->postJson($url.'/uploads', ['purpose' => 'lesson_video', 'file' => UploadedFile::fake()->create('main.mp4', 10, 'video/mp4')])->assertOk()->json('url');
        $data = ['title' => 'Files lesson', 'type' => 'video', 'learning_course_section_id' => $section->id, 'video_url' => $video, 'attachments_text' => implode("\n", $files)];
        $lesson = $this->postJson($url.'/lessons', $data)->assertCreated()->assertJsonPath('data.video_url', $video)->assertJsonCount(3, 'data.attachments')->json('data.id');
        $this->putJson($url.'/lessons/'.$lesson, [...$data, 'title' => 'Updated files lesson', 'attachments_text' => implode("\n", [...$files, 'https://example.test/existing.pdf'])])->assertOk()->assertJsonCount(4, 'data.attachments')->assertJsonPath('data.attachments.0.url', $files[0]);
    }

    public function test_upload_requires_course_ownership_and_supported_types(): void
    {
        Storage::fake(UploadStorage::disk());
        [$course] = $this->course();
        $url = '/api/v1/learning-studio/courses/'.$course->id.'/uploads';
        $this->postJson($url)->assertUnauthorized();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson($url, ['purpose' => 'lesson_attachment', 'file' => UploadedFile::fake()->image('image.png')])->assertForbidden();
        Sanctum::actingAs($course->tutor);
        $this->postJson($url, ['purpose' => 'lesson_video', 'file' => UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($url, ['purpose' => 'lesson_attachment', 'file' => UploadedFile::fake()->create('run.php', 10, 'application/x-httpd-php')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson($url, ['purpose' => 'lesson_attachment', 'file' => UploadedFile::fake()->create('huge.pdf', 512001, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    #[DataProvider('platforms')]
    public function test_assessment_images_require_ownership_and_image_content(bool $api): void
    {
        Storage::fake(UploadStorage::disk());
        [$course] = $this->course();
        $url = $api ? '/api/v1/learning-studio/courses/'.$course->id.'/uploads' : route('auth.learning.studio.uploads.store', $course);
        $api ? Sanctum::actingAs(User::factory()->create()) : $this->actingAs(User::factory()->create());
        $this->postJson($url, ['purpose' => 'assessment_image', 'file' => UploadedFile::fake()->image('q.png')])->assertForbidden();
        $api ? Sanctum::actingAs($course->tutor) : $this->actingAs($course->tutor);
        $response = $this->postJson($url, ['purpose' => 'assessment_image', 'file' => UploadedFile::fake()->image('q.png')])->assertOk();
        Storage::disk(UploadStorage::disk())->assertExists($response->json('path'));
        $this->postJson($url, ['purpose' => 'assessment_image', 'file' => UploadedFile::fake()->create('q.pdf', 10, 'application/pdf')])->assertUnprocessable();
    }

    private function course(): array
    {
        $course = LearningCourse::create(['user_id' => User::factory()->create()->id, 'title' => 'File course', 'category' => 'training', 'level' => 'beginner']);

        return [$course, $course->sections()->create(['title' => 'Start', 'position' => 1])];
    }
}
