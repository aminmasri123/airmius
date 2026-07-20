import 'package:flutter/material.dart';

import '../core/airmius_mvp_surface.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'account_operations_screen.dart';
import 'data_rights_request_screen.dart';
import 'airmius_design_system_screen.dart';
import 'app_onboarding_screen.dart';
import 'club_policy_documents_screen.dart';
import 'club_profile_editor_screen.dart';
import 'club_reports_analytics_screen.dart';
import 'club_request_inbox_screen.dart';
import 'club_role_permissions_screen.dart';
import 'club_setup_onboarding_screen.dart';
import 'club_visibility_settings_screen.dart';
import 'club_contribution_rules_screen.dart';
import 'club_communication_center_screen.dart';
import 'club_finance_cockpit_screen.dart';
import 'club_member_directory_screen.dart';
import 'club_membership_form_builder_screen.dart';
import 'club_team_admin_screen.dart';
import 'localization_center_screen.dart';
import 'membership_application_form_screen.dart';
import 'membership_request_status_screen.dart';
import 'operations_hub_screen.dart';
import 'platform_operations_screen.dart';
import 'legal_status_center_screen.dart';
import 'privacy_consent_center_screen.dart';
import 'guardian_family_consent_screen.dart';
import 'guest_jobs_careers_screen.dart';
import 'guest_pricing_plans_screen.dart';
import 'guest_marketplace_buyer_screen.dart';
import 'guest_learning_certificate_screen.dart';
import 'guest_blog_content_screen.dart';
import 'guest_sponsors_gamification_screen.dart';
import 'guest_ad_agency_screen.dart';
import 'release_readiness_screen.dart';
import 'report_moderation_center_screen.dart';
import 'settings_detail_screen.dart';
import 'support_helpdesk_screen.dart';
import 'system_admin_operations_screen.dart';
import 'ui_coverage_screen.dart';
import 'web_route_parity_screen.dart';
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

class SettingsCenterScreen extends StatefulWidget {
  const SettingsCenterScreen({super.key});

  @override
  State<SettingsCenterScreen> createState() => _SettingsCenterScreenState();
}

class _SettingsCenterScreenState extends State<SettingsCenterScreen> {
  String _section = 'Profil';

  @override
  Widget build(BuildContext context) {
    final accent = Theme.of(context).colorScheme.primary;
    final text = Theme.of(context).textTheme.bodyLarge?.color ?? AirmiusColors.text;
    final muted = Theme.of(context).textTheme.bodyMedium?.color ?? AirmiusColors.muted;
    final border = Theme.of(context).dividerColor;
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: accent, foregroundColor: Colors.white, icon: const Icon(Icons.manage_accounts_outlined), label: const Text('Account Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AccountOperationsScreen(initialTab: 'Konto')))),
        
      appBar: AppBar(backgroundColor: Theme.of(context).appBarTheme.backgroundColor, surfaceTintColor: Colors.transparent, title: const Text('Einstellungen', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Einstellungen',
        subtitle: 'Profil, Sprache, Datenschutz, Benachrichtigungen, Sicherheit und Zahlungen',
        trailing: const StatusPill('DE'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Konto & App'),
            const SizedBox(height: 8),
            Text('Deine Airmius-App einstellen.', style: TextStyle(color: text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            Text('Profil, Sprache, Datenschutz, Push-Benachrichtigungen, Sicherheit, Zahlungen und Kontoaktionen im mobilen Web-App-Stil.', style: TextStyle(color: muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Profil', 'Datenschutz', 'Push', 'Sicherheit', 'Zahlung'].map((item) => ChoiceChip(
              selected: _section == item,
              label: Text(item),
              onSelected: (_) => setState(() => _section = item),
              selectedColor: accent.withValues(alpha: 0.22),
              backgroundColor: Color.lerp(Theme.of(context).colorScheme.surface, accent, 0.10),
              side: BorderSide(color: _section == item ? accent : border),
              labelStyle: TextStyle(color: _section == item ? accent : muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '82%', label: 'Profil')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'DE', label: 'Sprache')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2FA', label: 'Sicher'))]),
          const SizedBox(height: 14),
          const AirmiusThemeChooser(),
          const SizedBox(height: 14),
          _SettingLine(
            icon: Icons.person_outline,
            title: 'Profil & Sportprofil',
            body: 'Persoenliche Daten, Sportdaten, Sichtbarkeit und Profilvollstaendigkeit.',
            status: '82%',
            color: accent,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SettingsDetailScreen(section: 'Profil & Sportprofil', status: '82%'))),
          ),
          const SizedBox(height: 12),
          _SettingLine(
            icon: Icons.privacy_tip_outlined,
            title: 'Datenschutz',
            body: 'Einwilligungen, Datenexport, Sichtbarkeit und Konto löschen.',
            status: 'Sicher',
            color: AirmiusColors.green,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SettingsDetailScreen(section: 'Datenschutz', status: 'Sicher'))),
          ),
          const SizedBox(height: 12),
          _SettingLine(
            icon: Icons.language_outlined,
            title: 'Sprache & Übersetzungen',
            body: 'Deutsch, Englisch, Franzoesisch, Arabisch, RTL und API-Synchronisierung.',
            status: '4',
            color: accent,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LocalizationCenterScreen())),
          ),
          const SizedBox(height: 12),
          _SettingLine(
            icon: Icons.notifications_active_outlined,
            title: 'Benachrichtigungen',
            body: 'Push, E-Mail, Chat, Events, Zahlungen und Vereinsupdates konfigurieren.',
            status: 'Aktiv',
            color: AirmiusColors.amber,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SettingsDetailScreen(section: 'Benachrichtigungen', status: 'Aktiv'))),
          ),
          const SizedBox(height: 12),
          _SettingLine(
            icon: Icons.api_outlined,
            title: 'Plattformbetrieb',
            body: 'API, Webhooks, SEO, Gast-Checkout, Wartung und Systemstatus.',
            status: 'Ops',
            color: accent,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PlatformOperationsScreen())),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Schnellaktionen'),
            const SizedBox(height: 12),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
              AirmiusButton(label: 'Profil bearbeiten', icon: Icons.edit_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SettingsDetailScreen(section: 'Profil & Sportprofil', status: '82%')))),
              AirmiusButton(label: 'Sprache wechseln', icon: Icons.language_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LocalizationCenterScreen()))),
              AirmiusButton(label: 'Daten exportieren', icon: Icons.download_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SettingsDetailScreen(section: 'Datenschutz', status: 'Export')))),
              AirmiusButton(label: 'Vereinsregeln', icon: Icons.rule_folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPolicyDocumentsScreen()))),
              AirmiusButton(label: 'Vereinssichtbarkeit', icon: Icons.visibility_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubVisibilitySettingsScreen()))),
              AirmiusButton(label: 'Vereinsprofil', icon: Icons.edit_note_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubProfileEditorScreen()))),
                AirmiusButton(label: 'Verein einrichten', icon: Icons.rocket_launch_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubSetupOnboardingScreen()))),
              AirmiusButton(label: 'Beitragsregeln', icon: Icons.payments_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubContributionRulesScreen()))),
              AirmiusButton(label: 'Vereinsfinanzen', icon: Icons.account_balance_wallet_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubFinanceCockpitScreen()))),
                AirmiusButton(label: 'Vereinsberichte', icon: Icons.insights_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubReportsAnalyticsScreen()))),
              AirmiusButton(label: 'Anfrage-Eingang', icon: Icons.inbox_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubRequestInboxScreen()))),
                AirmiusButton(label: 'Mitgliedsantrag konfigurieren', icon: Icons.format_list_bulleted_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMembershipFormBuilderScreen()))),
                AirmiusButton(label: 'Mitgliedsantrag ausfuellen', icon: Icons.assignment_add, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipApplicationFormScreen()))),
                AirmiusButton(label: 'Meine Mitgliedsanfrage', icon: Icons.assignment_turned_in_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipRequestStatusScreen()))),
                AirmiusButton(label: 'Vereinsrollen & Rechte', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubRolePermissionsScreen()))),
                AirmiusButton(label: 'Vereinskommunikation', icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubCommunicationCenterScreen()))),
              AirmiusButton(label: 'Mitgliederverwaltung', icon: Icons.people_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberDirectoryScreen()))),
              AirmiusButton(label: 'Teamverwaltung', icon: Icons.diversity_3_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubTeamAdminScreen()))),
              if (AirmiusMvpSurface.isOperationsHubVisible)
                AirmiusButton(label: 'Operations Hub', icon: Icons.hub_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OperationsHubScreen()))),
              if (AirmiusMvpSurface.showDeveloperSuites)
                AirmiusButton(label: 'Onboarding', icon: Icons.rocket_launch_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AppOnboardingScreen()))),
              if (AirmiusMvpSurface.showDeveloperSuites)
                AirmiusButton(label: 'UI Coverage', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiCoverageScreen()))),
              if (AirmiusMvpSurface.showDeveloperSuites)
                AirmiusButton(label: 'Design System', icon: Icons.palette_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AirmiusDesignSystemScreen()))),
                AirmiusButton(label: 'Datenschutz & Einwilligungen', icon: Icons.privacy_tip_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PrivacyConsentCenterScreen()))),
                AirmiusButton(label: 'Recht & Systemstatus', icon: Icons.gavel_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LegalStatusCenterScreen()))),
                AirmiusButton(label: 'Guardian & Elternfreigaben', icon: Icons.family_restroom_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuardianFamilyConsentScreen()))),
                AirmiusButton(label: 'Datenrechte', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DataRightsRequestScreen()))),
                AirmiusButton(label: 'Support & Helpdesk', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
                AirmiusButton(label: 'Melden & Moderation', icon: Icons.flag_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ReportModerationCenterScreen()))),
              AirmiusButton(label: 'Web Parity', icon: Icons.route_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WebRouteParityScreen()))),
                AirmiusButton(label: 'Jobs bei Airmius', icon: Icons.work_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestJobsCareersScreen()))),
                AirmiusButton(label: 'Preise & Plaene', icon: Icons.price_change_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestPricingPlansScreen()))),
                AirmiusButton(label: 'Marketplace Guest', icon: Icons.storefront_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestMarketplaceBuyerScreen()))),
                AirmiusButton(label: 'E-Learning & Zertifikate', icon: Icons.school_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestLearningCertificateScreen()))),
                AirmiusButton(label: 'Airmius Blog & Inhalte', icon: Icons.article_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestBlogContentScreen()))),
                AirmiusButton(label: 'Sponsoren & Gamification', icon: Icons.emoji_events_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestSponsorsGamificationScreen()))),
                AirmiusButton(label: 'Airmius Werbeagentur', icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuestAdAgencyScreen()))),
              AirmiusButton(label: 'Release Ready', icon: Icons.verified_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ReleaseReadinessScreen()))),
              AirmiusButton(label: 'Store Device QA', icon: Icons.mobile_friendly_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StoreDeviceQaReadinessSuiteScreen()))),
              AirmiusButton(label: 'Auth Guard Status', icon: Icons.security_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AuthGuardStatusSuiteScreen()))),
              AirmiusButton(label: 'Exact Page Flows', icon: Icons.view_list_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ExactPageFlowParitySuiteScreen()))),
              AirmiusButton(label: 'Navigation Menu', icon: Icons.menu_open_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NavigationMenuParitySuiteScreen()))),
              AirmiusButton(label: 'Mobile Table Actions', icon: Icons.table_rows_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MobileTableActionParitySuiteScreen()))),
              AirmiusButton(label: 'Media Uploads', icon: Icons.cloud_upload_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MediaUploadAttachmentParitySuiteScreen()))),
              AirmiusButton(label: 'Modal & Sheets', icon: Icons.layers_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ModalSheetOverlayParitySuiteScreen()))),
              AirmiusButton(label: 'Offline Sync', icon: Icons.sync_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OfflineSyncCacheParitySuiteScreen()))),
              AirmiusButton(label: 'Analytics Dashboards', icon: Icons.insights_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AnalyticsChartDashboardParitySuiteScreen()))),
              AirmiusButton(label: 'Map Location Routes', icon: Icons.map_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MapLocationRouteParitySuiteScreen()))),
              AirmiusButton(label: 'Push Deep Links', icon: Icons.notifications_none_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PushNotificationDeeplinkParitySuiteScreen()))),
              AirmiusButton(label: 'State Feedback', icon: Icons.task_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StateFeedbackParitySuiteScreen()))),
              AirmiusButton(label: 'Input Accessibility', icon: Icons.keyboard_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InputKeyboardAccessibilityParitySuiteScreen()))),
              AirmiusButton(label: 'Brand Theme Tokens', icon: Icons.palette_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BrandThemeTokenParitySuiteScreen()))),
              AirmiusButton(label: 'Localization RTL', icon: Icons.translate_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LocalizationRtlFormatParitySuiteScreen()))),
              AirmiusButton(label: 'Device Permissions', icon: Icons.privacy_tip_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DevicePermissionPrivacyParitySuiteScreen()))),
              AirmiusButton(label: 'End-to-End Journeys', icon: Icons.route_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EndToEndJourneyParitySuiteScreen()))),
                    AirmiusButton(label: 'Session Security', icon: Icons.security_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SessionSecurityTokenParitySuiteScreen()))),
                    AirmiusButton(label: 'Web-App Conversion', icon: Icons.dashboard_customize_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WebAppFullConversionControlScreen()))),
                    AirmiusButton(label: 'Module Completion', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WebAppModuleCompletionSuiteScreen()))),
                    AirmiusButton(label: 'Vereinsregeln', icon: Icons.rule_folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubVisibilityRulesSuiteScreen()))),
                    AirmiusButton(label: 'Anforderungsbuilder', icon: Icons.format_list_bulleted_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMembershipRequirementsBuilderScreen()))),
                    AirmiusButton(label: 'Anfrage-Inbox', icon: Icons.inbox_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubApplicationInboxSuiteScreen()))),
                    AirmiusButton(label: 'Beitragsregeln', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubDuesPaymentRulesSuiteScreen()))),
                    AirmiusButton(label: 'Member Onboarding', icon: Icons.check_circle_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberOnboardingAcceptanceSuiteScreen()))),
                    AirmiusButton(label: 'Dokumente & Consent', icon: Icons.folder_copy_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubDocumentConsentFileManagerSuiteScreen()))),
                    AirmiusButton(label: 'Benachrichtigungen', icon: Icons.notifications_active_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationDeliveryPreferencesSuiteScreen()))),
                    AirmiusButton(label: 'Globale Suche', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GlobalSearchDirectorySuiteScreen()))),
                    AirmiusButton(label: 'Kalender & Teilnahme', icon: Icons.event_available_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CalendarEventRsvpSuiteScreen()))),
                    AirmiusButton(label: 'Teamverwaltung', icon: Icons.groups_2_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamRosterRoleAssignmentSuiteScreen()))),
                    AirmiusButton(label: 'Meine Mitgliedschaften', icon: Icons.badge_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MemberSelfServiceCenterSuiteScreen()))),
                    AirmiusButton(label: 'Support Center', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportTicketServiceCenterSuiteScreen()))),
                    AirmiusButton(label: 'Rechnungen', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FinanceInvoiceReceiptCenterSuiteScreen()))),
                    AirmiusButton(label: 'Feed & Community', icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FeedCommunityComposerSuiteScreen()))),
                    AirmiusButton(label: 'Nachrichten', icon: Icons.chat_bubble_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MessagingConversationCenterSuiteScreen()))),
                    AirmiusButton(label: 'Profil & Datenschutz', icon: Icons.privacy_tip_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProfilePrivacyVisibilitySuiteScreen()))),
                    AirmiusButton(label: 'Moderation & Audit', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AdminModerationAuditQueueSuiteScreen()))),
                    AirmiusButton(label: 'Marketplace', icon: Icons.storefront_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MarketplaceOrderFulfillmentSuiteScreen()))),
                    AirmiusButton(label: 'Sponsoren', icon: Icons.campaign_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorCampaignManagementSuiteScreen()))),
                    AirmiusButton(label: 'Analytics', icon: Icons.insights_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AnalyticsReportingKpiSuiteScreen()))),
                    AirmiusButton(label: 'Kurse & Zertifikate', icon: Icons.school_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LearningCourseProgressCertificateSuiteScreen()))),
                    AirmiusButton(label: 'Badges & Erfolge', icon: Icons.emoji_events_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GamificationBadgeAchievementSuiteScreen()))),
                    AirmiusButton(label: 'Gesundheit & Vorfaelle', icon: Icons.health_and_safety_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => HealthIncidentReportSuiteScreen()))),
                    AirmiusButton(label: 'Orte & Karten', icon: Icons.map_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LocationMapFacilitySuiteScreen()))),
                    AirmiusButton(label: 'Import & Export', icon: Icons.sync_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMemberImportExportSuiteScreen()))),
                    AirmiusButton(label: 'Content Publishing', icon: Icons.article_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ContentPublishingCmsSuiteScreen()))),
                    AirmiusButton(label: 'Rollen & Workspaces', icon: Icons.switch_account_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RoleWorkspaceSwitcherSuiteScreen()))),
                    AirmiusButton(label: 'App Onboarding', icon: Icons.phone_iphone_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AppOnboardingPermissionSuiteScreen()))),
                    AirmiusButton(label: 'API States', icon: Icons.sync_problem_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ApiStateEmptyErrorSuiteScreen()))),
                    AirmiusButton(label: 'Formularvalidierung', icon: Icons.rule_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MobileFormValidationSchemaSuiteScreen()))),
                    AirmiusButton(label: 'Store Release Assets', icon: Icons.store_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NativeStoreReleaseAssetsSuiteScreen()))),
                    AirmiusButton(label: 'Club Public Preview', icon: Icons.apartment_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPublicProfilePreviewSuiteScreen()))),
                    AirmiusButton(label: 'Laravel API Mapping', icon: Icons.hub_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LaravelApiEndpointMappingSuiteScreen()))),
                    AirmiusButton(label: 'Feature Gates', icon: Icons.workspace_premium_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SubscriptionEntitlementFeatureGateSuiteScreen()))),
                    AirmiusButton(label: 'Audit Timeline', icon: Icons.history_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AuditActivityTimelineSuiteScreen()))),
                    AirmiusButton(label: 'Integrationen', icon: Icons.webhook_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => IntegrationWebhookProviderSuiteScreen()))),
                    AirmiusButton(label: 'Job Queue', icon: Icons.pending_actions_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemJobQueueMonitorSuiteScreen()))),
                    AirmiusButton(label: 'Systemstatus', icon: Icons.monitor_heart_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemStatusIncidentCenterSuiteScreen()))),
                    AirmiusButton(label: 'Drafts & Autosave', icon: Icons.restore_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DraftAutosaveRecoverySuiteScreen()))),
                    AirmiusButton(label: 'Einladungen', icon: Icons.qr_code_2_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => InvitationAccessLinkSuiteScreen()))),
                    AirmiusButton(label: 'Consent & Signatur', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ConsentSignatureVersioningSuiteScreen()))),
                    AirmiusButton(label: 'Mitgliedskarte', icon: Icons.badge_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DigitalMemberCardCheckinSuiteScreen()))),
                    AirmiusButton(label: 'Deep Links', icon: Icons.route_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DeepLinkRouteResolverSuiteScreen()))),
                    AirmiusButton(label: 'Home Widgets', icon: Icons.dashboard_customize_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RoleHomeDashboardWidgetSuiteScreen()))),
                    AirmiusButton(label: 'Saved Views', icon: Icons.bookmark_outline, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SavedViewSearchAlertSuiteScreen()))),
                    AirmiusButton(label: 'Freigaben', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CrossModuleApprovalWorkflowSuiteScreen()))),
                    AirmiusButton(label: 'Ressourcen buchen', icon: Icons.event_available_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FacilityBookingResourceSchedulerSuiteScreen()))),
                    AirmiusButton(label: 'Verfuegbarkeit', icon: Icons.how_to_reg_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AvailabilityAbsencePlanningSuiteScreen()))),
                    AirmiusButton(label: 'Helferplanung', icon: Icons.assignment_turned_in_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => VolunteerShiftTaskPlannerSuiteScreen()))),
                    AirmiusButton(label: 'Umfragen', icon: Icons.how_to_vote_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubSurveyPollVotingSuiteScreen()))),
                    AirmiusButton(label: 'Sitzungen', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MeetingMinutesDecisionLogSuiteScreen()))),
                    AirmiusButton(label: 'Inventar', icon: Icons.inventory_2_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubAssetInventoryCheckoutSuiteScreen()))),
                    AirmiusButton(label: 'Sponsor CRM', icon: Icons.handshake_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorLeadCrmPipelineSuiteScreen()))),
                    AirmiusButton(label: 'Trainingsplanung', icon: Icons.fitness_center_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainingPlanPeriodizationSuiteScreen()))),
                    AirmiusButton(label: 'Feedback', icon: Icons.sentiment_satisfied_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MemberFeedbackSatisfactionSuiteScreen()))),
                    AirmiusButton(label: 'Policy Rollout', icon: Icons.gavel_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LegalPolicyRolloutSuiteScreen()))),
                    AirmiusButton(label: 'Produktfortschritt', icon: Icons.speed_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MobileVisualParityProgressAuditSuiteScreen()))),
                    AirmiusButton(label: 'API Bindung', icon: Icons.cloud_sync_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LaravelApiBindingProgressSuiteScreen()))),
                    AirmiusButton(label: 'API Datenmodelle', icon: Icons.data_object_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ApiDataModelRepositorySuiteScreen()))),
                    AirmiusButton(label: 'API Repositories', icon: Icons.alt_route_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ApiRepositoryBindingSuiteScreen()))),
                    AirmiusButton(label: 'Auth State', icon: Icons.security_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AuthStateTokenStoreSuiteScreen()))),
                    AirmiusButton(label: 'Service Container', icon: Icons.settings_ethernet_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ServiceContainerTransportSuiteScreen()))),
                    AirmiusButton(label: 'HTTP Transport', icon: Icons.cloud_sync_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => HttpTransportReleaseSuiteScreen()))),
                    AirmiusButton(label: 'Store Konfiguration', icon: Icons.app_settings_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => StoreReleaseConfigurationSuiteScreen()))),
              AirmiusButton(label: 'Betrieb prüfen', icon: Icons.monitor_heart_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PlatformOperationsScreen()))),
              AirmiusButton(label: 'System Admin', icon: Icons.settings_suggest_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemAdminOperationsScreen()))),
              ],
            ),
          ])),
        ]),
      ),
    );
  }
}

class _SettingLine extends StatelessWidget {
  const _SettingLine({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final textColor = Theme.of(context).textTheme.bodyLarge?.color ?? AirmiusColors.text;
    final mutedColor = Theme.of(context).textTheme.bodyMedium?.color ?? AirmiusColors.muted;
    return AirmiusPanel(onTap: onTap, borderColor: color.withValues(alpha: 0.45), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: color, size: 28),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: TextStyle(color: textColor, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: TextStyle(color: mutedColor, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
      Icon(Icons.chevron_right, color: mutedColor),
    ]));
  }
}




































