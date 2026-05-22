<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Provider cost assumptions
    |--------------------------------------------------------------------------
    |
    | These values are deliberately configurable. Provider prices change, so the
    | admin dashboard treats them as estimates for operational decisions, not as
    | invoices. Update the env values whenever a contract or public plan changes.
    |
    */

    'usd_to_eur' => (float) env('PROVIDER_COST_USD_TO_EUR', 0.92),
    'self_hosted_monthly_eur' => (float) env('PROVIDER_COST_SELF_HOSTED_ROUTING_EUR', 180),

    'plans' => [
        'mapbox_map_loads' => [
            'label' => 'Mapbox GL JS Webkarte',
            'provider' => 'mapbox',
            'kind' => 'metered',
            'unit_label' => 'Map Loads',
            'free_units' => 50000,
            'tiers' => [
                ['up_to' => null, 'price_per_1000_usd' => 5.00],
            ],
        ],
        'mapbox_directions' => [
            'label' => 'Mapbox Directions',
            'provider' => 'mapbox',
            'kind' => 'metered',
            'unit_label' => 'Routing-Requests',
            'free_units' => 100000,
            'tiers' => [
                ['up_to' => 500000, 'price_per_1000_usd' => 2.00],
                ['up_to' => 1000000, 'price_per_1000_usd' => 1.60],
                ['up_to' => null, 'price_per_1000_usd' => 1.20],
            ],
        ],
        'mapbox_navigation' => [
            'label' => 'Mapbox Navigation SDK',
            'provider' => 'mapbox',
            'kind' => 'navigation',
            'free_users' => 100,
            'price_per_user_usd' => 0.30,
            'free_trips' => 1000,
            'trip_tiers' => [
                ['up_to' => 50000, 'price_per_trip_usd' => 0.08],
                ['up_to' => 100000, 'price_per_trip_usd' => 0.064],
                ['up_to' => null, 'price_per_trip_usd' => 0.048],
            ],
        ],
        'graphhopper_basic' => [
            'label' => 'GraphHopper Basic',
            'provider' => 'graphhopper',
            'kind' => 'fixed',
            'monthly_eur' => 69,
            'included_units' => 150000,
            'unit_label' => 'Credit-Schaetzung/Monat',
        ],
        'graphhopper_standard' => [
            'label' => 'GraphHopper Standard',
            'provider' => 'graphhopper',
            'kind' => 'fixed',
            'monthly_eur' => 199,
            'included_units' => 450000,
            'unit_label' => 'Credit-Schaetzung/Monat',
        ],
        'openrouteservice_standard' => [
            'label' => 'openrouteservice Standard',
            'provider' => 'openrouteservice',
            'kind' => 'fixed',
            'monthly_eur' => 0,
            'included_units' => 60000,
            'unit_label' => 'Directions/Monat',
            'risk' => 'Guter Test-/MVP-Plan, aber mit Tages- und Minutenlimits.',
        ],
        'self_hosted_routing' => [
            'label' => 'Eigene OSM/OSRM/ORS-Infrastruktur',
            'provider' => 'airmius',
            'kind' => 'fixed',
            'monthly_eur' => (float) env('PROVIDER_COST_SELF_HOSTED_ROUTING_EUR', 180),
            'included_units' => 750000,
            'unit_label' => 'Routing-Requests/Monat',
            'risk' => 'Mehr Technikaufwand, dafuer volle Kontrolle und bessere DSGVO-Steuerung.',
        ],
        'mistral_text' => [
            'label' => 'Mistral Text-KI',
            'provider' => 'mistral',
            'kind' => 'tokens',
            'input_per_million_eur' => (float) env('PROVIDER_COST_MISTRAL_INPUT_1M_EUR', 0.20),
            'output_per_million_eur' => (float) env('PROVIDER_COST_MISTRAL_OUTPUT_1M_EUR', 0.60),
        ],
        'openai_text' => [
            'label' => 'OpenAI Text-KI',
            'provider' => 'openai',
            'kind' => 'tokens',
            'input_per_million_eur' => (float) env('PROVIDER_COST_OPENAI_INPUT_1M_EUR', 0.25),
            'output_per_million_eur' => (float) env('PROVIDER_COST_OPENAI_OUTPUT_1M_EUR', 2.00),
        ],
        'ionos_text' => [
            'label' => 'IONOS AI Model Hub',
            'provider' => 'ionos',
            'kind' => 'tokens',
            'input_per_million_eur' => (float) env('PROVIDER_COST_IONOS_INPUT_1M_EUR', 0.25),
            'output_per_million_eur' => (float) env('PROVIDER_COST_IONOS_OUTPUT_1M_EUR', 0.75),
        ],
        'ai_image' => [
            'label' => 'KI-Bilder',
            'provider' => 'configurable',
            'kind' => 'metered',
            'unit_label' => 'Bilder',
            'free_units' => 0,
            'tiers' => [
                ['up_to' => null, 'price_per_1000_eur' => (float) env('PROVIDER_COST_AI_IMAGE_PER_1000_EUR', 40)],
            ],
        ],
    ],
];
