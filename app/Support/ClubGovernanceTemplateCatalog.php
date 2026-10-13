<?php

namespace App\Support;

final class ClubGovernanceTemplateCatalog
{
    public static function all(): array
    {
        return [
            self::template('board', 'board', 'Vorstand', 'Leitung und Vertretung des Vereins.', [
                self::position('Vorsitz', true),
                self::position('Stellvertretung'),
                self::position('Kassenwart'),
                self::position('Schriftführung'),
            ]),
            self::template('youth_committee', 'committee', 'Jugendausschuss', 'Koordiniert Jugendangebote und vertritt die Interessen junger Mitglieder.', [
                self::position('Jugendleitung', true),
                self::position('Trainervertretung'),
                self::position('Jugendsprecher'),
            ]),
            self::template('finance_committee', 'committee', 'Finanzausschuss', 'Bereitet Finanzthemen, Budgets und wirtschaftliche Entscheidungen vor.', [
                self::position('Leitung Finanzen', true),
                self::position('Kassenwart'),
                self::position('Vorstandsvertretung'),
            ]),
            self::template('procurement_committee', 'committee', 'Beschaffungsausschuss', 'Plant und bewertet Anschaffungen wie Trikots, Bälle oder Geräte.', [
                self::position('Leitung', true),
                self::position('Bedarfserfassung'),
                self::position('Budgetfreigabe'),
            ]),
            self::template('events_committee', 'committee', 'Veranstaltungsausschuss', 'Organisiert Turniere, Vereinsfeste und Ausflüge.', [
                self::position('Leitung', true),
                self::position('Helferkoordination'),
                self::position('Logistik'),
                self::position('Kommunikation'),
            ]),
            self::template('disciplinary_committee', 'committee', 'Disziplinarausschuss', 'Behandelt Regelverstöße und Beschwerden in einem geschützten Rahmen.', [
                self::position('Vorsitz', true),
                self::position('Beisitz', false, true),
                self::position('Protokollführung'),
            ]),
            self::template('cash_audit', 'committee', 'Kassenprüfung', 'Kontrolliert Einnahmen, Ausgaben und Belege unabhängig von der laufenden Kassenführung.', [
                self::position('Erster Kassenprüfer', true),
                self::position('Zweiter Kassenprüfer', true),
            ]),
            self::template('coaching_team', 'working_group', 'Trainerteam', 'Bündelt Trainer, die gemeinsam Mannschaften oder Trainingsgruppen betreuen.', [
                self::position('Cheftrainer'),
                self::position('Trainer', false, true),
                self::position('Co-Trainer', false, true),
                self::position('Teammanagement'),
            ]),
        ];
    }

    public static function keys(): array
    {
        return array_column(self::all(), 'key');
    }

    private static function template(string $key, string $type, string $name, string $description, array $positions): array
    {
        return compact('key', 'type', 'name', 'description', 'positions');
    }

    private static function position(string $title, bool $required = false, bool $allowsMultiple = false): array
    {
        return [
            'title' => $title,
            'responsibilities' => null,
            'is_required' => $required,
            'allows_multiple' => $allowsMultiple,
            'is_public' => false,
        ];
    }
}
