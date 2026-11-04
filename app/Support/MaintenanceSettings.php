<?php
namespace App\Support;
use App\Models\Setting;
class MaintenanceSettings
{
    public static function data(): array
    {
        return [
            'enabled' => Setting::boolFor('maintenance_mode'),
            'title' => Setting::valueFor('maintenance_title', 'Airmius ist gerade im Wartemodus'),
            'message' => Setting::valueFor('maintenance_message', 'Wir verbessern gerade die Plattform. Bitte versuche es in Kürze erneut.'),
            'scenario' => Setting::valueFor('maintenance_scenario', 'maintenance'),
            'logo_url' => Setting::valueFor('maintenance_logo_url', ''),
            'ends_at' => Setting::valueFor('maintenance_ends_at', ''),
            'support_url' => Setting::valueFor('maintenance_support_url', ''),
        ];
    }
}
