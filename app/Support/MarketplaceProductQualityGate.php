<?php

namespace App\Support;

use App\Models\MarketplaceProduct;

class MarketplaceProductQualityGate
{
    public const VERSION = '2026-06-03';

    public static function evaluate(MarketplaceProduct $product): array
    {
        $product->loadMissing(['inventories', 'user.approvedSellerApplications', 'club']);

        $checks = [
            self::check('title', mb_strlen(trim((string) $product->title)) >= 8, 'required', 'Titel ist klar genug.'),
            self::check('description', mb_strlen(trim((string) $product->description)) >= 80, 'required', 'Beschreibung erklärt Nutzen, Zustand, Ablauf und Einschraenkungen.'),
            self::check('main_image', filled($product->image_url), 'required', 'Hauptbild ist vorhanden.'),
            self::check('price', (int) $product->price_cents > 0, 'required', 'Preis ist gesetzt.'),
            self::check('seller', (bool) ($product->club_id || $product->user_id || $product->payout_status === 'not_applicable'), 'required', 'Anbieter ist zugeordnet.'),
            self::check('seller_verified', self::sellerVerified($product), 'required', 'Anbieter ist für den Marketplace freigegeben.'),
            self::check('return_policy', filled($product->return_policy_type), 'required', 'Rückgabe-/Leistungsregel ist gesetzt.'),
            self::check('physical_stock', $product->isDigitalDelivery() || ! (bool) $product->manages_stock || $product->sellableStockForCountry(null) > 0, 'required', 'Verkaufbarer Bestand ist vorhanden.'),
            self::check('physical_fulfillment', $product->isDigitalDelivery() || ! (bool) $product->is_shippable || self::hasFulfillmentSignal($product), 'required', 'Versandland, Lager oder Abholung ist gepflegt.'),
            self::check('digital_delivery', ! $product->isDigitalDelivery() || filled($product->digital_delivery_note) || filled($product->learning_course_id), 'required', 'Digitale Lieferung oder Kurszugang ist erklärt.'),
            self::check('features', count((array) ($product->features ?: [])) >= 2, 'recommended', 'Mindestens zwei Merkmale helfen Käufern bei der Entscheidung.'),
            self::check('gallery', count((array) ($product->gallery_images ?: [])) >= 2, 'recommended', 'Galerie zeigt weitere Ansichten oder Details.'),
            self::check('attributes', count((array) ($product->product_attributes ?: [])) >= 1, 'recommended', 'Attribute wie Größe, Material, Dauer oder Level sind gepflegt.'),
            self::check('variants', $product->product_type !== 'variable' || count((array) ($product->variants ?: [])) >= 1, 'recommended', 'Varianten sind für variable Produkte vorhanden.'),
            self::check('learning_goals', ! in_array($product->offer_type, ['online_course', 'training_plan'], true) || count((array) ($product->learning_goals ?: [])) >= 1, 'recommended', 'Lernziele oder Trainingsergebnis sind beschrieben.'),
        ];

        $required = collect($checks)->where('severity', 'required');
        $recommended = collect($checks)->where('severity', 'recommended');
        $requiredDone = $required->where('done', true)->count();
        $recommendedDone = $recommended->where('done', true)->count();
        $score = (int) round(($requiredDone / max(1, $required->count())) * 82 + ($recommendedDone / max(1, $recommended->count())) * 18);
        $blocks = $required->where('done', false)->pluck('key')->values()->all();
        $warnings = $recommended->where('done', false)->pluck('key')->values()->all();

        return [
            'version' => self::VERSION,
            'score' => $score,
            'status' => empty($blocks) ? ($score >= 90 ? 'excellent' : 'ready') : 'blocked',
            'can_publish' => empty($blocks),
            'required_done' => $requiredDone,
            'required_total' => $required->count(),
            'recommended_done' => $recommendedDone,
            'recommended_total' => $recommended->count(),
            'blocks' => $blocks,
            'warnings' => $warnings,
            'issues' => collect($checks)->where('done', false)->pluck('label')->values()->all(),
            'checklist' => array_values($checks),
        ];
    }

    public static function applyPublicationGate(MarketplaceProduct $product): MarketplaceProduct
    {
        $gate = self::evaluate($product);

        if (! $gate['can_publish']) {
            $product->forceFill([
                'status' => 'review',
                'moderation_status' => 'pending',
                'rejection_reason' => 'QualitaetsPrüfung offen: '.implode(', ', $gate['blocks']),
            ])->save();

            $product->setAttribute('quality_gate', $gate);

            return $product;
        }

        $product->setAttribute('quality_gate', $gate);

        return $product;
    }

    private static function sellerVerified(MarketplaceProduct $product): bool
    {
        if ($product->club?->verification_status === 'verified') {
            return true;
        }

        if (! $product->user_id && ! $product->club_id && $product->payout_status === 'not_applicable') {
            return true;
        }

        if (! $product->user) {
            return false;
        }

        return $product->user->relationLoaded('approvedSellerApplications')
            ? $product->user->approvedSellerApplications->isNotEmpty()
            : $product->user->approvedSellerApplications()->exists();
    }

    private static function hasFulfillmentSignal(MarketplaceProduct $product): bool
    {
        if (collect($product->available_countries ?: [])->filter()->isNotEmpty()) {
            return true;
        }

        if ($product->relationLoaded('inventories') && $product->inventories->where('is_active', true)->isNotEmpty()) {
            return true;
        }

        return $product->inventories()->where('is_active', true)->exists();
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


