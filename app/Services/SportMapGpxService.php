<?php

namespace App\Services;

use App\Models\SportRoute;
use App\Models\SportRouteTrack;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use XMLWriter;

class SportMapGpxService
{
    public function routeGpx(SportRoute $route): string
    {
        return $this->writeGpx(
            title: $route->title,
            description: $route->description,
            points: $this->routeExportPoints($route),
            type: 'route',
        );
    }

    public function trackGpx(SportRouteTrack $track): string
    {
        return $this->writeGpx(
            title: $track->title,
            description: $track->route?->title,
            points: $track->track_points ?? [],
            type: 'track',
        );
    }

    public function routePayloadFromGpx(string $gpx, array $overrides = []): array
    {
        $parsed = $this->parse($gpx);
        $points = $parsed['route_points'] ?: $parsed['track_points'];

        if (count($points) < 2) {
            throw ValidationException::withMessages([
                'gpx' => __('Die GPX-Datei muss mindestens zwei Routen- oder Trackpunkte enthalten.'),
            ]);
        }

        return array_filter([
            'title' => $overrides['title'] ?? $parsed['title'] ?? __('Importierte GPX-Route'),
            'description' => $overrides['description'] ?? $parsed['description'] ?? null,
            'sport_id' => $overrides['sport_id'] ?? null,
            'sport_type' => $overrides['sport_type'] ?? null,
            'visibility' => $overrides['visibility'] ?? 'private',
            'team_id' => $overrides['team_id'] ?? null,
            'status' => $overrides['status'] ?? 'planned',
            'difficulty' => $overrides['difficulty'] ?? null,
            'surface' => $overrides['surface'] ?? null,
            'waypoints' => $points,
            'metrics' => [
                'imported_from' => 'gpx',
                'gpx_source' => $parsed['source_type'],
            ],
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function trackPayloadFromGpx(string $gpx, array $overrides = []): array
    {
        $parsed = $this->parse($gpx);
        $points = $parsed['track_points'] ?: $parsed['route_points'];

        if ($points === []) {
            throw ValidationException::withMessages([
                'gpx' => __('Die GPX-Datei muss mindestens einen Track- oder Routenpunkt enthalten.'),
            ]);
        }

        return array_filter([
            'title' => $overrides['title'] ?? $parsed['title'] ?? __('Importierter GPX-Track'),
            'sport_route_id' => $overrides['sport_route_id'] ?? null,
            'sport_id' => $overrides['sport_id'] ?? null,
            'team_id' => $overrides['team_id'] ?? null,
            'sport_type' => $overrides['sport_type'] ?? null,
            'status' => $overrides['status'] ?? 'completed',
            'started_at' => $points[0]['recorded_at'] ?? null,
            'ended_at' => $points[count($points) - 1]['recorded_at'] ?? null,
            'track_points' => $points,
            'metrics' => [
                'imported_from' => 'gpx',
                'gpx_source' => $parsed['source_type'],
            ],
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function filename(string $title, string $fallback = 'airmius-route'): string
    {
        return (Str::slug($title) ?: $fallback).'.gpx';
    }

    private function writeGpx(string $title, ?string $description, array $points, string $type): string
    {
        $writer = new XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement('gpx');
        $writer->writeAttribute('version', '1.1');
        $writer->writeAttribute('creator', 'Airmius');
        $writer->writeAttribute('xmlns', 'http://www.topografix.com/GPX/1/1');
        $writer->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $writer->writeAttribute('xsi:schemaLocation', 'http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd');

        $writer->startElement('metadata');
        $this->writeTextElement($writer, 'name', $title);
        if ($description) {
            $this->writeTextElement($writer, 'desc', $description);
        }
        $writer->endElement();

        if ($type === 'route') {
            $writer->startElement('rte');
            $this->writeTextElement($writer, 'name', $title);
            if ($description) {
                $this->writeTextElement($writer, 'desc', $description);
            }

            foreach ($points as $point) {
                $this->writePoint($writer, 'rtept', $point, false);
            }

            $writer->endElement();
        } else {
            $writer->startElement('trk');
            $this->writeTextElement($writer, 'name', $title);
            if ($description) {
                $this->writeTextElement($writer, 'desc', $description);
            }
            $writer->startElement('trkseg');

            foreach ($points as $point) {
                $this->writePoint($writer, 'trkpt', $point, true);
            }

            $writer->endElement();
            $writer->endElement();
        }

        $writer->endElement();
        $writer->endDocument();

        return $writer->outputMemory();
    }

    private function writePoint(XMLWriter $writer, string $element, array $point, bool $includeTime): void
    {
        if (! isset($point['latitude'], $point['longitude'])) {
            return;
        }

        $writer->startElement($element);
        $writer->writeAttribute('lat', $this->coordinate($point['latitude']));
        $writer->writeAttribute('lon', $this->coordinate($point['longitude']));

        if (isset($point['elevation_m'])) {
            $this->writeTextElement($writer, 'ele', $this->elevation($point['elevation_m']));
        }

        if (($point['name'] ?? null) !== null) {
            $this->writeTextElement($writer, 'name', (string) $point['name']);
        }

        if ($includeTime && ($point['recorded_at'] ?? null)) {
            $this->writeTextElement($writer, 'time', (string) $point['recorded_at']);
        }

        $writer->endElement();
    }

    private function writeTextElement(XMLWriter $writer, string $name, string $value): void
    {
        $writer->startElement($name);
        $writer->text($value);
        $writer->endElement();
    }

    private function routeExportPoints(SportRoute $route): array
    {
        $coordinates = data_get($route->route_geometry, 'coordinates', []);

        if (is_array($coordinates) && count($coordinates) >= 2) {
            return collect($coordinates)
                ->filter(fn ($coordinate) => is_array($coordinate) && count($coordinate) >= 2)
                ->values()
                ->map(function (array $coordinate, int $index) use ($coordinates, $route) {
                    $point = [
                        'latitude' => (float) $coordinate[1],
                        'longitude' => (float) $coordinate[0],
                    ];

                    if (isset($coordinate[2])) {
                        $point['elevation_m'] = (float) $coordinate[2];
                    }

                    if ($index === 0 && $route->start_name) {
                        $point['name'] = $route->start_name;
                    } elseif ($index === count($coordinates) - 1 && $route->end_name) {
                        $point['name'] = $route->end_name;
                    }

                    return $point;
                })
                ->all();
        }

        return $route->waypoints ?? [];
    }

    private function parse(string $gpx): array
    {
        $gpx = trim($gpx);

        if ($gpx === '') {
            throw ValidationException::withMessages([
                'gpx' => __('Bitte lade eine gültige GPX-Datei hoch.'),
            ]);
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($gpx, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $xml instanceof SimpleXMLElement) {
            throw ValidationException::withMessages([
                'gpx' => __('Die GPX-Datei konnte nicht gelesen werden.'),
            ]);
        }

        $routePoints = $this->pointsFromXml($xml, 'rtept');
        $trackPoints = $this->pointsFromXml($xml, 'trkpt', true);

        return [
            'title' => $this->firstText($xml, 'name'),
            'description' => $this->firstText($xml, 'desc'),
            'route_points' => $routePoints,
            'track_points' => $trackPoints,
            'source_type' => $routePoints !== [] ? 'route' : 'track',
        ];
    }

    private function pointsFromXml(SimpleXMLElement $xml, string $element, bool $includeTime = false): array
    {
        return collect($xml->xpath("//*[local-name()='{$element}']") ?: [])
            ->map(function (SimpleXMLElement $node) use ($includeTime) {
                $latitude = (string) ($node['lat'] ?? '');
                $longitude = (string) ($node['lon'] ?? '');

                if (! is_numeric($latitude) || ! is_numeric($longitude)) {
                    return null;
                }

                $point = [
                    'latitude' => round((float) $latitude, 7),
                    'longitude' => round((float) $longitude, 7),
                ];

                $elevation = $this->directChildText($node, 'ele');
                if ($elevation !== null && is_numeric($elevation)) {
                    $point['elevation_m'] = round((float) $elevation, 1);
                }

                $name = $this->directChildText($node, 'name');
                if ($name !== null && $name !== '') {
                    $point['name'] = Str::limit($name, 120, '');
                }

                $time = $this->directChildText($node, 'time');
                if ($includeTime && $time !== null && $time !== '') {
                    $point['recorded_at'] = $time;
                }

                return $point;
            })
            ->filter()
            ->values()
            ->all();
    }

    private function firstText(SimpleXMLElement $xml, string $element): ?string
    {
        $matches = $xml->xpath("//*[local-name()='{$element}']") ?: [];
        $value = isset($matches[0]) ? trim((string) $matches[0]) : '';

        return $value !== '' ? Str::limit($value, 160, '') : null;
    }

    private function directChildText(SimpleXMLElement $node, string $element): ?string
    {
        foreach ($node->children() as $child) {
            if ($child->getName() === $element) {
                return trim((string) $child);
            }
        }

        return null;
    }

    private function coordinate(float|int|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 7, '.', ''), '0'), '.');
    }

    private function elevation(float|int|string $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.');
    }
}
