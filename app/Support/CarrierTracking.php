<?php

namespace App\Support;

class CarrierTracking
{
    private const CARRIERS = [
        'dhl' => [
            'label' => 'DHL',
            'aliases' => ['dhl', 'deutsche post', 'deutschepost'],
            'url' => 'https://www.dhl.de/de/privatkunden/pakete-empfangen/verfolgen.html?piececode=%s',
        ],
        'ups' => [
            'label' => 'UPS',
            'aliases' => ['ups', 'united parcel service'],
            'url' => 'https://www.ups.com/track?tracknum=%s',
        ],
        'dpd' => [
            'label' => 'DPD',
            'aliases' => ['dpd'],
            'url' => 'https://tracking.dpd.de/status/de_DE/parcel/%s',
        ],
        'hermes' => [
            'label' => 'Hermes',
            'aliases' => ['hermes'],
            'url' => 'https://www.myhermes.de/empfangen/sendungsverfolgung/sendungsinformation/#%s',
        ],
        'gls' => [
            'label' => 'GLS',
            'aliases' => ['gls'],
            'url' => 'https://gls-group.com/DE/de/paketverfolgung?match=%s',
        ],
        'fedex' => [
            'label' => 'FedEx',
            'aliases' => ['fedex', 'fed ex'],
            'url' => 'https://www.fedex.com/fedextrack/?trknbr=%s',
        ],
    ];

    public static function normalizeCarrier(?string $carrier): ?string
    {
        $carrier = trim((string) $carrier);

        if ($carrier === '') {
            return null;
        }

        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $carrier));
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        foreach (self::CARRIERS as $config) {
            foreach ($config['aliases'] as $alias) {
                if ($normalized === $alias) {
                    return $config['label'];
                }
            }
        }

        return $carrier;
    }

    public static function trackingNumber(?string $trackingNumber): ?string
    {
        $trackingNumber = strtoupper(trim((string) $trackingNumber));
        $trackingNumber = preg_replace('/\s+/', '', $trackingNumber);

        return $trackingNumber !== '' ? $trackingNumber : null;
    }

    public static function trackingUrl(?string $carrier, ?string $trackingNumber, ?string $trackingUrl = null): ?string
    {
        $trackingUrl = trim((string) $trackingUrl);

        if ($trackingUrl !== '') {
            return $trackingUrl;
        }

        $trackingNumber = self::trackingNumber($trackingNumber);

        if (! $trackingNumber) {
            return null;
        }

        $key = self::carrierKey($carrier);

        if (! $key) {
            return null;
        }

        return sprintf(self::CARRIERS[$key]['url'], rawurlencode($trackingNumber));
    }

    public static function carriers(): array
    {
        return collect(self::CARRIERS)
            ->map(fn (array $config, string $key) => [
                'value' => $config['label'],
                'label' => $config['label'],
                'key' => $key,
            ])
            ->values()
            ->all();
    }

    private static function carrierKey(?string $carrier): ?string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', ' ', (string) $carrier));
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized));

        foreach (self::CARRIERS as $key => $config) {
            foreach ($config['aliases'] as $alias) {
                if ($normalized === $alias || strtolower($config['label']) === $normalized) {
                    return $key;
                }
            }
        }

        return null;
    }
}
