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
            'account_welcome' => [
                'label' => 'Konto: Willkommen',
                'description' => 'Wird nach erfolgreicher Registrierung gesendet.',
                'variables' => ['name'],
                'template' => [
                    'subject' => 'Willkommen bei Airmius',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "willkommen bei Airmius. Dein Konto wurde erfolgreich erstellt.\nDu kannst dich jetzt anmelden, dein Profil vervollstaendigen und Airmius fuer Training, Vereine, Teams, Events und deinen Sportalltag nutzen.\nWenn du dieses Konto nicht selbst erstellt hast, kontaktiere bitte den Airmius-Support.",
                    'action_label' => 'Airmius öffnen',
                ],
            ],
            'account_created_with_credentials' => [
                'label' => 'Konto: Zugangsdaten bei Erstellung',
                'description' => 'Wird gesendet, wenn ein Admin ein Konto mit generiertem Passwort anlegt.',
                'variables' => ['name', 'email', 'temporary_password'],
                'template' => [
                    'subject' => 'Dein Airmius-Konto wurde erstellt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "für dich wurde ein Airmius-Konto erstellt.\nE-Mail: {{ email }}\nTemporäres Passwort: {{ temporary_password }}\nBitte melde dich an und ändere dein Passwort direkt nach dem ersten Login.\nWenn du diese Kontoerstellung nicht erwartest, kontaktiere bitte den Airmius-Support.",
                    'action_label' => 'Bei Airmius anmelden',
                ],
            ],
            'account_deletion_code' => [
                'label' => 'Kontolöschung: Code',
                'description' => 'Wird gesendet, bevor ein Konto gelöscht werden kann.',
                'variables' => ['code'],
                'template' => [
                    'subject' => 'Bestätigungscode zur Kontolöschung',
                    'greeting' => 'Hallo,',
                    'body' => "du hast angefordert, dein Airmius-Konto zu löschen.\nDein Bestätigungscode lautet: {{ code }}\nDer Code ist 15 Minuten gültig.\nWenn du dein Konto nicht löschen möchtest, kannst du diese E-Mail ignorieren.",
                    'action_label' => '',
                ],
            ],
            'account_deletion_completed' => [
                'label' => 'Kontolöschung: Bestätigung',
                'description' => 'Wird nach erfolgreicher Kontolöschung gesendet.',
                'variables' => ['name'],
                'template' => [
                    'subject' => 'Dein Airmius-Konto wurde gelöscht',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Airmius-Konto wurde erfolgreich gelöscht.\nDiese E-Mail bestätigt, dass die Kontolöschung abgeschlossen wurde.\nFalls du diese Löschung nicht selbst ausgelöst hast, kontaktiere bitte den Airmius-Support.",
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
                    'body' => "du hast einen Zugangscode für den Elternbereich von Airmius angefordert.\nDein Code lautet: {{ code }}\nDer Code ist 15 Minuten gültig.\nWenn du diesen Code nicht angefordert hast, kannst du diese E-Mail ignorieren.",
                    'action_label' => '',
                ],
            ],
            'guardian_consent_requested' => [
                'label' => 'Elternzustimmung',
                'description' => 'Bitte um Zustimmung für ein minderjähriges Konto.',
                'variables' => ['minor_name'],
                'template' => [
                    'subject' => 'Zustimmung zur Registrierung bei Airmius',
                    'greeting' => 'Hallo,',
                    'body' => "{{ minor_name }} hat sich bei Airmius registriert und ist unter 16 Jahre alt.\nBitte prüfen Sie die Anfrage. Sie können der Registrierung zustimmen oder sie ablehnen.\nWenn Sie diese Anfrage nicht erwartet haben, können Sie diese E-Mail ignorieren.",
                    'action_label' => 'Zustimmen oder ablehnen',
                ],
            ],
            'password_reset' => [
                'label' => 'Passwort zurücksetzen',
                'description' => 'E-Mail mit Link zum Zurücksetzen des Passworts.',
                'variables' => ['reset_url', 'expires_minutes'],
                'template' => [
                    'subject' => 'Passwort zurücksetzen',
                    'greeting' => 'Hallo!',
                    'body' => "Du erhältst diese E-Mail, weil wir eine Anfrage zum Zurücksetzen des Passworts für dein Konto erhalten haben.\nDieser Link läuft in {{ expires_minutes }} Minuten ab.",
                    'action_label' => 'Passwort zurücksetzen',
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
                    'body' => "in deinem Airmius-Konto gab es gerade eine erfolgreiche Anmeldung.\nZeitpunkt: {{ logged_in_at }}\nIP-Adresse: {{ ip_address }}\nGerät/Browser: {{ user_agent }}\nWenn du das warst, musst du nichts weiter tun.\nWenn du das nicht warst, ändere bitte sofort dein Passwort und informiere den Airmius-Support.",
                    'action_label' => '',
                ],
            ],
            'login_two_factor_code' => [
                'label' => 'Sicherheit: Anmeldecode',
                'description' => 'Einmalcode zur Bestätigung einer Anmeldung.',
                'variables' => ['name', 'code', 'expires_minutes'],
                'template' => [
                    'subject' => 'Dein Sicherheitscode für Airmius',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Sicherheitscode für die Anmeldung lautet: {{ code }}\nDer Code ist {{ expires_minutes }} Minuten gültig und kann nur einmal verwendet werden.\nWenn du diese Anmeldung nicht gestartet hast, ändere bitte dein Passwort.",
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
                    'body' => "für dein Airmius-Konto wurden mehrere falsche Login-Versuche erkannt.\nDer Login wurde vorübergehend blockiert, um dein Konto zu schützen.\nZeitpunkt: {{ locked_at }}\nIP-Adresse: {{ ip_address }}\nGerät/Browser: {{ user_agent }}\nWenn du das warst, warte bitte kurz und versuche es danach erneut.\nWenn du das nicht warst, ändere bitte dein Passwort und prüfe deine Kontosicherheit.",
                    'action_label' => '',
                ],
            ],
            'account_suspended' => [
                'label' => 'Sicherheit: Konto gesperrt',
                'description' => 'Hinweis, wenn ein Konto automatisch oder manuell gesperrt wurde.',
                'variables' => ['name', 'reason', 'suspended_until'],
                'template' => [
                    'subject' => 'Dein Airmius-Konto wurde vorübergehend gesperrt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Airmius-Konto wurde vorübergehend gesperrt.\nGrund: {{ reason }}\nGesperrt bis: {{ suspended_until }}\nWenn du glaubst, dass diese Sperre falsch ist, kannst du den Support kontaktieren und um Prüfung bitten.",
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
                    'body' => "dein Airmius Konto wurde seit längerer Zeit nicht genutzt.\nAus Datenschutzgründen prüfen wir inaktive Konten regelmäßig.\nWenn du Airmius weiter nutzen möchtest, melde dich einfach wieder an. Dadurch bleibt dein Konto aktiv.",
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
                    'body' => "dein Airmius Konto ist weiterhin inaktiv.\nWenn du dich wieder anmeldest, wird die geplante Datenschutzprüfung zurückgesetzt.\nOhne Reaktion kann dein Konto später deaktiviert und anonymisiert werden.",
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
                    'body' => "dein Airmius Konto ist seit längerer Zeit inaktiv.\nWir haben dein Konto deshalb zur Datenschutz-Anonymisierung vorgemerkt.\nGeplantes Datum: {{ scheduled_date }}\nWenn du dich vor diesem Datum wieder anmeldest, bleibt dein Konto aktiv.",
                    'action_label' => 'Konto aktiv halten',
                ],
            ],
            'subscription_renewed' => [
                'label' => 'Abo verlaengert',
                'description' => 'Bestätigung nach Abo-Verlaengerung.',
                'variables' => ['name', 'plan_name', 'end_date'],
                'template' => [
                    'subject' => 'Airmius Abo wurde verlaengert',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "dein Airmius Abo wurde verlaengert.\nPlan: {{ plan_name }}\nNeue Laufzeit bis: {{ end_date }}\nDanke, dass du Airmius nutzt.",
                    'action_label' => 'Pläne ansehen',
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
                    'body' => "{{ ending_message }}\nPlan: {{ plan_name }}\nEnddatum: {{ end_date }}\nWenn du Airmius weiter nutzen möchtest, kannst du rechtzeitig einen passenden Plan wählen.",
                    'action_label' => 'Pläne ansehen',
                ],
            ],
            'subscription_cancelled' => [
                'label' => 'Abo gekündigt',
                'description' => 'Bestätigung einer Abo-Kündigung.',
                'variables' => ['name', 'plan_name', 'end_date', 'cancel_message'],
                'template' => [
                    'subject' => 'Airmius Abo-Kündigung bestätigt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "{{ cancel_message }}\nPlan: {{ plan_name }}\nEndet am: {{ end_date }}\nDu kannst später jederzeit wieder einen passenden Plan aktivieren.",
                    'action_label' => 'Pläne ansehen',
                ],
            ],
            'subscription_invoice_awaiting_transfer' => [
                'label' => 'Rechnung wartet auf Überweisung',
                'description' => 'Bankdaten für Abo-Rechnung per Überweisung.',
                'variables' => ['name', 'invoice_number', 'plan_name', 'amount', 'due_date', 'payment_reference', 'bank_account_holder', 'bank_name', 'iban', 'bic'],
                'template' => [
                    'subject' => 'Airmius Rechnung {{ invoice_number }} wartet auf Überweisung',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "deine Airmius Rechnung wurde erstellt und wartet auf Zahlung per Überweisung.\nRechnung: {{ invoice_number }}\nPlan: {{ plan_name }}\nBetrag: {{ amount }}\nFällig bis: {{ due_date }}\nVerwendungszweck: {{ payment_reference }}\nKontoinhaber: {{ bank_account_holder }}\nBank: {{ bank_name }}\nIBAN: {{ iban }}\nBIC: {{ bic }}\nSobald die Zahlung eingegangen ist, bestätigen wir sie in Airmius.",
                    'action_label' => 'Rechnung herunterladen',
                ],
            ],
            'subscription_invoice_paid' => [
                'label' => 'Rechnung bezahlt',
                'description' => 'Bestätigung einer bezahlten Abo-Rechnung.',
                'variables' => ['name', 'invoice_number', 'plan_name', 'amount', 'paid_date', 'payment_method'],
                'template' => [
                    'subject' => 'Zahlung für Airmius Rechnung {{ invoice_number }} bestätigt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "deine Zahlung wurde bestätigt. Dein Airmius Abo ist aktiv.\nRechnung: {{ invoice_number }}\nPlan: {{ plan_name }}\nBetrag: {{ amount }}\nBezahlt am: {{ paid_date }}\nZahlungsart: {{ payment_method }}\nDanke, dass du Airmius nutzt.",
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
                    'body' => "für deine Airmius Rechnung ist noch keine Zahlung verbucht.\nRechnung: {{ invoice_number }}\nPlan: {{ plan_name }}\nBetrag: {{ amount }}\nFällig seit: {{ due_date }}\nVerwendungszweck: {{ payment_reference }}\nFalls du bereits bezahlt hast, kannst du diese Erinnerung ignorieren. Die Zahlung wird nach dem Bankabgleich markiert.",
                    'action_label' => 'Rechnung herunterladen',
                ],
            ],
            'commerce_order_completed' => [
                'label' => 'Marketplace Bestellung bestätigt',
                'description' => 'Bestätigung nach Marketplace-Bestellung.',
                'variables' => ['name', 'order_title', 'amount'],
                'template' => [
                    'subject' => 'Airmius Bestellung bestätigt',
                    'greeting' => 'Hallo {{ name }},',
                    'body' => "deine Bestellung wurde erfolgreich bestätigt.\nBestellung: {{ order_title }}\nBetrag: {{ amount }}\nStatus: bezahlt\nDanke, dass du Airmius nutzt.",
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
                    'body' => "{{ inviter_name }} hat dich im Namen von {{ club_name }} eingeladen und möchte dich mit Airmius verknüpfen.\nMit einem Airmius-Konto kannst du deine Vereinsdaten, Rechnungen, Zahlungshistorie, Teams und Nachrichten besser überblicken.\nWenn du bereits ein Konto mit dieser E-Mail hast, kannst du dich anmelden und die Verknüpfung abschließen. Falls nicht, kannst du dich mit dieser E-Mail registrieren.",
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
                    'subject' => '{{ sender_name }} möchte dich auf Airmius verbinden',
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
