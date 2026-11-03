<?php

namespace App\Services\Training;

use RuntimeException;

class TrainingInstructionService
{
    /** Validate the provider output before converting it to editable instructions. */
    public function description(array $item): string
    {
        $steps = $item['steps'] ?? [];
        $phases = [];
        $lines = [];
        $minutes = 0;
        if (! is_array($steps) || count($steps) < 3 || count($steps) > 20) {
            throw new RuntimeException('Die KI hat keine vollständige Übungsanleitung geliefert.');
        }
        foreach ($steps as $step) {
            if (! is_array($step)) {
                throw new RuntimeException('Ungültige Übungsanleitung.');
            }
            foreach (['phase', 'exercise', 'prescription', 'instruction', 'rest', 'intensity'] as $field) {
                if (! is_string($step[$field] ?? null) || trim($step[$field]) === '') {
                    throw new RuntimeException('In der Übungsanleitung fehlen Umfang, Ausführung, Intensität oder Pause.');
                }
            }
            if (! in_array($step['phase'], ['warmup', 'main', 'cooldown'], true)
                || ! is_numeric($step['minutes'] ?? null) || $step['minutes'] <= 0
                || ! preg_match('/\d/u', $step['prescription'])) {
                throw new RuntimeException('In der Übungsanleitung fehlen konkrete Zeit- oder Mengenangaben.');
            }
            if (preg_match('/^(lauf[- ]?abc|warm[- ]?up|cool[- ]?down|aufwärmen|dehnen|mobilität)$/iu', trim($step['exercise']))) {
                throw new RuntimeException('Die KI muss konkrete Übungen statt Sammelbegriffen nennen.');
            }
            $phases[] = $step['phase'];
            $minutes += (float) $step['minutes'];
            $label = ['warmup' => 'Aufwärmen', 'main' => 'Hauptteil', 'cooldown' => 'Abschluss'][$step['phase']];
            $lines[] = $label.': '.$step['exercise'].' — '.$step['prescription']
                .' ('.$step['minutes'].' Min. inkl. Pausen)'."\n"
                .$step['instruction'].' Intensität: '.$step['intensity'].'. Pause: '.$step['rest'];
        }
        if (array_diff(['warmup', 'main', 'cooldown'], $phases)
            || abs($minutes - (float) ($item['duration_minutes'] ?? 0)) > 2
            || ! is_string($item['adaptation'] ?? null) || trim($item['adaptation']) === '') {
            throw new RuntimeException('Aufbau, Gesamtdauer oder Anpassungshinweise der Einheit sind unvollständig.');
        }
        $description = implode("\n\n", $lines)."\n\nAnpassung: ".$item['adaptation'];
        if (mb_strlen($description) > 3000) {
            throw new RuntimeException('Die Übungsanleitung überschreitet die speicherbare Länge.');
        }

        return $description;
    }
}
