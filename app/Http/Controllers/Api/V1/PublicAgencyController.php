<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WebsiteRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicAgencyController extends Controller
{
    public function store(Request $request, WebsiteRequestService $websiteRequests): JsonResponse
    {
        $websiteRequest = $websiteRequests->createPublic(
            $request->validate(WebsiteRequestService::publicRules()),
            $request->user(),
        );

        return response()->json([
            'message' => __('agency.flash.request_sent'),
            'data' => [
                'id' => $websiteRequest->id,
                'status' => $websiteRequest->status,
                'retention_expires_at' => $websiteRequest->retention_expires_at?->toJSON(),
            ],
        ], 201);
    }
}
