<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;

class EmailTemplate
{
    public const SETTINGS_KEY = 'email_templates';

    public static function forAdmin(): array
    {
        $stored = self::storedTemplates();

        return collect(self::definitions())
            ->map(function (array $definition, string $key) use ($stored) {
                $template = array_merge($definition['template'], $stored[$key] ?? []);

                return [
                    'key' => $key,
                    'label' => $definition['label'],
                    'description' => $definition['description'],
                    'variables' => $definition['variables'],
                    'template' => $template,
                ];
            })
            ->values()
            ->all();
    }

    public static function defaultsForValidation(): array
    {
        return collect(self::definitions())
            ->mapWithKeys(fn (array $definition, string $key) => [$key => $definition['template']])
            ->all();
    }

    public static function save(array $templates): void
    {
        $allowed = array_keys(self::definitions());
        $current = self::storedTemplates();
        $clean = [];

        foreach ($allowed as $key) {
            $template = array_merge(self::definitions()[$key]['template'], $current[$key] ?? [], $templates[$key] ?? []);

            $clean[$key] = [
                'subject' => (string) $template['subject'],
                'greeting' => (string) $template['greeting'],
                'body' => (string) $template['body'],
                'action_label' => (string) ($template['action_label'] ?? ''),
            ];
        }

        Setting::setValue(self::SETTINGS_KEY, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public static function mail(string $key, array $variables = [], ?string $actionUrl = null): MailMessage
    {
        $template = self::content($key, $variables);
        $message = (new MailMessage)
            ->subject($template['subject'])
            ->greeting($template['greeting']);

        foreach (preg_split('/\R+/', (string) $template['body']) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $message->line($line);
            }
        }

        $actionLabel = trim((string) ($template['action_label'] ?? ''));

        if ($actionUrl && $actionLabel !== '') {
            $message->action($actionLabel, $actionUrl);
        }

        return app(TransactionalMail::class)->applyToMessage($message, self::categoryFor($key));
    }

    public static function content(string $key, array $variables = []): array
    {
        $template = self::template($key);

        return [
            'subject' => self::render((string) $template['subject'], $variables),
            'greeting' => self::render((string) $template['greeting'], $variables),
            'body' => self::render((string) $template['body'], $variables),
            'action_label' => self::render((string) ($template['action_label'] ?? ''), $variables),
        ];
    }

    public static function definitions(): array
    {
        return [
            'account_deletion_code' => [
                'label' => 'Kontoloeschung: Code',
                'description' => 'Wird gesendet, bevor ein Konto geloescht werden kann.',
                'variables' => ['code'],
                'template' => [
                    'subject' => 'Bestaetigungscode zur Kontoloeschung',
                    'greeting' => 'Hallo,',
                    'body' => "du hast angefordert, dein Airmius-Konto zu loeschen.\nDein Bestaetigungscode lautet: {{ code }}\nDer Code ist 15 Minuten gueltig.\nWenn du dein Konto nicht loeschen moechtest, kannst du diese E-Mail ignorieren.",
                    'action_label' => '',
                ],
            ],
            'account_deletion_completed' => [
                'label' => 'Kontoloeschung: Bestaetigung',
                'description' => 'Wird nach erfolgreicher Kontoloeschung gesendet.',
                'variables' => ['name'],
                'template' => [
                    'subject' => 'Dein Airmius-Konto wurde geloescht',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Airmius-Konto wurde erfolgreich geloescht.\nDiese E-Mail bestaetigt, dass die Kontoloeschung abgeschlossen wurde.\nFalls du diese Loeschung nicht selbst ausgeloest hast, kontaktiere bitte den Airmius-Support.",
                    'action_label' => '',
                ],
            ],
            'guardian_access_code' => [
                'label' => 'Elternbereich: Zugangscode',
                'description' => 'Code für den Eltern-Zugang.',
                'variables' => ['code'],
                'template' => [
                    'subject' => 'Dein Eltern-Zugangscode für Airmius',
                    'greeting' => 'Hallo,',
                    'body' => "du hast einen Zugangscode für den Elternbereich von Airmius angefordert.\nDein Code lautet: {{ code }}\nDer Code ist 15 Minuten gueltig.\nWenn du diesen Code nicht angefordert hast, kannst du diese E-Mail ignorieren.",
                    'action_label' => '',
                ],
            ],
            'guardian_consent_requested' => [
                'label' => 'Elternzustimmung',
                'description' => 'Bitte um Zustimmung für ein minderjaehriges Konto.',
                'variables' => ['minor_name'],
                'template' => [
                    'subject' => 'Zustimmung zur Registrierung bei Airmius',
                    'greeting' => 'Hallo,',
                    'body' => "{{ minor_name }} hat sich bei Airmius registriert und ist unter 16 Jahre alt.\nBitte pruefen Sie die Anfrage. Sie koennen der Registrierung zustimmen oder sie ablehnen.\nWenn Sie diese Anfrage nicht erwartet haben, koennen Sie diese E-Mail ignorieren.",
                    'action_label' => 'Zustimmen oder ablehnen',
                ],
            ],
            'password_reset' => [
                'label' => 'Passwort zuruecksetzen',
                'description' => 'E-Mail mit Link zum Zuruecksetzen des Passworts.',
                'variables' => ['reset_url', 'expires_minutes'],
                'template' => [
                    'subject' => 'Passwort zuruecksetzen',
                    'greeting' => 'Hallo!',
                    'body' => "Du erhaeltst diese E-Mail, weil wir eine Anfrage zum Zuruecksetzen des Passworts für dein Konto erhalten haben.\nDieser Link laeuft in {{ expires_minutes }} Minuten ab.",
                    'action_label' => 'Passwort zuruecksetzen',
                ],
            ],
            'contact_form_admin' => [
                'label' => 'Kontaktformular an Admin',
                'description' => 'E-Mail, die bei einer neuen Kontaktanfrage an Airmius gesendet wird.',
                'variables' => ['name', 'email', 'message'],
                'template' => [
                    'subject' => 'Neue Kontaktanfrage von Kontaktformular - Anfrage von {{ name }}',
                    'greeting' => 'Neue Kontaktanfrage',
                    'body' => "Name: {{ name }}\nE-Mail: {{ email }}\n\n{{ message }}",
                    'action_label' => '',
                ],
            ],
            'login_successful' => [
                'label' => 'Sicherheit: Neue Anmeldung',
                'description' => 'Hinweis bei erfolgreicher Anmeldung.',
                'variables' => ['logged_in_at', 'ip_address', 'user_agent'],
                'template' => [
                    'subject' => 'Neue Anmeldung bei Airmius',
                    'greeting' => 'Hallo,',
                    'body' => "in deinem Airmius-Konto gab es gerade eine erfolgreiche Anmeldung.\nZeitpunkt: {{ logged_in_at }}\nIP-Adresse: {{ ip_address }}\nGeraet/Browser: {{ user_agent }}\nWenn du das warst, musst du nichts weiter tun.\nWenn du das nicht warst, aendere bitte sofort dein Passwort und informiere den Airmius-Support.",
                    'action_label' => '',
                ],
            ],
            'login_lockout' => [
                'label' => 'Sicherheit: Login blockiert',
                'description' => 'Hinweis bei mehreren fehlgeschlagenen Login-Versuchen.',
                'variables' => ['locked_at', 'ip_address', 'user_agent'],
                'template' => [
                    'subject' => 'Mehrere fehlgeschlagene Anmeldeversuche bei Airmius',
                    'greeting' => 'Hallo,',
                    'body' => "für dein Airmius-Konto wurden mehrere falsche Login-Versuche erkannt.\nDer Login wurde voruebergehend blockiert, um dein Konto zu schuetzen.\nZeitpunkt: {{ locked_at }}\nIP-Adresse: {{ ip_address }}\nGeraet/Browser: {{ user_agent }}\nWenn du das warst, warte bitte kurz und versuche es danach erneut.\nWenn du das nicht warst, aendere bitte dein Passwort und pruefe deine Kontosicherheit.",
                    'action_label' => '',
                ],
            ],
            'account_suspended' => [
                'label' => 'Sicherheit: Konto gesperrt',
                'description' => 'Hinweis, wenn ein Konto automatisch oder manuell gesperrt wurde.',
                'variables' => ['name', 'reason', 'suspended_until'],
                'template' => [
                    'subject' => 'Dein Airmius-Konto wurde voruebergehend gesperrt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Airmius-Konto wurde voruebergehend gesperrt.\nGrund: {{ reason }}\nGesperrt bis: {{ suspended_until }}\nWenn du glaubst, dass diese Sperre falsch ist, kannst du den Support kontaktieren und um Pruefung bitten.",
                    'action_label' => 'Support kontaktieren',
                ],
            ],
            'inactive_account_first' => [
                'label' => 'Inaktivitaet: Erste Erinnerung',
                'description' => 'Erste Datenschutz-Erinnerung bei Inaktivitaet.',
                'variables' => ['name'],
                'template' => [
                    'subject' => 'Dein Airmius Konto war lange nicht aktiv',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Airmius Konto wurde seit laengerer Zeit nicht genutzt.\nAus Datenschutzgruenden pruefen wir inaktive Konten regelmässig .\nWenn du Airmius weiter nutzen moechtest, melde dich einfach wieder an. Dadurch bleibt dein Konto aktiv.",
                    'action_label' => 'Bei Airmius anmelden',
                ],
            ],
            'inactive_account_second' => [
                'label' => 'Inaktivitaet: Zweite Erinnerung',
                'description' => 'Zweite Erinnerung bei Inaktivitaet.',
                'variables' => ['name'],
                'template' => [
                    'subject' => 'Erinnerung: Dein Airmius Konto ist weiterhin inaktiv',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Airmius Konto ist weiterhin inaktiv.\nWenn du dich wieder anmeldest, wird die geplante Datenschutzpruefung zurueckgesetzt.\nOhne Reaktion kann dein Konto spaeter deaktiviert und anonymisiert werden.",
                    'action_label' => 'Konto aktiv halten',
                ],
            ],
            'inactive_account_scheduled' => [
                'label' => 'Inaktivitaet: Anonymisierung',
                'description' => 'Hinweis auf geplante Anonymisierung.',
                'variables' => ['name', 'scheduled_date'],
                'template' => [
                    'subject' => 'Airmius Konto wird zur Anonymisierung vorgemerkt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Airmius Konto ist seit laengerer Zeit inaktiv.\nWir haben dein Konto deshalb zur Datenschutz-Anonymisierung vorgemerkt.\nGeplantes Datum: {{ scheduled_date }}\nWenn du dich vor diesem Datum wieder anmeldest, bleibt dein Konto aktiv.",
                    'action_label' => 'Konto aktiv halten',
                ],
            ],
            'subscription_renewed' => [
                'label' => 'Abo verlaengert',
                'description' => 'Bestaetigung nach Abo-Verlaengerung.',
                'variables' => ['name', 'plan_name', 'end_date'],
                'template' => [
                    'subject' => 'Airmius Abo wurde verlaengert',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Airmius Abo wurde verlaengert.\nPlan: {{ plan_name }}\nNeue Laufzeit bis: {{ end_date }}\nDanke, dass du Airmius nutzt.",
                    'action_label' => 'Plaene ansehen',
                ],
            ],
            'subscription_payment_issue' => [
                'label' => 'Abo Zahlung offen',
                'description' => 'Zahlungsproblem oder abgelaufene Testphase.',
                'variables' => ['name', 'plan_name'],
                'template' => [
                    'subject' => 'Zahlung für dein Airmius Abo ist offen',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "für dein Airmius Abo ist eine Zahlung offen oder deine Testphase ist abgelaufen.\nPlan: {{ plan_name }}\nStatus: Zahlung offen\nBitte aktualisiere die Zahlung, damit alle gebuchten Funktionen aktiv bleiben.",
                    'action_label' => 'Plan verlaengern',
                ],
            ],
            'subscription_ending_soon' => [
                'label' => 'Abo endet bald',
                'description' => 'Hinweis bei bald endendem Abo oder Testphase.',
                'variables' => ['name', 'plan_name', 'end_date', 'ending_message'],
                'template' => [
                    'subject' => 'Dein Airmius Abo endet bald',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "{{ ending_message }}\nPlan: {{ plan_name }}\nEnddatum: {{ end_date }}\nWenn du Airmius weiter nutzen moechtest, kannst du rechtzeitig einen passenden Plan waehlen.",
                    'action_label' => 'Plaene ansehen',
                ],
            ],
            'subscription_cancelled' => [
                'label' => 'Abo gekuendigt',
                'description' => 'Bestaetigung einer Abo-Kuendigung.',
                'variables' => ['name', 'plan_name', 'end_date', 'cancel_message'],
                'template' => [
                    'subject' => 'Airmius Abo-Kuendigung bestaetigt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "{{ cancel_message }}\nPlan: {{ plan_name }}\nEndet am: {{ end_date }}\nDu kannst spaeter jederzeit wieder einen passenden Plan aktivieren.",
                    'action_label' => 'Plaene ansehen',
                ],
            ],
            'subscription_invoice_awaiting_transfer' => [
                'label' => 'Rechnung wartet auf Ueberweisung',
                'description' => 'Bankdaten für Abo-Rechnung per Ueberweisung.',
                'variables' => ['name', 'invoice_number', 'plan_name', 'amount', 'due_date', 'payment_reference', 'bank_account_holder', 'bank_name', 'iban', 'bic'],
                'template' => [
                    'subject' => 'Airmius Rechnung {{ invoice_number }} wartet auf Ueberweisung',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "deine Airmius Rechnung wurde erstellt und wartet auf Zahlung per Ueberweisung.\nRechnung: {{ invoice_number }}\nPlan: {{ plan_name }}\nBetrag: {{ amount }}\nFaellig bis: {{ due_date }}\nVerwendungszweck: {{ payment_reference }}\nKontoinhaber: {{ bank_account_holder }}\nBank: {{ bank_name }}\nIBAN: {{ iban }}\nBIC: {{ bic }}\nSobald die Zahlung eingegangen ist, bestaetigen wir sie in Airmius.",
                    'action_label' => 'Rechnung herunterladen',
                ],
            ],
            'subscription_invoice_paid' => [
                'label' => 'Rechnung bezahlt',
                'description' => 'Bestaetigung einer bezahlten Abo-Rechnung.',
                'variables' => ['name', 'invoice_number', 'plan_name', 'amount', 'paid_date', 'payment_method'],
                'template' => [
                    'subject' => 'Zahlung für Airmius Rechnung {{ invoice_number }} bestaetigt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "deine Zahlung wurde bestaetigt. Dein Airmius Abo ist aktiv.\nRechnung: {{ invoice_number }}\nPlan: {{ plan_name }}\nBetrag: {{ amount }}\nBezahlt am: {{ paid_date }}\nZahlungsart: {{ payment_method }}\nDanke, dass du Airmius nutzt.",
                    'action_label' => 'Rechnung herunterladen',
                ],
            ],
            'subscription_invoice_reminder' => [
                'label' => 'Rechnungserinnerung',
                'description' => 'Erinnerung für offene Abo-Rechnung.',
                'variables' => ['name', 'invoice_number', 'plan_name', 'amount', 'due_date', 'payment_reference'],
                'template' => [
                    'subject' => 'Erinnerung: Airmius Rechnung {{ invoice_number }} ist offen',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "für deine Airmius Rechnung ist noch keine Zahlung verbucht.\nRechnung: {{ invoice_number }}\nPlan: {{ plan_name }}\nBetrag: {{ amount }}\nFaellig seit: {{ due_date }}\nVerwendungszweck: {{ payment_reference }}\nFalls du bereits bezahlt hast, kannst du diese Erinnerung ignorieren. Die Zahlung wird nach dem Bankabgleich markiert.",
                    'action_label' => 'Rechnung herunterladen',
                ],
            ],
            'commerce_order_completed' => [
                'label' => 'Marketplace Bestellung bestaetigt',
                'description' => 'Bestaetigung nach Marketplace-Bestellung.',
                'variables' => ['name', 'order_title', 'amount'],
                'template' => [
                    'subject' => 'Airmius Bestellung bestaetigt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "deine Bestellung wurde erfolgreich bestaetigt.\nBestellung: {{ order_title }}\nBetrag: {{ amount }}\nStatus: bezahlt\nDanke, dass du Airmius nutzt.",
                    'action_label' => 'Marketplace ansehen',
                ],
            ],
            'external_club_membership_invitation' => [
                'label' => 'Vereinsmitglied Einladung',
                'description' => 'Einladung für extern hinterlegte Vereinsmitglieder.',
                'variables' => ['name', 'club_name', 'inviter_name'],
                'template' => [
                    'subject' => 'Einladung zu {{ club_name }} auf Airmius',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "{{ inviter_name }} hat dich im Namen von {{ club_name }} eingeladen und moechte dich mit Airmius verknuepfen.\nMit einem Airmius-Konto kannst du deine Vereinsdaten, Rechnungen, Zahlungshistorie, Teams und Nachrichten besser ueberblicken.\nWenn du bereits ein Konto mit dieser E-Mail hast, kannst du dich anmelden und die Verknuepfung abschliessen. Falls nicht, kannst du dich mit dieser E-Mail registrieren.",
                    'action_label' => 'Einladung ansehen',
                ],
            ],
            'external_team_invitation' => [
                'label' => 'Team Einladung',
                'description' => 'Einladung zu einem Team per E-Mail.',
                'variables' => ['team_name', 'inviter_name'],
                'template' => [
                    'subject' => 'Einladung zu {{ team_name }}',
                    'greeting' => 'Hallo,',
                    'body' => "{{ inviter_name }} hat dich zu {{ team_name }} auf Airmius eingeladen.\nNimm die Einladung an, um dem Team beizutreten.",
                    'action_label' => 'Einladung annehmen',
                ],
            ],
            'external_friend_invitation' => [
                'label' => 'Freundschafts-Einladung',
                'description' => 'Einladung zu Airmius von einer Person aus der Freunde-Seite.',
                'variables' => ['sender_name'],
                'template' => [
                    'subject' => '{{ sender_name }} moechte dich auf Airmius verbinden',
                    'greeting' => 'Hallo,',
                    'body' => "{{ sender_name }} hat dich auf Airmius als Freund eingeladen.\nWenn du bereits ein Konto mit dieser E-Mail hast, kannst du dich anmelden und die Einladung annehmen. Falls nicht, kannst du dich mit dieser E-Mail registrieren.",
                    'action_label' => 'Einladung annehmen',
                ],
            ],
        ];
    }

    private static function template(string $key): array
    {
        $definition = self::definitions()[$key] ?? null;

        if (! $definition) {
            return [
                'subject' => 'Airmius',
                'greeting' => 'Hallo,',
                'body' => '',
                'action_label' => '',
            ];
        }

        return array_merge($definition['template'], self::storedTemplates()[$key] ?? []);
    }

    private static function storedTemplates(): array
    {
        $raw = Setting::valueFor(self::SETTINGS_KEY, '{}');
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function render(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{ '.$key.' }}', (string) $value, $text);
            $text = str_replace('{{'.$key.'}}', (string) $value, $text);
        }

        return $text;
    }

    private static function categoryFor(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'subscription_') => 'billing',
            str_starts_with($key, 'commerce_') => 'marketplace',
            str_starts_with($key, 'login_'),
            str_starts_with($key, 'account_'),
            str_starts_with($key, 'guardian_'),
            $key === 'password_reset' => 'security',
            str_starts_with($key, 'external_') => 'support',
            default => 'system',
        };
    }
}
