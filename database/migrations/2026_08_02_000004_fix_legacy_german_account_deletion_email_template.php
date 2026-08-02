<?php

use App\Models\Setting;
use App\Support\EmailTemplate;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $stored = Setting::valueFor(EmailTemplate::SETTINGS_KEY);

        if (! is_string($stored) || trim($stored) === '') {
            return;
        }

        $templates = json_decode($stored, true);

        if (! is_array($templates) || ! isset($templates['account_deletion_code']) || ! is_array($templates['account_deletion_code'])) {
            return;
        }

        $defaults = EmailTemplate::defaultsForValidation()['account_deletion_code'];
        $current = $templates['account_deletion_code'];

        $legacyText = implode("\n", [
            (string) ($current['subject'] ?? ''),
            (string) ($current['greeting'] ?? ''),
            (string) ($current['body'] ?? ''),
        ]);

        if (! preg_match('/bestaet|loesch|guelt|identitaet/i', $legacyText)) {
            return;
        }

        $templates['account_deletion_code'] = array_merge($current, [
            'subject' => $defaults['subject'],
            'greeting' => $defaults['greeting'],
            'body' => $defaults['body'],
            'action_label' => $defaults['action_label'],
        ]);

        Setting::setValue(
            EmailTemplate::SETTINGS_KEY,
            json_encode($templates, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }

    public function down(): void
    {
        // The migration only repairs legacy presentation text.
    }
};
