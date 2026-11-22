<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\ClubNewsletterCampaign;
use App\Models\ClubNewsletterSubscription;
use App\Models\ClubNewsletterDelivery;
use App\Models\ClubNewsletterSuppression;
use App\Services\ClubNewsletterService;
use App\Support\ClubPermissions;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClubNewsletterPageController extends Controller
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

        [$subscription, $token] = $this->newsletters->requestOptIn(
            $club,
            $data['email'],
            $data['name'] ?? null,
            $request->user(),
        );

        $request->session()->flash('newsletter_club_id', $club->id);
        $request->session()->flash('newsletter_status', $subscription->status);
        $request->session()->flash('newsletter_confirmation_sent', $token !== null);

        return back()->with('success', $subscription->status === 'confirmed'
            ? 'Der Newsletter ist bereits abonniert.'
            : ($token === null
                ? 'Die Bestätigung steht bereits aus. Bitte prüfe dein E-Mail-Postfach.'
                : 'Bestätigungsmail wurde gesendet. Bitte öffne den Link in der E-Mail.'));
    }

    public function confirm(string $token)
    {
        $subscription = $this->newsletters->confirm($token);

        return Inertia::render('Guest/NewsletterStatus', [
            'status' => $subscription ? 'confirmed' : 'invalid',
            'clubName' => $subscription?->club?->name,
        ]);
    }

    public function unsubscribe(string $token)
    {
        $subscription = $this->newsletters->unsubscribe($token);

        return Inertia::render('Guest/NewsletterStatus', [
            'status' => $subscription ? 'unsubscribed' : 'invalid',
            'clubName' => $subscription?->club?->name,
        ]);
    }

    public function show(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);

        $counts = ClubNewsletterSubscription::query()
            ->where('club_id', $club->id)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('Auth/Dashboard/Clubs/Newsletter', [
            'club' => $club->only(['id', 'name']),
            'subscribers' => $this->newsletters->subscribers($club),
            'counts' => [
                'confirmed' => (int) ($counts['confirmed'] ?? 0),
                'pending' => (int) ($counts['pending'] ?? 0),
                'unsubscribed' => (int) ($counts['unsubscribed'] ?? 0),
            ],
            'campaigns' => ClubNewsletterCampaign::query()
                ->where('club_id', $club->id)
                ->withCount('deliveries')
                ->latest('id')
                ->limit(20)
                ->get(['id', 'subject', 'status', 'sent_at', 'created_at']),
            'failures' => ClubNewsletterDelivery::query()
                ->where('club_id', $club->id)
                ->where('status', 'bounced')
                ->latest('bounced_at')
                ->limit(30)
                ->get(['id', 'email', 'bounce_type', 'failure_reason', 'bounced_at']),
            'suppressions' => ClubNewsletterSuppression::query()
                ->where('club_id', $club->id)
                ->latest('suppressed_at')
                ->limit(30)
                ->get(['email', 'reason', 'suppressed_at']),
        ]);
    }

    public function send(Request $request, Club $club)
    {
        $this->authorizeManage($request, $club);
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:10000'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);

        $campaign = $this->newsletters->sendCampaign($club, $request->user(), $data);

        return back()->with('success', $campaign->deliveries->count().' Newsletter wurden versendet.');
    }

    private function authorizeManage(Request $request, Club $club): void
    {
        abort_unless(ClubPermissions::allows($club, $request->user(), ClubPermissions::ANNOUNCEMENTS_PUBLISH), 403);
    }
}
