import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'airmius_design_system_screen.dart';
import 'admin_club_verification_screen.dart';
import 'admin_commerce_center_screen.dart';
import 'admin_finance_billing_center_screen.dart';
import 'admin_mail_center_screen.dart';
import 'admin_platform_settings_screen.dart';
import 'admin_provider_contracts_screen.dart';
import 'admin_subscription_outfit_center_screen.dart';
import 'admin_user_management_screen.dart';
import 'data_rights_request_screen.dart';
import 'app_onboarding_screen.dart';
import 'guest_ad_agency_screen.dart';
import 'club_policy_documents_screen.dart';
import 'club_profile_editor_screen.dart';
import 'club_reports_analytics_screen.dart';
import 'club_request_inbox_screen.dart';
import 'club_role_permissions_screen.dart';
import 'club_setup_onboarding_screen.dart';
import 'club_visibility_settings_screen.dart';
import 'club_contribution_rules_screen.dart';
import 'club_communication_center_screen.dart';
import 'club_document_upload_manager_screen.dart';
import 'club_finance_cockpit_screen.dart';
import 'club_member_directory_screen.dart';
import 'club_membership_form_builder_screen.dart';
import 'club_team_admin_screen.dart';
import 'club_event_attendance_screen.dart';
import 'membership_application_form_screen.dart';
import 'membership_request_status_screen.dart';
import 'legal_status_center_screen.dart';
import 'guest_jobs_careers_screen.dart';
import 'guest_pricing_plans_screen.dart';
import 'guest_blog_content_screen.dart';
import 'guest_learning_certificate_screen.dart';
import 'guest_marketplace_buyer_screen.dart';
import 'guest_sponsors_gamification_screen.dart';
import 'localization_center_screen.dart';
import 'privacy_consent_center_screen.dart';
import 'guardian_family_consent_screen.dart';
import 'ui_coverage_screen.dart';
import 'report_moderation_center_screen.dart';
import 'rides_carpool_planner_screen.dart';
import 'support_helpdesk_screen.dart';
import 'system_admin_operations_screen.dart';
import 'web_route_parity_screen.dart';
import 'workspace_collaboration_screen.dart';
import 'maturity_media_guidelines_screen.dart';
import 'guardian_access_portal_screen.dart';
import 'public_system_pages_screen.dart';
import 'auth_recovery_security_screen.dart';
import 'guest_marketplace_flow_screen.dart';
import 'profile_account_forms_screen.dart';
import 'public_growth_guest_pages_screen.dart';
import 'admin_finance_contract_suite_screen.dart';
import 'dashboard_action_flows_screen.dart';
import 'content_blog_editorial_suite_screen.dart';
import 'learning_studio_course_suite_screen.dart';
import 'sports_training_wellbeing_suite_screen.dart';
import 'gamification_badges_roles_suite_screen.dart';
import 'communication_files_notifications_suite_screen.dart';
import 'trust_moderation_admin_control_suite_screen.dart';
import 'commerce_subscription_outfit_suite_screen.dart';
import 'club_membership_lifecycle_suite_screen.dart';
import 'auth_api_entry_suite_screen.dart';
import 'app_shell_localization_quality_suite_screen.dart';
import 'public_interest_ads_sponsor_suite_screen.dart';
import 'finance_billing_member_payment_suite_screen.dart';
import 'feed_community_social_suite_screen.dart';
import 'search_directory_discovery_suite_screen.dart';
import 'web_parity_release_audit_suite_screen.dart';
import 'web_route_parity_matrix_suite_screen.dart';
import 'mobile_state_form_error_suite_screen.dart';
import 'api_binding_readiness_suite_screen.dart';
import 'mobile_web_fidelity_accessibility_suite_screen.dart';
import 'role_based_app_experience_suite_screen.dart';
import 'store_device_qa_readiness_suite_screen.dart';
import 'auth_guard_status_suite_screen.dart';
import 'exact_page_flow_parity_suite_screen.dart';
import 'navigation_menu_parity_suite_screen.dart';
import 'mobile_table_action_parity_suite_screen.dart';
import 'media_upload_attachment_parity_suite_screen.dart';
import 'modal_sheet_overlay_parity_suite_screen.dart';
import 'offline_sync_cache_parity_suite_screen.dart';
import 'analytics_chart_dashboard_parity_suite_screen.dart';
import 'map_location_route_parity_suite_screen.dart';
import 'push_notification_deeplink_parity_suite_screen.dart';
import 'state_feedback_parity_suite_screen.dart';
import 'input_keyboard_accessibility_parity_suite_screen.dart';
import 'brand_theme_token_parity_suite_screen.dart';
import 'localization_rtl_format_parity_suite_screen.dart';
import 'device_permission_privacy_parity_suite_screen.dart';
import 'end_to_end_journey_parity_suite_screen.dart';
import 'session_security_token_parity_suite_screen.dart';
import 'web_app_full_conversion_control_screen.dart';
import 'web_app_module_completion_suite_screen.dart';
import 'club_visibility_rules_suite_screen.dart';
import 'club_membership_requirements_builder_screen.dart';
import 'club_application_inbox_suite_screen.dart';
import 'club_dues_payment_rules_suite_screen.dart';
import 'club_member_onboarding_acceptance_suite_screen.dart';
import 'club_document_consent_file_manager_suite_screen.dart';
import 'notification_delivery_preferences_suite_screen.dart';
import 'global_search_directory_suite_screen.dart';
import 'calendar_event_rsvp_suite_screen.dart';
import 'team_roster_role_assignment_suite_screen.dart';
import 'member_self_service_center_suite_screen.dart';
import 'support_ticket_service_center_suite_screen.dart';
import 'finance_invoice_receipt_center_suite_screen.dart';
import 'feed_community_composer_suite_screen.dart';
import 'messaging_conversation_center_suite_screen.dart';
import 'profile_privacy_visibility_suite_screen.dart';
import 'admin_moderation_audit_queue_suite_screen.dart';
import 'marketplace_order_fulfillment_suite_screen.dart';
import 'sponsor_campaign_management_suite_screen.dart';
import 'analytics_reporting_kpi_suite_screen.dart';
import 'learning_course_progress_certificate_suite_screen.dart';
import 'gamification_badge_achievement_suite_screen.dart';
import 'health_incident_report_suite_screen.dart';
import 'location_map_facility_suite_screen.dart';
import 'club_member_import_export_suite_screen.dart';
import 'content_publishing_cms_suite_screen.dart';
import 'role_workspace_switcher_suite_screen.dart';
import 'app_onboarding_permission_suite_screen.dart';
import 'api_state_empty_error_suite_screen.dart';
import 'mobile_form_validation_schema_suite_screen.dart';
import 'native_store_release_assets_suite_screen.dart';
import 'club_public_profile_preview_suite_screen.dart';
import 'laravel_api_endpoint_mapping_suite_screen.dart';
import 'subscription_entitlement_feature_gate_suite_screen.dart';
import 'audit_activity_timeline_suite_screen.dart';
import 'integration_webhook_provider_suite_screen.dart';
import 'system_job_queue_monitor_suite_screen.dart';
import 'system_status_incident_center_suite_screen.dart';
import 'draft_autosave_recovery_suite_screen.dart';
import 'invitation_access_link_suite_screen.dart';
import 'consent_signature_versioning_suite_screen.dart';
import 'digital_member_card_checkin_suite_screen.dart';
import 'deep_link_route_resolver_suite_screen.dart';
import 'role_home_dashboard_widget_suite_screen.dart';
import 'saved_view_search_alert_suite_screen.dart';
import 'cross_module_approval_workflow_suite_screen.dart';
import 'facility_booking_resource_scheduler_suite_screen.dart';
import 'availability_absence_planning_suite_screen.dart';
import 'volunteer_shift_task_planner_suite_screen.dart';
import 'club_survey_poll_voting_suite_screen.dart';
import 'meeting_minutes_decision_log_suite_screen.dart';
import 'club_asset_inventory_checkout_suite_screen.dart';
import 'sponsor_lead_crm_pipeline_suite_screen.dart';
import 'training_plan_periodization_suite_screen.dart';
import 'member_feedback_satisfaction_suite_screen.dart';
import 'legal_policy_rollout_suite_screen.dart';
import 'mobile_visual_parity_progress_audit_suite_screen.dart';
import 'laravel_api_binding_progress_suite_screen.dart';
import 'api_data_model_repository_suite_screen.dart';
import 'api_repository_binding_suite_screen.dart';
import 'auth_state_token_store_suite_screen.dart';
import 'service_container_transport_suite_screen.dart';
import 'http_transport_release_suite_screen.dart';
import 'store_release_configuration_suite_screen.dart';

class ReleaseReadinessScreen extends StatefulWidget {
  const ReleaseReadinessScreen({super.key});

  @override
  State<ReleaseReadinessScreen> createState() => _ReleaseReadinessScreenState();
}

class _ReleaseReadinessScreenState extends State<ReleaseReadinessScreen> {
  String _filter = 'Alle';

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final items = _filter == 'Alle' ? _items : _items.where((item) => item.area == _filter).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(scope.t('release.title'), style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: scope.t('release.title'),
        subtitle: scope.t('release.subtitle'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const AirmiusLogo(),
            const SizedBox(height: 14),
            Text(scope.t('release.heroTitle'), style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
            const SizedBox(height: 8),
            Text(scope.t('release.heroBody'), style: const TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: 'DE/EN/FR/AR', label: 'Sprachen')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'UI', label: 'Flutter')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'API', label: 'Laravel'))]),
            const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(label: scope.t('release.localization'), icon: Icons.language_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LocalizationCenterScreen()))),
              AirmiusButton(label: scope.t('release.coverage'), icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiCoverageScreen()))),
              AirmiusButton(label: scope.t('release.designSystem'), icon: Icons.palette_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AirmiusDesignSystemScreen()))),
                AirmiusButton(label: scope.t('release.privacyConsent'), icon: Icons.privacy_tip_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PrivacyConsentCenterScreen()))),
                AirmiusButton(label: scope.t('release.legalStatus'), icon: Icons.gavel_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LegalStatusCenterScreen()))),
                AirmiusButton(label: scope.t('release.guardianConsent'), icon: Icons.family_restroom_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuardianFamilyConsentScreen()))),
                AirmiusButton(label: scope.t('release.dataRights'), icon: Icons.manage_search_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DataRightsRequestScreen()))),
                AirmiusButton(label: scope.t('release.supportHelpdesk'), icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
              AirmiusButton(label: scope.t('release.reportModeration'), icon: Icons.flag_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ReportModerationCenterScreen()))),
              AirmiusButton(label: scope.t('release.routeParity'), icon: Icons.route_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WebRouteParityScreen()))),
              AirmiusButton(label: scope.t('release.storeDeviceQaReadinessSuite'), icon: Icons.mobile_friendly_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StoreDeviceQaReadinessSuiteScreen()))),
              AirmiusButton(label: scope.t('release.authGuardStatusSuite'), icon: Icons.security_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AuthGuardStatusSuiteScreen()))),
              AirmiusButton(label: scope.t('release.exactPageFlowParitySuite'), icon: Icons.view_list_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ExactPageFlowParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.navigationMenuParitySuite'), icon: Icons.menu_open_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NavigationMenuParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.mobileTableActionParitySuite'), icon: Icons.table_rows_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MobileTableActionParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.mediaUploadAttachmentParitySuite'), icon: Icons.cloud_upload_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MediaUploadAttachmentParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.modalSheetOverlayParitySuite'), icon: Icons.layers_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ModalSheetOverlayParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.offlineSyncCacheParitySuite'), icon: Icons.sync_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OfflineSyncCacheParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.analyticsChartDashboardParitySuite'), icon: Icons.insights_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AnalyticsChartDashboardParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.mapLocationRouteParitySuite'), icon: Icons.map_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MapLocationRouteParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.pushNotificationDeeplinkParitySuite'), icon: Icons.notifications_none_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PushNotificationDeeplinkParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.stateFeedbackParitySuite'), icon: Icons.task_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StateFeedbackParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.inputKeyboardAccessibilityParitySuite'), icon: Icons.keyboard_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InputKeyboardAccessibilityParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.brandThemeTokenParitySuite'), icon: Icons.palette_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BrandThemeTokenParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.localizationRtlFormatParitySuite'), icon: Icons.translate_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LocalizationRtlFormatParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.devicePermissionPrivacyParitySuite'), icon: Icons.privacy_tip_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DevicePermissionPrivacyParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.endToEndJourneyParitySuite'), icon: Icons.route_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EndToEndJourneyParitySuiteScreen()))),
              AirmiusButton(label: scope.t('release.sessionSecurityTokenParitySuite'), icon: Icons.security_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SessionSecurityTokenParitySuiteScreen()))),
                    AirmiusButton(label: scope.t('release.webAppConversionControl'), icon: Icons.dashboard_customize_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WebAppFullConversionControlScreen()))),
                    AirmiusButton(label: scope.t('release.webAppModuleCompletion'), icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WebAppModuleCompletionSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.clubVisibilityRules'), icon: Icons.rule_folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubVisibilityRulesSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.membershipRequirementsBuilder'), icon: Icons.format_list_bulleted_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMembershipRequirementsBuilderScreen()))),
                    AirmiusButton(label: scope.t('release.clubApplicationInbox'), icon: Icons.inbox_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubApplicationInboxSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.clubDuesPaymentRules'), icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubDuesPaymentRulesSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.memberOnboardingAcceptance'), icon: Icons.check_circle_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberOnboardingAcceptanceSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.documentConsentFileManager'), icon: Icons.folder_copy_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubDocumentConsentFileManagerSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.notificationDeliveryPreferences'), icon: Icons.notifications_active_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationDeliveryPreferencesSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.globalSearchDirectory'), icon: Icons.manage_search_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GlobalSearchDirectorySuiteScreen()))),
                    AirmiusButton(label: scope.t('release.calendarEventRsvp'), icon: Icons.event_available_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CalendarEventRsvpSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.teamRosterRoleAssignment'), icon: Icons.groups_2_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamRosterRoleAssignmentSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.memberSelfServiceCenter'), icon: Icons.badge_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MemberSelfServiceCenterSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.supportTicketServiceCenter'), icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportTicketServiceCenterSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.financeInvoiceReceiptCenter'), icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceInvoiceReceiptCenterSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.feedCommunityComposer'), icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FeedCommunityComposerSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.messagingConversationCenter'), icon: Icons.chat_bubble_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MessagingConversationCenterSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.profilePrivacyVisibility'), icon: Icons.privacy_tip_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProfilePrivacyVisibilitySuiteScreen()))),
                    AirmiusButton(label: scope.t('release.adminModerationAuditQueue'), icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AdminModerationAuditQueueSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.marketplaceOrderFulfillment'), icon: Icons.storefront_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MarketplaceOrderFulfillmentSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.sponsorCampaignManagement'), icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorCampaignManagementSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.analyticsReportingKpi'), icon: Icons.insights_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AnalyticsReportingKpiSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.learningCourseProgressCertificate'), icon: Icons.school_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LearningCourseProgressCertificateSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.gamificationBadgeAchievement'), icon: Icons.emoji_events_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GamificationBadgeAchievementSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.healthIncidentReports'), icon: Icons.health_and_safety_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => HealthIncidentReportSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.locationMapFacilities'), icon: Icons.map_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LocationMapFacilitySuiteScreen()))),
                    AirmiusButton(label: scope.t('release.clubMemberImportExport'), icon: Icons.sync_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberImportExportSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.contentPublishingCms'), icon: Icons.article_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ContentPublishingCmsSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.roleWorkspaceSwitcher'), icon: Icons.switch_account_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RoleWorkspaceSwitcherSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.appOnboardingPermissions'), icon: Icons.phone_iphone_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AppOnboardingPermissionSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.apiStateEmptyError'), icon: Icons.sync_problem_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ApiStateEmptyErrorSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.mobileFormValidationSchema'), icon: Icons.rule_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MobileFormValidationSchemaSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.nativeStoreReleaseAssets'), icon: Icons.store_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NativeStoreReleaseAssetsSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.clubPublicProfilePreview'), icon: Icons.apartment_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPublicProfilePreviewSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.laravelApiEndpointMapping'), icon: Icons.hub_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LaravelApiEndpointMappingSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.subscriptionEntitlementFeatureGate'), icon: Icons.workspace_premium_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SubscriptionEntitlementFeatureGateSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.auditActivityTimeline'), icon: Icons.history_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AuditActivityTimelineSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.integrationWebhookProvider'), icon: Icons.webhook_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => IntegrationWebhookProviderSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.systemJobQueueMonitor'), icon: Icons.pending_actions_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemJobQueueMonitorSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.systemStatusIncidentCenter'), icon: Icons.monitor_heart_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemStatusIncidentCenterSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.draftAutosaveRecovery'), icon: Icons.restore_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DraftAutosaveRecoverySuiteScreen()))),
                    AirmiusButton(label: scope.t('release.invitationAccessLinks'), icon: Icons.qr_code_2_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InvitationAccessLinkSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.consentSignatureVersioning'), icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ConsentSignatureVersioningSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.digitalMemberCardCheckin'), icon: Icons.badge_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DigitalMemberCardCheckinSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.deepLinkRouteResolver'), icon: Icons.route_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DeepLinkRouteResolverSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.roleHomeDashboardWidgets'), icon: Icons.dashboard_customize_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RoleHomeDashboardWidgetSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.savedViewsSearchAlerts'), icon: Icons.bookmark_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SavedViewSearchAlertSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.crossModuleApprovalWorkflow'), icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CrossModuleApprovalWorkflowSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.facilityBookingResourceScheduler'), icon: Icons.event_available_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FacilityBookingResourceSchedulerSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.availabilityAbsencePlanning'), icon: Icons.how_to_reg_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AvailabilityAbsencePlanningSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.volunteerShiftTaskPlanner'), icon: Icons.assignment_turned_in_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => VolunteerShiftTaskPlannerSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.clubSurveyPollVoting'), icon: Icons.how_to_vote_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubSurveyPollVotingSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.meetingMinutesDecisionLog'), icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MeetingMinutesDecisionLogSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.clubAssetInventoryCheckout'), icon: Icons.inventory_2_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubAssetInventoryCheckoutSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.sponsorLeadCrmPipeline'), icon: Icons.handshake_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorLeadCrmPipelineSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.trainingPlanPeriodization'), icon: Icons.fitness_center_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainingPlanPeriodizationSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.memberFeedbackSatisfaction'), icon: Icons.sentiment_satisfied_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MemberFeedbackSatisfactionSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.legalPolicyRollout'), icon: Icons.gavel_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LegalPolicyRolloutSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.mobileVisualParityProgressAudit'), icon: Icons.speed_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MobileVisualParityProgressAuditSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.laravelApiBindingProgress'), icon: Icons.cloud_sync_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LaravelApiBindingProgressSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.apiDataModelRepository'), icon: Icons.data_object_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ApiDataModelRepositorySuiteScreen()))),
                    AirmiusButton(label: scope.t('release.apiRepositoryBinding'), icon: Icons.alt_route_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ApiRepositoryBindingSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.authStateTokenStore'), icon: Icons.security_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AuthStateTokenStoreSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.serviceContainerTransport'), icon: Icons.settings_ethernet_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ServiceContainerTransportSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.httpTransportRelease'), icon: Icons.cloud_sync_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => HttpTransportReleaseSuiteScreen()))),
                    AirmiusButton(label: scope.t('release.storeReleaseConfiguration'), icon: Icons.app_settings_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StoreReleaseConfigurationSuiteScreen()))),
                AirmiusButton(label: scope.t('release.guestJobs'), icon: Icons.work_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestJobsCareersScreen()))),
                AirmiusButton(label: scope.t('release.guestPricing'), icon: Icons.price_change_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestPricingPlansScreen()))),
                AirmiusButton(label: scope.t('release.guestMarketplace'), icon: Icons.storefront_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestMarketplaceBuyerScreen()))),
                AirmiusButton(label: scope.t('release.guestLearning'), icon: Icons.school_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestLearningCertificateScreen()))),
                AirmiusButton(label: scope.t('release.guestBlog'), icon: Icons.article_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestBlogContentScreen()))),
                AirmiusButton(label: scope.t('release.guestSponsorsGame'), icon: Icons.emoji_events_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestSponsorsGamificationScreen()))),
                AirmiusButton(label: scope.t('release.guestAdAgency'), icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestAdAgencyScreen()))),
              AirmiusButton(label: scope.t('release.systemAdmin'), icon: Icons.settings_suggest_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemAdminOperationsScreen()))),
              AirmiusButton(label: scope.t('onboarding.title'), icon: Icons.rocket_launch_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AppOnboardingScreen()))),
              AirmiusButton(label: scope.t('release.clubPolicies'), icon: Icons.rule_folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPolicyDocumentsScreen()))),
              AirmiusButton(label: scope.t('release.requestInbox'), icon: Icons.inbox_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubRequestInboxScreen()))),
                AirmiusButton(label: scope.t('release.membershipFormBuilder'), icon: Icons.format_list_bulleted_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMembershipFormBuilderScreen()))),
                AirmiusButton(label: scope.t('release.membershipApplicationForm'), icon: Icons.assignment_add, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipApplicationFormScreen()))),
                AirmiusButton(label: scope.t('release.membershipRequestStatus'), icon: Icons.assignment_turned_in_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipRequestStatusScreen()))),
                AirmiusButton(label: scope.t('release.clubRoles'), icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubRolePermissionsScreen()))),
                AirmiusButton(label: scope.t('release.clubCommunication'), icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubCommunicationCenterScreen()))),
              AirmiusButton(label: scope.t('release.clubVisibility'), icon: Icons.visibility_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubVisibilitySettingsScreen()))),
              AirmiusButton(label: scope.t('release.clubProfile'), icon: Icons.edit_note_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubProfileEditorScreen()))),
                AirmiusButton(label: scope.t('release.clubSetup'), icon: Icons.rocket_launch_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubSetupOnboardingScreen()))),
              AirmiusButton(label: scope.t('release.contributionRules'), icon: Icons.payments_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubContributionRulesScreen()))),
              AirmiusButton(label: scope.t('release.clubFinance'), icon: Icons.account_balance_wallet_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubFinanceCockpitScreen()))),
                AirmiusButton(label: scope.t('release.clubReports'), icon: Icons.insights_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubReportsAnalyticsScreen()))),
              AirmiusButton(label: scope.t('release.clubMembers'), icon: Icons.people_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberDirectoryScreen()))),
                    AirmiusButton(label: scope.t('release.clubTeams'), icon: Icons.diversity_3_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubTeamAdminScreen()))),
            ],
          ),
          ])),
          const SizedBox(height: 16),
          AirmiusPanel(child: Wrap(spacing: 8, runSpacing: 8, children: [
            for (final area in _areas)
              ChoiceChip(label: Text(area), selected: _filter == area, onSelected: (_) => setState(() => _filter = area), selectedColor: AirmiusColors.blue.withValues(alpha: .22), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _filter == area ? AirmiusColors.blue : AirmiusColors.border), labelStyle: TextStyle(color: _filter == area ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900)),
          ])),
          const SizedBox(height: 16),
          for (final item in items) ...[
            _ReadinessCard(item: item),
            const SizedBox(height: 12),
          ],
        ]),
      ),
    );
  }
}

class _ReadinessCard extends StatelessWidget {
  const _ReadinessCard({required this.item});

  final _ReadinessItem item;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: item.color.withValues(alpha: .42), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 50, height: 50, decoration: BoxDecoration(color: item.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .46))), child: Icon(item.icon, color: item.color)),
      const SizedBox(width: 14),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 5),
        Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.area, color: item.color), StatusPill(item.status, color: item.color), StatusPill(item.owner)]),
      ])),
    ]),
    const SizedBox(height: 12),
    LinearProgressIndicator(value: item.progress, minHeight: 8, borderRadius: BorderRadius.circular(99), backgroundColor: AirmiusColors.panelSoft, valueColor: AlwaysStoppedAnimation<Color>(item.color)),
  ]));
}

class _ReadinessItem {
  const _ReadinessItem({required this.area, required this.title, required this.body, required this.status, required this.owner, required this.progress, required this.icon, required this.color});
  final String area;
  final String title;
  final String body;
  final String status;
  final String owner;
  final double progress;
  final IconData icon;
  final Color color;
}

const _areas = ['Alle', 'UI', 'Sprache', 'Store', 'API', 'Safety', 'Betrieb'];

const _items = <_ReadinessItem>[
  _ReadinessItem(area: 'UI', title: 'Mobile Web-App Design', body: 'Dunkles Airmius-Layout, Panels, Pills, Bottom Navigation, Drawer, Logo und mobile Formulare sind als Flutter-Designsystem vorbereitet.', status: 'Bereit', owner: 'Flutter', progress: .92, icon: Icons.phone_iphone_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'UI', title: 'Design-System', body: 'Airmius-Web-App-Patterns für Header, Club-Hero, Panels, Formulare, Tabellenersatz, Modals, Empty/Loading/Error und Bottom Navigation sind als native Vorschau sichtbar.', status: 'Neu', owner: 'Design', progress: .86, icon: Icons.palette_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'UI', title: 'Modulabdeckung', body: 'Alle großen Webmodule sind in Center-, Detail- oder Operations-Screens erreichbar und in der Coverage sichtbar.', status: 'Breit', owner: 'Modules', progress: .88, icon: Icons.dashboard_customize_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'UI', title: 'Web Route Parity', body: 'Laravel-Webrouten sind in Public, Auth, Club, Social, Sport, Commerce, Admin und Ops gegen native Flutter-UI kartiert.', status: 'Neu', owner: 'Parity', progress: .74, icon: Icons.route_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'UI', title: 'Auth Guard Status', body: 'Complete Profile, Verify Email, Suspended, 2FA, Guardian Pending, Forbidden und Maintenance sind als native Status- und Entscheidungsflows vorbereitet.', status: 'Neu', owner: 'Auth UX', progress: .8, icon: Icons.security_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'UI', title: 'Exact Page Flow Parity', body: 'Show-, Create-, Edit-, Detail-, Checkout-, BankTransfer- und Statusseiten sind als mobile Flow-Karten für die Web-App-Paritaet vorbereitet.', status: 'Neu', owner: 'Page UX', progress: .78, icon: Icons.view_list_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'UI', title: 'Navigation Menu Parity', body: 'Logo, Header-Suche, Sidebar/Drawer-Gruppen, Rollen, Workspaces, Modulbadges und Bottom Navigation sind als mobile App-Shell modelliert.', status: 'Neu', owner: 'Shell UX', progress: .82, icon: Icons.menu_open_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'UI', title: 'Mobile Table Actions', body: 'Desktop-Tabellen werden als mobile Karten, Filterchips, Sortierung, Bulk-Bar, Export-CTA und sichere Action-Sheets vorbereitet.', status: 'Neu', owner: 'List UX', progress: .8, icon: Icons.table_rows_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'UI', title: 'Media Upload Attachments', body: 'Dateien, Bilder, Scans, Chat-Anhaenge, Vereinsdokumente, Produktbilder, Blogmedien und Trainingsnachweise sind als mobile Upload-Flows vorbereitet.', status: 'Neu', owner: 'Media UX', progress: .78, icon: Icons.cloud_upload_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'UI', title: 'Modal Sheet Overlays', body: 'Web-Modals, Confirmations, Filter-Sheets, Preview-Overlays, Drawer und lange Formulare sind als mobile Overlay-Muster vorbereitet.', status: 'Neu', owner: 'Overlay UX', progress: .8, icon: Icons.layers_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'API', title: 'Offline Sync Cache', body: 'Offline-Banner, lokale Drafts, Sync-Queue, Retry, Upload Resume, API-Fehler, Konflikte und Sync-Historie sind als mobile Status-UI vorbereitet.', status: 'Neu', owner: 'Client API', progress: .72, icon: Icons.sync_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'UI', title: 'Analytics Dashboards', body: 'Web-Reports, KPI-Karten, Mini-Charts, Trends, Exporte und Loading/Empty/Error-Zustaende sind als mobile Dashboard-UI vorbereitet.', status: 'Neu', owner: 'Analytics UX', progress: .78, icon: Icons.insights_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'UI', title: 'Map Location Routes', body: 'Sportkarte, Events, Fahrgemeinschaften, Vereinsadresse, Public-Orte, Routen, Permissions, Datenschutz und Offline-Karten sind als mobile UI vorbereitet.', status: 'Neu', owner: 'Location UX', progress: .76, icon: Icons.map_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'API', title: 'Push Notification Deep Links', body: 'Push, In-App, E-Mail, Chat, App-Badges, Ruhezeiten, Notification-Routing und Deep Links sind als mobile UI- und API-Zustaende vorbereitet.', status: 'Neu', owner: 'Notify UX', progress: .76, icon: Icons.notifications_none_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'UI', title: 'State Feedback Parity', body: 'Skeleton Loading, Empty, Error, Success, Unauthorized, Rate Limit, Retry, Cache-Hinweise und API-Feedback sind als mobile Muster vorbereitet.', status: 'Neu', owner: 'State UX', progress: .84, icon: Icons.task_alt_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'UI', title: 'Input Keyboard Accessibility', body: 'Mobile Keyboards, Masken, Pflichtfelder, Validierung, Fokus, Autofill, Touch Targets und Screenreader-Hinweise sind als Formularmuster vorbereitet.', status: 'Neu', owner: 'Input UX', progress: .82, icon: Icons.keyboard_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'UI', title: 'Brand Theme Tokens', body: 'Airmius-Logo, Header, Farben, Panels, Cards, Buttons, Inputs, Status-Pills, Overlays und Bottom Navigation sind als Design-Token-Paritaet vorbereitet.', status: 'Neu', owner: 'Brand UX', progress: .88, icon: Icons.palette_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Sprache', title: 'Localization RTL Formats', body: 'DE, EN, FR, AR, RTL, Datum, Währung, Einheiten, Fehlertexte, Legal-Texte, Fallbacks und API-Locale-Sync sind als mobile UI vorbereitet.', status: 'Neu', owner: 'L10n UX', progress: .82, icon: Icons.translate_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Store', title: 'Device Permission Privacy', body: 'Standort, Kamera, Dateien, Fotos, Push, Biometrie, Zweckbindung, Fallbacks und Store-ready Permission-Texte sind als native Mobile-UI vorbereitet.', status: 'Neu', owner: 'Native Privacy', progress: .74, icon: Icons.privacy_tip_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'UI', title: 'End-to-End Journeys', body: 'Verein beitreten, Antrag rückziehen, Training, Commerce, Guardian und Admin Review sind als durchgehende mobile User Journeys vorbereitet.', status: 'Neu', owner: 'Journey UX', progress: .8, icon: Icons.route_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'API', title: 'Session Security Tokens', body: 'Session Restore, Token Refresh, 2FA, Recovery Codes, Device Sessions, API Tokens, Logout und sensible Account-Aktionen sind als mobile Security-UI vorbereitet.', status: 'Neu', owner: 'Auth API', progress: .78, icon: Icons.security_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Sprache', title: 'Mehrsprachigkeit', body: 'DE, EN, FR und AR sind in der App-Scope-Logik angelegt; RTL wird für Arabisch über Directionality abgebildet.', status: 'Aktiv', owner: 'L10n', progress: .76, icon: Icons.language_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Sprache', title: 'Text-Hardening', body: 'Zentrale Navigation, Hub- und Release-Texte sind lokalisiert; weitere Detailtexte können schrittweise in L10n-Keys wandern.', status: 'Weiter', owner: 'Copy', progress: .62, icon: Icons.translate_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Store', title: 'App Store Struktur', body: 'Native Navigation, keine reine WebView-App, klare Mehrwerte und mobile Flows für Store-Prüfung vorbereitet.', status: 'Gut', owner: 'Product', progress: .72, icon: Icons.store_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Store', title: 'Native Onboarding', body: 'Sprache, Rolle, Verein/Team-Kontext, Datenschutz, Guardian-Hinweise und Berechtigungen sind als erster App-Start modelliert.', status: 'Neu', owner: 'UX', progress: .68, icon: Icons.rocket_launch_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Store', title: 'Berechtigungen', body: 'Standort, Kamera, Dateien, Push, Fotos und Datenschutzhinweise sind als UI-Flows modelliert und müssen später nativ konfiguriert werden.', status: 'Offen', owner: 'Native', progress: .48, icon: Icons.privacy_tip_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Store', title: 'Store Device QA', body: 'Logo, Splash, Store-Texte, Datenschutz, Permissions, Android/iOS Device-Matrix, Navigation Smoke, Form Smoke und Visual QA sind als Kontroll-UI sichtbar.', status: 'Neu', owner: 'Release QA', progress: .66, icon: Icons.mobile_friendly_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'API', title: 'Laravel API Contract', body: 'API-Kontrakt sammelt spätere Endpunkte für Auth, Vereine, Membership, Social, Commerce, Admin, Public und Sport.', status: 'Vorbereitet', owner: 'API', progress: .82, icon: Icons.api_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'API', title: 'Beitragsregeln', body: 'Mitgliedschaftstypen, Beitragshoehen, Zahlungsrhythmus, Barzahlung, Überweisung, SEPA, Rechnungen, Mahnungen und Rabatte sind als Club-Regel-UI vorbereitet.', status: 'Neu', owner: 'Club Billing', progress: .76, icon: Icons.payments_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'API', title: 'Vereinsfinanzen', body: 'Beiträge, Rechnungen, Zahlungen, Bankabgleich, SEPA, Mahnungen, DATEV, Exporte und Monatsabschluss sind als mobile Vereins-Finanz-UI vorbereitet.', status: 'Neu', owner: 'Club Finance', progress: .78, icon: Icons.account_balance_wallet_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'API', title: 'Anfrage-Eingang', body: 'Vereine haben eine mobile Inbox für neue Mitgliedschaftsanfragen, Rückzuege, Dokumentstatus, Adminentscheidungen und Benachrichtigungen.', status: 'Neu', owner: 'Club Admin', progress: .8, icon: Icons.inbox_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'API', title: 'Mitgliederverwaltung', body: 'Mitgliederlisten, externe Kontakte, Rollen, Zahlstatus, Dokumente, Import, Statuswechsel und Massenaktionen sind als mobile Vereins-UI umgesetzt.', status: 'Neu', owner: 'Club Admin', progress: .82, icon: Icons.people_outline, color: AirmiusColors.green),
  _ReadinessItem(area: 'API', title: 'Teamverwaltung', body: 'Teamprofile, Kader, Trainer, Captain, Einladungen, Join-Requests, Kalender, Dateien und Chatrechte sind als mobile Vereins-UI vorbereitet.', status: 'Neu', owner: 'Club Teams', progress: .8, icon: Icons.diversity_3_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'API', title: 'Offline/Loading/Error', body: 'UI-Aktionen zeigen Ergebnis- und Kontextseiten; echte Loading-, Retry- und Errorstates werden beim API-Client finalisiert.', status: 'Naechster Schritt', owner: 'Client', progress: .54, icon: Icons.sync_problem_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Safety', title: 'Guardian & Maturity', body: 'Elternfreigabe, Minderjaehrigen-Schutz, Maturity-Gates, Reports und Safety-Ops sind mobil vorbereitet.', status: 'Stark', owner: 'Safety', progress: .84, icon: Icons.family_restroom_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Safety', title: 'Vereinsdokumente & Regeln', body: 'Datenschutz, Satzung, Beitragsordnung, SEPA, Uploadpflicht, Sichtbarkeit, Guardian Consent und Dateimanager-Verknuepfung sind mobil modelliert.', status: 'Neu', owner: 'Club', progress: .78, icon: Icons.rule_folder_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Safety', title: 'Vereinssichtbarkeit', body: 'Vereine können Public-Profil, Adresse, Kontakt, Admins, Mitglieder, Teams, Beiträge, Dokumente, Sponsoren und Antragsschalter mobil steuern.', status: 'Neu', owner: 'Club Privacy', progress: .82, icon: Icons.visibility_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Safety', title: 'Membership Requirements Builder', body: 'Vereine konfigurieren Pflichtfelder, optionale Daten, Zahlungsrhythmus, Zahlungsart, Dokumentpflichten und Formularvorschau mobil.', status: 'Neu', owner: 'Club UX', progress: .88, icon: Icons.format_list_bulleted_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Safety', title: 'Application Inbox', body: 'Vereinsadmins sehen neue Anfragen, Rückzuege, Dokumente, Rückfragen, Entscheidungen und Benachrichtigungen als mobile Inbox.', status: 'Neu', owner: 'Club Admin', progress: .86, icon: Icons.inbox_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Safety', title: 'Dues Payment Rules', body: 'Beitragsgruppen, Zahlungszyklen, Zahlungsarten, SEPA, Barzahlung und Beitragsordnungs-Dokumente sind als mobile Vereins-UI vorbereitet.', status: 'Neu', owner: 'Club Finance', progress: .86, icon: Icons.receipt_long_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Safety', title: 'Member Onboarding Acceptance', body: 'Nach angenommener Anfrage sind Willkommensnachricht, Teamzuweisung, Zahlungsstart, Dokumentstatus und digitale Mitgliedskarte als UI vorbereitet.', status: 'Neu', owner: 'Club UX', progress: .87, icon: Icons.check_circle_outline, color: AirmiusColors.green),
  _ReadinessItem(area: 'Safety', title: 'Document Consent File Manager', body: 'Vereinsdokumente können als Upload, Dateimanager-Eintrag, Version, Consent-Pflicht und Mitgliedsantrags-Verknuepfung mobil modelliert werden.', status: 'Neu', owner: 'Club Docs', progress: .86, icon: Icons.folder_copy_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Core', title: 'Notification Delivery Preferences', body: 'Push, E-Mail, In-App-Badges, Digest, Ruhezeiten und thematische Zustellung sind als mobile Benachrichtigungs-UI vorbereitet.', status: 'Neu', owner: 'Notifications', progress: .84, icon: Icons.notifications_active_outlined, color: AirmiusColors.pink),
  _ReadinessItem(area: 'Core', title: 'Global Search Directory', body: 'Header- und App-Suche finden Personen, Vereine, Teams und öffentliche Profile mit direkter Navigation zu Profil, Clubseite und Mitgliedschaftsantrag.', status: 'Neu', owner: 'Discovery', progress: .86, icon: Icons.manage_search_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Events', title: 'Calendar Event RSVP', body: 'Kalender, Trainingstermine, Vereinsereignisse, Teilnahme, Anwesenheit, Erinnerungen und Fahrgemeinschaften sind als mobile UI vorbereitet.', status: 'Neu', owner: 'Events', progress: .84, icon: Icons.event_available_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Teams', title: 'Team Roster Role Assignment', body: 'Kader, Trainer, Captains, Join-Requests, Guardian-Sichtbarkeit, Dateien, Termine und Teamrechte sind als mobile UI vorbereitet.', status: 'Neu', owner: 'Club Teams', progress: .86, icon: Icons.groups_2_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Member', title: 'Member Self Service Center', body: 'Mitglieder sehen aktive Vereine, offene Anfragen, digitale Karte, Beiträge, Dokumente, Aufgaben und Support als mobile Zentrale.', status: 'Neu', owner: 'Member UX', progress: .86, icon: Icons.badge_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Support', title: 'Support Ticket Service Center', body: 'Tickets, Themen, Dateien, Verlauf, Vereinsadmin-Hinweise, Plattformeskalation und Status sind als mobile Support-UI vorbereitet.', status: 'Neu', owner: 'Support', progress: .84, icon: Icons.support_agent_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Finance', title: 'Invoice Receipt Center', body: 'Offene Beiträge, Rechnungen, Quittungen, Zahlungsstatus, Zahlung erneut versuchen, Mahnhinweise und Rückerstattungen sind als UI vorbereitet.', status: 'Neu', owner: 'Finance', progress: .84, icon: Icons.receipt_long_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Community', title: 'Feed Community Composer', body: 'Beiträge, Zielgruppen, Medien, Kommentare, Pinning, Meldungen und Moderationsprüfung sind als mobile Community-UI vorbereitet.', status: 'Neu', owner: 'Community', progress: .84, icon: Icons.forum_outlined, color: AirmiusColors.pink),
  _ReadinessItem(area: 'Messages', title: 'Messaging Conversation Center', body: 'Private Chats, Vereinsadmin-Kanal, Teamchat, Support-Konversationen, Dateianhaenge, Lesestatus und Meldungen sind als UI vorbereitet.', status: 'Neu', owner: 'Messages', progress: .84, icon: Icons.chat_bubble_outline, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Profile', title: 'Profile Privacy Visibility', body: 'Profilsichtbarkeit, Suche, Vereinsmitgliedschaften, Teams, Nachrichtenrechte, Datenexport, Löschanfragen und Blockieren sind als UI vorbereitet.', status: 'Neu', owner: 'Privacy', progress: .86, icon: Icons.privacy_tip_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Admin', title: 'Moderation Audit Queue', body: 'Meldungen, Vereinsprüfungen, Support-Eskalationen, Datenschutzanfragen, Adminentscheidungen und Audit-Verlauf sind als mobile Queue vorbereitet.', status: 'Neu', owner: 'Admin Trust', progress: .84, icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Commerce', title: 'Marketplace Order Fulfillment', body: 'Clubshop, Sponsorangebote, Warenkorb, Bestellungen, Abholung, Versand, Rückgabe und Statusmeldungen sind als mobile UI vorbereitet.', status: 'Neu', owner: 'Commerce', progress: .82, icon: Icons.storefront_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Ads', title: 'Sponsor Campaign Management', body: 'Sponsoren, Kampagnen, Placements, Budgets, Creatives, Freigaben, Club-Targeting und Reporting sind als mobile UI vorbereitet.', status: 'Neu', owner: 'Ads', progress: .82, icon: Icons.campaign_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Analytics', title: 'Analytics Reporting KPI', body: 'Mitgliederentwicklung, Finanzen, Community, Events, Support, Moderation, Marketplace, Ads, KPI-Trends und Exporte sind als UI vorbereitet.', status: 'Neu', owner: 'Analytics', progress: .82, icon: Icons.insights_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Learning', title: 'Course Progress Certificates', body: 'Kurse, Lernpfade, Lektionen, Quiz, Fortschritt, Zertifikate, Nachweise und Downloads sind als mobile Learning-UI vorbereitet.', status: 'Neu', owner: 'Learning', progress: .84, icon: Icons.school_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Gamification', title: 'Badge Achievement Center', body: 'Badges, Rollen, Level, Trainings-Streaks, Lernnachweise, Teamleistungen und Erfolgsbenachrichtigungen sind als UI vorbereitet.', status: 'Neu', owner: 'Gamification', progress: .84, icon: Icons.emoji_events_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Safety', title: 'Health Incident Reports', body: 'Verletzungen, Gesundheitshinweise, Notfallkontakt, Guardian-Info, medizinische Notizen, Supporttickets und Audit-Verlauf sind als UI vorbereitet.', status: 'Neu', owner: 'Safety', progress: .84, icon: Icons.health_and_safety_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Locations', title: 'Location Map Facilities', body: 'Vereinsorte, Trainingsstaetten, Treffpunkte, Routen, Fahrgemeinschaften, Abholung und Standort-Sichtbarkeit sind als UI vorbereitet.', status: 'Neu', owner: 'Locations', progress: .84, icon: Icons.map_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Data', title: 'Club Member Import Export', body: 'CSV-Import, Feldmapping, Dublettenprüfung, externe Kontakte, Einladungen, rollenbasierte Exporte und Audit sind als UI vorbereitet.', status: 'Neu', owner: 'Data Ops', progress: .82, icon: Icons.sync_alt_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Content', title: 'Content Publishing CMS', body: 'Blog, Vereinsnews, Newsletter, Sponsorinhalte, Vorschau, Medien, Freigaben, SEO und Publishing-Status sind als UI vorbereitet.', status: 'Neu', owner: 'Content', progress: .82, icon: Icons.article_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Core', title: 'Role Workspace Switcher', body: 'Mitglied, Vereinsadmin, Trainer, Guardian und Plattformadmin bekommen rollenbasierte Startseiten, Rechte und Navigationskontexte.', status: 'Neu', owner: 'Core UX', progress: .86, icon: Icons.switch_account_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Core', title: 'App Onboarding Permissions', body: 'Erststart, Sprache, Rollenwahl, Workspace, Datenschutz, Push, Standort, Dateien und Kamera sind als native Onboarding-UI vorbereitet.', status: 'Neu', owner: 'Mobile Core', progress: .86, icon: Icons.phone_iphone_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Core', title: 'API Empty Error States', body: 'Loading, Empty, Error, Retry, Offline, Cache, Skeletons und API-Fehlerprotokollierung sind als mobile State-UI vorbereitet.', status: 'Neu', owner: 'API UX', progress: .86, icon: Icons.sync_problem_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'API', title: 'Mobile Form Validation Schema', body: 'Pflichtfelder, bedingte Regeln, Masken, Defaultwerte, Fehlertexte und Schema-Versionen für dynamische Vereinsformulare sind als mobile UI vorbereitet.', status: 'Neu', owner: 'Forms API', progress: .86, icon: Icons.rule_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Store', title: 'Native Store Release Assets', body: 'App-Icon, Splash, Screenshots, Store-Texte, Datenschutzlabels, Berechtigungen und Release-Gates sind als Android/iOS-UI vorbereitet.', status: 'Neu', owner: 'Store QA', progress: .76, icon: Icons.store_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'UI', title: 'Club Public Profile Preview', body: 'Öffentliche Vereinsprofile mit Hero, Sichtbarkeitsregeln, Kontakt, Dokumenten, Teams, Admins und Mitgliedschafts-CTA sind als mobile Web-App-Paritaet vorbereitet.', status: 'Neu', owner: 'Club Public', progress: .88, icon: Icons.apartment_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'API', title: 'Laravel API Endpoint Mapping', body: 'Web-Routen, mobile Screens, Laravel-v1-Endpunkte, Auth-Header, Pagination, Fehlervertrag, Offline-Queue und spätere Client-Services sind als UI-Matrix vorbereitet.', status: 'Neu', owner: 'API Bridge', progress: .84, icon: Icons.hub_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Commerce', title: 'Subscription Entitlement Feature Gates', body: 'Tarife, Vereinslimits, Modulrechte, Rollenrechte, Feature-Locks, Upgrade-Hinweise und API-ready Entitlements sind als mobile Plattform-UI vorbereitet.', status: 'Neu', owner: 'Billing UX', progress: .82, icon: Icons.workspace_premium_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Betrieb', title: 'Audit Activity Timeline', body: 'Aktivitaeten, Vereinsaktionen, Mitgliedsanträge, Zahlungsereignisse, Rollenwechsel, Security-Events, Exporte und Aufbewahrung sind als mobile Timeline vorbereitet.', status: 'Neu', owner: 'Audit Ops', progress: .84, icon: Icons.history_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'API', title: 'Integration Webhook Provider Center', body: 'Mail, Push, Payments, Storage, Maps, AI, Providerstatus, Webhooks, Retry-Queue, Secrets und Laravel Jobs sind als mobile Betriebs-UI vorbereitet.', status: 'Neu', owner: 'Provider Ops', progress: .82, icon: Icons.webhook_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Betrieb', title: 'System Job Queue Monitor', body: 'E-Mail, Push, Upload-Scans, Importe, Exporte, Zahlungen, Webhooks, Retry, Dead Letter und Wartungsmodus sind als mobile Queue-UI vorbereitet.', status: 'Neu', owner: 'Queue Ops', progress: .82, icon: Icons.pending_actions_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Betrieb', title: 'System Status Incident Center', body: 'Systemstatus, Wartungsfenster, Incident-Kommunikation, Service-Health, Nutzerhinweise, Statusseite und Admin-Eskalation sind als mobile UI vorbereitet.', status: 'Neu', owner: 'Status Ops', progress: .84, icon: Icons.monitor_heart_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'UI', title: 'Draft Autosave Recovery', body: 'Autosave, Offline-Drafts, Wiederherstellung, Konfliktvergleich, Datenschutz-Ablauf und sichere Fortsetzung langer mobiler Formulare sind vorbereitet.', status: 'Neu', owner: 'Forms UX', progress: .86, icon: Icons.restore_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'API', title: 'Invitation Access Links', body: 'Einladungen, QR-Codes, Zugangslinks, Rollenbindung, Ablauf, Widerruf, Annahmestatus und Audit für Mitglieder, Teams, Guardians und Sponsoren sind vorbereitet.', status: 'Neu', owner: 'Invite UX', progress: .84, icon: Icons.qr_code_2_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Safety', title: 'Consent Signature Versioning', body: 'Dokumentversionen, Datenschutz, Satzung, Beitragsordnung, SEPA, Medienrechte, Guardian-Freigaben, digitale Bestätigungen und Audit-Nachweise sind vorbereitet.', status: 'Neu', owner: 'Legal UX', progress: .86, icon: Icons.fact_check_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'UI', title: 'Digital Member Card Check-in', body: 'Digitale Mitgliedskarte, QR-Verifikation, Training-Check-in, Offline-Prüfung, Minimaldaten, Token-Rotation und Anwesenheits-Audit sind vorbereitet.', status: 'Neu', owner: 'Member UX', progress: .86, icon: Icons.badge_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Core', title: 'Deep Link Route Resolver', body: 'Einladungen, QR, Push, E-Mail, Chat, Zahlung, Datei und Event-Links werden mit Auth, Workspace-Auswahl, Fallback und Routing-Audit vorbereitet.', status: 'Neu', owner: 'Routing UX', progress: .84, icon: Icons.route_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'UI', title: 'Role Home Dashboard Widgets', body: 'Rollenbasierte Home-Widgets für Mitglied, Vereinsadmin, Trainer, Guardian und Plattformadmin mit Aufgaben, Statuskarten, Schnellaktionen und Priorisierung sind vorbereitet.', status: 'Neu', owner: 'Home UX', progress: .86, icon: Icons.dashboard_customize_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'UI', title: 'Saved Views Search Alerts', body: 'Gespeicherte Filter, Suchalarme, geteilte Listenansichten, Exporte und rollenbasierte Sichtbarkeit für Mitglieder, Vereine, Events, Rechnungen, Dateien, Support und Shop sind vorbereitet.', status: 'Neu', owner: 'List UX', progress: .84, icon: Icons.bookmark_outline, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Admin', title: 'Cross Module Approval Workflow', body: 'Freigaben, Rückfragen, Entscheidungen, Eskalation und Audit für Mitgliedschaft, Dateien, Finanzen, Content, Events und Admin-Aktionen sind vorbereitet.', status: 'Neu', owner: 'Approval UX', progress: .84, icon: Icons.fact_check_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Events', title: 'Facility Booking Resource Scheduler', body: 'Plaetze, Hallen, Raeume, Geräte, Buchungen, Konfliktprüfung, Wartungszeiten, Rollenrechte, Zahlpflicht und Serientermine sind vorbereitet.', status: 'Neu', owner: 'Facility UX', progress: .84, icon: Icons.event_available_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Events', title: 'Availability Absence Planning', body: 'Verfuegbarkeit, Abwesenheiten, Guardian-Meldungen, Trainerübersicht, Gesundheitsnotizen, Erinnerungen und Anwesenheits-Sync sind vorbereitet.', status: 'Neu', owner: 'Team Planning', progress: .84, icon: Icons.how_to_reg_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Events', title: 'Volunteer Shift Task Planner', body: 'Helferlisten, Schichten, Aufgaben, Erinnerungen, Rollenregeln, Nachweise, offene Helferbedarfe und Export für Events und Vereinsbetrieb sind vorbereitet.', status: 'Neu', owner: 'Volunteer UX', progress: .84, icon: Icons.assignment_turned_in_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Verein', title: 'Club Survey Poll Voting', body: 'Umfragen, Abstimmungen, Feedback, Zielgruppen, Anonymitaet, Quorum, Auswertung, Export, Aufgaben und Audit sind als mobile Vereins-UI vorbereitet.', status: 'Neu', owner: 'Club Feedback', progress: .84, icon: Icons.how_to_vote_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Verein', title: 'Meeting Minutes Decision Log', body: 'Sitzungen, Agenda, Protokolle, Beschluesse, Aufgaben, Dateiverknuepfungen, Abstimmungsbezug und Audit sind als mobile Vereinsadmin-UI vorbereitet.', status: 'Neu', owner: 'Club Governance', progress: .84, icon: Icons.fact_check_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Verein', title: 'Club Asset Inventory Checkout', body: 'Vereinsmaterial, Schluessel, Trikots, Geräte, QR-Codes, Ausleihe, Rückgabe, Wartung, Fotos, Kaution und Audit sind vorbereitet.', status: 'Neu', owner: 'Club Material', progress: .84, icon: Icons.inventory_2_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Commerce', title: 'Sponsor Lead CRM Pipeline', body: 'Sponsor-Leads, Kontakte, Angebote, Pakete, Dateien, Freigaben, Kampagnen, Rechnungen, Reporting und Renewal sind als mobile Vereins-CRM-UI vorbereitet.', status: 'Neu', owner: 'Sponsor CRM', progress: .84, icon: Icons.handshake_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Sport', title: 'Training Plan Periodization', body: 'Trainingszyklen, Coach-Freigaben, Belastung, Athletendaten, Kalender-Sync, Logs, Fortschritt und Anpassungen sind als mobile Sport-UI vorbereitet.', status: 'Neu', owner: 'Training UX', progress: .84, icon: Icons.fitness_center_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Member', title: 'Member Feedback Satisfaction', body: 'Mitgliederfeedback, Zufriedenheit, Beschwerden, Ideen, Trainerfeedback, Follow-ups, Trends, Supportverknuepfung und Audit sind vorbereitet.', status: 'Neu', owner: 'Member Success', progress: .84, icon: Icons.sentiment_satisfied_alt_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Safety', title: 'Legal Policy Rollout', body: 'Datenschutz, Satzung, Beitragsordnung, SEPA, Medienfreigabe, Dokumentversionen, Pflichtbestätigungen, Guardian Consent und Audit sind vorbereitet.', status: 'Neu', owner: 'Legal UX', progress: .86, icon: Icons.gavel_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Core', title: 'Mobile Visual Parity Progress Audit', body: 'Rest-Prozente, mobile Web-App-Paritaet, UI-Qualitaet, API-Gaps, Store-Reife und naechste Release-Gates sind als Audit-UI sichtbar.', status: 'Neu', owner: 'Product Audit', progress: .82, icon: Icons.speed_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'API', title: 'Laravel API Binding Progress', body: 'AirmiusApiClient, Auth, Vereine, Mitgliedsanträge, Upload-Intent, Notifications, Chat, Events, Billing und offene API-Gates sind vorbereitet.', status: 'Neu', owner: 'API Bridge', progress: .46, icon: Icons.cloud_sync_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'API', title: 'API Data Model Repository', body: 'Typed Models, Pagination und Repository-Verträge für User, Vereine, Mitgliedsanträge, Dateien, Events und Rechnungen sind vorbereitet.', status: 'Neu', owner: 'API Models', progress: .47, icon: Icons.data_object_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'API', title: 'API Repository Binding', body: 'Repository-Implementierungen für Auth, Clubs, Memberships, Files, Events und Billing verbinden Client, Models und spätere Screens.', status: 'Neu', owner: 'API Repos', progress: .48, icon: Icons.alt_route_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'API', title: 'Auth State Token Store', body: 'Session, persistenter TokenStore, Restore, Login, Logout, User Refresh, Locale und Auth-Phasen sind als zentrale API-State-Schicht vorbereitet.', status: 'Neu', owner: 'Auth Core', progress: .58, icon: Icons.security_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'API', title: 'Service Container Transport', body: 'Environment, API-Client, Auth-State, TokenStore, RepositoryBundle, Offline Queue, Retry und Static Transport sind als Service-Schicht vorbereitet.', status: 'Neu', owner: 'App Core', progress: .5, icon: Icons.settings_ethernet_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'API', title: 'HTTP Transport Release', body: 'Conditional HTTP-Transport für Web, Mobile/Desktop, Stub-Fallback und Demo-StaticTransport ist vorbereitet; Laravel Base URL und Build-Gates bleiben offen.', status: 'Neu', owner: 'Network Core', progress: .56, icon: Icons.cloud_sync_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Store', title: 'Store Release Configuration', body: 'App-Metadaten, Bundle ID, Store-Texte, Permission-Begruendungen, Datenschutzlink, Supportkontakt und Release-Gates sind zentral vorbereitet.', status: 'Neu', owner: 'Store QA', progress: .58, icon: Icons.app_settings_alt_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'UI', title: 'Vereinsprofil bearbeiten', body: 'Stammdaten, Logo, Banner, Kontakt, Adresse, Sportarten, Social Links, Verifizierung und Public Preview sind als mobile Club-Admin-UI umgesetzt.', status: 'Neu', owner: 'Club Profile', progress: .84, icon: Icons.edit_note_outlined, color: AirmiusColors.blue),
  _ReadinessItem(area: 'Safety', title: 'Audit & Trust', body: 'Verifizierung, Reports, Moderation, Inaktivitaet und Adminentscheidungen haben eigene mobile Trust-Ops.', status: 'Stark', owner: 'Admin', progress: .82, icon: Icons.verified_user_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Betrieb', title: 'Operations Hub', body: 'Alle Operations-Center sind zentral erreichbar und dienen später als API-Test- und Admin-Navigationsschicht.', status: 'Bereit', owner: 'Ops', progress: .9, icon: Icons.hub_outlined, color: AirmiusColors.green),
  _ReadinessItem(area: 'Betrieb', title: 'System Admin Betrieb', body: 'Mail-Center, Providerkosten, Systemsettings, Webhooks, Wartung, SEO, Audit und Betriebsnotizen sind als mobile Admin-UI vorbereitet.', status: 'Neu', owner: 'Ops', progress: .7, icon: Icons.settings_suggest_outlined, color: AirmiusColors.amber),
  _ReadinessItem(area: 'Betrieb', title: 'Release-Prüfung', body: 'Vor echter Store-Abgabe fehlen noch native Plattformkonfiguration, echte API-Integration, Build-Checks und Gerätetests.', status: 'Nicht final', owner: 'Release', progress: .58, icon: Icons.fact_check_outlined, color: AirmiusColors.red),
];

































