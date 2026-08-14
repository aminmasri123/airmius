<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TrainingModalArchitectureContractTest extends TestCase
{
    public function test_training_workspace_uses_the_modular_accessible_modal_stack(): void
    {
        $page = $this->source('resources/js/Pages/Auth/Dashboard/Training/Index.vue');
        $stack = $this->source('resources/js/Components/Training/TrainingModalStack.vue');

        self::assertStringContainsString('import TrainingModalStack', $page);
        self::assertStringContainsString('<TrainingModalStack', $page);
        self::assertStringNotContainsString("activeModal === 'ai-plan'", $page);
        self::assertStringContainsString('role="dialog"', $stack);
        self::assertStringContainsString('aria-modal="true"', $stack);
        self::assertStringContainsString("event.key === 'Escape'", $stack);
        self::assertStringContainsString("event.key !== 'Tab'", $stack);
    }

    public function test_plan_audience_routes_and_ai_safety_are_preserved_end_to_end(): void
    {
        $page = $this->source('resources/js/Pages/Auth/Dashboard/Training/Index.vue');
        $stack = $this->source('resources/js/Components/Training/TrainingModalStack.vue');
        $workspace = $this->source('resources/js/composables/useTrainingWorkspace.js');
        $builder = $this->source('resources/js/composables/useAiTrainingPlanBuilder.js');
        $create = $this->source('resources/js/Components/Training/TrainingPlanCreateModal.vue');
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/training_plans_logs_screen.dart');

        foreach (['aiSafetyAccepted', 'aiSafetyCanSave', 'aiSafetyGate'] as $binding) {
            self::assertStringContainsString($binding, $workspace);
        }

        self::assertStringContainsString(':ai-safety-can-save="aiSafetyCanSave"', $page);
        self::assertStringContainsString(':sport-routes="sportRoutes"', $stack);
        self::assertStringContainsString(':private-people="privatePeople"', $stack);
        self::assertStringContainsString(':set-plan-target-type="setPlanTargetType"', $stack);
        self::assertStringContainsString('training_workspace.plan_audience.private_title', $create);
        self::assertStringContainsString('item_session_block', $workspace);
        self::assertStringContainsString('structuredMetricsFor', $workspace);
        self::assertStringContainsString('trainingSessionBlocks', $create);
        self::assertStringContainsString('Trainingsstruktur', $this->source('resources/js/Components/Training/TrainingPlanItemForm.vue'));
        self::assertStringContainsString('accepted_ai_safety: aiSafetyAccepted.value', $builder);
        self::assertStringContainsString("'accepted_ai_safety': true", $mobile);
        self::assertStringContainsString('canSave && _accepted', $mobile);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
