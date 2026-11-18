<?php

namespace App\Support\Performance;

use Closure;
use Illuminate\Http\Request;

/** Memoize repeated reads only while a response is being serialized. */
class ResourceReadCache
{
    private array $values = [];

    public static function render(Request $request, Closure $render): mixed
    {
        $key = self::class;
        $previous = $request->attributes->get($key);
        $request->attributes->set($key, new self);

        try {
            return $render();
        } finally {
            if ($previous !== null) {
                $request->attributes->set($key, $previous);
            } else {
                $request->attributes->remove($key);
            }
        }
    }

    public static function remember(string $key, Closure $read): mixed
    {
        $cache = request()->attributes->get(self::class);
        if (! $cache instanceof self) {
            return $read();
        }

        if (! array_key_exists($key, $cache->values)) {
            $cache->values[$key] = $read();
        }

        return $cache->values[$key];
    }
}
