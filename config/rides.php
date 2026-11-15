<?php

return [
    // Geocoding stays independent of a future self-hosted routing engine.
    'geocoding_url' => env('RIDES_GEOCODING_URL', 'https://graphhopper.com/api/1/geocode'),
    'geocoding_key' => env('RIDES_GEOCODING_API_KEY', env('GRAPHHOPPER_API_KEY')),
];
