<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiPaginationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_v1_paginated_collections_expose_data_meta_and_links(): void
    {
        Sanctum::actingAs(User::factory()->create());

        foreach (['/api/v1/feed', '/api/v1/events', '/api/v1/notifications'] as $uri) {
            $this->assertPaginatedResponse(
                $this->getJson($uri.'?per_page=1')->assertOk(),
                $uri,
            );
        }
    }

    public function test_api_v1_upload_workspace_nested_pagination_exposes_links(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/files?per_page=1')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'folders_pagination' => [
                        'current_page',
                        'from',
                        'last_page',
                        'path',
                        'per_page',
                        'to',
                        'total',
                        'links' => ['first', 'last', 'prev', 'next'],
                    ],
                    'files_pagination' => [
                        'current_page',
                        'from',
                        'last_page',
                        'path',
                        'per_page',
                        'to',
                        'total',
                        'links' => ['first', 'last', 'prev', 'next'],
                    ],
                ],
            ]);
    }

    private function assertPaginatedResponse($response, string $uri): void
    {
        $response->assertJsonStructure([
            'data',
            'meta' => [
                'current_page',
                'from',
                'last_page',
                'path',
                'per_page',
                'to',
                'total',
            ],
            'links' => ['first', 'last', 'prev', 'next'],
        ]);
    }
}