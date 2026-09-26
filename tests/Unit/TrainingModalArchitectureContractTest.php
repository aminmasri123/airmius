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

    public function test_mobile_training_logs_support_sport_agnostic_exercises_and_set_tabs(): void
    {
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/training_plans_logs_screen.dart');
        $translations = $this->source('mobile/airmius_mobile/lib/core/airmius_l10n.dart');

        foreach ([
            'class _WorkoutExerciseDialog',
            "'entries': [",
            "'tracking_mode': mode",
            "'set_index': setIndex",
            "'rest_seconds': int.tryParse",
            "'rounds': mode == 'rounds'",
            "'completed': completed",
            "t('workout.startSession')",
            '_builtInWorkoutTemplates(t)',
            "template('swimTechnique',",
            "template('footballPassing',",
        ] as $contract) {
            self::assertStringContainsString($contract, $mobile);
        }

        self::assertGreaterThanOrEqual(2, substr_count($mobile, 'DefaultTabController('));
        self::assertSame(4, substr_count($translations, "'workout.mode.reps':"));
        self::assertSame(4, substr_count($translations, "'workout.mode.distance':"));
        self::assertSame(4, substr_count($translations, "'workout.entryLimit':"));
    }

    public function test_mobile_live_workout_keeps_plan_and_actual_values_separate(): void
    {
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/training_plans_logs_screen.dart');
        $api = $this->source('app/Http/Controllers/Api/V1/TrainingController.php');
        $service = $this->source('app/Services/Training/TrainingLogService.php');

        foreach ([
            'class _LiveWorkoutScreen',
            'class _LiveSetEditorSheet',
            'class _WorkoutCompletionSheet',
            'Timer.periodic',
            "'status': allCompleted ? 'completed' : 'partial'",
            "'skip_reason': skipReason.trim().isEmpty",
            'class _WorkoutSetComparisonRow',
            'class _WorkoutReplacementSheet',
            "'substituted_for': substitutedFor",
        ] as $contract) {
            self::assertStringContainsString($contract, $mobile);
        }

        self::assertStringContainsString("'partial'", $api);
        self::assertStringContainsString("'plan_snapshot'", $service);
        self::assertStringContainsString('planSnapshot(?TrainingPlanItem', $service);
        self::assertSame(4, substr_count(
            $this->source('mobile/airmius_mobile/lib/core/airmius_l10n.dart'),
            "'liveWorkout.replaceExercise':"
        ));
    }

    public function test_mobile_planning_uses_the_same_exercise_and_set_model_as_execution(): void
    {
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/training_plans_logs_screen.dart');
        $translations = $this->source('mobile/airmius_mobile/lib/core/airmius_l10n.dart');
        $api = $this->source('app/Http/Controllers/Api/V1/TrainingController.php');

        foreach ([
            'class _PlannedWorkoutComposer',
            'toPlannedPayload()',
            '_workoutExercisesFromPlanItem(item)',
            'class _WorkoutPlanComparison',
            "'item_exercises': _itemExercises",
            "'exercises': _exercises",
        ] as $contract) {
            self::assertStringContainsString($contract, $mobile);
        }

        self::assertStringContainsString("'planned_exercises'", $api);
        self::assertStringContainsString("plannedExerciseRules('exercises')", $api);
        self::assertStringContainsString('class _TrainingStructureFields', $mobile);
        self::assertStringContainsString("t('trainingHub.equipment.choosePreset')", $mobile);
        self::assertStringContainsString('showModalBottomSheet<String>', $mobile);
        self::assertStringNotContainsString("'Abschnitt':", $mobile);
        // The full planning flow still has six steps; personal plans additionally
        // support a three-step quick flow and omit audience selection.
        self::assertStringContainsString('widget.initial == null ? const [0, 1, 2, 3, 4, 5] : const [0, 1, 2]', $mobile);
        self::assertStringContainsString('bool get _quickPersonalPlan => widget.personalOnly && !_advancedPlanning', $mobile);
        self::assertStringContainsString('? const [0, 1, 3]', $mobile);
        self::assertStringContainsString(': const [0, 1, 3, 4, 5]', $mobile);
        self::assertStringContainsString('final totalSteps = visibleSteps.length', $mobile);
        self::assertStringContainsString('_step = visibleSteps[displayStep + 1]', $mobile);
        self::assertStringContainsString('_step = visibleSteps[displayStep - 1]', $mobile);
        self::assertStringContainsString('class _PlanDateButton', $mobile);
        self::assertStringContainsString('value: _shortDate(_startsOn)', $mobile);
        self::assertStringContainsString('if (_step == 4)', $mobile);
        self::assertStringContainsString('if (_step == 5)', $mobile);
        self::assertSame(4, substr_count($translations, "'trainingHub.step6':"));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
