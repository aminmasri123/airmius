<?php

namespace App\Support\Api\V1;

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
}
