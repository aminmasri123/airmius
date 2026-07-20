<?php

namespace Tests\Feature;

use App\Models\ContentReport;
use App\Models\ModerationFlag;
use App\Models\ModerationLog;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ModerationDsaProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_review_appeal_and_moderation_log_flow(): void
    {
        $author = User::factory()->create();
        $reporter = User::factory()->create();
        $admin = User::factory()->create();
        $this->grantSystemManage($admin);

        $post = Post::factory()->create([
            'user_id' => $author->id,
            'content' => 'Dieser Inhalt soll geprueft werden.',
            'moderation_status' => 'approved',
            'visibility' => 'public',
        ]);

        Sanctum::actingAs($reporter);

        $reportId = $this->postJson('/api/v1/reports', [
            'type' => 'post',
            'id' => $post->id,
            'reason' => 'hate',
            'details' => 'Bitte pruefen, der Inhalt wirkt diskriminierend.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.reason', 'hate')
            ->assertJsonPath('data.status', 'open')
            ->json('data.id');

        $this->assertDatabaseHas('moderation_logs', [
            'case_type' => ContentReport::class,
            'case_id' => $reportId,
            'actor_id' => $reporter->id,
            'action' => 'reported',
            'new_status' => 'open',
        ]);

        $report = ContentReport::findOrFail($reportId);

        $this->actingAs($admin)
            ->put(route('admin.moderation.reports.update', $report), [
                'status' => 'actioned',
                'remove_content' => true,
                'decision_reason' => 'Der gemeldete Inhalt verstoest gegen die Community-Regeln.',
            ])
            ->assertRedirect();

        $report->refresh();
        $post->refresh();

        $this->assertSame('actioned', $report->status);
        $this->assertSame('content_removed', $report->action_taken);
        $this->assertSame('Der gemeldete Inhalt verstoest gegen die Community-Regeln.', $report->decision_reason);
        $this->assertSame('removed', $post->moderation_status);
        $this->assertDatabaseHas('moderation_logs', [
            'case_type' => ContentReport::class,
            'case_id' => $report->id,
            'actor_id' => $admin->id,
            'action' => 'decision',
            'previous_status' => 'open',
            'new_status' => 'actioned',
        ]);

        Sanctum::actingAs($reporter);

        $this->postJson("/api/v1/reports/{$report->id}/appeal", [
            'reason' => 'Ich moechte die Entscheidung nachvollziehen lassen, weil die Entfernung relevant fuer mein Verfahren ist.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.appeal_status', 'pending');

        $this->assertDatabaseHas('moderation_logs', [
            'case_type' => ContentReport::class,
            'case_id' => $report->id,
            'actor_id' => $reporter->id,
            'action' => 'appeal_submitted',
            'new_status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.moderation.reports.appeal.update', $report), [
                'appeal_status' => 'rejected',
                'appeal_decision' => 'Die Erstentscheidung bleibt bestehen, weil der Inhalt weiterhin regelwidrig ist.',
            ])
            ->assertRedirect();

        $report->refresh();

        $this->assertSame('rejected', $report->appeal_status);
        $this->assertSame('Die Erstentscheidung bleibt bestehen, weil der Inhalt weiterhin regelwidrig ist.', $report->appeal_decision);
        $this->assertDatabaseHas('moderation_logs', [
            'case_type' => ContentReport::class,
            'case_id' => $report->id,
            'actor_id' => $admin->id,
            'action' => 'appeal_decided',
            'previous_status' => 'pending',
            'new_status' => 'rejected',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.moderation.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Dashboard/Admin/Moderation/Index')
                ->where('reports.0.appeal_status', 'rejected')
                ->where('reports.0.action_taken', 'content_removed')
                ->where('reports.0.logs.0.action', 'appeal_decided')
            );
    }

    public function test_automatic_moderation_flag_decisions_are_logged(): void
    {
        $admin = User::factory()->create();
        $this->grantSystemManage($admin);

        $flag = ModerationFlag::create([
            'source' => 'automatic',
            'severity' => 'medium',
            'categories' => ['hate'],
            'matched_terms' => ['test'],
            'status' => 'open',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.moderation.flags.update', $flag), [
                'status' => 'dismissed',
                'decision_reason' => 'Kein Regelverstoss nach manueller Pruefung.',
            ])
            ->assertRedirect();

        $flag->refresh();

        $this->assertSame('dismissed', $flag->status);
        $this->assertSame('dismissed', $flag->action_taken);
        $this->assertSame('Kein Regelverstoss nach manueller Pruefung.', $flag->decision_reason);
        $this->assertSame(1, ModerationLog::query()
            ->where('case_type', ModerationFlag::class)
            ->where('case_id', $flag->id)
            ->where('action', 'decision')
            ->count());
    }

    private function grantSystemManage(User $user): void
    {
        Permission::findOrCreate('system.manage', 'web');
        $user->givePermissionTo('system.manage');
    }
}
