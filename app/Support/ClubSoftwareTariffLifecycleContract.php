<?php

namespace App\Support;

final class ClubSoftwareTariffLifecycleContract
{
    public const VERSION = '2026-09-26.club-software-tariff-lifecycle.v1';

    public static function forClient(): array
    {
        return [
            'version' => self::VERSION,
            'tariff_source' => [
                'model' => 'SubscriptionPlan',
                'public_filter' => ['is_active' => true, 'is_public' => true],
                'target_actor_for_clubs' => 'verein',
                'localized_price_source' => 'SubscriptionPlan::priceForCountry',
                'contract_terms' => [
                    'minimum_term_months',
                    'cancellation_notice_days',
                    'monthly_price_cents',
                    'yearly_price_cents',
                    'currency',
                ],
            ],
            'checkout' => [
                'endpoint' => '/api/v1/subscription-plans/{subscriptionPlan}/checkout',
                'accepted_terms_required' => true,
                'club_checkout_permission' => ClubPermissions::SUBSCRIPTIONS_EDIT,
                'providers' => ['bank_transfer', 'stripe', 'paypal'],
                'bank_transfer_creates_reference' => true,
                'provider_cancel_url_is_signed' => true,
                'invoice_created_with_checkout' => true,
                'provider_failure_marks_checkout_failed' => true,
            ],
            'activation' => [
                'source' => 'SubscriptionCheckoutActivationService',
                'checkout_statuses' => ['pending', 'awaiting_transfer', 'paid', 'failed', 'cancelled'],
                'subscription_statuses' => [
                    'trialing',
                    'active',
                    'past_due',
                    'cancels_at_period_end',
                    'cancelled',
                ],
                'trial_access_uses_trial_ends_at' => true,
                'period_and_next_invoice_are_written_on_activation' => true,
            ],
            'entitlements' => [
                'source' => 'PlanFeatureService',
                'club_resource_field' => 'subscription_capabilities',
                'access_scope' => 'HasSubscriptionEntitlements::grantingAccess',
                'feature_gate_method' => 'PlanFeatureService::ensureAllows',
                'free_plan_fallback_after_access_ends' => true,
                'granting_statuses' => [
                    'active',
                    'trialing_before_trial_ends_at',
                    'cancels_at_period_end_before_cancels_at',
                    'past_due_before_grace_period_ends_at',
                ],
            ],
            'recurring_lifecycle' => [
                'command' => 'airmius:process-subscription-lifecycle',
                'generates_recurring_invoices' => true,
                'marks_overdue_invoices' => true,
                'sets_past_due_with_grace_period' => true,
                'restricts_access_after_grace_period' => true,
                'finalizes_due_cancellations' => true,
                'localized_notifications' => true,
            ],
            'cancellation' => [
                'source' => 'SubscriptionLifecycleService',
                'modes' => ['period_end', 'now'],
                'contractual_date_uses_period_notice_and_minimum_term' => true,
                'provider_sync_on_cancel_and_resume' => true,
                'idempotent_customer_actions' => true,
                'paid_access_preserved_until_contractual_end' => true,
            ],
            'security' => [
                'subscriptions_view_permission' => ClubPermissions::SUBSCRIPTIONS_VIEW,
                'subscriptions_edit_permission' => ClubPermissions::SUBSCRIPTIONS_EDIT,
                'explicit_denials_override_legacy_billing_roles' => true,
                'foreign_club_checkout_rejected' => true,
                'admin_mark_paid_requires_subscription_management' => true,
                'payment_actions_are_throttled' => true,
            ],
            'decision' => 'local_contract_ready',
        ];
    }
}
