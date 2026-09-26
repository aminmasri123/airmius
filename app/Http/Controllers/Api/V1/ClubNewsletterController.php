<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubNewsletterDelivery;
use App\Services\ClubNewsletterService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubNewsletterController extends Controller
{
    public function __construct(private readonly ClubNewsletterService $newsletters) {}

    public function subscribe(Request $request, Club $club)
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'name' => ['nullable', 'string', 'max:160'],
        ]);

        [$subscription] = $this->newsletters->requestOptIn($club, $data['email'], $data['name'] ?? null, $request->user());

        return response()->json([
            'message' => 'newsletter_confirmation_required',
            'data' => ['id' => $subscription->id, 'status' => $subscription->status],
        ], 202);
    }

    public function confirm(string $token)
    {
        $subscription = $this->newsletters->confirm($token);
        abort_unless($subscription, 404);

        return response()->json(['message' => 'newsletter_confirmed', 'data' => ['status' => $subscription->status]]);
    }

    public function unsubscribe(string $token)
    {
        $subscription = $this->newsletters->unsubscribe($token);
        abort_unless($subscription, 404);

        return response()->json(['message' => 'newsletter_unsubscribed', 'data' => ['status' => $subscription->status]]);
    }

    public function send(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);

        $data = $request->validate([
            'subject' => ['required_without:template_key', 'string', 'max:180'],
            'body' => ['required_without:template_key', 'string', 'max:10000'],
            'template_key' => ['nullable', 'string', 'max:120'],
            'variables' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
        ]);

        $campaign = $this->newsletters->sendCampaign($club, $request->user(), $data);

        return response()->json(['data' => [
            'id' => $campaign->id,
            'status' => $campaign->status,
            'deliveries_count' => $campaign->deliveries->count(),
        ]], 201);
    }

    public function suppress(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'reason' => ['required', Rule::in(['manual', 'unsubscribe', 'hard_bounce', 'complaint'])],
        ]);

        $suppression = $this->newsletters->suppress($club->id, $data['email'], $data['reason']);

        return response()->json(['data' => [
            'email' => $suppression->email,
            'reason' => $suppression->reason,
        ]], 201);
    }

    public function bounce(Request $request, Club $club, ClubNewsletterDelivery $delivery)
    {
        $this->authorizeManage($request, $club);
        $data = $request->validate([
            'bounce_type' => ['required', Rule::in(['hard', 'soft', 'complaint', 'unknown'])],
            'failure_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $delivery = $this->newsletters->markBounced($club, $delivery, $data);

        return response()->json(['data' => [
            'id' => $delivery->id,
            'status' => $delivery->status,
            'bounce_type' => $delivery->bounce_type,
        ]]);
    }

    private function authorizeManage(Request $request, Club $club): void
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::ANNOUNCEMENTS_PUBLISH), 403);
    }
}
