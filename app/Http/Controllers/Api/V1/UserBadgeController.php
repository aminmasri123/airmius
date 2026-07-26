<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserBadge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserBadgeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $awards = $request->user()
            ->badgeAwards()
            ->with('badge:id,key,name,description,icon,actor_type,trigger,threshold')
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 30), 1), 100));

        return response()->json([
            'data' => collect($awards->items())
                ->map(fn (UserBadge $award) => $this->awardData($award))
                ->values(),
            'meta' => [
                'current_page' => $awards->currentPage(),
                'last_page' => $awards->lastPage(),
                'per_page' => $awards->perPage(),
                'total' => $awards->total(),
            ],
        ]);
    }

    public function show(Request $request, UserBadge $userBadge): JsonResponse
    {
        abort_unless(
            (int) $userBadge->user_id === (int) $request->user()->id
                || $request->user()->can('system.manage'),
            403,
        );

        return response()->json([
            'data' => $this->awardData(
                $userBadge->load('badge:id,key,name,description,icon,actor_type,trigger,threshold'),
            ),
        ]);
    }

    private function awardData(UserBadge $award): array
    {
        return [
            'id' => $award->id,
            'reason' => $award->reason,
            'meta' => $award->meta ?? [],
            'awarded_at' => $award->created_at?->toIso8601String(),
            'badge' => $award->badge ? [
                'id' => $award->badge->id,
                'key' => $award->badge->key,
                'name' => $award->badge->name,
                'description' => $award->badge->description,
                'icon' => $award->badge->icon,
                'actor_type' => $award->badge->actor_type,
                'trigger' => $award->badge->trigger,
                'threshold' => $award->badge->threshold,
            ] : null,
        ];
    }
}
