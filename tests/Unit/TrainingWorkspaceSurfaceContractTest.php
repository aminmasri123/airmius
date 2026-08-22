<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TrainingWorkspaceSurfaceContractTest extends TestCase
{
    public function test_workspace_copy_is_complete_for_every_supported_locale(): void
    {
        $catalog = json_decode($this->source('resources/js/Pages/Auth/Dashboard/Training/trainingWorkspaceCopy.json'), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['de', 'en', 'fr', 'ar'], array_keys($catalog));
        $germanKeys = array_keys($this->flatten($catalog['de']));

        foreach (['en', 'fr', 'ar'] as $locale) {
            $translations = $this->flatten($catalog[$locale]);

            self::assertSame($germanKeys, array_keys($translations), "Training workspace key mismatch for {$locale}.");
            self::assertNotContains('', array_map('trim', $translations), "Empty training workspace translation for {$locale}.");
        }

        self::assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', implode(' ', $this->flatten($catalog['ar'])));
        self::assertDoesNotMatchRegularExpression('/(?:Ã|Â|Ø|Ù)/u', implode(' ', $this->flatten($catalog['ar'])));
    }

    public function test_training_surface_uses_semantic_copy_and_accessible_controls(): void
    {
        $page = $this->source('resources/js/Pages/Auth/Dashboard/Training/Index.vue');
        $workspace = $this->source('resources/js/composables/useTrainingWorkspace.js');

        self::assertStringContainsString("import trainingWorkspaceCopy from './trainingWorkspaceCopy.json'", $page);
        self::assertStringNotContainsString("tx('auto.", $page);
        self::assertStringContainsString(':aria-selected="activeTrainingSection === section.key', $page);
        self::assertStringContainsString(':aria-pressed="activeSport === sport.key"', $page);
        self::assertStringContainsString('role="progressbar"', $page);
        self::assertStringContainsString("wc('week.move_previous'", $page);
        self::assertStringContainsString("wc('week.move_next'", $page);
        self::assertStringContainsString('movePlanItemByDays', $workspace);
        self::assertStringContainsString('if (!item.plan?.can_write) return', $workspace);
    }

    public function test_ai_plan_refresh_requests_only_the_changed_training_props(): void
    {
        $workspace = $this->source('resources/js/composables/useTrainingWorkspace.js');
        $controller = $this->source('app/Http/Controllers/TrainingController.php');

        self::assertStringContainsString("only: ['plans', 'aiCapabilities']", $workspace);
        self::assertStringContainsString("'plans' => fn () => TrainingPlan::query()", $controller);
        self::assertStringContainsString("'activities' => fn () => \$user", $controller);
        self::assertStringContainsString("'sportRoutes' => fn () =>", $controller);
        self::assertStringContainsString('$resolveManageableAthletes', $controller);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flattened += $this->flatten($value, $path);

                continue;
            }

            $flattened[$path] = (string) $value;
        }

        return $flattened;
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source);

        return $source;
    }
}
