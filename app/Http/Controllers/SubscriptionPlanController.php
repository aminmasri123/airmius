<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        return Inertia::render('Auth/Dashboard/Admin/Subscriptions/Index', [
            'plans' => SubscriptionPlan::query()
                ->withCount(['clubSubscriptions', 'userSubscriptions'])
                ->orderBy('sort_order')
                ->get(),
            'clubs' => Club::query()
                ->with('currentSubscription.plan')
                ->withCount(['users', 'externalMembers', 'teams'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Club $club) => [
                    'id' => $club->id,
                    'name' => $club->name,
                    'users_count' => $club->users_count,
                    'external_members_count' => $club->external_members_count,
                    'teams_count' => $club->teams_count,
                    'member_usage' => $club->users_count + $club->external_members_count,
                    'plan' => $club->subscriptionPlan(),
                ]),
        ]);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $data = $request->validate([
            'target_actor' => ['required', Rule::in(['sportler', 'trainer', 'verein', 'eltern', 'sponsor', 'anbieter', 'enterprise'])],
            'description' => ['nullable', 'string', 'max:1000'],
            'monthly_price_cents' => ['required', 'integer', 'min:0', 'max:99999900'],
            'yearly_price_cents' => ['required', 'integer', 'min:0', 'max:99999900'],
            'member_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'team_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'storage_gb' => ['required', 'integer', 'min:1', 'max:1000000'],
            'cta_label' => ['nullable', 'string', 'max:80'],
            'badge' => ['nullable', 'string', 'max:80'],
            'is_public' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $subscriptionPlan->update($data);

        return back()->with('success', 'Abo-Plan aktualisiert.');
    }

    public function assignClub(Request $request, Club $club)
    {
        $data = $request->validate([
            'subscription_plan_id' => ['required', Rule::exists('subscription_plans', 'id')],
            'status' => ['required', Rule::in(['trialing', 'active', 'past_due', 'cancelled'])],
            'trial_ends_at' => ['nullable', 'date'],
            'current_period_ends_at' => ['nullable', 'date'],
        ]);

        $club->currentSubscription()->updateOrCreate(
            ['club_id' => $club->id],
            $data,
        );

        return back()->with('success', 'Vereins-Abo aktualisiert.');
    }
}
