<?php

namespace App\Services;

use App\Models\Ride;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RideAddressService
{
    public function validate(Request $request): array
    {
        // Older app versions may still save rides without structured addresses.
        $required = $request->boolean('address_details') ? 'required' : 'nullable';
        $rules = [];
        foreach (['pickup', 'destination'] as $prefix) {
            $rules[$prefix.'_postal_code'] = [$required, 'string', 'max:30'];
            $rules[$prefix.'_city'] = [$required, 'string', 'max:255'];
            $rules[$prefix.'_country'] = [$required, 'string', 'regex:/^[a-zA-Z]{2}$/'];
            $rules[$prefix.'_street'] = ['nullable', 'string', 'max:255'];
            $rules[$prefix.'_house_number'] = ['nullable', 'string', 'max:40'];
        }
        $data = $request->validate($rules);
        foreach ($data as $key => $value) {
            $data[$key] = $value === null ? null : trim($value);
            if (str_ends_with($key, '_country') && $value !== null) {
                $data[$key] = strtoupper($data[$key]);
            }
        }

        $ride = $request->route('ride');

        return [...$data, ...$this->coordinates($data, $ride instanceof Ride ? $ride : null)];
    }

    public function coordinates(array $data, ?Ride $ride = null): array
    {
        $result = [];
        foreach (['pickup', 'destination'] as $prefix) {
            $fields = [$prefix.'_postal_code', $prefix.'_city', $prefix.'_country'];
            $changed = collect($fields)->contains(fn ($field) => array_key_exists($field, $data) && $data[$field] !== $ride?->{$field});
            if ($ride && ! $changed && $ride->{$prefix.'_area_latitude'} !== null) {
                continue;
            }
            $values = array_map(fn ($field) => (string) ($data[$field] ?? $ride?->{$field} ?? ''), $fields);
            try {
                $point = app(RideGeocodingService::class)->area(...$values);
            } catch (\Throwable) {
                $point = null;
            }
            $result[$prefix.'_area_latitude'] = $point['latitude'] ?? null;
            $result[$prefix.'_area_longitude'] = $point['longitude'] ?? null;
        }

        return $result;
    }

    public function payload(Ride $ride, bool $private): array
    {
        $data = [];
        foreach (['street', 'house_number', 'postal_code', 'city', 'country'] as $field) {
            $data['destination_'.$field] = in_array($field, ['street', 'house_number']) && ! $private
                ? null : $ride->{'destination_'.$field};
        }
        $data['destination_public_label'] = $this->label($ride, false);
        $data['destination_private_label'] = $private ? $this->label($ride, true) : null;
        $data['area_search_ready'] = $ride->pickup_area_latitude !== null && $ride->destination_area_latitude !== null;

        return $data;
    }

    private function label(Ride $ride, bool $private): string
    {
        return implode(', ', array_filter([
            $ride->to,
            $private ? trim($ride->destination_street.' '.$ride->destination_house_number) : null,
            trim($ride->destination_postal_code.' '.$ride->destination_city),
            $ride->destination_country,
        ]));
    }

    public function filter(Builder $query, Request $request): void
    {
        $rules = ['from_q' => ['nullable', 'string', 'max:200'], 'to_q' => ['nullable', 'string', 'max:200'], 'radius_km' => ['nullable', 'integer', 'in:0,5,10,25']];
        foreach (['from', 'to'] as $side) {
            $rules[$side.'_lat'] = ['nullable', 'required_with:'.$side.'_lng', 'numeric', 'between:-90,90'];
            $rules[$side.'_lng'] = ['nullable', 'required_with:'.$side.'_lat', 'numeric', 'between:-180,180'];
        }
        $request->validate($rules);
        $radius = (int) $request->query('radius_km', 0);
        if ($radius && ! $request->filled('from_lat') && ! $request->filled('to_lat')) {
            throw ValidationException::withMessages(['radius_km' => __('ride_locations.choose')]);
        }
        foreach (['from_q' => ['from', 'pickup_postal_code', 'pickup_city', 'pickup_country'],
            'to_q' => ['to', 'destination_postal_code', 'destination_city', 'destination_country']] as $parameter => $columns) {
            $side = $parameter === 'from_q' ? 'from' : 'to';
            if ($radius && $request->filled($side.'_lat')) {
                $prefix = $side === 'from' ? 'pickup' : 'destination';
                $latitude = (float) $request->query($side.'_lat');
                $delta = rad2deg($radius / 6371.0088);
                $query->whereNotNull($prefix.'_area_longitude')->whereBetween($prefix.'_area_latitude', [$latitude - $delta, $latitude + $delta]);

                continue;
            }
            $needle = trim((string) $request->query($parameter, ''));
            if ($radius && $needle !== '') {
                throw ValidationException::withMessages([$parameter => __('ride_locations.choose')]);
            }
            if ($needle === '') {
                continue;
            }
            // Only public fields participate: private street names must not leak through search results.
            foreach (preg_split('/\s+/u', mb_substr($needle, 0, 200)) as $word) {
                $query->where(function ($matching) use ($columns, $word) {
                    foreach ($columns as $column) {
                        $matching->orWhere($column, 'like', '%'.$word.'%');
                    }
                });
            }
        }
    }

    public function matchesRadius(Ride $ride, Request $request): bool
    {
        $radius = (int) $request->query('radius_km', 0);
        if (! $radius) {
            return true;
        }
        foreach (['from' => 'pickup', 'to' => 'destination'] as $side => $prefix) {
            if (! $request->filled($side.'_lat')) {
                continue;
            }
            if ($ride->{$prefix.'_area_latitude'} === null || $ride->{$prefix.'_area_longitude'} === null) {
                return false;
            }
            $lat = deg2rad((float) $request->query($side.'_lat'));
            $lat2 = deg2rad((float) $ride->{$prefix.'_area_latitude'});
            $dLng = deg2rad((float) $ride->{$prefix.'_area_longitude'} - (float) $request->query($side.'_lng'));
            $a = sin(($lat2 - $lat) / 2) ** 2 + cos($lat) * cos($lat2) * sin($dLng / 2) ** 2;
            if (6371.0088 * 2 * asin(sqrt(min(1, max(0, $a)))) > $radius + 0.000001) {
                return false;
            }
        }

        return true;
    }
}
