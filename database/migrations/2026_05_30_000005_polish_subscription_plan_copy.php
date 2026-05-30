<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $plans = [
            'free' => [
                'description' => 'Kostenloser Einstieg für Sportler, Teams und kleine Vereine mit klarer Basis.',
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
                'description' => 'Für kleine Teams und Vereine, die Mitglieder sauber aufnehmen und erste Verwaltung brauchen.',
                'features' => [
                    'Alles aus Free',
                    'Mitglieder-Onboarding',
                    'Excel/CSV-Import',
                    'Basis-Rechnungen',
                    'Mitglieds- und Lizenznummern',
                    'E-Mail-Einladungen',
                    'eigene Übungen Basis',
                ],
            ],
            'club' => [
                'description' => 'Der Arbeitsplan für Vereine mit Trainer-Cockpit, Anwesenheit und echter Organisation.',
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
                'description' => 'Für ambitionierte Vereine mit Saisonplanung, Analyse, SEPA/DATEV und KI-Regelprüfung.',
                'features' => [
                    'Alles aus Club',
                    'Saisonplanung',
                    'Belastungsanalyse',
                    'KI-Regelprüfung',
                    '150 automatische Routenvorschläge pro Monat',
                    'SEPA-Export',
                    'DATEV-Export',
                    'Bankabgleich',
                    'Material- und Medienrechte vorbereitet',
                ],
            ],
            'elite' => [
                'description' => 'Für große Vereine, Leistungszentren und Organisationen mit Standorten, API und Sonderlogik.',
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
                'description' => 'Kostenloses Sportprofil für Training, Teams, Community und Zahlungsverlauf.',
                'features' => [
                    'Sportprofil',
                    'Feed und Community',
                    'Teambeitritt per Anfrage',
                    'Chat Basis',
                    'Training dokumentieren',
                    'Sportkarte, Tracking und Sportplätze',
                    '10 automatische Routenvorschläge pro Monat',
                    '1 GB Speicher',
                ],
            ],
            'sportler-pro' => [
                'description' => 'Optionales Upgrade für KI-Unterstützung, Portfolio, Medien und bessere Auswertung.',
                'features' => [
                    'Alles aus Sportler Free',
                    'KI-Ernährungsbild',
                    'kurze KI-Trainingspläne',
                    '150 automatische Routenvorschläge pro Monat',
                    'Portfolio und Medien',
                    'Profilstatistiken vorbereitet',
                    '5 GB Speicher',
                ],
            ],
            'trainer-free' => [
                'description' => 'Trainer arbeiten kostenlos innerhalb eines Vereins- oder Teamplans mit.',
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
                'description' => 'Für Solo-Trainer, Camps, private Gruppen und KI-gestützte Trainingsplanung.',
                'features' => [
                    'Eigene Trainingsgruppen',
                    'KI-Planung mit Regelprüfung',
                    '150 automatische Routenvorschläge pro Monat',
                    'Übungsbibliothek verwalten',
                    'Kurse und Camps',
                    'Teilnehmerverwaltung',
                    '5 GB Speicher',
                ],
            ],
            'eltern-free' => [
                'description' => 'Kostenloser Sicherheits- und Zustimmungsbereich für Erziehungsberechtigte.',
                'features' => [
                    'Kinderüberblick',
                    'Zustimmungen verwalten',
                    'Widerruf',
                    'Login per E-Mail-Code',
                    'Benachrichtigungen Basis',
                ],
            ],
            'anbieter-marketplace' => [
                'description' => 'Für Produkte, Kurse, Camps und Dienstleistungen rund um Sport.',
                'features' => [
                    'Anbieterprofil',
                    'Produkt- und Kursverkauf',
                    'Standorte und Abholstationen',
                    'Provisionen vorbereitet',
                    'Premium-Platzierungen vorbereitet',
                ],
            ],
            'enterprise-verband' => [
                'description' => 'Individuelle Lösung für Verbände, Kommunen und große Sportnetzwerke.',
                'features' => [
                    'Mandantenfähigkeit vorbereitet',
                    'Datenmigration',
                    'Schnittstellen',
                    'Schulung',
                    'AVV und SLA Paket',
                ],
            ],
        ];

        foreach ($plans as $slug => $values) {
            DB::table('subscription_plans')->where('slug', $slug)->update([
                'description' => $values['description'],
                'features' => json_encode($values['features'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Copy-only migration; older migrations define the functional plan structure.
    }
};
