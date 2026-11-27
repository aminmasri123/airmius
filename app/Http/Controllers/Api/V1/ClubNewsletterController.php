<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\ClubNewsletterDelivery;
use App\Models\ClubNewsletterSuppression;
use App\Services\ClubNewsletterService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubNewsletterController extends Controller
{
    public function __construct(private readonly ClubNewsletterService $newsletters) {}

    public function subscribe(Request $request, Club $club)
    {
        abort_unless($club->verification_status === 'verified' && $club->is_listed, 404);

        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'name' => ['nullable', 'string', 'max:160'],
            'privacy_consent' => ['accepted'],
            'website' => ['nullable', 'max:0'],
        ]);

        [$subscription, $token] = $this->newsletters->requestOptIn($club, $data['email'], $data['name'] ?? null, $request->user());

        $message = match ($subscription->status) {
            'confirmed' => 'newsletter_already_subscribed',
            'pending' => $token === null ? 'newsletter_confirmation_pending' : 'newsletter_confirmation_required',
            default => 'newsletter_confirmation_required',
        };

        return response()->json([
            'message' => $message,
            'data' => [
                ...$this->newsletters->confirmationStatus($subscription),
                'confirmation_sent' => $token !== null,
            ],
        ], 202);
    }

    public function status(Request $request, Club $club)
    {
        return $this->accountStatusResponse($club, $this->newsletters->statusFor($club, $request->user()));
    }

    public function unsubscribeMine(Request $request, Club $club)
    {
        return $this->accountStatusResponse($club, $this->newsletters->unsubscribeFor($club, $request->user()));
    }

    public function subscribers(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        $request->validate(['subscribers_page' => ['nullable', 'integer', 'min:1']]);

        $payload = $this->newsletters->subscribers($club)->toArray();
        $payload['failures'] = ClubNewsletterDelivery::query()
            ->where('club_id', $club->id)
            ->where('status', 'bounced')
            ->latest('bounced_at')
            ->limit(30)
            ->get(['id', 'email', 'bounce_type', 'failure_reason', 'bounced_at']);
        $payload['suppressions'] = ClubNewsletterSuppression::query()
            ->where('club_id', $club->id)
            ->latest('suppressed_at')
            ->limit(30)
            ->get(['email', 'reason', 'suppressed_at']);
        $payload['recent_deliveries'] = ClubNewsletterDelivery::query()
            ->where('club_id', $club->id)
            ->latest('id')
            ->limit(30)
            ->get([
                'id',
                'email',
                'status',
                'sent_at',
                'bounced_at',
                'bounce_type',
                'failure_reason',
            ]);

        return response()->json($payload);
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

    private function accountStatusResponse(Club $club, array $status)
    {
        abort_unless($status['status'] !== 'none' || ($club->verification_status === 'verified' && $club->is_listed), 404);

        return response()->json(['data' => $status]);
    }

    private function authorizeManage(Request $request, Club $club): void
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::ANNOUNCEMENTS_PUBLISH), 403);
    }
}
