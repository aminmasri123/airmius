<?php

namespace App\Support;

use App\Models\MarketplaceProviderLocation;
use App\Models\MarketplaceProviderProfile;
use App\Models\MarketplaceSellerApplication;
use App\Models\User;
use Illuminate\Support\Str;

class MarketplaceSellerReadiness
{
    public static function forApplication(MarketplaceSellerApplication $application): array
    {
        $user = $application->relationLoaded('user')
            ? $application->user
            : $application->user()->first();

        $profile = self::profileForUser($user);
        $locations = $profile
            ? $profile->locations()->orderBy('sort_order')->orderBy('id')->get()
            : collect();

        $rules = self::acceptedRules($application);
        $businessLike = in_array($application->applicant_type, ['business', 'club'], true);

        $checks = [
            self::check('rules_product_truth', self::ruleAccepted($rules, 'product_truth'), 'required', 'Produktdaten, Bilder, Preis und Bestand sind verbindlich korrekt.'),
            self::check('rules_rights', self::ruleAccepted($rules, 'rights'), 'required', 'Bild-, Text- und Angebotsrechte sind bestätigt.'),
            self::check('rules_shipping_returns', self::ruleAccepted($rules, 'shipping_returns'), 'required', 'Versand, Rückgabe und Support-Pflichten sind bestätigt.'),
            self::check('rules_commission', self::ruleAccepted($rules, 'commission'), 'required', 'Provisionen und AuszahlungsPrüfung sind akzeptiert.'),
            self::check('rules_data_privacy', self::ruleAccepted($rules, 'data_privacy'), 'required', 'Kundendaten werden nur für Bestellungen genutzt.'),
            self::check('provider_name', filled($profile?->display_name ?: $application->business_name ?: $user?->name), 'required', 'Öffentlicher Anbietername ist vorhanden.'),
            self::check('support_email', filled($profile?->support_email ?: $user?->email), 'required', 'Support-E-Mail für Rückfragen ist vorhanden.'),
            self::check('legal_country_city', filled($profile?->legal_country) && filled($profile?->legal_city), 'required', 'Land und Ort des Anbieters sind gepflegt.'),
            self::check('legal_name', ! $businessLike || filled($profile?->legal_name ?: $application->business_name), 'required', 'Rechtlicher Name ist für Organisationen/Gewerbe vorhanden.'),
            self::check('public_description', Str::length(trim((string) $profile?->public_description)) >= 20, 'recommended', 'Kurzbeschreibung erklärt Angebot, Service und Spezialisierung.'),
            self::check('public_contact', (bool) ($profile?->show_support_email || $profile?->show_phone), 'recommended', 'Mindestens ein Kontaktkanal ist öffentlich sichtbar.'),
            self::check('fulfillment_location', $locations->contains(fn (MarketplaceProviderLocation $location) => $location->is_public && ($location->pickup_enabled || $location->returns_enabled)), 'recommended', 'Abhol- oder Retourenort ist für lokale Käufer sichtbar.'),
        ];

        $required = collect($checks)->where('severity', 'required');
        $recommended = collect($checks)->where('severity', 'recommended');
        $requiredDone = $required->where('done', true)->count();
        $recommendedDone = $recommended->where('done', true)->count();
        $score = (int) round(($requiredDone / max(1, $required->count())) * 80 + ($recommendedDone / max(1, $recommended->count())) * 20);
        $blocks = $required->where('done', false)->pluck('key')->values()->all();
        $warnings = $recommended->where('done', false)->pluck('key')->values()->all();

        return [
            'version' => '2026-06-03',
            'score' => $score,
            'status' => empty($blocks) ? ($score >= 90 ? 'excellent' : 'ready') : 'blocked',
            'can_approve' => empty($blocks),
            'required_done' => $requiredDone,
            'required_total' => $required->count(),
            'recommended_done' => $recommendedDone,
            'recommended_total' => $recommended->count(),
            'blocks' => $blocks,
            'warnings' => $warnings,
            'checklist' => array_values($checks),
            'provider_profile_id' => $profile?->id,
            'public_locations_count' => $locations->where('is_public', true)->count(),
            'pickup_locations_count' => $locations->where('is_public', true)->where('pickup_enabled', true)->count(),
            'returns_locations_count' => $locations->where('is_public', true)->where('returns_enabled', true)->count(),
        ];
    }

    public static function attach(MarketplaceSellerApplication $application): MarketplaceSellerApplication
    {
        $application->setAttribute('readiness', self::forApplication($application));

        return $application;
    }

    private static function profileForUser(?User $user): ?MarketplaceProviderProfile
    {
        if (! $user) {
            return null;
        }

        return MarketplaceProviderProfile::query()
            ->where('user_id', $user->id)
            ->with('locations')
            ->first();
    }

    private static function acceptedRules(MarketplaceSellerApplication $application): array
    {
        $rules = $application->accepted_rules ?: [];

        if (! is_array($rules)) {
            return [];
        }

        return $rules;
    }

    private static function ruleAccepted(array $rules, string $key): bool
    {
        return ($rules[$key] ?? false) === true || in_array($key, $rules, true);
    }

    private static function check(string $key, bool $done, string $severity, string $label): array
    {
        return [
            'key' => $key,
            'done' => $done,
            'severity' => $severity,
            'label' => $label,
        ];
    }
}



