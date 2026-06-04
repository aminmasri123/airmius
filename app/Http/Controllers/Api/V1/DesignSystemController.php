<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\Api\V1\DesignSystemContract;

class DesignSystemController extends Controller
{
    public function __invoke()
    {
        return response()->json([
            'data' => DesignSystemContract::payload(),
        ]);
    }
}
