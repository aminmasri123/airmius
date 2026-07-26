<?php

namespace App\Support;

use App\Models\User;

class ClubMembershipApplication
{
    public const FIELD_MODES = ['off', 'optional', 'required'];

    public const DEFAULT_FIELD_MODES = [
        'first_name' => 'required',
        'last_name' => 'required',
        'birth_date' => 'required',
        'gender' => 'required',
        'email' => 'required',
        'phone' => 'optional',
        'country' => 'required',
        'street' => 'required',
        'house_number' => 'required',
        'postal_code' => 'required',
        'city' => 'required',
        'state' => 'optional',
        'athlete_license_number' => 'optional',
        'guardian_name' => 'optional',
        'guardian_email' => 'optional',
        'emergency_contact_name' => 'optional',
        'emergency_contact_phone' => 'optional',
        'sepa_iban' => 'optional',
        'sepa_bic' => 'optional',
        'sepa_mandate_consent' => 'optional',
    ];

    public const DEFAULT_PAYMENT_METHODS = ['bank_transfer', 'cash'];

    public const DOCUMENT_TYPES = ['privacy', 'statutes', 'rules', 'fees', 'sepa', 'other'];

    public static function fields(): array
    {
        return [
            ['key' => 'first_name', 'label' => 'Vorname', 'section' => 'Personendaten', 'type' => 'text', 'max' => 120],
            ['key' => 'last_name', 'label' => 'Nachname', 'section' => 'Personendaten', 'type' => 'text', 'max' => 120],
            ['key' => 'birth_date', 'label' => 'Geburtsdatum', 'section' => 'Personendaten', 'type' => 'date'],
            ['key' => 'gender', 'label' => 'Geschlecht', 'section' => 'Personendaten', 'type' => 'select', 'options' => [
                ['value' => 'female', 'label' => 'Weiblich'],
                ['value' => 'male', 'label' => 'Männlich'],
                ['value' => 'diverse', 'label' => 'Divers'],
                ['value' => 'not_specified', 'label' => 'Keine Angabe'],
            ]],
            ['key' => 'nationality', 'label' => 'Staatsangehörigkeit', 'section' => 'Personendaten', 'type' => 'text', 'max' => 120],
            ['key' => 'athlete_license_number', 'label' => 'Lizenznummer', 'section' => 'Sportdaten', 'type' => 'text', 'max' => 120],
            ['key' => 'email', 'label' => 'E-Mail', 'section' => 'Kontaktdaten', 'type' => 'email', 'max' => 255],
            ['key' => 'phone', 'label' => 'Telefon', 'section' => 'Kontaktdaten', 'type' => 'tel', 'max' => 80],
            ['key' => 'country', 'label' => 'Land', 'section' => 'Wohndaten', 'type' => 'text', 'max' => 2],
            ['key' => 'street', 'label' => 'Straße', 'section' => 'Wohndaten', 'type' => 'text', 'max' => 255],
            ['key' => 'house_number', 'label' => 'Hausnummer', 'section' => 'Wohndaten', 'type' => 'text', 'max' => 40],
            ['key' => 'postal_code', 'label' => 'PLZ', 'section' => 'Wohndaten', 'type' => 'text', 'max' => 30],
            ['key' => 'city', 'label' => 'Stadt', 'section' => 'Wohndaten', 'type' => 'text', 'max' => 255],
            ['key' => 'state', 'label' => 'Bundesland / Region', 'section' => 'Wohndaten', 'type' => 'text', 'max' => 255],
            ['key' => 'guardian_name', 'label' => 'Name Erziehungsberechtigte/r', 'section' => 'Erziehungsberechtigte', 'type' => 'text', 'max' => 255],
            ['key' => 'guardian_email', 'label' => 'E-Mail Erziehungsberechtigte/r', 'section' => 'Erziehungsberechtigte', 'type' => 'email', 'max' => 255],
            ['key' => 'guardian_phone', 'label' => 'Telefon Erziehungsberechtigte/r', 'section' => 'Erziehungsberechtigte', 'type' => 'tel', 'max' => 80],
            ['key' => 'emergency_contact_name', 'label' => 'Notfallkontakt Name', 'section' => 'Notfallkontakt', 'type' => 'text', 'max' => 255],
            ['key' => 'emergency_contact_phone', 'label' => 'Notfallkontakt Telefon', 'section' => 'Notfallkontakt', 'type' => 'tel', 'max' => 80],
            ['key' => 'sepa_iban', 'label' => 'IBAN', 'section' => 'Zahlungsdaten', 'type' => 'text', 'max' => 40],
            ['key' => 'sepa_bic', 'label' => 'BIC', 'section' => 'Zahlungsdaten', 'type' => 'text', 'max' => 20],
            ['key' => 'sepa_mandate_consent', 'label' => 'SEPA-Lastschriftmandat bestätigt', 'section' => 'Zahlungsdaten', 'type' => 'checkbox'],
        ];
    }

    public static function paymentMethods(): array
    {
        return [
            ['value' => 'bank_transfer', 'label' => 'Überweisung'],
            ['value' => 'cash', 'label' => 'Barzahlung'],
            ['value' => 'sepa_debit', 'label' => 'SEPA-Lastschrift'],
        ];
    }

    public static function documentTypes(): array
    {
        return [
            ['value' => 'privacy', 'label' => 'Datenschutz'],
            ['value' => 'statutes', 'label' => 'Satzung'],
            ['value' => 'rules', 'label' => 'Vereinsregeln'],
            ['value' => 'fees', 'label' => 'Beitragsordnung'],
            ['value' => 'sepa', 'label' => 'SEPA-Mandat'],
            ['value' => 'other', 'label' => 'Sonstiges'],
        ];
    }

    public static function normalizeDocuments(?array $documents): array
    {
        return collect($documents ?: [])
            ->map(function (array $document) {
                $title = trim((string) ($document['title'] ?? ''));
                $url = trim((string) ($document['url'] ?? ''));

                if ($title === '' && $url === '') {
                    return null;
                }

                $type = $document['type'] ?? 'other';

                return [
                    'id' => (string) ($document['id'] ?? (string) \Illuminate\Support\Str::uuid()),
                    'type' => in_array($type, self::DOCUMENT_TYPES, true) ? $type : 'other',
                    'title' => $title !== '' ? $title : 'Dokument',
                    'url' => $url,
                    'file_id' => $document['file_id'] ?? null,
                    'file_name' => trim((string) ($document['file_name'] ?? '')),
                    'description' => trim((string) ($document['description'] ?? '')),
                    'is_visible' => (bool) ($document['is_visible'] ?? true),
                    'is_required' => (bool) ($document['is_required'] ?? false),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Stable version token for a document as it was presented to the applicant.
     * The token changes when the title, link or attached file changes, while
     * keeping the original document payload readable in the audit trail.
     */
    public static function documentVersion(array $document): string
    {
        return 'sha256:'.hash('sha256', json_encode([
            'id' => (string) ($document['id'] ?? ''),
            'type' => (string) ($document['type'] ?? 'other'),
            'title' => (string) ($document['title'] ?? ''),
            'url' => (string) ($document['url'] ?? ''),
            'file_id' => $document['file_id'] ?? null,
            'file_name' => (string) ($document['file_name'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public static function normalizeFieldModes(?array $settings): array
    {
        $settings = $settings ?: self::DEFAULT_FIELD_MODES;
        $knownKeys = collect(self::fields())->pluck('key')->all();

        return collect($knownKeys)
            ->mapWithKeys(function (string $key) use ($settings) {
                $mode = $settings[$key] ?? 'off';

                return [$key => in_array($mode, self::FIELD_MODES, true) ? $mode : 'off'];
            })
            ->all();
    }

    public static function fieldsForClub(?array $settings): array
    {
        $modes = self::normalizeFieldModes($settings);

        return collect(self::fields())
            ->map(fn (array $field) => $field + [
                'mode' => $field['key'] === 'gender' ? 'required' : ($modes[$field['key']] ?? 'off'),
            ])
            ->values()
            ->all();
    }

    public static function normalizePaymentMethods(?array $methods): array
    {
        $allowed = collect(self::paymentMethods())->pluck('value')->all();
        $methods = array_values(array_intersect($methods ?: self::DEFAULT_PAYMENT_METHODS, $allowed));

        return $methods ?: self::DEFAULT_PAYMENT_METHODS;
    }

    public static function prefillFor(User $user): array
    {
        $nameParts = preg_split('/\s+/', trim((string) $user->name), 2);

        return [
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'birth_date' => $user->birth_date?->toDateString() ?: '',
            'gender' => $user->gender ?: '',
            'email' => $user->email,
            'country' => $user->country ?: 'DE',
            'street' => $user->street ?: '',
            'house_number' => $user->house_number ?: '',
            'postal_code' => $user->postal_code ?: '',
            'city' => $user->city ?: '',
            'state' => $user->state ?: '',
            'athlete_license_number' => $user->athlete_license_number ?: '',
            'guardian_email' => $user->guardian_email ?: '',
        ];
    }
}
