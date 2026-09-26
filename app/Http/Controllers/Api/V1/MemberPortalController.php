<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\MemberPortalOverviewService;
use Illuminate\Http\Request;

final class MemberPortalController extends Controller
{
    public function __construct(private readonly MemberPortalOverviewService $portal)
    {
    }

    public function show(Request $request)
    {
        return response()->json([
            'data' => $this->portal->overview($request->user(), $request),
        ]);
    }

    public function details(Request $request, string $section)
    {
        $items = $this->portal->detail($request->user(), $request, $section);

        return response()->json($this->portal->transformDetail($items, $request, $section));
    }
}
