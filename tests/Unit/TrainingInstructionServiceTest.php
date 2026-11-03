<?php

namespace Tests\Unit;

use App\Services\Training\TrainingInstructionService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class TrainingInstructionServiceTest extends TestCase
{
    private function item(): array
    {
        return ['duration_minutes' => 30, 'adaptation' => 'Den Hauptteil um 5 Minuten verkürzen.', 'steps' => array_map(fn ($phase) => [
            'phase' => $phase, 'exercise' => 'Lockeres Laufen', 'prescription' => '10 Minuten',
            'minutes' => 10, 'instruction' => 'Aufrecht und mit entspannten Schultern laufen.',
            'intensity' => 'Sprechtempo', 'rest' => 'Keine zusätzliche Pause',
        ], ['warmup', 'main', 'cooldown'])];
    }

    public function test_instructions_preserve_exercises_dosage_rest_and_adaptation(): void
    {
        $description = (new TrainingInstructionService)->description($this->item());
        foreach (['Aufwärmen', 'Hauptteil', 'Abschluss', '10 Minuten', 'Schultern', 'Sprechtempo', 'Pause', 'verkürzen'] as $text) {
            $this->assertStringContainsString($text, $description);
        }
    }

    public function test_incomplete_steps_and_inconsistent_durations_are_rejected(): void
    {
        foreach (['rest', 'instruction', 'prescription', 'intensity'] as $field) {
            $item = $this->item();
            unset($item['steps'][1][$field]);
            try {
                (new TrainingInstructionService)->description($item);
                $this->fail('Missing '.$field.' was accepted');
            } catch (RuntimeException $error) {
                $this->assertNotEmpty($error->getMessage());
            }
        }
        $item = $this->item();
        $item['duration_minutes'] = 45;
        $this->expectException(RuntimeException::class);
        (new TrainingInstructionService)->description($item);
    }

    public function test_generic_lauf_abc_is_not_an_exercise_instruction(): void
    {
        $item = $this->item();
        $item['steps'][1]['exercise'] = 'Lauf-ABC';
        $this->expectException(RuntimeException::class);
        (new TrainingInstructionService)->description($item);
    }
}
