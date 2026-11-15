<?php

namespace App\Services;

use App\Models\Ride;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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

        return $data;
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
        $request->validate(['from_q' => ['nullable', 'string', 'max:200'], 'to_q' => ['nullable', 'string', 'max:200']]);
        foreach (['from_q' => ['from', 'pickup_postal_code', 'pickup_city', 'pickup_country'],
            'to_q' => ['to', 'destination_postal_code', 'destination_city', 'destination_country']] as $parameter => $columns) {
            $needle = trim((string) $request->query($parameter, ''));
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
}
