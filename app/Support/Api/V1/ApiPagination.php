<?php

namespace App\Support\Api\V1;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ApiPagination
{
    public const MAX_PER_PAGE = 50;

    public static function perPage(Request $request, int $default = 20, int $max = self::MAX_PER_PAGE): int
    {
        $max = min(max($max, 1), self::MAX_PER_PAGE);
        $default = min(max($default, 1), $max);

        return min(max((int) $request->integer('per_page', $default), 1), $max);
    }

    public static function meta(LengthAwarePaginator $page, array $extra = []): array
    {
        return array_merge([
            'current_page' => $page->currentPage(),
            'from' => $page->firstItem(),
            'last_page' => $page->lastPage(),
            'path' => $page->path(),
            'per_page' => $page->perPage(),
            'to' => $page->lastItem(),
            'total' => $page->total(),
        ], $extra);
    }

    public static function links(LengthAwarePaginator $page): array
    {
        return [
            'first' => $page->url(1),
            'last' => $page->url($page->lastPage()),
            'prev' => $page->previousPageUrl(),
            'next' => $page->nextPageUrl(),
        ];
    }

    public static function payload(LengthAwarePaginator $page, array $data, array $metaExtra = [], array $extra = []): array
    {
        return array_merge([
            'data' => $data,
            'meta' => self::meta($page, $metaExtra),
            'links' => self::links($page),
        ], $extra);
    }
}