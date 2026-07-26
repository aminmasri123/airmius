import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_mvp_surface.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'access_operations_screen.dart';
import 'account_operations_screen.dart';
import 'airmius_design_system_screen.dart';
import 'api_connection_screen.dart';
import 'app_onboarding_screen.dart';
import 'billing_operations_screen.dart';
import 'commerce_operations_screen.dart';
import 'content_operations_screen.dart';
import 'club_policy_documents_screen.dart';
import 'club_profile_editor_screen.dart';
import 'club_request_inbox_screen.dart';
import 'club_visibility_settings_screen.dart';
import 'club_contribution_rules_screen.dart';
import 'club_finance_cockpit_screen.dart';
import 'club_member_directory_screen.dart';
import 'file_operations_screen.dart';
import 'gamification_operations_screen.dart';
import 'learning_operations_screen.dart';
import 'legal_support_operations_screen.dart';
import 'marketplace_operations_screen.dart';
import 'membership_operations_screen.dart';
import 'notification_chat_operations_screen.dart';
import 'outfit_operations_screen.dart';
import 'platform_operations_screen.dart';
import 'public_growth_operations_screen.dart';
import 'public_top_content_screen.dart';
import 'release_readiness_screen.dart';
import 'safety_community_operations_screen.dart';
import 'search_operations_screen.dart';
import 'social_operations_screen.dart';
import 'sponsor_ads_operations_screen.dart';
import 'system_admin_operations_screen.dart';
import 'sports_operations_screen.dart';
import 'team_operations_screen.dart';
import 'club_team_admin_screen.dart';
import 'training_operations_screen.dart';
import 'trust_operations_screen.dart';
import 'wellbeing_operations_screen.dart';
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
import 'release_candidate_gate_register_screen.dart';
import 'release_evidence_center_screen.dart';

class OperationsHubScreen extends StatefulWidget {
  const OperationsHubScreen({super.key});

  @override
  State<OperationsHubScreen> createState() => _OperationsHubScreenState();
}

class _OperationsHubScreenState extends State<OperationsHubScreen> {
  String _filter = 'Alle';
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final visibleItems = _items
        .where((item) => AirmiusMvpSurface.isOperationVisible(item.title))
        .toList();
    final areas = [
      'Alle',
      ...{for (final item in visibleItems) item.area},
    ];
    final effectiveFilter = areas.contains(_filter) ? _filter : 'Alle';
    final items = visibleItems.where((item) {
      final areaMatch =
          effectiveFilter == 'Alle' || item.area == effectiveFilter;
      final text = '${item.title} ${item.body} ${item.area}'.toLowerCase();
      return areaMatch &&
          (_query.isEmpty || text.contains(_query.toLowerCase()));
    }).toList();

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('ops.hub'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: scope.t('ops.hub'),
        subtitle: scope.t('ops.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 14),
                  Text(
                    scope.t('ops.brandTitle'),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 24,
                      fontWeight: FontWeight.w900,
                      height: 1.08,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    scope.t('ops.brandBody'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 14),
                  SearchBox(
                    hint: scope.t('ops.search'),
                    onChanged: (value) => setState(() => _query = value.trim()),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final area in areas)
                        ChoiceChip(
                          label: Text(area),
                          selected: effectiveFilter == area,
                          onSelected: (_) => setState(() => _filter = area),
                          selectedColor: airmiusAccentColor(
                            context,
                          ).withValues(alpha: .22),
                          backgroundColor: airmiusSurfaceSoftColor(context),
                          side: BorderSide(
                            color: effectiveFilter == area
                                ? airmiusAccentColor(context)
                                : airmiusBorderColor(context),
                          ),
                          labelStyle: TextStyle(
                            color: effectiveFilter == area
                                ? airmiusTextColor(context)
                                : airmiusMutedColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(
                  child: MetricCard(
                    value: '${visibleItems.length}',
                    label: scope.t('ops.metricCount'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: AirmiusMvpSurface.showDeveloperSuites
                        ? 'Dev'
                        : 'MVP',
                    label: scope.t('ops.metricMode'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: '${_items.length - visibleItems.length}',
                    label: scope.t('ops.metricHidden'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            for (final item in items) ...[
              _OpsHubCard(item: item),
              const SizedBox(height: 12),
            ],
            if (items.isEmpty) EmptyPanel(scope.t('ops.empty')),
          ],
        ),
      ),
    );
  }
}

class _OpsHubCard extends StatelessWidget {
  const _OpsHubCard({required this.item});

  final _OpsHubItem item;

  @override
  Widget build(BuildContext context) {
    final color = _opsTone(context, item.color);
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => item.screen),
      ),
      borderColor: color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: color.withValues(alpha: .13),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: color.withValues(alpha: .48)),
            ),
            child: Icon(item.icon, color: color),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  item.body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(item.area, color: color),
                    StatusPill(scope.t('ops.nativeUi')),
                  ],
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
        ],
      ),
    );
  }
}

Color _opsTone(BuildContext context, Color semanticColor) {
  final scheme = Theme.of(context).colorScheme;
  if (semanticColor == AirmiusColors.green) return scheme.secondary;
  if (semanticColor == AirmiusColors.amber) return scheme.tertiary;
  if (semanticColor == AirmiusColors.red) return scheme.error;
  if (semanticColor == AirmiusColors.pink) return scheme.primaryContainer;
  return scheme.primary;
}

class _OpsHubItem {
  const _OpsHubItem({
    required this.area,
    required this.title,
    required this.body,
    required this.icon,
    required this.color,
    required this.screen,
  });

  final String area;
  final String title;
  final String body;
  final IconData icon;
  final Color color;
  final Widget screen;
}

final _items = <_OpsHubItem>[
  _OpsHubItem(
    area: 'Core',
    title: 'Konto & Sicherheit',
    body:
        'Login, Registrierung, OAuth, Passwort, 2FA, Export und Kontolöschung.',
    icon: Icons.manage_accounts_outlined,
    color: AirmiusColors.blue,
    screen: AccountOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'App Onboarding',
    body:
        'Erste Nutzung, Sprache, Rolle, Verein/Team, Datenschutz, Guardian und native Berechtigungen.',
    icon: Icons.rocket_launch_outlined,
    color: AirmiusColors.green,
    screen: AppOnboardingScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Airmius Design System',
    body:
        'Mobile Web-App-Patterns für Header, Drawer, Bottom Navigation, Panels, Formulare, Modals, Listen und Status.',
    icon: Icons.palette_outlined,
    color: AirmiusColors.blue,
    screen: AirmiusDesignSystemScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Globale Suche',
    body: 'Personen, Vereine, Teams, Dateien, Kurse, Events und Produkte.',
    icon: Icons.manage_search_outlined,
    color: AirmiusColors.blue,
    screen: SearchOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Inbox & Chat',
    body: 'Notifications, Push, Konversationen, Nachrichten, Typing und Mute.',
    icon: Icons.forum_outlined,
    color: AirmiusColors.blue,
    screen: NotificationChatOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Platform Ops',
    body: 'API, Session, Checkouts, Webhooks, SEO, Sprache und Systemstatus.',
    icon: Icons.monitor_heart_outlined,
    color: AirmiusColors.amber,
    screen: PlatformOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'API Connection',
    body:
        'Laravel-v1-Kontrakt für Auth, Vereine, Suche, Dateien, Chat, Social, Sport, Commerce, Admin und Public.',
    icon: Icons.api_outlined,
    color: AirmiusColors.blue,
    screen: ApiConnectionScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Web Route Parity',
    body:
        'Abgleich von Laravel-Webrouten mit nativer Flutter-UI für alle Hauptbereiche und offene API-Punkte.',
    icon: Icons.route_outlined,
    color: AirmiusColors.green,
    screen: WebRouteParityScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Release Readiness',
    body:
        'Mehrsprachigkeit, Store-Struktur, API-Verknuepfung, Safety und Betrieb als native Prüfansicht.',
    icon: Icons.fact_check_outlined,
    color: AirmiusColors.green,
    screen: ReleaseReadinessScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Store Device QA',
    body:
        'App Logo, Splash, Permissions, Datenschutz, Device-Matrix, Smoke-Tests und Store-Blocker.',
    icon: Icons.mobile_friendly_outlined,
    color: AirmiusColors.green,
    screen: StoreDeviceQaReadinessSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Auth Guard Status',
    body:
        'Complete Profile, Verify Email, Suspended, 2FA, Guardian Pending, Forbidden und Maintenance als Mobile-Flows.',
    icon: Icons.security_outlined,
    color: AirmiusColors.amber,
    screen: AuthGuardStatusSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Exact Page Flow Parity',
    body:
        'Show-, Create-, Edit-, Detail-, Checkout-, BankTransfer- und Statusseiten aus der Web-App als mobile Flow-Karten.',
    icon: Icons.view_list_outlined,
    color: AirmiusColors.blue,
    screen: ExactPageFlowParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Navigation Menu Parity',
    body:
        'Mobile Web-App-Shell mit Logo, Header-Suche, Rollen, Workspaces, Drawer-Gruppen und Bottom Navigation.',
    icon: Icons.menu_open_outlined,
    color: AirmiusColors.blue,
    screen: NavigationMenuParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Mobile Table Actions',
    body:
        'Web-Tabellen, Filter, Sortierung, Bulk-Aktionen, Export und Action-Sheets als mobile Kartenlisten.',
    icon: Icons.table_rows_outlined,
    color: AirmiusColors.green,
    screen: MobileTableActionParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Media Upload Attachments',
    body:
        'Dateien, Kamera, Galerie, Scan, Vorschau, Uploadstatus, Datenschutz und Dateimanager-Verknuepfung.',
    icon: Icons.cloud_upload_outlined,
    color: AirmiusColors.amber,
    screen: MediaUploadAttachmentParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Modal Sheet Overlays',
    body:
        'Web-Modals als mobile Dialoge, Bottom-Sheets, Drawer, Fullscreen-Formulare und Sticky-Actions.',
    icon: Icons.layers_outlined,
    color: AirmiusColors.blue,
    screen: ModalSheetOverlayParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Offline Sync Cache',
    body:
        'Offline-Banner, Cache, Queue, Retry, Drafts, Upload Resume, Konflikte und Sync-Historie für mobile API-Zustaende.',
    icon: Icons.sync_outlined,
    color: AirmiusColors.green,
    screen: OfflineSyncCacheParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Analytics Dashboards',
    body:
        'Web-Reports, KPI-Karten, Mini-Charts, Trends, Exporte und Empty/Loading/Error-Zustaende als mobile Dashboards.',
    icon: Icons.insights_outlined,
    color: AirmiusColors.amber,
    screen: AnalyticsChartDashboardParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Map Location Routes',
    body:
        'Sportkarte, Events, Fahrgemeinschaften, Vereinsadresse, Public-Orte, Routen, Permissions und Datenschutz.',
    icon: Icons.map_outlined,
    color: AirmiusColors.green,
    screen: MapLocationRouteParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Push Notification Deep Links',
    body:
        'Push, In-App, E-Mail, Chat, App-Badges, Ruhezeiten und Deep-Link-Routing für mobile Benachrichtigungen.',
    icon: Icons.notifications_none_outlined,
    color: AirmiusColors.blue,
    screen: PushNotificationDeeplinkParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'State Feedback Parity',
    body:
        'Skeleton Loading, Empty, Error, Success, Unauthorized, Rate Limit, Retry und API-Feedback als mobile Muster.',
    icon: Icons.task_alt_outlined,
    color: AirmiusColors.green,
    screen: StateFeedbackParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Input Keyboard Accessibility',
    body:
        'Mobile Keyboards, Masken, Pflichtfelder, Fokus, Autofill, Touch Targets und Screenreader-Hinweise für Formulare.',
    icon: Icons.keyboard_outlined,
    color: AirmiusColors.blue,
    screen: InputKeyboardAccessibilityParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Brand Theme Tokens',
    body:
        'Airmius-Logo, Header, Farben, Panels, Buttons, Inputs, Status-Pills, Overlays und mobile Design-Tokens.',
    icon: Icons.palette_outlined,
    color: AirmiusColors.amber,
    screen: BrandThemeTokenParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Localization RTL Formats',
    body:
        'DE, EN, FR, AR, RTL, Datum, Währung, Einheiten, Fehlertexte, Legal-Texte und API-Locale-Sync.',
    icon: Icons.translate_outlined,
    color: AirmiusColors.blue,
    screen: LocalizationRtlFormatParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Device Permission Privacy',
    body:
        'Standort, Kamera, Dateien, Fotos, Push, Biometrie, Zweckbindung, Fallbacks und Store-ready Permission-Texte.',
    icon: Icons.privacy_tip_outlined,
    color: AirmiusColors.green,
    screen: DevicePermissionPrivacyParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'End-to-End Journeys',
    body:
        'Verein beitreten, Antrag rückziehen, Training, Commerce, Guardian und Admin Review als durchgehende mobile User Journeys.',
    icon: Icons.route_outlined,
    color: AirmiusColors.blue,
    screen: EndToEndJourneyParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Session Security Tokens',
    body:
        'Session Restore, Token Refresh, 2FA, Recovery Codes, Device Sessions, API Tokens, Logout und sensible Account-Aktionen.',
    icon: Icons.security_outlined,
    color: AirmiusColors.amber,
    screen: SessionSecurityTokenParitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Web-App Conversion Control',
    body:
        'Zentrale Übersicht für komplette Flutter-Paritaet zur mobilen Web-App: Bereiche, Status, Qualitaet und API-Naechstschritt.',
    icon: Icons.dashboard_customize_outlined,
    color: AirmiusColors.blue,
    screen: WebAppFullConversionControlScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Web-App Module Completion',
    body:
        'Buendelt alle Web-App-Module und User-Flows als mobile Flutter-UI-Abdeckung für die spätere Laravel-API.',
    icon: Icons.fact_check_outlined,
    color: AirmiusColors.green,
    screen: WebAppModuleCompletionSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Clubs',
    title: 'Club Visibility Rules',
    body:
        'Vereine steuern Sichtbarkeit, Pflichtfelder, Datenschutz, Vereinsregeln, Dokument-Uploads und Admin-Benachrichtigungen mobil.',
    icon: Icons.rule_folder_outlined,
    color: AirmiusColors.amber,
    screen: ClubVisibilityRulesSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Clubs',
    title: 'Membership Requirements Builder',
    body:
        'Vereine definieren Personendaten, Wohndaten, Kontakt, Erziehungsberechtigte, Notfallkontakt, Sportdaten, Zahlung und Dokumentpflichten.',
    icon: Icons.format_list_bulleted_outlined,
    color: AirmiusColors.green,
    screen: ClubMembershipRequirementsBuilderScreen(),
  ),
  _OpsHubItem(
    area: 'Clubs',
    title: 'Application Inbox',
    body:
        'Vereinsadmins sehen neue Anfragen, Rückzuege, Dokumente, Rückfragen und Entscheidungen als mobile Inbox.',
    icon: Icons.inbox_outlined,
    color: AirmiusColors.blue,
    screen: ClubApplicationInboxSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Clubs',
    title: 'Dues Payment Rules',
    body:
        'Vereine steuern Beitragsgruppen, Zahlungszyklen, Zahlungsarten, SEPA, Barzahlung und Beitragsordnungs-Dokumente.',
    icon: Icons.receipt_long_outlined,
    color: AirmiusColors.amber,
    screen: ClubDuesPaymentRulesSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Clubs',
    title: 'Member Onboarding Acceptance',
    body:
        'Nach Annahme einer Anfrage werden Willkommensnachricht, Teamzuweisung, Zahlungsstart und digitale Mitgliedskarte vorbereitet.',
    icon: Icons.check_circle_outline,
    color: AirmiusColors.green,
    screen: ClubMemberOnboardingAcceptanceSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Clubs',
    title: 'Document Consent File Manager',
    body:
        'Vereine laden Datenschutz, Satzung, Beitragsordnung und Nachweise hoch, versionieren sie und verknuepfen Consent-Pflichten.',
    icon: Icons.folder_copy_outlined,
    color: AirmiusColors.blue,
    screen: ClubDocumentConsentFileManagerSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Notification Delivery Preferences',
    body:
        'Push, E-Mail, In-App-Badges, Digest, Ruhezeiten und thematische Zustellung für User, Vereine und Admins.',
    icon: Icons.notifications_active_outlined,
    color: AirmiusColors.pink,
    screen: NotificationDeliveryPreferencesSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Global Search Directory',
    body:
        'Globale Suche für Personen, Vereine, Teams, öffentliche Profile und direkte Beitrittsaktionen als mobile UI.',
    icon: Icons.manage_search_outlined,
    color: AirmiusColors.blue,
    screen: GlobalSearchDirectorySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Events',
    title: 'Calendar Event RSVP',
    body:
        'Kalender, Training, Events, Teilnahme, Anwesenheit, Erinnerungen und Fahrgemeinschaften als mobile Vereins-UI.',
    icon: Icons.event_available_outlined,
    color: AirmiusColors.green,
    screen: CalendarEventRsvpSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Teams',
    title: 'Team Roster Role Assignment',
    body:
        'Kader, Trainer, Captains, Join-Requests, Guardian-Sichtbarkeit und Teamrechte als mobile Vereins-UI.',
    icon: Icons.groups_2_outlined,
    color: AirmiusColors.amber,
    screen: TeamRosterRoleAssignmentSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Member',
    title: 'Member Self Service Center',
    body:
        'Mitglieder sehen aktive Vereine, offene Anfragen, digitale Karte, Beiträge, Dokumente, Aufgaben und Support zentral.',
    icon: Icons.badge_outlined,
    color: AirmiusColors.green,
    screen: MemberSelfServiceCenterSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Support',
    title: 'Support Ticket Service Center',
    body:
        'User, Vereine und Admins erstellen Tickets, sehen Verlauf, haengen Dateien an und eskalieren an Plattform oder Vereinsadmin.',
    icon: Icons.support_agent_outlined,
    color: AirmiusColors.blue,
    screen: SupportTicketServiceCenterSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Finance',
    title: 'Invoice Receipt Center',
    body:
        'Mitglieder und Vereine sehen offene Beiträge, Rechnungen, Quittungen, Zahlungsstatus, Mahnungen und Rückerstattungen.',
    icon: Icons.receipt_long_outlined,
    color: AirmiusColors.amber,
    screen: FinanceInvoiceReceiptCenterSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Community',
    title: 'Feed Community Composer',
    body:
        'Beiträge, Zielgruppen, Medien, Kommentare, Pinning, Meldungen und Moderationsprüfung als mobile Community-UI.',
    icon: Icons.forum_outlined,
    color: AirmiusColors.pink,
    screen: FeedCommunityComposerSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Messages',
    title: 'Messaging Conversation Center',
    body:
        'Private Chats, Vereinsadmin-Kanal, Teamchat, Support-Konversationen, Dateianhaenge, Lesestatus und Meldungen.',
    icon: Icons.chat_bubble_outline,
    color: AirmiusColors.blue,
    screen: MessagingConversationCenterSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Profile',
    title: 'Profile Privacy Visibility',
    body:
        'User steuern Profilsichtbarkeit, Suche, Vereinsmitgliedschaften, Teams, Nachrichtenrechte, Datenexport, Löschanfragen und Blockieren.',
    icon: Icons.privacy_tip_outlined,
    color: AirmiusColors.green,
    screen: ProfilePrivacyVisibilitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'Moderation Audit Queue',
    body:
        'Plattformadmins prüfen Meldungen, Vereinsverifizierung, Datenschutzanfragen, Eskalationen, Entscheidungen und Audit-Verlauf mobil.',
    icon: Icons.admin_panel_settings_outlined,
    color: AirmiusColors.amber,
    screen: AdminModerationAuditQueueSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Commerce',
    title: 'Marketplace Order Fulfillment',
    body:
        'Clubshop, Sponsorangebote, Warenkorb, Bestellungen, Abholung, Versand, Rückgabe und Statusmeldungen als mobile UI.',
    icon: Icons.storefront_outlined,
    color: AirmiusColors.blue,
    screen: MarketplaceOrderFulfillmentSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Ads',
    title: 'Sponsor Campaign Management',
    body:
        'Sponsoren, Kampagnen, Placements, Budgets, Creatives, Freigaben, Club-Targeting und Reporting als mobile UI.',
    icon: Icons.campaign_outlined,
    color: AirmiusColors.green,
    screen: SponsorCampaignManagementSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Analytics',
    title: 'Analytics Reporting KPI',
    body:
        'Mitglieder, Finanzen, Community, Events, Support, Moderation, Marketplace und Ads als mobile KPI- und Export-UI.',
    icon: Icons.insights_outlined,
    color: AirmiusColors.blue,
    screen: AnalyticsReportingKpiSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Learning',
    title: 'Course Progress Certificates',
    body:
        'Kurse, Lernpfade, Lektionen, Quiz, Fortschritt, Zertifikate, Nachweise und Downloads als mobile Learning-UI.',
    icon: Icons.school_outlined,
    color: AirmiusColors.amber,
    screen: LearningCourseProgressCertificateSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Gamification',
    title: 'Badge Achievement Center',
    body:
        'Badges, Rollen, Level, Trainings-Streaks, Lernnachweise, Teamleistungen und Erfolgsbenachrichtigungen als mobile UI.',
    icon: Icons.emoji_events_outlined,
    color: AirmiusColors.green,
    screen: GamificationBadgeAchievementSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Safety',
    title: 'Health Incident Reports',
    body:
        'Verletzungen, Gesundheitshinweise, Notfallkontakt, Guardian-Info, medizinische Notizen und Eskalationen als mobile Safety-UI.',
    icon: Icons.health_and_safety_outlined,
    color: AirmiusColors.blue,
    screen: HealthIncidentReportSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Locations',
    title: 'Location Map Facilities',
    body:
        'Vereinsorte, Trainingsstaetten, Treffpunkte, Routen, Fahrgemeinschaften, Abholung und Standort-Sichtbarkeit als mobile UI.',
    icon: Icons.map_outlined,
    color: AirmiusColors.amber,
    screen: LocationMapFacilitySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Data',
    title: 'Club Member Import Export',
    body:
        'CSV-Import, Feldmapping, Dublettenprüfung, Einladungen, externe Kontakte, rollenbasierte Exporte und Audit als mobile UI.',
    icon: Icons.sync_alt_outlined,
    color: AirmiusColors.green,
    screen: ClubMemberImportExportSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Content',
    title: 'Content Publishing CMS',
    body:
        'Blog, Vereinsnews, Newsletter, Sponsorinhalte, Vorschau, Medien, Freigaben, SEO und Publishing-Status als mobile UI.',
    icon: Icons.article_outlined,
    color: AirmiusColors.blue,
    screen: ContentPublishingCmsSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Role Workspace Switcher',
    body:
        'Mitglied, Vereinsadmin, Trainer, Guardian und Plattformadmin wechseln mobil zwischen passenden Startseiten, Rechten und Navigationskontexten.',
    icon: Icons.switch_account_outlined,
    color: AirmiusColors.amber,
    screen: RoleWorkspaceSwitcherSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'App Onboarding Permissions',
    body:
        'Erststart, Sprache, Rollenwahl, Workspace, Datenschutz, Push, Standort, Dateien und Kamera als native App-Onboarding-UI.',
    icon: Icons.phone_iphone_outlined,
    color: AirmiusColors.green,
    screen: AppOnboardingPermissionSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'API Empty Error States',
    body:
        'Loading, Empty, Error, Retry, Offline, Cache, Skeletons und API-Fehlerprotokollierung als mobile State-UI.',
    icon: Icons.sync_problem_outlined,
    color: AirmiusColors.blue,
    screen: ApiStateEmptyErrorSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Forms',
    title: 'Mobile Form Validation Schema',
    body:
        'Pflichtfelder, bedingte Regeln, Eingabemasken, Fehlertexte, Defaultwerte und Schema-Versionen für dynamische Vereinsformulare.',
    icon: Icons.rule_outlined,
    color: AirmiusColors.green,
    screen: MobileFormValidationSchemaSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Store',
    title: 'Native Store Release Assets',
    body:
        'App-Icon, Splash, Screenshots, Store-Texte, Datenschutzlabels, Berechtigungen und Release-Gates für Android und iOS.',
    icon: Icons.store_outlined,
    color: AirmiusColors.amber,
    screen: NativeStoreReleaseAssetsSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Club Public Profile Preview',
    body:
        'Mobile Vereinsseite mit Hero, Sichtbarkeitsregeln, Kontakt, Dokumenten, Teams, Admins und Mitgliedschafts-CTA wie in der Web-App.',
    icon: Icons.apartment_outlined,
    color: AirmiusColors.blue,
    screen: ClubPublicProfilePreviewSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'API',
    title: 'Laravel API Endpoint Mapping',
    body:
        'Web-Routen, Flutter-Screens, Laravel-v1-Endpunkte, Auth-Regeln, Payloads, Fehlerzustaende und spätere Client-Bindings.',
    icon: Icons.hub_outlined,
    color: AirmiusColors.blue,
    screen: LaravelApiEndpointMappingSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Commerce',
    title: 'Subscription Entitlement Feature Gates',
    body:
        'Tarife, Vereinslimits, Rollenrechte, Modulzugriff, Upgrade-Hinweise, gesperrte Features und API-ready Entitlements.',
    icon: Icons.workspace_premium_outlined,
    color: AirmiusColors.amber,
    screen: SubscriptionEntitlementFeatureGateSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'Audit Activity Timeline',
    body:
        'Aktivitaeten, Vereinsaktionen, Mitgliedsanträge, Zahlungen, Rollenwechsel, Security-Events, Exporte und Aufbewahrung als mobile Timeline.',
    icon: Icons.history_outlined,
    color: AirmiusColors.amber,
    screen: AuditActivityTimelineSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'API',
    title: 'Integration Webhook Provider Center',
    body:
        'Mail, Push, Payments, Storage, Maps, AI, Providerstatus, Webhooks, Secrets, Retry-Queue und Laravel Jobs als mobile Ops-UI.',
    icon: Icons.webhook_outlined,
    color: AirmiusColors.blue,
    screen: IntegrationWebhookProviderSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'System Job Queue Monitor',
    body:
        'E-Mail, Push, Upload-Scans, Importe, Exporte, Zahlungen, Webhooks, Retry, Dead Letter und Wartungsmodus als mobile Ops-UI.',
    icon: Icons.pending_actions_outlined,
    color: AirmiusColors.amber,
    screen: SystemJobQueueMonitorSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'System Status Incident Center',
    body:
        'Systemstatus, Wartungsfenster, Incident-Kommunikation, Service-Health, Nutzerhinweise und Admin-Eskalation als mobile UI.',
    icon: Icons.monitor_heart_outlined,
    color: AirmiusColors.green,
    screen: SystemStatusIncidentCenterSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Forms',
    title: 'Draft Autosave Recovery',
    body:
        'Autosave, Offline-Drafts, Wiederherstellung, Konfliktvergleich, Datenschutz-Ablauf und sichere Formularfortsetzung für lange mobile Flows.',
    icon: Icons.restore_outlined,
    color: AirmiusColors.green,
    screen: DraftAutosaveRecoverySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Invitation Access Links',
    body:
        'Einladungen, QR-Codes, Zugangslinks, Rollenbindung, Ablauf, Widerruf, Annahmestatus und Audit für Mitglieder, Teams und Guardians.',
    icon: Icons.qr_code_2_outlined,
    color: AirmiusColors.blue,
    screen: InvitationAccessLinkSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Forms',
    title: 'Consent Signature Versioning',
    body:
        'Dokumentversionen, Einwilligungen, digitale Bestätigungen, Guardian-Freigaben, SEPA-Signaturen und Audit-Nachweise.',
    icon: Icons.fact_check_outlined,
    color: AirmiusColors.green,
    screen: ConsentSignatureVersioningSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Member',
    title: 'Digital Member Card Check-in',
    body:
        'Digitale Mitgliedskarte, QR-Verifikation, Training-Check-in, Offline-Prüfung, Minimaldaten und Anwesenheits-Audit.',
    icon: Icons.badge_outlined,
    color: AirmiusColors.green,
    screen: DigitalMemberCardCheckinSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Deep Link Route Resolver',
    body:
        'Einladungen, QR-Codes, Push, E-Mail, Chat, Zahlung, Datei und Event-Links mit Auth, Workspace, Fallback und Routing-Audit.',
    icon: Icons.route_outlined,
    color: AirmiusColors.blue,
    screen: DeepLinkRouteResolverSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Role Home Dashboard Widgets',
    body:
        'Rollenbasierte Home-Widgets für Mitglied, Vereinsadmin, Trainer, Guardian und Plattformadmin mit Aufgaben, Statuskarten und Schnellaktionen.',
    icon: Icons.dashboard_customize_outlined,
    color: AirmiusColors.green,
    screen: RoleHomeDashboardWidgetSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Saved Views Search Alerts',
    body:
        'Gespeicherte Filter, Suchalarme, geteilte Listenansichten, Exporte und rollenbasierte Sichtbarkeit für große Web-App-Listen.',
    icon: Icons.bookmark_outline,
    color: AirmiusColors.blue,
    screen: SavedViewSearchAlertSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'Cross Module Approval Workflow',
    body:
        'Freigaben, Rückfragen, Entscheidungen, Eskalation und Audit über Mitgliedschaft, Dateien, Finanzen, Content, Events und Admin.',
    icon: Icons.fact_check_outlined,
    color: AirmiusColors.amber,
    screen: CrossModuleApprovalWorkflowSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Events',
    title: 'Facility Booking Resource Scheduler',
    body:
        'Plaetze, Hallen, Raeume, Geräte, Buchungen, Konflikte, Wartung, Rollenrechte und Serien-Termine als mobile Vereinsplanung.',
    icon: Icons.event_available_outlined,
    color: AirmiusColors.green,
    screen: FacilityBookingResourceSchedulerSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Events',
    title: 'Availability Absence Planning',
    body:
        'Verfuegbarkeit, Abwesenheiten, Guardian-Meldungen, Trainerübersicht, Gesundheitsnotizen und Anwesenheits-Sync für Teams.',
    icon: Icons.how_to_reg_outlined,
    color: AirmiusColors.green,
    screen: AvailabilityAbsencePlanningSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Events',
    title: 'Volunteer Shift Task Planner',
    body:
        'Helferlisten, Schichten, Aufgaben, Erinnerungen, Rollenregeln, Nachweise und offene Helferbedarfe für Events und Vereinsbetrieb.',
    icon: Icons.assignment_turned_in_outlined,
    color: AirmiusColors.blue,
    screen: VolunteerShiftTaskPlannerSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Club Survey Poll Voting',
    body:
        'Umfragen, Abstimmungen, Feedback, Quorum, Zielgruppen, Anonymitaet, Auswertung, Export und Audit für Vereine und Teams.',
    icon: Icons.how_to_vote_outlined,
    color: AirmiusColors.blue,
    screen: ClubSurveyPollVotingSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Meeting Minutes Decision Log',
    body:
        'Sitzungen, Agenda, Protokolle, Beschluesse, Aufgaben, Dokumentverknuepfung, Abstimmungen und Audit für Vereinsadmins.',
    icon: Icons.fact_check_outlined,
    color: AirmiusColors.amber,
    screen: MeetingMinutesDecisionLogSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Club Asset Inventory Checkout',
    body:
        'Vereinsmaterial, Schluessel, Trikots, Geräte, QR-Codes, Ausleihe, Rückgabe, Wartung, Fotos und Audit.',
    icon: Icons.inventory_2_outlined,
    color: AirmiusColors.green,
    screen: ClubAssetInventoryCheckoutSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Commerce',
    title: 'Sponsor Lead CRM Pipeline',
    body:
        'Sponsor-Leads, Kontakte, Angebote, Pakete, Freigaben, Dateien, Rechnungen, Kampagnen, Reporting und Renewal.',
    icon: Icons.handshake_outlined,
    color: AirmiusColors.green,
    screen: SponsorLeadCrmPipelineSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Sport',
    title: 'Training Plan Periodization',
    body:
        'Trainingszyklen, Coach-Freigaben, Belastung, Kalender-Sync, Athletendaten, Logs, Fortschritt und Anpassungen.',
    icon: Icons.fitness_center_outlined,
    color: AirmiusColors.green,
    screen: TrainingPlanPeriodizationSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Member',
    title: 'Member Feedback Satisfaction',
    body:
        'Mitgliederfeedback, Zufriedenheit, Beschwerden, Ideen, Trainerfeedback, Follow-ups, Trends, Support und Audit.',
    icon: Icons.sentiment_satisfied_alt_outlined,
    color: AirmiusColors.blue,
    screen: MemberFeedbackSatisfactionSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'Legal Policy Rollout',
    body:
        'Datenschutz, Satzung, Beitragsordnung, SEPA, Medienfreigabe, Versionierung, Consent, Guardian und Audit.',
    icon: Icons.gavel_outlined,
    color: AirmiusColors.amber,
    screen: LegalPolicyRolloutSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Core',
    title: 'Mobile Visual Parity Progress Audit',
    body:
        'Rest-Prozente, Web-App-Paritaet, UI-Qualitaet, API-Gaps, Store-Reife, Release-Gates und Produktfortschritt.',
    icon: Icons.speed_outlined,
    color: AirmiusColors.amber,
    screen: MobileVisualParityProgressAuditSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'API',
    title: 'Laravel API Binding Progress',
    body:
        'API-Client-Kontrakt, Auth, Vereine, Mitgliedsanträge, Upload-Intent, Notifications, Chat, Events, Billing und Rest-Gates.',
    icon: Icons.cloud_sync_outlined,
    color: AirmiusColors.blue,
    screen: LaravelApiBindingProgressSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'API',
    title: 'API Data Model Repository',
    body:
        'Typed Models, Pagination, Repository-Verträge und Laravel-Response-Mapping für User, Vereine, Antraege, Dateien, Events und Rechnungen.',
    icon: Icons.data_object_outlined,
    color: AirmiusColors.blue,
    screen: ApiDataModelRepositorySuiteScreen(),
  ),
  _OpsHubItem(
    area: 'API',
    title: 'API Repository Binding',
    body:
        'Repository-Implementierungen verbinden AirmiusApiClient, typed Models, Pagination und spätere Screens für echte Daten.',
    icon: Icons.alt_route_outlined,
    color: AirmiusColors.blue,
    screen: ApiRepositoryBindingSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'API',
    title: 'Auth State Token Store',
    body:
        'Session, persistenter TokenStore, Restore, Login, Logout, User Refresh, Locale, Auth-Phasen und offene Secure-Storage-Gates.',
    icon: Icons.security_outlined,
    color: AirmiusColors.amber,
    screen: AuthStateTokenStoreSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'API',
    title: 'Service Container Transport',
    body:
        'Environment, API-Client, Auth-State, TokenStore, RepositoryBundle, Offline Queue, Retry, Static Transport und naechste HTTP-Gates.',
    icon: Icons.settings_ethernet_outlined,
    color: AirmiusColors.blue,
    screen: ServiceContainerTransportSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'API',
    title: 'HTTP Transport Release',
    body:
        'Conditional HTTP transport für Web, Mobile/Desktop, Fallback, Demo, Laravel Base URL, CORS/Auth und Build-Gates.',
    icon: Icons.cloud_sync_outlined,
    color: AirmiusColors.blue,
    screen: HttpTransportReleaseSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Store',
    title: 'Store Release Configuration',
    body:
        'App-Metadaten, Bundle ID, Store-Texte, Berechtigungen, Datenschutzlabels, Supportkontakt, Release-Gates und offene Builds.',
    icon: Icons.app_settings_alt_outlined,
    color: AirmiusColors.amber,
    screen: StoreReleaseConfigurationSuiteScreen(),
  ),
  _OpsHubItem(
    area: 'Store',
    title: 'Release Candidate Gates',
    body:
        'Harte Rest-Gates mit Evidence: Analyze, Builds, Signing, Domain, Screenshots, API-QA, Legal und Localization.',
    icon: Icons.fact_check_outlined,
    color: AirmiusColors.amber,
    screen: ReleaseCandidateGateRegisterScreen(),
  ),
  _OpsHubItem(
    area: 'Store',
    title: 'Release Evidence Center',
    body:
        'Evidence-Paket für Logs, Artefakte, Domain-Checks, Screenshots, API-QA, Legal und Localization sammeln.',
    icon: Icons.inventory_outlined,
    color: AirmiusColors.green,
    screen: ReleaseEvidenceCenterScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Mitgliedschaft',
    body:
        'Antraege, Formularfelder, Zahlweisen, Dokumente, Rückzug und Adminentscheidungen.',
    icon: Icons.assignment_ind_outlined,
    color: AirmiusColors.green,
    screen: MembershipOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Mitgliederverwaltung',
    body:
        'Mitglieder, externe Kontakte, Rollen, Zahlstatus, Dokumente, Import, Statuswechsel und Massenaktionen.',
    icon: Icons.people_outline,
    color: AirmiusColors.green,
    screen: ClubMemberDirectoryScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Anfrage-Eingang',
    body:
        'Neue Mitgliedschaftsanfragen, Rückzuege, Antragstellerdaten, Dokumentstatus, Adminentscheidungen und Benachrichtigungen.',
    icon: Icons.inbox_outlined,
    color: AirmiusColors.green,
    screen: ClubRequestInboxScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Beitragsregeln',
    body:
        'Mitgliedschaftstypen, Beitragshoehen, Zahlungsrhythmus, Barzahlung, Überweisung, SEPA, Rechnungen und Mahnungen.',
    icon: Icons.payments_outlined,
    color: AirmiusColors.amber,
    screen: ClubContributionRulesScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Vereinsfinanzen',
    body:
        'Beiträge, Rechnungen, Zahlungen, Bankabgleich, SEPA, Mahnungen, DATEV und Monatsabschluss.',
    icon: Icons.account_balance_wallet_outlined,
    color: AirmiusColors.amber,
    screen: ClubFinanceCockpitScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Teams',
    body: 'Kader, Rollen, Einladungen, Teamdateien, Kalender und Teamchat.',
    icon: Icons.groups_2_outlined,
    color: AirmiusColors.green,
    screen: TeamOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Teamverwaltung',
    body:
        'Teamprofile, Kader, Trainer, Captain, Einladungen, Join-Requests, Kalender, Dateien und Chatrechte.',
    icon: Icons.diversity_3_outlined,
    color: AirmiusColors.green,
    screen: ClubTeamAdminScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Dateien',
    body:
        'Uploads, Vereinsdokumente, Share-Links, Teamdateien und Dokumentzwecke.',
    icon: Icons.folder_outlined,
    color: AirmiusColors.amber,
    screen: FileOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Vereinsdokumente & Regeln',
    body:
        'Datenschutz, Satzung, Beitragsordnung, SEPA, Uploadpflicht, Sichtbarkeit und Dateimanager-Verknuepfung.',
    icon: Icons.rule_folder_outlined,
    color: AirmiusColors.green,
    screen: ClubPolicyDocumentsScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Vereinssichtbarkeit',
    body:
        'Public-Profil, Adresse, Kontakt, Admins, Mitglieder, Teams, Beiträge, Dokumente, Sponsoren und Antragsschalter.',
    icon: Icons.visibility_outlined,
    color: AirmiusColors.blue,
    screen: ClubVisibilitySettingsScreen(),
  ),
  _OpsHubItem(
    area: 'Verein',
    title: 'Vereinsprofil bearbeiten',
    body:
        'Stammdaten, Logo, Banner, Kontakt, Adresse, Sportarten, Social Links, Verifizierung und Public Preview.',
    icon: Icons.edit_note_outlined,
    color: AirmiusColors.blue,
    screen: ClubProfileEditorScreen(),
  ),
  _OpsHubItem(
    area: 'Social',
    title: 'Safety & Community',
    body: 'Freunde, Fahrgemeinschaften, Guardian, Maturity und Safety Reports.',
    icon: Icons.security_outlined,
    color: AirmiusColors.red,
    screen: SafetyCommunityOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Social',
    title: 'Feed & Stories',
    body: 'Posts, Kommentare, Reaktionen, Medienrechte, Reports und Discovery.',
    icon: Icons.dynamic_feed_outlined,
    color: AirmiusColors.blue,
    screen: SocialOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Commerce',
    title: 'Marketplace',
    body: 'Shop, Produktdetail, Cart, Checkout, Orders, Retouren und Anbieter.',
    icon: Icons.storefront_outlined,
    color: AirmiusColors.blue,
    screen: MarketplaceOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Commerce',
    title: 'Commerce Admin',
    body:
        'Produkte, Coupons, Inventar, Versand, Retouren, Payouts und Website-Anfragen.',
    icon: Icons.store_mall_directory_outlined,
    color: AirmiusColors.green,
    screen: CommerceOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Commerce',
    title: 'Billing',
    body:
        'Zahlungen, Invoices, Banktransfer, Providerkosten, Downloads und Adminstatus.',
    icon: Icons.receipt_long_outlined,
    color: AirmiusColors.amber,
    screen: BillingOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Commerce',
    title: 'Outfit-Abos',
    body: 'Styleprofil, Plaene, Lieferungen, Zahlstatus, Pausen und Support.',
    icon: Icons.checkroom_outlined,
    color: AirmiusColors.green,
    screen: OutfitOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Commerce',
    title: 'Sponsoren & Ads',
    body:
        'Sponsorprofile, Pakete, Kampagnen, Active Ads, Clicks, Conversions und Leads.',
    icon: Icons.handshake_outlined,
    color: AirmiusColors.green,
    screen: SponsorAdsOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Public',
    title: 'Public Growth',
    body: 'Preise, Jobs, Werbeagentur, Website-Anfragen, Standort und Leads.',
    icon: Icons.campaign_outlined,
    color: AirmiusColors.blue,
    screen: PublicGrowthOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Public',
    title: 'Top Inhalte',
    body:
        'Kuratierte Public-Inhalte aus Blog, Kursen, Marketplace, Vereinen und Sponsoring.',
    icon: Icons.auto_awesome_outlined,
    color: AirmiusColors.amber,
    screen: PublicTopContentScreen(),
  ),
  _OpsHubItem(
    area: 'Public',
    title: 'Legal & Support',
    body:
        'Impressum, Datenschutz, AGB, Jugendschutz, Widerruf, Kontakt und Meldungen.',
    icon: Icons.gavel_outlined,
    color: AirmiusColors.amber,
    screen: LegalSupportOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'Trust',
    body:
        'Club-Verifizierung, Moderation, Reports, Inaktivitaet und Operating Contracts.',
    icon: Icons.verified_user_outlined,
    color: AirmiusColors.amber,
    screen: TrustOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'Access',
    body: 'Rollen, Rechte, Mitglieder, Permissions, Statuswechsel und Audit.',
    icon: Icons.admin_panel_settings_outlined,
    color: AirmiusColors.blue,
    screen: AccessOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'Content',
    body:
        'Blog, Medien, Learning Quality, Sponsorenfreigaben und Public Preview.',
    icon: Icons.article_outlined,
    color: AirmiusColors.blue,
    screen: ContentOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Admin',
    title: 'System Admin',
    body:
        'Mail-Center, Providerkosten, Systemsettings, Webhooks, Wartung, SEO, Audit und Betriebsnotizen.',
    icon: Icons.settings_suggest_outlined,
    color: AirmiusColors.amber,
    screen: SystemAdminOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Sport',
    title: 'Training',
    body: 'Events, Trainingsplaene, Logs, Coach Weekly, Feedback und Risiken.',
    icon: Icons.event_available_outlined,
    color: AirmiusColors.green,
    screen: TrainingOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Sport',
    title: 'Sportprofile',
    body: 'Sportarten, Ziele, Leistungsdaten, Coach-Freigabe und KI-Readiness.',
    icon: Icons.sports_outlined,
    color: AirmiusColors.blue,
    screen: SportsOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Sport',
    title: 'Wellbeing',
    body: 'Ernährung, Wasser, Barcode, Fotoanalyse, Routen, Tracks und Orte.',
    icon: Icons.favorite_border_outlined,
    color: AirmiusColors.green,
    screen: WellbeingOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Sport',
    title: 'Learning',
    body: 'Kurse, Lektionen, Quiz, Aufgaben, Zertifikate und Quality-Gates.',
    icon: Icons.school_outlined,
    color: AirmiusColors.green,
    screen: LearningOperationsScreen(),
  ),
  _OpsHubItem(
    area: 'Sport',
    title: 'Gamification',
    body: 'Badges, Regeln, XP, Streaks, Leaderboard und Datenschutz.',
    icon: Icons.workspace_premium_outlined,
    color: AirmiusColors.amber,
    screen: GamificationOperationsScreen(),
  ),
];
