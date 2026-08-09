<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\Event;
use App\Models\Sport;
use App\Services\PublicDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class PublicDiscoveryController extends Controller
{
    public function __construct(private readonly PublicDiscoveryService $discovery) {}

    public function events(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:training,match,meeting,public'],
            'location' => ['nullable', 'string', 'max:120'],
        ]);

        return Inertia::render('Guest/Events', [
            ...$this->commonProps(),
            'events' => fn () => $this->discovery->events($filters),
            'eventTypes' => Event::TYPES,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'type' => $filters['type'] ?? '',
                'location' => $filters['location'] ?? '',
            ],
        ]);
    }

    public function sports(Request $request): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:80'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        return Inertia::render('Guest/Sportarten', [
            ...$this->commonProps(),
            'sports' => fn () => $this->discovery->sports($filters, false)['sports'],
            'categories' => fn () => $this->discovery->sportCategories(),
            'filters' => [
                'category' => $filters['category'] ?? '',
                'search' => $filters['search'] ?? '',
            ],
        ]);
    }

    public function cities(): Response
    {
        $cities = $this->discovery->cities();

        return Inertia::render('Guest/Discovery/Cities', [
            ...$this->commonProps(),
            'cities' => $cities,
            'seo' => [
                'title' => __('public_discovery.cities.meta_title'),
                'description' => __('public_discovery.cities.meta_description'),
                'canonical' => route('guest.cities'),
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => __('public_discovery.cities.meta_title'),
                    'url' => route('guest.cities'),
                    'mainEntity' => [
                        '@type' => 'ItemList',
                        'itemListElement' => $cities->take(50)->values()->map(fn (array $city, int $index): array => [
                            '@type' => 'ListItem',
                            'position' => $index + 1,
                            'name' => $city['name'],
                            'url' => $city['detail_url'],
                        ])->all(),
                    ],
                ],
            ],
        ]);
    }

    public function club(Club $club): Response
    {
        $payload = $this->discovery->club($club);
        abort_if($payload === null, 404);
        $entity = $payload['entity'];
        $description = __('public_discovery.club.meta_description', [
            'name' => $entity['name'],
            'city' => $entity['city'] ?: __('public_discovery.common.location_open'),
            'sport' => $entity['sport_type'] ?: __('public_discovery.common.sport_open'),
        ]);

        return Inertia::render('Guest/Discovery/Show', [
            ...$this->commonProps(),
            'kind' => 'club',
            ...$payload,
            'seo' => [
                'title' => __('public_discovery.club.meta_title', ['name' => $entity['name']]),
                'description' => $description,
                'canonical' => $entity['detail_url'],
                'image' => $entity['cover_image_url'] ?: $entity['logo_url'],
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'SportsOrganization',
                    'name' => $entity['name'],
                    'url' => $entity['detail_url'],
                    'logo' => $entity['logo_url'],
                    'sport' => $entity['sport_type'],
                    'address' => [
                        '@type' => 'PostalAddress',
                        'postalCode' => $entity['postal_code'],
                        'addressLocality' => $entity['city'],
                        'addressRegion' => $entity['state'],
                        'addressCountry' => $entity['country'],
                    ],
                ],
            ],
        ]);
    }

    public function event(Event $event): Response
    {
        $payload = $this->discovery->event($event);
        abort_if($payload === null, 404);
        $entity = $payload['entity'];
        $organizer = $entity['team']['name'] ?? $entity['club']['name'] ?? 'Airmius';

        return Inertia::render('Guest/Discovery/Show', [
            ...$this->commonProps(),
            'kind' => 'event',
            ...$payload,
            'seo' => [
                'title' => __('public_discovery.event.meta_title', ['title' => $entity['title']]),
                'description' => __('public_discovery.event.meta_description', [
                    'title' => $entity['title'],
                    'city' => $entity['location_city'] ?: __('public_discovery.common.location_open'),
                ]),
                'canonical' => $entity['detail_url'],
                'type' => 'article',
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'SportsEvent',
                    'name' => $entity['title'],
                    'url' => $entity['detail_url'],
                    'startDate' => $entity['start_time'],
                    'endDate' => $entity['end_time'],
                    'eventStatus' => 'https://schema.org/EventScheduled',
                    'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
                    'location' => [
                        '@type' => 'Place',
                        'name' => $entity['location'],
                        'address' => [
                            '@type' => 'PostalAddress',
                            'addressLocality' => $entity['location_city'],
                            'addressCountry' => $entity['location_country'],
                        ],
                    ],
                    'organizer' => [
                        '@type' => 'Organization',
                        'name' => $organizer,
                        'url' => $entity['club']['detail_url'] ?? route('welcome'),
                    ],
                ],
            ],
        ]);
    }

    public function sport(Sport $sport): Response
    {
        $payload = $this->discovery->sport($sport);
        abort_if($payload === null, 404);
        $entity = $payload['entity'];

        return Inertia::render('Guest/Discovery/Show', [
            ...$this->commonProps(),
            'kind' => 'sport',
            ...$payload,
            'seo' => [
                'title' => __('public_discovery.sport.meta_title', ['sport' => $entity['name']]),
                'description' => __('public_discovery.sport.meta_description', ['sport' => $entity['name']]),
                'canonical' => $entity['detail_url'],
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => $entity['name'],
                    'url' => $entity['detail_url'],
                    'about' => [
                        '@type' => 'Thing',
                        'name' => $entity['name'],
                        'additionalType' => 'https://schema.org/SportsActivity',
                    ],
                ],
            ],
        ]);
    }

    public function city(string $country, string $city): Response
    {
        $payload = $this->discovery->city($country, $city);
        abort_if($payload === null, 404);
        $entity = $payload['entity'];

        return Inertia::render('Guest/Discovery/Show', [
            ...$this->commonProps(),
            'kind' => 'city',
            ...$payload,
            'seo' => [
                'title' => __('public_discovery.city.meta_title', ['city' => $entity['name']]),
                'description' => __('public_discovery.city.meta_description', ['city' => $entity['name']]),
                'canonical' => $entity['detail_url'],
                'schema' => [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => __('public_discovery.city.meta_title', ['city' => $entity['name']]),
                    'url' => $entity['detail_url'],
                    'about' => [
                        '@type' => 'City',
                        'name' => $entity['name'],
                        'addressCountry' => $entity['country'],
                    ],
                ],
            ],
        ]);
    }

    /** @return array<string, bool> */
    private function commonProps(): array
    {
        return [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
        ];
    }
}
