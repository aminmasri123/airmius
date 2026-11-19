<?php

namespace Tests\Feature;

use App\Models\LearningCertificate;
use App\Models\LearningCourse;
use App\Models\LearningEnrollment;
use App\Models\User;
use App\Services\Learning\CertificateLogo;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LearningCertificateLogoTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_upload_before_course_creation_is_saved_and_embedded_in_certificate(): void
    {
        Storage::fake(UploadStorage::disk());
        $creator = User::factory()->create();
        Sanctum::actingAs($creator);
        $url = $this->postJson('/api/v1/learning-studio/certificate-logo', ['file' => UploadedFile::fake()->image('logo.png', 200, 120)])
            ->assertCreated()->json('url');
        $id = $this->postJson('/api/v1/learning-studio/courses', [
            'title' => 'Logo course', 'category' => 'training', 'level' => 'beginner', 'language' => 'de',
            'status' => 'draft', 'is_free' => true, 'certificate_logo_url' => $url,
        ])->assertCreated()->assertJsonPath('data.certificate_logo_url', $url)->json('data.id');
        $course = LearningCourse::findOrFail($id);
        $this->assertNotNull(app(CertificateLogo::class)->bytes($course));
        $student = User::factory()->create();
        $enrollment = LearningEnrollment::create(['learning_course_id' => $id, 'user_id' => $student->id, 'status' => 'active', 'completed_at' => now(), 'progress_percent' => 100]);
        $certificate = LearningCertificate::create(['learning_course_id' => $id, 'learning_enrollment_id' => $enrollment->id, 'user_id' => $student->id, 'code' => 'LOGO-TEST-001', 'issued_at' => now()]);
        $pdf = $this->actingAs($student)->get(route('auth.learning.certificates.show', $certificate))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertStringContainsString('/Im1 Do', $pdf);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $course->user_id = User::factory()->create()->id;
        $this->assertNull(app(CertificateLogo::class)->bytes($course));
        $course->certificate_logo_url = 'http://127.0.0.1/private.png';
        $this->assertNull(app(CertificateLogo::class)->bytes($course));
    }

    public function test_web_upload_and_image_validation(): void
    {
        Storage::fake(UploadStorage::disk());
        $this->actingAs(User::factory()->create());
        $this->postJson(route('auth.learning.studio.certificate-logo.store'), ['file' => UploadedFile::fake()->image('brand.jpg')])->assertCreated()->assertJsonStructure(['url']);
        foreach ([UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'), UploadedFile::fake()->image('large.png')->size(5121), UploadedFile::fake()->image('wide.png', 4097, 10)] as $file) {
            $this->postJson('/api/v1/learning-studio/certificate-logo', ['file' => $file])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
    }

    public function test_guest_cannot_upload_a_certificate_logo(): void
    {
        $this->postJson('/api/v1/learning-studio/certificate-logo')->assertUnauthorized();
    }
}
