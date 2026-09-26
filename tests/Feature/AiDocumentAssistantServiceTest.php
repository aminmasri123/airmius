<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\ClubPolicyDocument;
use App\Models\File;
use App\Models\User;
use App\Services\Ai\AiDocumentAssistantService;
use App\Support\UploadStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiDocumentAssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_document_summary_and_translation_only_use_authorized_files_and_label_output(): void
    {
        Storage::fake(UploadStorage::disk());
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $personal = $this->file('users/'.$user->id.'/session.txt', $user, 'session.txt', 'Training: Technikblock und Abschlussdehnung.');
        $restricted = $this->clubFile($club, $owner, 'vorstand.txt', 'Geheime Vorstandszahl: 4242.');

        $result = app(AiDocumentAssistantService::class)->summarizeAuthorizedFiles(
            $user,
            [$personal, $restricted],
            'translation',
            'en'
        );

        $this->assertTrue($result['answerable']);
        $this->assertSame('translation', $result['operation']);
        $this->assertSame('KI-Übersetzung aus bereits berechtigten Quellen', $result['output_label']);
        $this->assertSame(1, $result['sources_considered']);
        $this->assertStringContainsString('Training: Technikblock', $result['content']);
        $this->assertStringNotContainsString('4242', $result['content']);
        $this->assertSame($personal->id, $result['sources'][0]['id']);
    }

    public function test_manual_questions_require_versioned_authorized_sources_with_quotes(): void
    {
        Storage::fake(UploadStorage::disk());
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $club = Club::factory()->create(['owner_id' => $owner->id]);
        $club->users()->attach($member->id, [
            'role' => 'member',
            'roles' => ['member'],
            'membership_status' => 'active',
        ]);
        $publicFile = $this->clubFile(
            $club,
            $owner,
            'beitragsordnung.txt',
            'Beiträge werden am 15. März per SEPA eingezogen. Barzahlung ist nicht vorgesehen.'
        );
        $privateFile = $this->clubFile(
            $club,
            $owner,
            'vorstand.txt',
            'Der interne Vorstandscode lautet DELTA-9.'
        );
        $this->document($club, $publicFile, true, 'Beitragsordnung', '2026.1');
        $this->document($club, $privateFile, false, 'Vorstandsnotiz', '2026.intern');

        $answer = app(AiDocumentAssistantService::class)->answerManualQuestion(
            $member,
            $club,
            'Wann werden Beiträge eingezogen?'
        );

        $this->assertTrue($answer['answerable']);
        $this->assertSame('quotes_required', $answer['policy']);
        $this->assertStringContainsString('15. März', $answer['answer']);
        $this->assertStringNotContainsString('DELTA-9', $answer['answer']);
        $this->assertSame('Beitragsordnung', $answer['citations'][0]['title']);
        $this->assertSame('2026.1', $answer['citations'][0]['version_label']);
        $this->assertStringContainsString('Beiträge werden am 15. März', $answer['citations'][0]['quote']);

        $noAnswer = app(AiDocumentAssistantService::class)->answerManualQuestion(
            $member,
            $club,
            'Wie lautet der Vorstandscode?'
        );

        $this->assertFalse($noAnswer['answerable']);
        $this->assertSame('no_answer_without_citation', $noAnswer['policy']);
        $this->assertSame([], $noAnswer['citations']);
        $this->assertStringNotContainsString('DELTA-9', $noAnswer['answer']);
    }

    private function file(string $path, User $user, string $name, string $content): File
    {
        Storage::disk(UploadStorage::disk())->put($path, $content);

        return File::query()->create([
            'user_id' => $user->id,
            'display_name' => $name,
            'path' => $path,
            'type' => 'text/plain',
            'size' => strlen($content),
        ]);
    }

    private function clubFile(Club $club, User $user, string $name, string $content): File
    {
        Storage::disk(UploadStorage::disk())->put("clubs/{$club->id}/{$name}", $content);

        return File::query()->create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'display_name' => $name,
            'path' => "clubs/{$club->id}/{$name}",
            'type' => 'text/plain',
            'size' => strlen($content),
        ]);
    }

    private function document(Club $club, File $file, bool $public, string $title, string $version): ClubPolicyDocument
    {
        return ClubPolicyDocument::query()->create([
            'club_id' => $club->id,
            'file_id' => $file->id,
            'created_by' => $club->owner_id,
            'type' => 'regulation',
            'title' => $title,
            'version_label' => $version,
            'valid_from' => '2026-01-01',
            'is_public' => $public,
        ]);
    }
}
