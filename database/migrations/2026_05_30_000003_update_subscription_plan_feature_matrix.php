<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $plans = [
            'free' => [
                'description' => 'Kostenloser Einstieg fuer Sportler, Teams und kleine Vereine mit klarer Basis.',
                'member_limit' => 25,
                'team_limit' => 1,
                'storage_gb' => 1,
                'features' => [
                    'Vereinsprofil und Gastseite',
                    '1 Team',
                    'bis 25 Mitglieder',
                    'Basis-Feed und Basis-Chat',
                    'Events und Training Basis',
                    'Sportkarte Basis',
                    'Dateien 1 GB',
                ],
            ],
            'starter' => [
                'description' => 'Fuer kleine Teams und Vereine, die Mitglieder sauber aufnehmen und erste Verwaltung brauchen.',
                'member_limit' => 50,
                'team_limit' => 5,
                'storage_gb' => 2,
                'features' => [
                    'Alles aus Free',
                    'Mitglieder-Onboarding',
                    'Excel/CSV-Import',
                    'Basis-Rechnungen',
                    'Mitglieds- und Lizenznummern',
                    'E-Mail-Einladungen',
                    'eigene Uebungen Basis',
                ],
            ],
            'club' => [
                'description' => 'Der Arbeitsplan fuer Vereine mit Trainer-Cockpit, Anwesenheit und echter Organisation.',
                'member_limit' => 150,
                'team_limit' => null,
                'storage_gb' => 5,
                'features' => [
                    'Alles aus Starter',
                    'Vereins-Cockpit',
                    'Trainer-Cockpit im Verein',
                    'QR-Anwesenheit',
                    'Sportorte und Vereinsstandorte',
                    'Mahnungen',
                    'Sponsorenverwaltung Basis',
                    'unbegrenzte Teams',
                ],
            ],
            'pro' => [
                'description' => 'Fuer ambitionierte Vereine mit Saisonplanung, Analyse, SEPA/DATEV und KI-Regelpruefung.',
                'member_limit' => 500,
                'team_limit' => null,
                'storage_gb' => 20,
                'features' => [
                    'Alles aus Club',
                    'Saisonplanung',
                    'Belastungsanalyse',
                    'KI-Regelpruefung',
                    '150 automatische Routenvorschlaege pro Monat',
                    'SEPA-Export',
                    'DATEV-Export',
                    'Bankabgleich',
                    'Material- und Medienrechte vorbereitet',
                ],
            ],
            'elite' => [
                'description' => 'Fuer grosse Vereine, Leistungszentren und Organisationen mit Standorten, API und Sonderlogik.',
                'member_limit' => null,
                'team_limit' => null,
                'storage_gb' => 100,
                'features' => [
                    'Alles aus Pro',
                    'API-Zugang vorbereitet',
                    'Multi-Standort',
                    'Audit-Logs',
                    'eigene Regeln',
                    'Sponsoren-CRM',
                    '100 GB Speicher',
                ],
            ],
            'sportler-free' => [
                'description' => 'Kostenloses Sportprofil fuer Training, Teams, Community und Zahlungsverlauf.',
                'storage_gb' => 1,
                'features' => [
                    'Sportprofil',
                    'Feed und Community',
                    'Teambeitritt per Anfrage',
                    'Chat Basis',
                    'Training dokumentieren',
                    'Sportkarte, Tracking und Sportplaetze',
                    '10 automatische Routenvorschlaege pro Monat',
                    '1 GB Speicher',
                ],
            ],
            'sportler-pro' => [
                'description' => 'Optionales Upgrade fuer KI-Unterstuetzung, Portfolio, Medien und bessere Auswertung.',
                'storage_gb' => 5,
                'features' => [
                    'Alles aus Sportler Free',
                    'KI-Ernaehrungsbild',
                    'kurze KI-Trainingsplaene',
                    '150 automatische Routenvorschlaege pro Monat',
                    'Portfolio und Medien',
                    'Profilstatistiken vorbereitet',
                    '5 GB Speicher',
                ],
            ],
            'trainer-free' => [
                'description' => 'Trainer arbeiten kostenlos innerhalb eines Vereins- oder Teamplans mit.',
                'storage_gb' => 1,
                'features' => [
                    'Trainer-Cockpit im Vereinsplan',
                    'Training dokumentieren',
                    'Sportkarte und Tracking Basis',
                    'Teamkommunikation',
                    'Feedback Basis',
                    'Dateien im Vereinsspeicher',
                ],
            ],
            'trainer-pro' => [
                'description' => 'Fuer Solo-Trainer, Camps, private Gruppen und KI-gestuetzte Trainingsplanung.',
                'storage_gb' => 5,
                'features' => [
                    'Eigene Trainingsgruppen',
                    'KI-Planung mit Regelpruefung',
                    '150 automatische Routenvorschlaege pro Monat',
                    'Uebungsbibliothek verwalten',
                    'Kurse und Camps',
                    'Teilnehmerverwaltung',
                    '5 GB Speicher',
                ],
            ],
            'eltern-free' => [
                'description' => 'Kostenloser Sicherheits- und Zustimmungsbereich fuer Erziehungsberechtigte.',
                'storage_gb' => 1,
                'features' => [
                    'Kinderueberblick',
                    'Zustimmungen verwalten',
                    'Widerruf',
                    'Login per E-Mail-Code',
                    'Benachrichtigungen Basis',
                ],
            ],
            'sponsor-local' => [
                'description' => 'Regionale Sichtbarkeit bei Vereinen, Teams und Sport-Communities.',
                'storage_gb' => 2,
                'features' => [
                    'Sponsorprofil',
                    'Vereinsplatzierungen',
                    'Kampagnen vorbereitet',
                    'Klick- und View-Reporting vorbereitet',
                    'Rechnungen',
                ],
            ],
            'anbieter-marketplace' => [
                'description' => 'Fuer Produkte, Kurse, Camps und Dienstleistungen rund um Sport.',
                'storage_gb' => 5,
                'features' => [
                    'Anbieterprofil',
                    'Produkt- und Kursverkauf',
                    'Standorte und Abholstationen',
                    'Provisionen vorbereitet',
                    'Premium-Platzierungen vorbereitet',
                ],
            ],
            'enterprise-verband' => [
                'description' => 'Individuelle Loesung fuer Verbaende, Kommunen und grosse Sportnetzwerke.',
                'storage_gb' => 100,
                'features' => [
                    'Mandantenfaehigkeit vorbereitet',
                    'Datenmigration',
                    'Schnittstellen',
                    'Schulung',
                    'AVV und SLA Paket',
                ],
            ],
        ];

        foreach ($plans as $slug => $values) {
            $payload = ['updated_at' => $now];

            foreach (['description', 'member_limit', 'team_limit', 'storage_gb'] as $column) {
                if (array_key_exists($column, $values)) {
                    $payload[$column] = $values[$column];
                }
            }

            $payload['features'] = json_encode($values['features']);

            DB::table('subscription_plans')->where('slug', $slug)->update($payload);
        }
    }

    public function down(): void
    {
        // Plan wording is intentionally not rolled back; older migrations keep the base plans.
    }
};
