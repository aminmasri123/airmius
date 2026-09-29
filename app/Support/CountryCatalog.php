<?php

namespace App\Support;

use App\Models\Setting;

class CountryCatalog
{
    public static function defaults(): array
    {
        return json_decode(file_get_contents(resource_path('data/countries.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function all(): array
    {
        $countries = collect(self::defaults())->keyBy('code');
        foreach (Setting::query()->where('key', 'like', 'country_catalog.%')->pluck('value', 'key') as $key => $value) {
            $code = substr($key, strlen('country_catalog.'));
            $names = json_decode($value, true);
            if (preg_match('/^[A-Z]{2}$/', $code) && is_array($names) && ! $countries->has($code)) {
                $countries->put($code, ['code' => $code, 'names' => $names]);
            }
        }

        return $countries->sortKeys()->values()->all();
    }
}
