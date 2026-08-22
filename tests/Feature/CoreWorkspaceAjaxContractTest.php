<?php

namespace Tests\Feature;

use Tests\TestCase;

class CoreWorkspaceAjaxContractTest extends TestCase
{
    public function test_primary_role_workspaces_do_not_force_full_page_reloads(): void
    {
        foreach ([
            'resources/js/Pages/Auth/Dashboard/Feed/Index.vue',
            'resources/js/composables/useNutritionWorkspace.js',
            'resources/js/composables/useEventsWorkspace.js',
            'resources/js/composables/useTrainingWorkspace.js',
            'resources/js/composables/useSportMapRoutePlanner.js',
            'resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue',
            'resources/js/composables/useCommerceWorkspace.js',
            'resources/js/composables/useCommerceProducts.js',
            'resources/js/Pages/Auth/Dashboard/SponsorWorkspace/Index.vue',
            'resources/js/Pages/Auth/Dashboard/OutfitSubscriptions/Index.vue',
        ] as $path) {
            $source = $this->source($path);

            $this->assertStringNotContainsString('location.reload(', $source, "Full reload found in {$path}");
            $this->assertStringNotContainsString('document.location', $source, "Document navigation found in {$path}");
        }
    }

    public function test_outfit_actions_update_the_visible_workspace_from_json_responses(): void
    {
        $outfit = $this->source('resources/js/Pages/Auth/Dashboard/OutfitSubscriptions/Index.vue');

        $this->assertStringContainsString("window.axios.put(route('auth.outfit-subscriptions.profile.update')", $outfit);
        $this->assertStringContainsString('replaceSubscription(response.data?.data)', $outfit);
        $this->assertStringContainsString('const freshDelivery = response.data?.data', $outfit);
        $this->assertStringContainsString('delivery.id === freshDelivery?.id ? freshDelivery : delivery', $outfit);
        $this->assertStringNotContainsString('router.reload(', $outfit);
        $this->assertStringNotContainsString('router.post(', $outfit);
    }

    public function test_marketplace_cart_updates_quantity_and_removal_from_json(): void
    {
        $cart = $this->source('resources/js/Pages/Auth/Dashboard/Commerce/Cart.vue');

        $this->assertStringContainsString("window.axios.put(route('auth.commerce.cart.items.update'", $cart);
        $this->assertStringContainsString("window.axios.delete(route('auth.commerce.cart.items.destroy'", $cart);
        $this->assertStringContainsString('localCart.value = response.data?.data', $cart);
        $this->assertStringNotContainsString('router.put(', $cart);
        $this->assertStringNotContainsString('router.delete(', $cart);
    }

    public function test_membership_import_updates_web_and_android_from_the_json_response(): void
    {
        $web = $this->source('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue');
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/club_membership_management_screen.dart');

        $this->assertStringContainsString("window.axios.post(\n            route('api.v1.clubs.members.import'", $web);
        $this->assertStringContainsString('Object.assign(selectedClub.value, management)', $web);
        $this->assertStringContainsString("payload.append('send_invitation'", $web);
        $this->assertStringNotContainsString("importForm.post(route('auth.club-memberships.email-members.import'", $web);

        $this->assertStringContainsString("request.fields['send_invitation']", $mobile);
        $this->assertStringContainsString('AirmiusClubManagement.fromJson(', $mobile);
        $this->assertStringContainsString('_applyManagement(', $mobile);
        $this->assertStringContainsString('bank-transactions/preview', $mobile);
        $this->assertStringNotContainsString('if (bank) _reloadClub();', $mobile);
    }

    public function test_uc23_request_and_member_edits_update_the_open_workspaces_directly(): void
    {
        $web = $this->source('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue');
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/club_membership_management_screen.dart');

        $this->assertStringContainsString('route(`api.v1.clubs.membership-requests.${decision}`', $web);
        $this->assertStringContainsString("route('api.v1.clubs.members.update'", $web);
        $this->assertStringContainsString('applyMembershipManagement(response.data?.management)', $web);
        $this->assertStringContainsString('applyMembershipManagement(response.data?.data)', $web);
        $this->assertStringNotContainsString("router.put(route('auth.club-memberships.members.update'", $web);
        $this->assertStringNotContainsString("router.post(route('auth.club-membership-requests.approve'", $web);

        foreach ([
            "'athlete_license_number': licenseNumber.text",
            "'contribution_next_invoice_on': nextInvoice.text",
            "'joined_on': joinedOn.text",
        ] as $contract) {
            $this->assertStringContainsString($contract, $mobile);
        }
    }

    public function test_uc25_invoice_payment_and_reminder_update_the_workspace_directly(): void
    {
        $web = $this->source('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue');
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/club_membership_management_screen.dart');

        foreach ([
            "route('api.v1.clubs.members.invoices.store'",
            "route('api.v1.clubs.membership-invoices.payments.store'",
            "route('api.v1.clubs.membership-invoices.status.update'",
            "route('api.v1.clubs.membership-invoices.reminder.store'",
        ] as $contract) {
            $this->assertStringContainsString($contract, $web);
        }

        $this->assertStringContainsString('billing_period_start: invoiceForm.billing_period_start', $web);
        $this->assertStringContainsString('applyMembershipManagement(response.data?.data)', $web);
        $this->assertStringNotContainsString("invoiceForm.post(route('auth.club-memberships.invoices.store'", $web);
        $this->assertStringNotContainsString("router.post(route('auth.club-memberships.invoices.payments.store'", $web);

        $this->assertStringContainsString("'billing_period_start': _dateInputForApi(periodStart.text)", $mobile);
        $this->assertStringContainsString('initialInvoiceId: invoice.id', $mobile);
        $this->assertStringContainsString('recordMembershipPayment(club.id, invoiceId, payload)', $mobile);
    }

    public function test_uc26_bank_import_previews_then_updates_web_and_android_directly(): void
    {
        $web = $this->source('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue');
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/club_membership_management_screen.dart');

        $this->assertStringContainsString("route('api.v1.clubs.bank-transactions.preview'", $web);
        $this->assertStringContainsString("route('api.v1.clubs.bank-transactions.import'", $web);
        $this->assertStringContainsString("route('api.v1.clubs.finance-entries.store'", $web);
        $this->assertStringContainsString("route('api.v1.clubs.finance-entries.update'", $web);
        $this->assertStringContainsString("route('api.v1.clubs.membership.sepa-settings.update'", $web);
        $this->assertStringContainsString("route('api.v1.clubs.membership.datev-settings.update'", $web);
        $this->assertStringContainsString("route('api.v1.clubs.bank-transactions.confirm'", $web);
        $this->assertStringContainsString('applyMembershipManagement(response.data?.data)', $web);
        $this->assertStringNotContainsString("bankImportForm.post(route('auth.club-memberships.bank-transactions.import'", $web);
        $this->assertStringNotContainsString("financeEntryForm.post(route('auth.club-memberships.finance-entries.store'", $web);
        $this->assertStringNotContainsString("router.put(route('auth.club-memberships.sepa-settings.update'", $web);
        $this->assertStringNotContainsString("router.put(route('auth.club-memberships.datev-settings.update'", $web);
        $this->assertStringNotContainsString("router.post(route('auth.club-memberships.bank-transactions.confirm'", $web);

        $this->assertStringContainsString("'/api/v1/clubs/\${club.id}/bank-transactions/preview'", $mobile);
        $this->assertStringContainsString('if (bank)', $mobile);
        $this->assertStringContainsString('_confirmBankImport(preview)', $mobile);
        $this->assertStringNotContainsString('if (bank) _reloadClub();', $mobile);
    }

    public function test_uc27_audit_and_plan_limits_are_clear_on_web_and_android(): void
    {
        $membership = $this->source('resources/js/Pages/Auth/Dashboard/ClubMemberships/Index.vue');
        $cockpit = $this->source('resources/js/Pages/Auth/Dashboard/ClubCockpit/Index.vue');
        $mobileMembership = $this->source('mobile/airmius_mobile/lib/screens/club_membership_management_screen.dart');
        $mobileCockpit = $this->source('mobile/airmius_mobile/lib/screens/club_cockpit_screen.dart');

        $this->assertStringContainsString('formatDateTime(entry.created_at)', $membership);
        $this->assertStringContainsString("t('Unbegrenzt')", $cockpit);
        $this->assertStringContainsString('Verwendet / Planlimit', $cockpit);
        $this->assertStringContainsString("'club.payment.recorded'", $mobileMembership);
        $this->assertStringContainsString('_createdAt(context, log[\'created_at\'])', $mobileMembership);
        $this->assertStringContainsString("t('clubHub.unlimited')", $mobileCockpit);
        $this->assertStringContainsString("subscription['storage_bytes']", $mobileCockpit);
    }

    public function test_uc28_opening_the_bell_preserves_unread_notifications_until_an_explicit_action(): void
    {
        $layout = $this->source('resources/js/Components/Auth/Layouts/AppLayout.vue');
        $center = $this->source('resources/js/Pages/Auth/Dashboard/Notifications/Index.vue');
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/notifications_center_screen.dart');

        $this->assertStringContainsString('const toggleNotifications = () => {', $layout);
        $this->assertStringNotContainsString("notificationOpen.value = !notificationOpen.value\n    markAllNotificationsAsRead()", $layout);
        $this->assertStringContainsString("t('notifications.mark_all_read')", $layout);
        $this->assertStringContainsString("route('auth.notifications.read-all')", $layout);
        $this->assertStringContainsString('@click.prevent="notification.data?.url ? openNotification(notification)', $layout);
        $this->assertStringContainsString('onFinish: () => router.visit(target)', $layout);
        $this->assertStringContainsString("tx('notifications.mark_all_read')", $center);
        $this->assertStringContainsString("only: ['notifications', 'notificationCenter', 'auth']", $center);
        $this->assertStringContainsString('@click.prevent="openNotification(notification)"', $center);
        $this->assertStringContainsString('onFinish: () => router.visit(target)', $center);
        $this->assertStringContainsString('repositories.notifications.markAllAsRead()', $mobile);
        $this->assertStringContainsString('repositories.notifications.markAsRead(widget.item.id)', $mobile);
        $this->assertStringContainsString('AirmiusDeepLinkNavigator.open(context, notification.actionUrl!)', $mobile);
    }

    public function test_uc29_sport_integrations_and_activities_update_inline_on_web_and_android(): void
    {
        $web = $this->source('resources/js/Pages/Auth/Dashboard/Settings/Index.vue');
        $mobile = $this->source('mobile/airmius_mobile/lib/screens/sport_integrations_screen.dart');
        $client = $this->source('mobile/airmius_mobile/lib/core/airmius_api_client.dart');

        foreach ([
            "route('api.v1.sport-integrations.request'",
            "route('api.v1.sport-integrations.sync'",
            "route('api.v1.sport-integrations.disconnect'",
            "route('api.v1.sport-integrations.activities.update'",
            "route('api.v1.sport-integrations.activities.destroy'",
            'upsertSportActivity(response.data?.data?.activity)',
            'sportIntegrationState.value.activities =',
        ] as $contract) {
            $this->assertStringContainsString($contract, $web);
        }

        $this->assertStringNotContainsString("sportActivityEditForm.put(route('auth.sport-activities.update'", $web);
        $this->assertStringNotContainsString("router.delete(route('auth.sport-activities.destroy'", $web);
        $this->assertStringContainsString('_upsertActivity(', $mobile);
        $this->assertStringContainsString('AirmiusSportIntegrationActivity.fromJson(activityData)', $mobile);
        $this->assertStringContainsString('_removeActivity(activity.id)', $mobile);
        $this->assertStringNotContainsString("scope.t('fitness.activityImported')),\n      );\n      _reload();", $mobile);
        $this->assertStringContainsString("'/api/v1/sport-integrations/activities/\$activityId'", $client);
    }

    public function test_commerce_mutations_preserve_the_workspace_and_close_dialogs_after_success(): void
    {
        $commerce = $this->source('resources/js/composables/useCommerceWorkspace.js');
        $products = $this->source('resources/js/composables/useCommerceProducts.js');

        foreach ([
            "route('auth.commerce.campaigns.store')",
            "route('auth.commerce.campaigns.groups.store'",
            "route('auth.commerce.campaigns.groups.creatives.store'",
            "route('auth.commerce.campaigns.update'",
            "route('auth.commerce.provider-profile.store')",
            "route('auth.commerce.provider-locations.store')",
        ] as $route) {
            $this->assertStringContainsString($route, $commerce);
        }

        foreach (['onSuccess: closeAdGroupModal', 'onSuccess: closeAdCreativeModal', 'onSuccess: closeEditCampaignModal'] as $callback) {
            $this->assertStringContainsString($callback, $commerce);
        }

        foreach ([
            "route('auth.commerce.products.store')",
            "route('auth.commerce.my-products.update'",
            "route('auth.commerce.website-requests.store')",
        ] as $route) {
            $this->assertStringContainsString($route, $products);
        }

        $this->assertGreaterThanOrEqual(8, substr_count($commerce.$products, 'preserveScroll: true'));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(base_path($path));
        $this->assertIsString($source);

        return $source;
    }
}
