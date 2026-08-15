import 'dart:convert';

import 'package:airmius/airmius_app.dart';
import 'package:airmius/core/airmius_accessibility_scope.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_api_repositories.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_module_access.dart';
import 'package:airmius/core/airmius_mvp_surface.dart';
import 'package:airmius/core/airmius_preferences.dart';
import 'package:airmius/core/airmius_preferences_store_base.dart';
import 'package:airmius/core/airmius_persona.dart';
import 'package:airmius/core/airmius_push_device_registry.dart';
import 'package:airmius/core/airmius_secure_token_store.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/airmius_theme.dart';
import 'package:airmius/core/airmius_theme_mode_scope.dart';
import 'package:airmius/core/airmius_upload_retry_policy.dart';
import 'package:airmius/models/app_tab.dart';
import 'package:airmius/models/club_summary.dart';
import 'package:airmius/models/footer_navigation_destination.dart';
import 'package:airmius/models/module_definition.dart';
import 'package:airmius/navigation/airmius_deep_link_navigator.dart';
import 'package:airmius/navigation/airmius_module_destination.dart';
import 'package:airmius/screens/admin_backoffice_screen.dart';
import 'package:airmius/screens/admin_commerce_operations_screen.dart';
import 'package:airmius/screens/admin_mail_center_screen.dart';
import 'package:airmius/screens/admin_platform_settings_screen.dart';
import 'package:airmius/screens/application_screen.dart';
import 'package:airmius/screens/auth_flows_screen.dart';
import 'package:airmius/screens/admin_center_screen.dart';
import 'package:airmius/screens/admin_user_management_screen.dart';
import 'package:airmius/screens/app_onboarding_screen.dart';
import 'package:airmius/screens/app_onboarding_permission_suite_screen.dart';
import 'package:airmius/screens/analytics_chart_dashboard_parity_suite_screen.dart';
import 'package:airmius/screens/brand_theme_token_parity_suite_screen.dart';
import 'package:airmius/screens/device_permission_privacy_parity_suite_screen.dart';
import 'package:airmius/screens/deep_link_route_resolver_suite_screen.dart';
import 'package:airmius/screens/invitation_access_link_suite_screen.dart';
import 'package:airmius/screens/consent_signature_versioning_suite_screen.dart';
import 'package:airmius/screens/end_to_end_journey_parity_suite_screen.dart';
import 'package:airmius/screens/auth_guard_status_suite_screen.dart';
import 'package:airmius/screens/volunteer_shift_task_planner_suite_screen.dart';
import 'package:airmius/screens/facility_booking_resource_scheduler_suite_screen.dart';
import 'package:airmius/screens/exact_page_flow_parity_suite_screen.dart';
import 'package:airmius/screens/draft_autosave_recovery_suite_screen.dart';
import 'package:airmius/screens/cross_module_approval_workflow_suite_screen.dart';
import 'package:airmius/screens/availability_absence_planning_suite_screen.dart';
import 'package:airmius/screens/role_home_dashboard_widget_suite_screen.dart';
import 'package:airmius/screens/laravel_api_endpoint_mapping_suite_screen.dart';
import 'package:airmius/screens/native_store_release_assets_suite_screen.dart';
import 'package:airmius/screens/club_public_profile_preview_suite_screen.dart';
import 'package:airmius/screens/system_job_queue_monitor_suite_screen.dart';
import 'package:airmius/screens/audit_activity_timeline_suite_screen.dart';
import 'package:airmius/screens/sponsor_lead_crm_pipeline_suite_screen.dart';
import 'package:airmius/screens/store_release_configuration_suite_screen.dart';
import 'package:airmius/screens/mobile_visual_parity_progress_audit_suite_screen.dart';
import 'package:airmius/screens/meeting_minutes_decision_log_suite_screen.dart';
import 'package:airmius/screens/club_asset_inventory_checkout_suite_screen.dart';
import 'package:airmius/screens/training_plan_periodization_suite_screen.dart';
import 'package:airmius/screens/member_feedback_satisfaction_suite_screen.dart';
import 'package:airmius/screens/legal_policy_rollout_suite_screen.dart';
import 'package:airmius/screens/laravel_api_binding_progress_suite_screen.dart';
import 'package:airmius/screens/club_survey_poll_voting_suite_screen.dart';
import 'package:airmius/screens/service_container_transport_suite_screen.dart';
import 'package:airmius/screens/http_transport_release_suite_screen.dart';
import 'package:airmius/screens/auth_state_token_store_suite_screen.dart';
import 'package:airmius/screens/api_repository_binding_suite_screen.dart';
import 'package:airmius/screens/api_data_model_repository_suite_screen.dart';
import 'package:airmius/screens/integration_webhook_provider_suite_screen.dart';
import 'package:airmius/screens/input_keyboard_accessibility_parity_suite_screen.dart';
import 'package:airmius/screens/localization_rtl_format_parity_suite_screen.dart';
import 'package:airmius/screens/navigation_menu_parity_suite_screen.dart';
import 'package:airmius/screens/offline_sync_cache_parity_suite_screen.dart';
import 'package:airmius/screens/operations_hub_screen.dart';
import 'package:airmius/screens/release_readiness_screen.dart';
import 'package:airmius/screens/session_security_token_parity_suite_screen.dart';
import 'package:airmius/screens/state_feedback_parity_suite_screen.dart';
import 'package:airmius/screens/badges_center_screen.dart';
import 'package:airmius/screens/blog_media_center_screen.dart';
import 'package:airmius/screens/carpool_center_screen.dart';
import 'package:airmius/screens/certificate_verification_screen.dart';
import 'package:airmius/screens/chat_detail_screen.dart';
import 'package:airmius/screens/club_cockpit_screen.dart';
import 'package:airmius/screens/club_request_inbox_screen.dart';
import 'package:airmius/screens/club_event_attendance_screen.dart';
import 'package:airmius/screens/club_membership_management_screen.dart';
import 'package:airmius/screens/club_membership_admin_screen.dart';
import 'package:airmius/screens/clubs_screen.dart';
import 'package:airmius/screens/commerce_center_screen.dart';
import 'package:airmius/screens/conversations_center_screen.dart';
import 'package:airmius/screens/dashboard_screen.dart';
import 'package:airmius/screens/daily_flow_screen.dart';
import 'package:airmius/screens/editorial_management_screen.dart';
import 'package:airmius/screens/edit_form_screen.dart';
import 'package:airmius/screens/friends_social_graph_screen.dart';
import 'package:airmius/screens/file_manager_screen.dart';
import 'package:airmius/screens/file_operations_screen.dart';
import 'package:airmius/screens/file_preview_screen.dart';
import 'package:airmius/screens/feed_center_screen.dart';
import 'package:airmius/screens/guardian_center_screen.dart';
import 'package:airmius/screens/guardian_child_overview_screen.dart';
import 'package:airmius/screens/guest_marketplace_parity_screen.dart';
import 'package:airmius/screens/guest_club_directory_screen.dart';
import 'package:airmius/screens/guest_learning_certificate_screen.dart';
import 'package:airmius/screens/guest_portal_screen.dart';
import 'package:airmius/screens/guest_blog_content_screen.dart';
import 'package:airmius/screens/guest_pricing_plans_screen.dart';
import 'package:airmius/screens/login_screen.dart';
import 'package:airmius/screens/learning_screen.dart';
import 'package:airmius/screens/learning_studio_course_suite_screen.dart';
import 'package:airmius/screens/map_location_route_parity_suite_screen.dart';
import 'package:airmius/screens/media_upload_attachment_parity_suite_screen.dart';
import 'package:airmius/screens/mobile_form_validation_schema_suite_screen.dart';
import 'package:airmius/screens/member_self_service_center_suite_screen.dart';
import 'package:airmius/screens/marketplace_order_fulfillment_suite_screen.dart';
import 'package:airmius/screens/location_map_facility_suite_screen.dart';
import 'package:airmius/screens/learning_course_progress_certificate_suite_screen.dart';
import 'package:airmius/screens/health_incident_report_suite_screen.dart';
import 'package:airmius/screens/club_visibility_rules_suite_screen.dart';
import 'package:airmius/screens/admin_moderation_audit_queue_suite_screen.dart';
import 'package:airmius/screens/analytics_reporting_kpi_suite_screen.dart';
import 'package:airmius/screens/api_state_empty_error_suite_screen.dart';
import 'package:airmius/screens/content_publishing_cms_suite_screen.dart';
import 'package:airmius/screens/ui_coverage_screen.dart';
import 'package:airmius/screens/ui_action_result_screen.dart';
import 'package:airmius/screens/legal_status_center_screen.dart';
import 'package:airmius/screens/lesson_detail_screen.dart';
import 'package:airmius/screens/marketplace_screen.dart';
import 'package:airmius/screens/media_guidelines_screen.dart';
import 'package:airmius/screens/membership_request_status_screen.dart';
import 'package:airmius/screens/maturity_center_screen.dart';
import 'package:airmius/screens/modal_sheet_overlay_parity_suite_screen.dart';
import 'package:airmius/screens/mobile_table_action_parity_suite_screen.dart';
import 'package:airmius/screens/global_search_screen.dart';
import 'package:airmius/screens/global_search_directory_suite_screen.dart';
import 'package:airmius/screens/module_screen.dart';
import 'package:airmius/screens/nutrition_center_screen.dart';
import 'package:airmius/screens/notification_preferences_screen.dart';
import 'package:airmius/screens/notifications_center_screen.dart';
import 'package:airmius/screens/outfit_operations_screen.dart';
import 'package:airmius/screens/outfit_subscription_center_screen.dart';
import 'package:airmius/screens/platform_admin_screen.dart';
import 'package:airmius/screens/push_notification_deeplink_parity_suite_screen.dart';
import 'package:airmius/screens/privacy_consent_center_screen.dart';
import 'package:airmius/screens/profile_screen.dart';
import 'package:airmius/screens/member_card_screen.dart';
import 'package:airmius/screens/exercise_library_screen.dart';
import 'package:airmius/screens/training_progress_screen.dart';
import 'package:airmius/screens/training_availability_screen.dart';
import 'package:airmius/screens/training_plan_templates_screen.dart';
import 'package:airmius/screens/public_growth_operations_screen.dart';
import 'package:airmius/screens/public_detail_screen.dart';
import 'package:airmius/screens/public_location_submission_screen.dart';
import 'package:airmius/screens/roles_permissions_screen.dart';
import 'package:airmius/screens/sport_map_center_screen.dart';
import 'package:airmius/screens/sports_center_screen.dart';
import 'package:airmius/screens/sponsor_management_screen.dart';
import 'package:airmius/screens/sponsor_campaign_management_suite_screen.dart';
import 'package:airmius/screens/sponsors_center_screen.dart';
import 'package:airmius/screens/settings_center_screen.dart';
import 'package:airmius/screens/saved_view_search_alert_suite_screen.dart';
import 'package:airmius/screens/sport_integrations_screen.dart';
import 'package:airmius/screens/sport_matching_screen.dart';
import 'package:airmius/screens/subscription_entitlement_feature_gate_suite_screen.dart';
import 'package:airmius/screens/system_status_incident_center_suite_screen.dart';
import 'package:airmius/screens/shared_file_access_screen.dart';
import 'package:airmius/screens/shell_screen.dart';
import 'package:airmius/screens/subscription_center_screen.dart';
import 'package:airmius/screens/support_helpdesk_screen.dart';
import 'package:airmius/screens/team_detail_screen.dart';
import 'package:airmius/screens/team_invitation_response_screen.dart';
import 'package:airmius/screens/teams_center_screen.dart';
import 'package:airmius/screens/training_event_detail_screen.dart';
import 'package:airmius/screens/training_center_screen.dart';
import 'package:airmius/screens/training_plan_detail_screen.dart';
import 'package:airmius/screens/training_plans_logs_screen.dart';
import 'package:airmius/screens/trainer_cockpit_screen.dart';
import 'package:airmius/screens/two_factor_security_screen.dart';
import 'package:airmius/screens/updates_center_screen.dart';
import 'package:airmius/screens/workspace_center_screen.dart';
import 'package:airmius/screens/role_workspace_switcher_suite_screen.dart';
import 'package:airmius/widgets/airmius_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Airmius app starts', (WidgetTester tester) async {
    await tester.pumpWidget(const AirmiusApp());
    expect(find.byType(AirmiusApp), findsOneWidget);
  });

  test('private post media keeps only the protected proxy candidate', () {
    const proxy = 'https://app.airmius.com/api/v1/posts/42/image';
    final post = AirmiusPost.fromJson({
      'id': 42,
      'user_id': 7,
      'content': 'Privater Beitrag',
      'visibility': 'organization',
      'moderation_status': 'approved',
      'post_type': 'normal',
      'content_origin': 'self',
      'image': proxy,
      'image_url': proxy,
      'image_proxy_url': proxy,
    });

    expect(post.imageUrl, proxy);
    expect(post.imageUrls, [proxy]);
  });

  testWidgets('legacy demo entries forward to real API-backed screens', (
    WidgetTester tester,
  ) async {
    final container = _widgetTestContainer();

    await _pumpAirmiusWidget(
      tester,
      container,
      const AdminUserManagementScreen(),
    );
    expect(find.byType(PlatformAdminScreen), findsOneWidget);

    await _pumpAirmiusWidget(
      tester,
      container,
      const GuestPricingPlansScreen(),
    );
    expect(find.byType(GuestPortalScreen), findsOneWidget);

    await _pumpAirmiusWidget(tester, container, const GuestBlogContentScreen());
    expect(find.byType(BlogMediaCenterScreen), findsOneWidget);

    await _pumpAirmiusWidget(
      tester,
      container,
      const PublicGrowthOperationsScreen(),
    );
    expect(find.byType(GuestPortalScreen), findsOneWidget);
  });

  testWidgets('login widget submits credentials and social provider', (
    WidgetTester tester,
  ) async {
    final container = _widgetTestContainer();
    String? submittedEmail;
    String? submittedPassword;
    String? socialProvider;

    await _pumpAirmiusWidget(
      tester,
      container,
      LoginScreen(
        authState: container.authState,
        onLogin: (email, password) {
          submittedEmail = email;
          submittedPassword = password;
        },
        onSocialLogin: (provider) => socialProvider = provider,
      ),
    );

    expect(find.text('Einloggen'), findsOneWidget);
    expect(find.text('Google'), findsOneWidget);

    await tester.enterText(
      find.byType(TextField).at(0),
      ' sportler@example.test ',
    );
    await tester.enterText(find.byType(TextField).at(1), 'secret-password');
    await tester.tap(find.text('Einloggen'));
    await tester.pump();

    expect(submittedEmail, 'sportler@example.test');
    expect(submittedPassword, 'secret-password');

    await tester.tap(find.text('Google'));
    await tester.pump();

    expect(socialProvider, 'google');
  });

  testWidgets('login shows accessible local validation before network calls', (
    WidgetTester tester,
  ) async {
    final container = _widgetTestContainer();
    var submitted = false;

    await _pumpAirmiusWidget(
      tester,
      container,
      LoginScreen(
        authState: container.authState,
        onLogin: (_, _) => submitted = true,
        onSocialLogin: (_) {},
      ),
    );

    await tester.tap(find.text('Einloggen'));
    await tester.pump();

    expect(submitted, isFalse);
    expect(
      find.text('Bitte gib eine gültige E-Mail-Adresse ein.'),
      findsOneWidget,
    );
    expect(find.text('Bitte gib dein Passwort ein.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('registration widget exposes MVP fields and local validation', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(1000, 1600));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const AuthFlowsScreen(),
    );

    expect(find.text('Registrieren'), findsWidgets);
    expect(find.text('Konto-Aktionen'), findsNothing);
    expect(find.text('Social Login'), findsNothing);
    expect(find.text('2FA'), findsNothing);
    expect(find.text('Profil'), findsNothing);
    expect(find.text('Gesperrt'), findsNothing);
    expect(find.text('Löschen'), findsNothing);
    expect(find.text('Sicherheitsübersicht öffnen'), findsNothing);
    expect(find.text('Vorname'), findsOneWidget);
    expect(find.text('Nachname'), findsOneWidget);
    expect(find.text('Land'), findsOneWidget);
    expect(find.text('Geburtsdatum'), findsOneWidget);
    expect(find.text('Geschlecht'), findsOneWidget);
    expect(find.text('Neues Passwort'), findsOneWidget);
    expect(find.text('Passwort bestätigen'), findsOneWidget);
    expect(find.text('AGB und Datenschutz akzeptieren'), findsOneWidget);

    await tester.ensureVisible(find.text('AGB und Datenschutz akzeptieren'));
    await tester.tap(find.byType(Checkbox).first);
    await tester.pump();
    await tester.ensureVisible(find.text('Konto erstellen'));
    await tester.tap(find.text('Konto erstellen'));
    await tester.pump();

    expect(find.text('Bitte wähle dein Geschlecht aus.'), findsOneWidget);
  });

  testWidgets('social registration keeps the selected account type', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(1000, 1600));
    String? selectedProvider;
    String? selectedAccountType;

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      AuthFlowsScreen(
        onSocialRegister: (provider, accountType) {
          selectedProvider = provider;
          selectedAccountType = accountType;
        },
      ),
    );

    await tester.tap(find.text('Trainer / Coach'));
    await tester.pump();
    await tester.tap(find.text('Mit Google registrieren'));

    expect(selectedProvider, 'google');
    expect(selectedAccountType, 'coach');
    expect(tester.takeException(), isNull);
  });

  testWidgets('registration keeps essential fields in compact Arabic layouts', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1200));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const AuthFlowsScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.25),
    );
    await tester.pumpAndSettle();

    expect(find.text('الدولة'), findsOneWidget);
    expect(find.text('تاريخ الميلاد'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('dashboard widget renders athlete MVP home', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(1000, 1600));

    final openedTabs = <AppTab>[];
    final openedModules = <String>[];

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      Scaffold(
        body: DashboardScreen(
          onOpenTab: openedTabs.add,
          onOpenModule: (module) => openedModules.add(module.title),
          requestedClubIds: const {},
        ),
      ),
    );

    expect(find.text('DASHBOARD'), findsOneWidget);
    expect(find.text('Hallo Sportler'), findsOneWidget);
    expect(
      find.text(
        'Deine wichtigsten Werte, Aufgaben und Schnellstarts auf einen Blick.',
      ),
      findsWidgets,
    );
    expect(find.text('Training'), findsWidgets);
    expect(find.text('Updates'), findsNothing);

    await tester.tap(find.text('Anpassen'));
    await tester.pump();

    expect(find.text('Widgets'), findsOneWidget);
    expect(find.text('Alles zeigen'), findsOneWidget);
    expect(openedTabs, isEmpty);
    expect(openedModules, isEmpty);
  });

  testWidgets('dashboard supports large RTL text and localized widgets', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1600));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      Scaffold(
        body: DashboardScreen(
          onOpenTab: (_) {},
          onOpenModule: (_) {},
          requestedClubIds: const {},
        ),
      ),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.champion,
    );
    await tester.pumpAndSettle();

    expect(find.text('مرحباً رياضي'), findsOneWidget);
    expect(find.text('التدريب'), findsWidgets);
    expect(find.text('الملفات'), findsWidgets);
    expect(find.text('مهم اليوم'), findsWidgets);
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('dashboard uses API file summary instead of fabricated metrics', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(1000, 1600));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body: '{"data":{"files":{"count":2,"bytes":4096}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      Scaffold(
        body: DashboardScreen(
          onOpenTab: (_) {},
          onOpenModule: (_) {},
          requestedClubIds: const {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('4.00 KB'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/dashboard/daily-flow'));
    expect(find.text('1.8 GB'), findsNothing);
    expect(find.text('24'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('daily flow renders authenticated API steps without demo values', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"score":68,"summary":"35 Trainingsminuten","coach_note":"Weiter so","steps":[{"key":"training","title":"Training","body":"Einheit A","meta":"Heute 18:00","progress":78,"cta":"Dokumentieren"}]}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const Scaffold(body: DailyFlowScreen()),
    );
    await tester.pumpAndSettle();

    expect(find.text('68%'), findsOneWidget);
    expect(find.text('Einheit A'), findsOneWidget);
    expect(find.text('35 Trainingsminuten'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/dashboard/daily-flow'));
    expect(find.text('Heute ist dein Flow zu 68% komplett.'), findsNothing);
  });

  test('login uses only api v1 auth endpoint when server rejects it', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 404,
        body: '{"message":"Not found"}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    await expectLater(
      client.login(email: 'sportler@example.test', password: 'secret'),
      throwsA(isA<AirmiusApiException>()),
    );

    expect(transport.paths, ['/api/v1/auth/login']);
  });

  test('password recovery uses the public api v1 contract', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    await client.requestPasswordReset(email: 'member@example.test');
    await client.resetPassword(
      token: 'secure-reset-token',
      email: 'member@example.test',
      password: 'New-password-456!',
      passwordConfirmation: 'New-password-456!',
    );

    expect(transport.requests[0].method, 'POST');
    expect(transport.requests[0].path, '/api/v1/auth/forgot-password');
    expect(
      transport.requests[0].body,
      containsPair('email', 'member@example.test'),
    );
    expect(transport.requests[1].method, 'POST');
    expect(transport.requests[1].path, '/api/v1/auth/reset-password');
    expect(
      transport.requests[1].body,
      containsPair('token', 'secure-reset-token'),
    );
    expect(
      transport.requests[1].body,
      containsPair('password_confirmation', 'New-password-456!'),
    );
  });

  test('auth state waits for two-factor challenge before storing token', () async {
    final tokenStore = AirmiusMemoryTokenStore();
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 202,
        body:
            '{"data":{"two_factor_required":true,"challenge_token":"challenge-token-with-sufficient-length-1234567890","expires_in":600}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"token":"secure-session-token","token_type":"Bearer","user":{"id":7,"name":"Mina Sprint","email":"mina@example.test","email_verified":true,"two_factor_enabled":true,"role":"athlete"}}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","email_verified":true,"two_factor_enabled":true,"role":"athlete","first_name":"Mina","last_name":"Sprint","birth_date":"2000-01-01","gender":"female","country":"DE"}}',
      ),
    ]);
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://airmius.test',
        enableOfflineQueue: false,
      ),
      transport: transport,
      tokenStore: tokenStore,
      pushDeviceStore: _MemoryPreferencesStore(),
    );

    await container.authState.signIn(
      email: 'mina@example.test',
      password: 'secret',
    );

    expect(container.authState.phase, AirmiusAuthPhase.twoFactorRequired);
    expect(await tokenStore.read(), isNull);
    expect(transport.requests.single.path, '/api/v1/auth/login');

    await container.authState.completeTwoFactor(value: '123456');

    expect(container.authState.phase, AirmiusAuthPhase.authenticated);
    expect((await tokenStore.read())?.token, 'secure-session-token');
    expect(
      transport.requests.map((request) => request.path),
      containsAllInOrder([
        '/api/v1/auth/login',
        '/api/v1/auth/two-factor-challenge',
        '/api/v1/me',
      ]),
    );
    expect(
      transport.requests[1].body,
      containsPair(
        'challenge_token',
        'challenge-token-with-sufficient-length-1234567890',
      ),
    );
    expect(transport.requests[1].body, containsPair('code', '123456'));
  });

  test('auth state requests and submits an email OTP', () async {
    final tokenStore = AirmiusMemoryTokenStore();
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 202,
        body:
            '{"data":{"two_factor_required":true,"challenge_token":"challenge-token-with-sufficient-length-1234567890","expires_in":600,"available_methods":["authenticator","recovery_code","email_otp"]}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body: '{"data":{"message":"sent","expires_in":600}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"token":"email-otp-session-token","token_type":"Bearer","user":{"id":7,"name":"Mina Sprint","email":"mina@example.test","email_verified":true,"two_factor_enabled":true,"role":"athlete"}}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","email_verified":true,"two_factor_enabled":true,"role":"athlete","first_name":"Mina","last_name":"Sprint","birth_date":"2000-01-01","gender":"female","country":"DE"}}',
      ),
    ]);
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://airmius.test',
        enableOfflineQueue: false,
      ),
      transport: transport,
      tokenStore: tokenStore,
      pushDeviceStore: _MemoryPreferencesStore(),
    );

    await container.authState.signIn(
      email: 'mina@example.test',
      password: 'secret',
    );

    expect(container.authState.supportsTwoFactorEmail, isTrue);
    expect(await container.authState.requestTwoFactorEmailCode(), isTrue);
    await container.authState.completeTwoFactor(
      value: '654321',
      emailCode: true,
    );

    expect(container.authState.phase, AirmiusAuthPhase.authenticated);
    expect(
      transport.requests.map((request) => request.path),
      containsAllInOrder([
        '/api/v1/auth/login',
        '/api/v1/auth/two-factor-challenge/email-code',
        '/api/v1/auth/two-factor-challenge',
        '/api/v1/me',
      ]),
    );
    expect(transport.requests[2].body, containsPair('email_code', '654321'));
    expect(transport.requests[2].body?.containsKey('code'), isFalse);
  });

  test(
    'account security client covers email and two-factor contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.resendEmailVerification();
      await client.verifyEmailLink(
        userId: 7,
        hash: 'email-hash',
        query: const {'expires': '123', 'signature': 'signed-value'},
      );
      await client.twoFactorStatus();
      await client.enableTwoFactor(currentPassword: 'current-password');
      await client.confirmTwoFactor(code: '123456');
      await client.regenerateTwoFactorRecoveryCodes(
        currentPassword: 'current-password',
      );
      await client.disableTwoFactor(currentPassword: 'current-password');

      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/me/email/verification-notification',
          '/api/v1/auth/verify-email/7/email-hash',
          '/api/v1/me/two-factor-authentication',
          '/api/v1/me/two-factor-authentication',
          '/api/v1/me/two-factor-authentication/confirm',
          '/api/v1/me/two-factor-recovery-codes',
          '/api/v1/me/two-factor-authentication',
        ]),
      );
      expect(transport.requests[1].query['signature'], 'signed-value');
      expect(
        transport.requests[3].body,
        containsPair('current_password', 'current-password'),
      );
      expect(transport.requests[4].body, containsPair('code', '123456'));
      expect(transport.requests[6].method, 'DELETE');
    },
  );

  test(
    'dashboard client reads the authenticated daily-flow contract',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body: '{"data":{"score":42}}',
        ),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      final response = await client.dashboardDailyFlow();

      expect(response['data'], containsPair('score', 42));
      expect(transport.paths, ['/api/v1/dashboard/daily-flow']);
      expect(transport.requests.single.method, 'GET');
    },
  );

  test('training plan and log client uses api v1 write contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.trainingPlans();
    await client.trainingPlan(8);
    await client.createTrainingPlan({'title': 'Plan'});
    await client.publishTrainingPlan(8);
    await client.duplicateTrainingPlan(8);
    await client.createTrainingPlanItem(8, {'title': 'Einheit'});
    await client.updateTrainingPlanItem(8, 3, {'title': 'Einheit 2'});
    await client.duplicateTrainingPlanItem(8, 3);
    await client.markTrainingPlanItemMissed(
      8,
      3,
      reason: 'krank',
      notes: 'Erholung',
    );
    await client.deleteTrainingPlanItem(8, 3);
    await client.previewAiTrainingPlan({'goal': '5 km'});
    await client.saveAiTrainingPlan({
      'plan': {'title': 'KI-Plan'},
    });
    await client.trainingLogs();
    await client.trainingLog(12);
    await client.createTrainingLog({'title': 'Lauf'});
    await client.updateTrainingLog(12, {'title': 'Lauf 2'});
    await client.deleteTrainingLog(12);
    await client.trainingAnalytics(userId: 5, days: 28);
    await client.trainingExercises(query: 'Kniebeuge', sportType: 'strength');
    await client.trainingExercise(21);
    await client.createTrainingExercise({
      'scope': 'personal',
      'name': 'Kniebeuge',
      'difficulty': 'beginner',
    });
    await client.updateTrainingExercise(21, {'name': 'Kniebeuge Plus'});
    await client.addTrainingExerciseToPlan(21, 8);
    await client.deleteTrainingExercise(21);
    await client.trainingAvailability();
    await client.createTrainingAvailability({
      'status': 'limited',
      'visibility': 'private',
      'starts_on': '2026-07-26',
    });
    await client.updateTrainingAvailability(31, {'status': 'available'});
    await client.clearTrainingAvailability(31);
    await client.trainingTemplates();
    await client.createTrainingTemplate(8, title: 'Vorlage');
    await client.instantiateTrainingTemplate(
      8,
      title: 'Neue Woche',
      startsOn: '2026-07-27',
      endsOn: '2026-08-02',
    );

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/training/plans',
        '/api/v1/training/plans/8',
        '/api/v1/training/plans',
        '/api/v1/training/plans/8/publish',
        '/api/v1/training/plans/8/duplicate',
        '/api/v1/training/plans/8/items',
        '/api/v1/training/plans/8/items/3',
        '/api/v1/training/plans/8/items/3/duplicate',
        '/api/v1/training/plans/8/items/3/missed',
        '/api/v1/training/plans/8/items/3',
        '/api/v1/training/ai/plans/preview',
        '/api/v1/training/ai/plans',
        '/api/v1/training/logs',
        '/api/v1/training/logs/12',
        '/api/v1/training/logs',
        '/api/v1/training/logs/12',
        '/api/v1/training/logs/12',
        '/api/v1/training/analytics',
        '/api/v1/training/exercises',
        '/api/v1/training/exercises/21',
        '/api/v1/training/exercises',
        '/api/v1/training/exercises/21',
        '/api/v1/training/exercises/21/add-to-plan',
        '/api/v1/training/exercises/21',
        '/api/v1/training/availability',
        '/api/v1/training/availability',
        '/api/v1/training/availability/31',
        '/api/v1/training/availability/31',
        '/api/v1/training/templates',
        '/api/v1/training/plans/8/template',
        '/api/v1/training/templates/8/instantiate',
      ]),
    );
    expect(transport.requests[2].method, 'POST');
    expect(transport.requests[8].body, containsPair('reason', 'krank'));
    expect(transport.requests[9].method, 'DELETE');
    expect(transport.requests[15].method, 'PUT');
    expect(transport.requests[16].method, 'DELETE');
    expect(transport.requests[24].method, 'GET');
    expect(transport.requests[25].method, 'POST');
    expect(transport.requests[26].method, 'PUT');
    expect(transport.requests[27].method, 'DELETE');
    expect(transport.requests[28].method, 'GET');
    expect(transport.requests[29].method, 'POST');
    expect(transport.requests[29].body, containsPair('title', 'Vorlage'));
    expect(transport.requests[30].method, 'POST');
    expect(
      transport.requests[30].body,
      containsPair('starts_on', '2026-07-27'),
    );
  });

  test(
    'event client covers management attendance and comment contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.eventComments(9);
      await client.createEventComment(9, 'Bis gleich');
      await client.updateEvent(9, {'title': 'Neuer Titel'});
      await client.cancelEvent(9, reason: 'Halle gesperrt');
      await client.eventAttendance(9);
      await client.recordEventAttendance(9, [
        {'user_id': 4, 'status': 'yes'},
      ]);
      await client.deleteEvent(9);

      expect(transport.requests.map((request) => request.path), [
        '/api/v1/events/9/comments',
        '/api/v1/events/9/comments',
        '/api/v1/events/9',
        '/api/v1/events/9/cancel',
        '/api/v1/events/9/attendance',
        '/api/v1/events/9/attendance',
        '/api/v1/events/9',
      ]);
      expect(transport.requests.map((request) => request.method), [
        'GET',
        'POST',
        'PUT',
        'POST',
        'GET',
        'PUT',
        'DELETE',
      ]);
      expect(transport.requests[1].body, containsPair('content', 'Bis gleich'));
      expect(
        transport.requests[3].body,
        containsPair('reason', 'Halle gesperrt'),
      );
      expect(transport.requests[5].body!['attendance'], isA<List<dynamic>>());
    },
  );

  test(
    'marketplace client covers buyer purchase and support contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.commerceProduct(4);
      await client.commerceProductReviews(4);
      await client.createCommerceProductReview(
        4,
        rating: 5,
        title: 'Sehr gut',
        body: 'Passt.',
      );
      await client.commerceCart();
      await client.addCommerceCartItem(4, quantity: 2);
      await client.updateCommerceCartItem(8, quantity: 3);
      await client.removeCommerceCartItem(8);
      await client.checkoutCommerceCart({
        'provider': 'bank_transfer',
        'accepted_terms': true,
      });
      await client.commerceOrder(12);
      await client.cancelCommerceOrder(12);
      await client.reportCommerceOrderIssue(12, note: 'Verpackung beschädigt');
      await client.requestCommerceOrderReturn(
        12,
        reason: 'Passt nicht',
        itemId: 7,
        quantity: 1,
      );

      expect(transport.requests.map((request) => request.path), [
        '/api/v1/commerce/products/4',
        '/api/v1/commerce/products/4/reviews',
        '/api/v1/commerce/products/4/reviews',
        '/api/v1/commerce/cart',
        '/api/v1/commerce/cart/items/4',
        '/api/v1/commerce/cart/items/8',
        '/api/v1/commerce/cart/items/8',
        '/api/v1/commerce/cart/checkout',
        '/api/v1/commerce/orders/12',
        '/api/v1/commerce/orders/12/cancel',
        '/api/v1/commerce/orders/12/issue',
        '/api/v1/commerce/orders/12/returns',
      ]);
      expect(transport.requests[2].method, 'POST');
      expect(transport.requests[2].body, containsPair('rating', 5));
      expect(transport.requests[5].method, 'PATCH');
      expect(transport.requests[6].method, 'DELETE');
      expect(
        transport.requests[7].body,
        containsPair('provider', 'bank_transfer'),
      );
      expect(
        transport.requests[10].body,
        containsPair('issue_note', 'Verpackung beschädigt'),
      );
      expect(
        transport.requests[11].body,
        containsPair('commerce_order_item_id', 7),
      );
    },
  );

  test('event models expose server permissions without private user data', () {
    final event = AirmiusEvent.fromJson({
      'id': 9,
      'user_id': 2,
      'conversation_id': 18,
      'title': 'Teamabend',
      'start_time': '2026-07-24T18:00:00Z',
      'type': 'meeting',
      'status': 'scheduled',
      'visibility': 'private',
      'participants_count': 1,
      'comments_count': 2,
      'yes_count': 1,
      'late_count': 0,
      'maybe_count': 0,
      'no_count': 0,
      'can_join': true,
      'can_update': true,
      'can_delete': true,
      'can_cancel': true,
      'can_manage_attendance': true,
    });
    final comment = AirmiusEventComment.fromJson({
      'id': 3,
      'event_id': 9,
      'content': 'Ich bin dabei.',
      'mine': true,
      'created_at': '2026-07-24T10:00:00Z',
      'user': {'id': 4, 'name': 'Mira Member'},
    });

    expect(event.conversationId, 18);
    expect(event.canUpdate, isTrue);
    expect(event.canManageAttendance, isTrue);
    expect(comment.userName, 'Mira Member');
    expect(comment.mine, isTrue);
  });

  test('event workspace exposes the server recurring-event entitlement', () {
    final workspace = AirmiusEventWorkspace.fromJson({
      'data': const [],
      'calendar_events': const [],
      'event_stats': const {'upcoming': 0, 'today': 0, 'cancelled': 0},
      'event_creation': const {'allows_recurring': true},
    });

    expect(workspace.allowsRecurring, isTrue);
    expect(
      AirmiusEventWorkspace.fromJson({
        'data': const [],
        'event_creation': const {'allows_recurring': false},
      }).allowsRecurring,
      isFalse,
    );
  });

  testWidgets(
    'event creation shows recurring options only when the API allows them',
    (WidgetTester tester) async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":[],"calendar_events":[],"event_stats":{"upcoming":0,"today":0,"cancelled":0},"event_creation":{"allows_recurring":true},"event_types":["training"],"visibilities":["public"],"clubs":[],"teams":[],"sports":[]}',
        ),
      );

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        const TrainingCenterScreen(),
        themeMode: ThemeMode.light,
        palette: AirmiusThemePalette.air,
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Erstellen').first);
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byType(TextFormField).first,
        'Wöchentliches Training',
      );
      await tester.tap(find.text('Weiter'));
      await tester.pump();
      await tester.tap(find.text('Weiter'));
      await tester.pump();

      expect(find.text('Wiederholung'), findsOneWidget);
      expect(find.text('Wochentage'), findsNothing);
      await tester.ensureVisible(
        find.byType(DropdownButtonFormField<String?>).last,
      );
      await tester.tap(find.byType(DropdownButtonFormField<String?>).last);
      await tester.pumpAndSettle();
      expect(find.text('Wöchentlich'), findsOneWidget);
      await tester.tap(find.text('Wöchentlich').last);
      await tester.pump();
      expect(find.text('Wochentage'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('training workspace scrolls as one continuous mobile page', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 700));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[],"calendar_events":[],"event_stats":{"upcoming":0,"today":0,"cancelled":0},"event_creation":{"allows_recurring":false},"event_types":["training","match"],"visibilities":["public","private"],"clubs":[],"teams":[],"sports":[]}',
      ),
    );
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TrainingCenterScreen(),
    );
    await tester.pumpAndSettle();

    final pageScroll = tester.state<ScrollableState>(
      find.descendant(
        of: find.byType(CustomScrollView),
        matching: find.byType(Scrollable),
      ),
    );
    expect(pageScroll.position.maxScrollExtent, greaterThan(0));
    expect(pageScroll.position.pixels, 0);

    await tester.drag(find.byType(CustomScrollView), const Offset(0, -350));
    await tester.pumpAndSettle();

    expect(pageScroll.position.pixels, greaterThan(0));
    expect(tester.takeException(), isNull);
  });

  testWidgets('training search opens compact filters in a modal sheet', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 800));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[],"calendar_events":[],"event_stats":{"upcoming":0,"today":0,"cancelled":0},"event_creation":{"allows_recurring":false},"event_types":["training","match"],"visibilities":["public","private"],"clubs":[],"teams":[],"sports":[]}',
      ),
    );
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TrainingCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Suche nach Titel, Ort, Team oder Verein'), findsNothing);
    await tester.tap(find.byTooltip('Suchen'));
    await tester.pumpAndSettle();

    expect(find.byType(BottomSheet), findsOneWidget);
    expect(
      find.text('Suche nach Titel, Ort, Team oder Verein'),
      findsOneWidget,
    );
    expect(find.text('Kommend'), findsOneWidget);
    expect(find.text('Vergangen'), findsOneWidget);
    expect(find.text('Alle Typen'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('event detail renders management attendance and comments', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1800));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":9,"user_id":2,"conversation_id":18,"title":"Teamabend","notes":"Gemeinsam planen","start_time":"2026-07-24T18:00:00Z","type":"meeting","status":"scheduled","visibility":"private","participants_count":1,"comments_count":1,"yes_count":1,"late_count":0,"maybe_count":0,"no_count":0,"can_join":true,"can_update":true,"can_delete":true,"can_cancel":true,"can_manage_attendance":true,"participants":[{"id":4,"name":"Mira Member","pivot":{"status":"yes"}}]}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":3,"event_id":9,"content":"Ich bringe Wasser mit.","mine":false,"created_at":"2026-07-24T10:00:00Z","user":{"id":4,"name":"Mira Member"}}],"meta":{"current_page":1,"last_page":1}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"name":"Mira Member","status":"yes"},{"id":5,"name":"Noah Neu","status":null}],"meta":{"event_id":9,"statuses":["yes","late","maybe","no"]}}',
      ),
    ]);
    final event = AirmiusEvent(
      id: 9,
      title: 'Teamabend',
      startsAt: DateTime.utc(2026, 7, 24, 18),
      type: 'meeting',
      status: 'scheduled',
      visibility: 'private',
      participantsCount: 0,
      commentsCount: 0,
      yesCount: 0,
      maybeCount: 0,
      noCount: 0,
      canJoin: true,
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      TrainingEventDetailScreen(event: event, fallbackBody: 'Gemeinsam planen'),
    );
    await tester.pumpAndSettle();

    expect(find.text('Anwesenheit verwalten'), findsOneWidget);
    expect(find.text('Mira Member'), findsWidgets);
    expect(find.text('Noah Neu'), findsOneWidget);
    expect(find.text('Ich bringe Wasser mit.'), findsOneWidget);
    expect(find.byType(PopupMenuButton<String>), findsOneWidget);

    await tester.tap(find.byType(PopupMenuButton<String>));
    await tester.pumpAndSettle();
    expect(find.text('Bearbeiten'), findsOneWidget);
    expect(find.text('Absagen'), findsWidgets);
    expect(find.text('Löschen'), findsOneWidget);
  });

  test(
    'nutrition client uses real day, goal, meal and water contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.nutrition(date: '2026-07-24');
      await client.updateNutritionGoal({'goal_type': 'maintain'});
      await client.searchNutritionFoods('Skyr');
      await client.lookupNutritionBarcode('1234567890123');
      await client.createNutritionMeal({'title': 'Runner Oats'});
      await client.updateNutritionMeal(9, {'title': 'Runner Oats 2'});
      await client.logNutritionWater(date: '2026-07-24', amountMl: 500);
      await client.deleteNutritionMeal(9);

      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/nutrition',
          '/api/v1/nutrition/goal',
          '/api/v1/nutrition/foods/search',
          '/api/v1/nutrition/foods/barcode',
          '/api/v1/nutrition/meals',
          '/api/v1/nutrition/meals/9',
          '/api/v1/nutrition/water',
          '/api/v1/nutrition/meals/9',
        ]),
      );
      expect(transport.requests[0].query, containsPair('date', '2026-07-24'));
      expect(transport.requests[1].method, 'PATCH');
      expect(transport.requests[5].method, 'PATCH');
      expect(transport.requests[6].body, containsPair('amount_ml', 500));
      expect(transport.requests[7].method, 'DELETE');
    },
  );

  test('badges client uses the personal award contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.badges();
    await client.badge(7);

    expect(transport.requests[0].method, 'GET');
    expect(transport.requests[0].path, '/api/v1/badges');
    expect(transport.requests[0].query, containsPair('per_page', '50'));
    expect(transport.requests[1].path, '/api/v1/badges/7');
  });

  test('sport profile client uses profile and skill write contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.sportProfiles();
    await client.sportCv();
    await client.sportCvForUser(7);
    await client.updateSportProfile(3, {'status': 'active'});
    await client.updateSportSkill(8, {'self_level': 'strong'});
    await client.deleteSportProfile(3);

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/sport-profiles',
        '/api/v1/users/me/sport-cv',
        '/api/v1/users/7/sport-cv',
        '/api/v1/sport-profiles/3',
        '/api/v1/sport-skills/8',
        '/api/v1/sport-profiles/3',
      ]),
    );
    expect(transport.requests[3].method, 'PUT');
    expect(transport.requests[4].method, 'PATCH');
    expect(transport.requests[5].method, 'DELETE');
  });

  test(
    'learning client covers course progress and participation contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.learning(query: 'Schutz');
      await client.learningCourse(4);
      await client.enrollLearningCourse(4);
      await client.completeLearningLesson(4, 8);
      await client.trackLearningLesson(4, 8, 120);
      await client.addLearningNote(4, 8, 'Notiz');
      await client.addLearningComment(4, 8, 'Frage');
      await client.submitLearningQuiz(4, 2, {'9': 'Antwort'});
      await client.submitLearningAssignment(4, 3, body: 'Abgabe');
      await client.reviewLearningCourse(4, rating: 5, body: 'Sehr gut');
      await client.learningCertificate(7);

      expect(transport.requests[0].query, containsPair('q', 'Schutz'));
      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/learning',
          '/api/v1/learning/courses/4',
          '/api/v1/learning/courses/4/enroll',
          '/api/v1/learning/courses/4/lessons/8/complete',
          '/api/v1/learning/courses/4/lessons/8/progress',
          '/api/v1/learning/courses/4/lessons/8/notes',
          '/api/v1/learning/courses/4/lessons/8/comments',
          '/api/v1/learning/courses/4/quizzes/2/attempts',
          '/api/v1/learning/courses/4/assignments/3/submissions',
          '/api/v1/learning/courses/4/reviews',
          '/api/v1/learning/certificates/7',
        ]),
      );
      expect(transport.requests[3].method, 'PUT');
      expect(transport.requests[7].body?['answers'], {'9': 'Antwort'});
    },
  );

  test(
    'trainer cockpit client uses coach-scoped read and feedback contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.trainerCockpit();
      await client.sendTrainerFeedback(17, 'Sehr gute Kontrolle.');

      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/trainer-cockpit',
          '/api/v1/training/logs/17/feedback',
        ]),
      );
      expect(transport.requests.first.method, 'GET');
      expect(transport.requests.last.method, 'POST');
      expect(
        transport.requests.last.body,
        containsPair('body', 'Sehr gute Kontrolle.'),
      );
    },
  );

  test('learning studio client uses protected creator contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.learningStudio(courseId: 4);
    await client.createLearningStudioCourse({'title': 'Sicher trainieren'});
    await client.updateLearningStudioCourse(4, {'status': 'published'});
    await client.createLearningStudioSection(4, {'title': 'Grundlagen'});
    await client.createLearningStudioLesson(4, {
      'learning_course_section_id': 2,
      'title': 'Warm-up',
    });
    await client.updateLearningStudioLesson(4, 8, {
      'title': 'Sicheres Warm-up',
    });
    await client.deleteLearningStudioLesson(4, 8);
    await client.reorderLearningStudioLessons(4, [
      {'id': 8, 'position': 1},
    ]);
    await client.replyLearningStudioQuestion(4, 11, 'Gern erklärt.');
    await client.updateLearningStudioQuestion(4, 11, 'resolved');
    await client.createLearningStudioQuiz(4, {'title': 'Grundlagen'});
    await client.deleteLearningStudioQuiz(4, 12);
    await client.createLearningStudioAssignment(4, {'title': 'Praxis'});
    await client.gradeLearningStudioAssignment(4, 13, {'status': 'passed'});
    await client.createLearningStudioCoupon(4, {'code': 'START10'});
    await client.grantLearningStudioEnrollment(4, 'lerner@example.test');
    await client.revokeLearningStudioEnrollment(4, 9);

    expect(transport.requests.first.query, containsPair('course', '4'));
    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/learning-studio',
        '/api/v1/learning-studio/courses',
        '/api/v1/learning-studio/courses/4',
        '/api/v1/learning-studio/courses/4/sections',
        '/api/v1/learning-studio/courses/4/lessons',
        '/api/v1/learning-studio/courses/4/lessons/8',
        '/api/v1/learning-studio/courses/4/lessons/8',
        '/api/v1/learning-studio/courses/4/lessons/reorder',
        '/api/v1/learning-studio/courses/4/comments/11/replies',
        '/api/v1/learning-studio/courses/4/comments/11',
        '/api/v1/learning-studio/courses/4/quizzes',
        '/api/v1/learning-studio/courses/4/quizzes/12',
        '/api/v1/learning-studio/courses/4/assignments',
        '/api/v1/learning-studio/courses/4/assignment-submissions/13',
        '/api/v1/learning-studio/courses/4/coupons',
        '/api/v1/learning-studio/courses/4/enrollments',
        '/api/v1/learning-studio/courses/4/enrollments/9/revoke',
      ]),
    );
    expect(transport.requests[1].method, 'POST');
    expect(transport.requests[2].method, 'PUT');
    expect(transport.requests[6].method, 'DELETE');
    expect(transport.requests[16].method, 'PUT');
  });

  test('rides client covers the full carpool lifecycle contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.rides();
    await client.createRide({'from': 'Koeln', 'to': 'Bonn'});
    await client.updateRide(5, {'from': 'Koeln', 'to': 'Siegburg'});
    await client.requestRide(5, message: 'Pünktlich');
    await client.leaveRide(5);
    await client.approveRideRequest(5, 9);
    await client.rejectRideRequest(5, 10);
    await client.removeRideMember(5, 11);
    await client.deleteRide(5);

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/rides',
        '/api/v1/rides',
        '/api/v1/rides/5',
        '/api/v1/rides/5/join',
        '/api/v1/rides/5/leave',
        '/api/v1/rides/5/requests/9/approve',
        '/api/v1/rides/5/requests/10/reject',
        '/api/v1/rides/5/members/11',
        '/api/v1/rides/5',
      ]),
    );
    expect(transport.requests[1].method, 'POST');
    expect(transport.requests[2].method, 'PUT');
    expect(transport.requests[3].body, containsPair('message', 'Pünktlich'));
    expect(transport.requests[7].method, 'DELETE');
    expect(transport.requests[8].method, 'DELETE');
  });

  test('public content client uses blog and sponsor contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    await client.publicBlog(query: 'Training', category: 'vereine');
    await client.publicBlogPost('sicher-im-verein');
    await client.publicSponsors();
    await client.publicClubs(
      query: 'Airmius',
      sport: 'Running',
      location: 'Berlin',
    );
    await client.publicMarketplace(query: 'Schuhe', category: 'service');
    await client.publicLearningCourses(query: 'Training', category: 'training');

    expect(transport.paths, [
      '/api/v1/public/blog',
      '/api/v1/public/blog/sicher-im-verein',
      '/api/v1/public/sponsors',
      '/api/v1/public/clubs',
      '/api/v1/public/marketplace',
      '/api/v1/public/learning/courses',
    ]);
    expect(transport.requests.first.query, containsPair('q', 'Training'));
    expect(transport.requests.first.query, containsPair('category', 'vereine'));
    expect(transport.requests[3].query, containsPair('q', 'Airmius'));
    expect(transport.requests[3].query, containsPair('sport', 'Running'));
    expect(transport.requests[3].query, containsPair('location', 'Berlin'));
    expect(transport.requests[4].query, containsPair('search', 'Schuhe'));
    expect(transport.requests[4].query, containsPair('category', 'service'));
    expect(transport.requests[5].query, containsPair('q', 'Training'));
    expect(transport.requests[5].query, containsPair('category', 'training'));
  });

  test(
    'public recruiting client preserves filters and interest payload',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
      );

      await client.publicRecruitingJobs(
        query: ' Coach ',
        type: 'professional',
        sportType: 'padel',
        address: 'Berlin',
        sort: 'oldest',
        page: 2,
      );
      await client.submitPublicRecruitingInterest(17, {
        'name': 'Nora Athlete',
        'email': 'nora@example.test',
        'message': 'Ich möchte helfen.',
        'accepted_privacy': true,
      }, idempotencyKey: 'recruiting-interest-17');

      expect(transport.paths, [
        '/api/v1/public/recruiting/jobs',
        '/api/v1/public/recruiting/jobs/17/interest',
      ]);
      expect(transport.requests.first.method, 'GET');
      expect(transport.requests.first.query, {
        'page': '2',
        'q': 'Coach',
        'type': 'professional',
        'sport_type': 'padel',
        'address': 'Berlin',
        'sort': 'oldest',
      });
      expect(transport.requests.last.method, 'POST');
      expect(
        transport.requests.last.headers,
        containsPair('Idempotency-Key', 'recruiting-interest-17'),
      );
      expect(
        transport.requests.last.body,
        containsPair('email', 'nora@example.test'),
      );
    },
  );

  test(
    'public certificate client uses the guest verification contract',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
      );

      await client.publicCertificate(' AIR-LEARN/VERIFY ');

      expect(
        transport.paths.single,
        '/api/v1/public/learning/certificates/AIR-LEARN%2FVERIFY',
      );
      expect(transport.requests.single.method, 'GET');
    },
  );

  testWidgets('certificate verification renders the public API result', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1400));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"code":"AIR-API-VERIFY","issued_at":"2026-07-25T10:00:00Z","student_name":"Teilnehmende Person","course_title":"API Verify Kurs","course_subtitle":"Öffentliche Kursbeschreibung","progress_percent":100,"tutor":{"id":7,"name":"Kursleitung"}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const CertificateVerificationScreen(),
    );
    await tester.enterText(find.byType(TextField), 'AIR-API-VERIFY');
    await tester.tap(find.text('Code prüfen'));
    await tester.pumpAndSettle();

    expect(find.text('Zertifikat gültig'), findsOneWidget);
    expect(find.text('API Verify Kurs'), findsOneWidget);
    expect(find.text('Teilnehmende Person'), findsOneWidget);
    expect(
      transport.paths,
      contains('/api/v1/public/learning/certificates/AIR-API-VERIFY'),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('guest marketplace renders published public offers', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1600));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"title":"Vereinsausrüstung","description":"Sicheres Trainingszubehör.","category":"service","offer_type":"service","price_cents":3900,"currency":"EUR","provider_name":"Öffentlicher Anbieter","delivery_label":"Digital","segment":"plans","badge":"Neu"}],"meta":{"total":1}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const GuestMarketplaceParityScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Öffentlicher Marketplace'), findsWidgets);
    expect(find.text('Vereinsausrüstung'), findsOneWidget);
    expect(find.text('Interesse senden'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/public/marketplace'));
    expect(tester.takeException(), isNull);
  });

  testWidgets('guest clubs renders the privacy-safe public directory', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1600));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":8,"name":"Airmius Running Club","sport_type":"Running","city":"Berlin","country":"DE","is_official":true,"membership_requests_enabled":true,"teams_count":3,"membership_types":[{"id":1,"name":"Aktiv","amount":"12.00","billing_interval":"monthly"}]}],"meta":{"total":1}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const GuestClubDirectoryScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Öffentliche Vereine'), findsWidgets);
    expect(find.text('Airmius Running Club'), findsOneWidget);
    expect(find.text('Offiziell'), findsOneWidget);
    expect(find.text('Nach diesem Verein fragen'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/public/clubs'));
    expect(tester.takeException(), isNull);
  });

  testWidgets('guest jobs navigation renders live public recruiting data', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":17,"title":"Padel Coach","type":"professional","description":"Begleite unser Nachwuchsteam.","location":"Berlin","workload":"20 Stunden","employment_type":"Teilzeit","club":{"id":8,"name":"Airmius Padel","sport_type":"Padel"}}],"meta":{"total":1}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const GuestPortalScreen(),
    );
    await tester.tap(find.text('Jobs'));
    await tester.pumpAndSettle();

    expect(find.text('Jobs & Ehrenamt'), findsWidgets);
    expect(find.text('Padel Coach'), findsOneWidget);
    expect(find.text('Airmius Padel • Padel'), findsOneWidget);
    expect(find.text('Ich habe Interesse'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/public/recruiting/jobs'));
    expect(tester.takeException(), isNull);
  });

  testWidgets('guest marketplace stays usable in Arabic with large text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1600));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"title":"معدات تدريب للنادي","description":"مستلزمات رياضية آمنة للتدريب.","category":"خدمة","price_cents":3900,"currency":"EUR","provider_name":"مزود عام","delivery_label":"رقمي","badge":"جديد"}],"meta":{"total":1}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const GuestMarketplaceParityScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('معدات تدريب للنادي'), findsOneWidget);
    expect(find.text('اسأل عن العرض'), findsOneWidget);
    expect(find.text('Public Marketplace'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('guest learning renders published courses and certificate entry', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":8,"title":"Vereinsadmin Grundlagen","subtitle":"Sicher organisieren","description":"Rollen und Datenschutz im Verein.","category":"Vereine","level":"beginner","is_free":true,"lessons_count":8,"tutor":{"id":2,"name":"Kursleitung"}}],"facets":{"categories":["Vereine"]},"meta":{"total":1}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const GuestLearningCertificateScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Öffentliches Lernen'), findsWidgets);
    expect(find.text('Vereinsadmin Grundlagen'), findsOneWidget);
    expect(find.text('Zertifikat prüfen'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/public/learning/courses'));
    expect(tester.takeException(), isNull);
  });

  testWidgets('public detail stays localized and exposes real public actions', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1400));
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const PublicDetailScreen(
        title: 'Vereine',
        body: 'Öffentliche Vereinsinformationen',
        icon: Icons.groups_outlined,
        kind: 'Public',
      ),
      language: AirmiusLanguage.de,
      textScaler: const TextScaler.linear(1.25),
    );
    await tester.pumpAndSettle();
    await tester.drag(find.byType(CustomScrollView), const Offset(0, -900));
    await tester.pumpAndSettle();
    expect(find.text('ÖFFENTLICHE INFORMATION'), findsOneWidget);
    expect(find.text('Datenschutz standardmäßig'), findsOneWidget);
    expect(find.text('Airmius kontaktieren'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  test(
    'management client covers editorial and sponsor write contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.editorialPosts(status: 'review');
      await client.createEditorialPost({'title': 'Neu'});
      await client.updateEditorialPost(4, {'title': 'Aktualisiert'});
      await client.deleteEditorialPost(4);
      await client.createEditorialCategory({'name': 'Training'});
      await client.updateEditorialCategory(3, {'name': 'Vereine'});
      await client.deleteEditorialCategory(3);
      await client.sponsorManagement();
      await client.createManagedSponsor({'name': 'Partner'});
      await client.updateManagedSponsor(7, {'name': 'Partner Plus'});
      await client.deleteManagedSponsor(7);

      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/editorial/posts',
          '/api/v1/editorial/posts',
          '/api/v1/editorial/posts/4',
          '/api/v1/editorial/posts/4',
          '/api/v1/editorial/categories',
          '/api/v1/editorial/categories/3',
          '/api/v1/editorial/categories/3',
          '/api/v1/sponsor-management',
          '/api/v1/sponsor-management',
          '/api/v1/sponsor-management/7',
          '/api/v1/sponsor-management/7',
        ]),
      );
      expect(transport.requests[2].method, 'PUT');
      expect(transport.requests[3].method, 'DELETE');
      expect(transport.requests[10].method, 'DELETE');
    },
  );

  test('user model preserves effective roles and permissions', () {
    final user = AirmiusUser.fromJson({
      'id': 7,
      'name': 'Mina Redaktion',
      'email': 'mina@example.test',
      'role': 'redaktor',
      'roles': ['redaktor'],
      'permissions': ['blog.view', 'blog.create'],
      'clubs': [
        {
          'id': 9,
          'name': 'Editorial Club',
          'can_manage': true,
          'membership': {'role': 'admin'},
        },
      ],
    });

    expect(user.hasRole('redaktor'), isTrue);
    expect(user.can('blog.view'), isTrue);
    expect(user.can('finance.edit'), isFalse);
    expect(user.clubs.single.canManage, isTrue);
  });

  test('sport map client uses real route, track and place contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.sportRoutes();
    await client.createSportRoute({'title': 'Runde'});
    await client.updateSportRoute(3, {'title': 'Neue Runde'});
    await client.duplicateSportRoute(3);
    await client.deleteSportRoute(3);
    await client.sportTracks();
    await client.completeSportTrack(4);
    await client.deleteSportTrack(4);
    await client.sportPlaces();
    await client.createSportPlace({'name': 'Laufbahn'});
    await client.updateSportPlace(5, {'name': 'Stadion'});
    await client.deleteSportPlace(5);

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/sport-routes',
        '/api/v1/sport-routes',
        '/api/v1/sport-routes/3',
        '/api/v1/sport-routes/3/duplicate',
        '/api/v1/sport-routes/3',
        '/api/v1/sport-tracks',
        '/api/v1/sport-tracks/4/complete',
        '/api/v1/sport-tracks/4',
        '/api/v1/sport-places',
        '/api/v1/sport-places',
        '/api/v1/sport-places/5',
        '/api/v1/sport-places/5',
      ]),
    );
    expect(transport.requests[2].method, 'PATCH');
    expect(transport.requests[4].method, 'DELETE');
    expect(transport.requests[6].method, 'POST');
    expect(transport.requests[10].method, 'PATCH');
  });

  test(
    'sport matching client covers partner and team matching contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'token',
      );

      await client.sportMatchings(mode: 'team', city: 'Berlin', sportId: 3);
      await client.createSportMatching({
        'mode': 'team',
        'sport_id': 3,
        'team_id': 8,
        'title': 'Gegner gesucht',
      });
      await client.applyForSportMatching(
        12,
        teamId: 9,
        message: 'Wir spielen.',
      );
      await client.decideSportMatchingApplication(12, 21, 'accepted');
      await client.cancelSportMatching(12);

      expect(
        transport.requests.map(
          (request) => '${request.method} ${request.path}',
        ),
        [
          'GET /api/v1/sport-matching',
          'POST /api/v1/sport-matching',
          'POST /api/v1/sport-matching/12/apply',
          'PUT /api/v1/sport-matching/12/applications/21',
          'POST /api/v1/sport-matching/12/cancel',
        ],
      );
      expect(transport.requests.first.query['mode'], 'team');
      expect(transport.requests.first.query['city'], 'Berlin');
      expect(transport.requests[2].body, containsPair('team_id', 9));
    },
  );

  testWidgets('sport matching screen separates partners and team opponents', (
    WidgetTester tester,
  ) async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":1,"mode":"partner","title":"Lauf in Kenitra","city":"Kenitra","country_code":"MA","radius_km":20,"starts_at":"2026-08-10T09:00:00Z","participants_needed":2,"skill_level":"recreational","mine":false,"sport":{"id":1,"name":"Laufen","slug":"running"},"owner":{"id":2,"name":"Nora"}}],"meta":{"sports":[{"id":1,"name":"Laufen","slug":"running"}],"teams":[]}}',
      ),
    );
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const SportMatchingScreen(),
    );
    await tester.pump();

    expect(find.text('Sportpartner'), findsOneWidget);
    expect(find.text('Teamgegner'), findsNothing);
    expect(find.text('Lauf in Kenitra'), findsOneWidget);
    expect(find.textContaining('Kenitra'), findsWidgets);

    await tester.tap(find.text('Konfigurieren'));
    await tester.pumpAndSettle();

    expect(find.text('Sportpartner'), findsWidgets);
    expect(find.text('Teamgegner'), findsOneWidget);

    final sportSearch = find.byWidgetPredicate(
      (widget) =>
          widget is TextField &&
          widget.decoration?.labelText?.startsWith('Wunschsport suchen') ==
              true,
    );
    expect(sportSearch, findsNothing);

    final sportConfigurationSearch = find.byWidgetPredicate(
      (widget) =>
          widget is TextField && widget.decoration?.labelText == 'Sportart',
    );
    expect(sportConfigurationSearch, findsOneWidget);
    await tester.enterText(sportConfigurationSearch, 'Lauf');
    await tester.pump();
    expect(find.text('Laufen'), findsWidgets);
    await tester.tap(find.widgetWithText(ListTile, 'Laufen'));
    await tester.pump();
    await tester.tap(find.text('Übernehmen'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Erstellen'));
    await tester.pumpAndSettle();

    expect(find.text('Stadt / Ort'), findsOneWidget);
    expect(find.text('Land'), findsOneWidget);
    expect(find.text('Land (ISO)'), findsNothing);
    expect(find.text('Deutschland'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('sport matching hides technical server errors from users', (
    WidgetTester tester,
  ) async {
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(
        transport: _RecordingTransport(
          const AirmiusApiResponse(
            statusCode: 500,
            body:
                '{"message":"An unexpected error occurred.","code":"server_error"}',
          ),
        ),
      ),
      const SportMatchingScreen(),
    );
    await tester.pumpAndSettle();

    expect(
      find.text(
        'Sport-Matching konnte gerade nicht geladen werden. Bitte versuche es erneut.',
      ),
      findsOneWidget,
    );
    expect(find.text('Erneut versuchen'), findsOneWidget);
    expect(find.textContaining('AirmiusApiException'), findsNothing);
    expect(find.textContaining('server_error'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  test('friends client uses list, invitation and removal contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.friends();
    await client.inviteFriend(email: 'friend@example.test');
    await client.acceptFriendInvitation(7);
    await client.declineFriendInvitation(8);
    await client.removeFriend(12);

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/friends',
        '/api/v1/friends/invitations',
        '/api/v1/friends/invitations/7/accept',
        '/api/v1/friends/invitations/8/decline',
        '/api/v1/friends/12',
      ]),
    );
    expect(
      transport.requests[1].body,
      containsPair('email', 'friend@example.test'),
    );
    expect(transport.requests[2].method, 'POST');
    expect(transport.requests[4].method, 'DELETE');
  });

  test('maturity client uses the account-scoped overview contract', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.maturityOverview();

    expect(transport.paths, ['/api/v1/maturity/overview']);
    expect(transport.requests.single.method, 'GET');
  });

  testWidgets('app onboarding remains readable in Arabic light trail mode', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 2100));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"scores":{"onboarding":60},"next_actions":[{"key":"name","done":true},{"key":"email_verified","done":false}]}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const AppOnboardingScreen(),
      language: AirmiusLanguage.ar,
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.trail,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(transport.paths, contains('/api/v1/maturity/overview'));
    expect(find.text('الإعداد'), findsWidgets);
    expect(find.text('الخصوصية والقواعد أولاً'), findsOneWidget);
    expect(find.text('الخطوة التالية'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('operations and release hubs adapt to Arabic light palettes', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 2100));
    final container = _widgetTestContainer();

    await _pumpAirmiusWidget(
      tester,
      container,
      const OperationsHubScreen(),
      language: AirmiusLanguage.ar,
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.champion,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();
    expect(find.text('المناطق'), findsOneWidget);
    expect(find.text('مخفي'), findsOneWidget);
    expect(tester.takeException(), isNull);

    await _pumpAirmiusWidget(
      tester,
      container,
      const ReleaseReadinessScreen(),
      language: AirmiusLanguage.ar,
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.champion,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();
    expect(find.text('اللغات'), findsOneWidget);
    expect(find.text('Flutter'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('parity suites stay readable in Arabic light trail mode', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 2400));
    final container = _widgetTestContainer();
    const children = <Widget>[
      NavigationMenuParitySuiteScreen(),
      OfflineSyncCacheParitySuiteScreen(),
      InputKeyboardAccessibilityParitySuiteScreen(),
      LocalizationRtlFormatParitySuiteScreen(),
      BrandThemeTokenParitySuiteScreen(),
      DevicePermissionPrivacyParitySuiteScreen(),
      SessionSecurityTokenParitySuiteScreen(),
      StateFeedbackParitySuiteScreen(),
      MapLocationRouteParitySuiteScreen(),
      MediaUploadAttachmentParitySuiteScreen(),
      ModalSheetOverlayParitySuiteScreen(),
      MobileFormValidationSchemaSuiteScreen(),
      AnalyticsChartDashboardParitySuiteScreen(),
      SubscriptionEntitlementFeatureGateSuiteScreen(),
      PushNotificationDeeplinkParitySuiteScreen(),
      MobileTableActionParitySuiteScreen(),
      IntegrationWebhookProviderSuiteScreen(),
      SystemStatusIncidentCenterSuiteScreen(),
      SavedViewSearchAlertSuiteScreen(),
      DeepLinkRouteResolverSuiteScreen(),
      InvitationAccessLinkSuiteScreen(),
      ConsentSignatureVersioningSuiteScreen(),
      EndToEndJourneyParitySuiteScreen(),
      AuthGuardStatusSuiteScreen(),
      VolunteerShiftTaskPlannerSuiteScreen(),
      FacilityBookingResourceSchedulerSuiteScreen(),
      ExactPageFlowParitySuiteScreen(),
      DraftAutosaveRecoverySuiteScreen(),
      CrossModuleApprovalWorkflowSuiteScreen(),
      AvailabilityAbsencePlanningSuiteScreen(),
      RoleHomeDashboardWidgetSuiteScreen(),
      LaravelApiEndpointMappingSuiteScreen(),
      NativeStoreReleaseAssetsSuiteScreen(),
      ClubPublicProfilePreviewSuiteScreen(),
      SystemJobQueueMonitorSuiteScreen(),
      AuditActivityTimelineSuiteScreen(),
      SponsorLeadCrmPipelineSuiteScreen(),
      StoreReleaseConfigurationSuiteScreen(),
      MobileVisualParityProgressAuditSuiteScreen(),
      MeetingMinutesDecisionLogSuiteScreen(),
      ClubAssetInventoryCheckoutSuiteScreen(),
      TrainingPlanPeriodizationSuiteScreen(),
      MemberFeedbackSatisfactionSuiteScreen(),
      LegalPolicyRolloutSuiteScreen(),
      LaravelApiBindingProgressSuiteScreen(),
      ClubSurveyPollVotingSuiteScreen(),
      ServiceContainerTransportSuiteScreen(),
      HttpTransportReleaseSuiteScreen(),
      AuthStateTokenStoreSuiteScreen(),
      ApiRepositoryBindingSuiteScreen(),
      ApiDataModelRepositorySuiteScreen(),
      AppOnboardingPermissionSuiteScreen(),
      SponsorCampaignManagementSuiteScreen(),
      RoleWorkspaceSwitcherSuiteScreen(),
      MemberSelfServiceCenterSuiteScreen(),
      MarketplaceOrderFulfillmentSuiteScreen(),
      LocationMapFacilitySuiteScreen(),
      LearningCourseProgressCertificateSuiteScreen(),
      HealthIncidentReportSuiteScreen(),
      ClubVisibilityRulesSuiteScreen(),
      AdminModerationAuditQueueSuiteScreen(),
      AnalyticsReportingKpiSuiteScreen(),
      ApiStateEmptyErrorSuiteScreen(),
      ContentPublishingCmsSuiteScreen(),
      UiCoverageScreen(),
      UiActionResultScreen(
        title: 'Testaktion',
        body: 'Eine sichere Aktion mit Status und nächstem Schritt.',
        status: 'Bereit',
        icon: Icons.check_circle_outline,
      ),
    ];

    for (final child in children) {
      await _pumpAirmiusWidget(
        tester,
        container,
        child,
        language: AirmiusLanguage.ar,
        themeMode: ThemeMode.light,
        palette: AirmiusThemePalette.trail,
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    }
  });

  test(
    'marketplace client uses catalog, wishlist and order contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.commerceProducts();
      await client.commerceWishlist();
      await client.addCommerceWishlist(7);
      await client.removeCommerceWishlist(7);
      await client.commerceOrders();

      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/commerce/products',
          '/api/v1/commerce/wishlist',
          '/api/v1/commerce/products/7/wishlist',
          '/api/v1/commerce/products/7/wishlist',
          '/api/v1/commerce/orders',
        ]),
      );
      expect(transport.requests[0].method, 'GET');
      expect(transport.requests[0].query, containsPair('page', '1'));
      expect(transport.requests[0].query, containsPair('per_page', '50'));
      expect(transport.requests[2].method, 'POST');
      expect(transport.requests[3].method, 'DELETE');
      expect(transport.requests[4].method, 'GET');
    },
  );

  test(
    'seller commerce client uses owner-scoped management contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.commerceSellerDashboard();
      await client.submitCommerceSellerApplication({
        'applicant_type': 'private',
      });
      await client.createCommerceSellerProduct({'title': 'Trainingsband'});
      await client.updateCommerceSellerProduct(4, {'title': 'Band Pro'});
      await client.updateCommerceSellerProductStatus(4, 'archived');
      await client.updateCommerceProviderProfile({'display_name': 'Shop'});
      await client.createCommerceProviderLocation({'name': 'Berlin'});
      await client.updateCommerceProviderLocation(3, {'name': 'Hamburg'});
      await client.deleteCommerceProviderLocation(3);
      await client.updateCommercePayoutProfile({'iban': 'DE123'});
      await client.requestCommercePayout(method: 'bank_transfer');
      await client.createCommerceCampaign({'name': 'Sommer'});
      await client.updateCommerceCampaign(9, {'name': 'Sommer Pro'});
      await client.updateCommerceCampaignStatus(9, 'paused');
      await client.deleteCommerceCampaign(9);
      await client.createCommerceWebsiteRequest({'domain': 'verein.example'});

      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/commerce/seller',
          '/api/v1/commerce/seller/application',
          '/api/v1/commerce/seller/products',
          '/api/v1/commerce/seller/products/4',
          '/api/v1/commerce/seller/products/4/status',
          '/api/v1/commerce/seller/provider-profile',
          '/api/v1/commerce/seller/provider-locations',
          '/api/v1/commerce/seller/provider-locations/3',
          '/api/v1/commerce/seller/provider-locations/3',
          '/api/v1/commerce/seller/payout-profile',
          '/api/v1/commerce/seller/payouts',
          '/api/v1/commerce/seller/campaigns',
          '/api/v1/commerce/seller/campaigns/9',
          '/api/v1/commerce/seller/campaigns/9/status',
          '/api/v1/commerce/seller/campaigns/9',
          '/api/v1/commerce/seller/website-requests',
        ]),
      );
      expect(transport.requests[0].method, 'GET');
      expect(transport.requests[2].method, 'POST');
      expect(transport.requests[3].method, 'PUT');
      expect(transport.requests[4].method, 'PATCH');
      expect(transport.requests[8].method, 'DELETE');
      expect(transport.requests[11].method, 'POST');
      expect(transport.requests[12].method, 'PUT');
      expect(transport.requests[13].method, 'PATCH');
      expect(transport.requests[14].method, 'DELETE');
      expect(
        transport.requests[14].body,
        containsPair('confirmation', 'delete'),
      );
    },
  );

  test('support client sends the protected contact contract', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 201, body: '{"data":{"sent":true}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.sendSupportContact({
      'name': 'Mina Sport',
      'email': 'mina@example.test',
      'subject': 'Hilfe',
      'message': 'Ein genauer Fehlerbericht.',
      'category': 'technical',
    });

    expect(transport.requests.single.method, 'POST');
    expect(transport.requests.single.path, '/api/v1/support/contact');
    expect(
      transport.requests.single.body,
      containsPair('category', 'technical'),
    );
  });

  test('support operations client uses protected SLA contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'support-token',
    );

    await client.adminSupportTickets(overdue: true);
    await client.adminUpdateSupportTicket(12, {
      'status': 'in_progress',
      'priority': 'high',
      'escalated': true,
    });

    expect(transport.paths, [
      '/api/v1/admin/support/tickets',
      '/api/v1/admin/support/tickets/12',
    ]);
    expect(transport.requests.first.query, {'overdue': '1'});
    expect(transport.requests.last.method, 'PATCH');
    expect(transport.requests.last.body, containsPair('escalated', true));
  });

  test('support and club models parse tenant SLA and onboarding contracts', () {
    final ticket = AirmiusSupportTicket.fromJson({
      'id': 9,
      'subject': 'Club setup',
      'message': 'Please review the setup.',
      'category': 'club',
      'priority': 'high',
      'status': 'open',
      'response_due_at': '2026-08-09T08:00:00Z',
      'due_at': '2026-08-10T04:00:00Z',
      'club': {'id': 17, 'name': 'Airmius Club'},
      'sla': {
        'state': 'breached',
        'response_overdue': true,
        'resolution_overdue': false,
        'is_overdue': true,
      },
    });
    final management = AirmiusClubManagement.fromJson({
      'can_manage': true,
      'onboarding': {
        'version': '2026-08-09.club-onboarding.v1',
        'completion_percent': 56,
        'steps': [
          {'key': 'profile', 'done': true},
        ],
      },
    });

    expect(ticket.clubId, 17);
    expect(ticket.clubName, 'Airmius Club');
    expect(ticket.slaState, 'breached');
    expect(ticket.responseOverdue, isTrue);
    expect(ticket.resolutionOverdue, isFalse);
    expect(ticket.responseDueAt, isNotNull);
    expect(management.onboarding['completion_percent'], 56);
    expect(management.onboarding['steps'], hasLength(1));
  });

  test('public interest client sends the guest contact contract', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 201, body: '{"data":{"sent":true}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    await client.sendPublicContact({
      'name': 'Gast Verein',
      'email': 'guest@example.test',
      'subject': 'Sponsoring',
      'message': 'Bitte senden Sie Informationen.',
    });

    expect(transport.requests.single.method, 'POST');
    expect(transport.requests.single.path, '/api/v1/public/contact');
  });

  test(
    'public location suggestions use the same guest contact contract',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 201,
          body: '{"data":{"sent":true}}',
        ),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
      );

      await client.sendPublicContact({
        'name': 'Sportverein Gast',
        'email': 'guest@example.test',
        'subject': 'Standort vorschlagen',
        'category': 'location_club',
        'message': 'Sportverein, Hauptstraße 1, 12345 Musterstadt',
        'privacy_consent': true,
      });

      expect(transport.requests.single.method, 'POST');
      expect(transport.requests.single.path, '/api/v1/public/contact');
      expect(
        transport.requests.single.body,
        containsPair('category', 'location_club'),
      );
    },
  );

  test('admin commerce client uses protected moderation contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'admin-token',
    );

    await client.adminCommerceDashboard();
    await client.adminCommerceCatalog();
    await client.adminUpdateCommerceProduct(4, {'status': 'published'});
    await client.adminUpdateSellerApplication(5, {'status': 'approved'});
    await client.adminUpdateWebsiteRequest(6, {'status': 'in_progress'});
    await client.adminUpdateCampaign(7, {'status': 'active'});
    await client.adminMarkCommerceOrderPaid(8);
    await client.adminUpdateCommerceShipping(8, {'shipping_status': 'shipped'});
    await client.adminCreateCommerceProduct({'title': 'Ball'});
    await client.adminEditCommerceProduct(4, {'title': 'Ball Plus'});
    await client.adminAdjustCommerceProductStock(4, {'quantity_delta': 2});
    await client.adminCreateCommerceCoupon({'code': 'APP10'});
    await client.adminRefundCommerceOrder(8, {'amount_cents': 500});
    await client.adminUpdateCommerceReturn(9, {'status': 'approved'});
    await client.adminCreateCommercePayout(5, {'method': 'bank_transfer'});
    await client.adminUpdateCommerceSettings({'company_country': 'DE'});
    await client.adminUpdateCommerceVisuals({'sources': <String, String>{}});

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/admin/commerce',
        '/api/v1/admin/commerce/catalog',
        '/api/v1/admin/commerce/products/4/status',
        '/api/v1/admin/commerce/seller-applications/5',
        '/api/v1/admin/commerce/website-requests/6',
        '/api/v1/admin/commerce/campaigns/7/status',
        '/api/v1/admin/commerce/orders/8/mark-paid',
        '/api/v1/admin/commerce/orders/8/shipping',
        '/api/v1/admin/commerce/products',
        '/api/v1/admin/commerce/products/4',
        '/api/v1/admin/commerce/products/4/stock',
        '/api/v1/admin/commerce/coupons',
        '/api/v1/admin/commerce/orders/8/refund',
        '/api/v1/admin/commerce/returns/9',
        '/api/v1/admin/commerce/payouts/users/5',
        '/api/v1/admin/commerce/settings',
        '/api/v1/admin/commerce/marketplace-visuals',
      ]),
    );
    expect(transport.requests[2].method, 'PATCH');
    expect(transport.requests[6].method, 'POST');
    expect(transport.requests[8].method, 'POST');
    expect(transport.requests[9].method, 'PUT');
    expect(transport.requests[13].method, 'PATCH');
    expect(transport.requests[14].method, 'POST');
    expect(transport.requests[15].method, 'PUT');
  });

  test('platform admin client uses protected management contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'admin-token',
    );

    await client.adminPlatformDashboard();
    await client.adminUpdateUserStatus(9, {
      'status': 'suspended',
      'reason': 'Security review',
      'days': 7,
    });
    await client.adminApproveClub(4, {
      'is_official': true,
      'official_club_number': 'VR-4',
    });
    await client.adminRejectClub(5, {'review_note': 'Documents missing'});
    await client.adminCreateSport({'name': 'Laufen', 'slug': 'laufen'});
    await client.adminUpdateSport(3, {'name': 'Trailrunning'});
    await client.adminDeleteSport(3);
    await client.adminCreateBadge({
      'key': 'starter',
      'name': 'Starter',
      'actor_type': 'sportler',
      'trigger': 'xp',
      'threshold': 10,
    });
    await client.adminUpdateBadge(5, {'name': 'Erste Schritte'});
    await client.adminDeleteBadge(5);
    await client.adminCreateRole({
      'name': 'moderator',
      'permissions': ['reports.review'],
    });
    await client.adminUpdateRole(6, {
      'description': 'Moderation',
      'permissions': ['reports.review'],
    });
    await client.adminDeleteRole(6);
    await client.adminCreatePermission({'name': 'reports.review'});
    await client.adminUpdateModerationFlag(11, {'status': 'dismissed'});
    await client.adminUpdateModerationReport(12, {'status': 'actioned'});
    await client.adminDecideModerationAppeal(12, {
      'appeal_status': 'accepted',
      'appeal_decision': 'Re-review required.',
    });
    await client.adminUpdateGamificationRule(13, {
      'label': 'Helpful post',
      'actor_type': 'sportler',
    });

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/admin/platform',
        '/api/v1/admin/platform/users/9/status',
        '/api/v1/admin/platform/clubs/4/approve',
        '/api/v1/admin/platform/clubs/5/reject',
        '/api/v1/admin/platform/sports',
        '/api/v1/admin/platform/sports/3',
        '/api/v1/admin/platform/sports/3',
        '/api/v1/admin/platform/badges',
        '/api/v1/admin/platform/badges/5',
        '/api/v1/admin/platform/badges/5',
        '/api/v1/admin/platform/roles',
        '/api/v1/admin/platform/roles/6',
        '/api/v1/admin/platform/roles/6',
        '/api/v1/admin/platform/permissions',
        '/api/v1/admin/platform/moderation/flags/11',
        '/api/v1/admin/platform/moderation/reports/12',
        '/api/v1/admin/platform/moderation/reports/12/appeal',
        '/api/v1/admin/platform/gamification-rules/13',
      ]),
    );
    expect(transport.requests[1].method, 'PATCH');
    expect(
      transport.requests[1].body,
      containsPair('reason', 'Security review'),
    );
    expect(transport.requests[4].method, 'POST');
    expect(transport.requests[6].method, 'DELETE');
    expect(transport.requests[6].body, containsPair('confirmation', 'delete'));
    expect(transport.requests[9].method, 'DELETE');
    expect(transport.requests[10].method, 'POST');
    expect(transport.requests[12].method, 'DELETE');
    expect(transport.requests[16].method, 'PATCH');
    expect(
      transport.requests[16].body,
      containsPair('appeal_status', 'accepted'),
    );
  });

  test('admin backoffice client uses protected finance contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'admin-token',
    );

    await client.adminBackofficeDashboard();
    await client.adminUpdateSubscriptionPlan(2, {'is_active': true});
    await client.adminAssignUserSubscription(3, {'subscription_plan_id': 2});
    await client.adminAssignClubSubscription(4, {'subscription_plan_id': 2});
    await client.adminCancelUserSubscription(5);
    await client.adminRenewUserSubscription(5, months: 2);
    await client.adminCancelClubSubscription(6);
    await client.adminRenewClubSubscription(6, months: 2);
    await client.adminMarkSubscriptionTransferPaid(7);
    await client.adminMarkSubscriptionInvoicePaid(8);
    await client.adminCreatePayment({'amount': 12.5});
    await client.adminDeletePayment(9);
    await client.adminCreateInvoice({'amount': 49.9});
    await client.adminUpdateInvoiceStatus(10, 'paid');
    await client.adminDeleteInvoice(10);
    await client.adminCreateContract({'name': 'Hosting'});
    await client.adminUpdateContract(11, {'status': 'paused'});
    await client.adminDeleteContract(11);

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/admin/backoffice',
        '/api/v1/admin/backoffice/plans/2',
        '/api/v1/admin/backoffice/users/3/subscription',
        '/api/v1/admin/backoffice/clubs/4/subscription',
        '/api/v1/admin/backoffice/user-subscriptions/5/cancel',
        '/api/v1/admin/backoffice/user-subscriptions/5/renew',
        '/api/v1/admin/backoffice/club-subscriptions/6/cancel',
        '/api/v1/admin/backoffice/club-subscriptions/6/renew',
        '/api/v1/admin/backoffice/transfers/7/mark-paid',
        '/api/v1/admin/backoffice/subscription-invoices/8/mark-paid',
        '/api/v1/admin/backoffice/payments',
        '/api/v1/admin/backoffice/payments/9',
        '/api/v1/admin/backoffice/invoices',
        '/api/v1/admin/backoffice/invoices/10/status',
        '/api/v1/admin/backoffice/invoices/10',
        '/api/v1/admin/backoffice/contracts',
        '/api/v1/admin/backoffice/contracts/11',
        '/api/v1/admin/backoffice/contracts/11',
      ]),
    );
    expect(transport.requests.first.method, 'GET');
    expect(transport.requests[1].method, 'PATCH');
    expect(transport.requests[2].method, 'PUT');
    expect(transport.requests[8].method, 'POST');
    expect(transport.requests[11].method, 'DELETE');
    expect(transport.requests[13].method, 'PATCH');
    expect(transport.requests.last.method, 'DELETE');
  });

  test('admin outfit client uses protected management contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'admin-token',
    );

    await client.adminOutfitDashboard();
    await client.adminCreateOutfitPlan({'name': 'Box'});
    await client.adminUpdateOutfitPlan(2, {'name': 'Box Plus'});
    await client.adminDeleteOutfitPlan(2);
    await client.adminUpdateOutfitVisuals('/images/outfit.webp');
    await client.adminMarkOutfitSubscriptionPaid(3, note: 'Paid');
    await client.adminMarkOutfitSubscriptionUnpaid(3, reason: 'Review');
    await client.adminUpdateOutfitShippingAddress(3, {
      'shipping_city': 'Berlin',
    });
    await client.adminSendOutfitPaymentReminder(3);
    await client.adminCancelOutfitSubscription(3, reason: 'Requested');
    await client.adminDeleteOutfitSubscription(3);
    await client.adminUpdateOutfitDelivery(4, {'status': 'preparing'});
    await client.adminUpdateOutfitDeliveryIssue(4, {
      'issue_status': 'reviewing',
    });
    await client.adminMarkOutfitDeliveryShipped(4, {
      'tracking_number': 'TRACK-4',
    });
    await client.adminMarkOutfitDeliveryDelivered(4);
    await client.adminDeleteOutfitDelivery(4);

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/admin/outfits',
        '/api/v1/admin/outfits/plans',
        '/api/v1/admin/outfits/plans/2',
        '/api/v1/admin/outfits/plans/2',
        '/api/v1/admin/outfits/visuals',
        '/api/v1/admin/outfits/subscriptions/3/mark-paid',
        '/api/v1/admin/outfits/subscriptions/3/mark-unpaid',
        '/api/v1/admin/outfits/subscriptions/3/shipping-address',
        '/api/v1/admin/outfits/subscriptions/3/payment-reminder',
        '/api/v1/admin/outfits/subscriptions/3/cancel',
        '/api/v1/admin/outfits/subscriptions/3',
        '/api/v1/admin/outfits/deliveries/4',
        '/api/v1/admin/outfits/deliveries/4/issue',
        '/api/v1/admin/outfits/deliveries/4/shipped',
        '/api/v1/admin/outfits/deliveries/4/delivered',
        '/api/v1/admin/outfits/deliveries/4',
      ]),
    );
    expect(transport.requests.first.method, 'GET');
    expect(transport.requests[1].method, 'POST');
    expect(transport.requests[2].method, 'PUT');
    expect(transport.requests[3].method, 'DELETE');
    expect(transport.requests[10].body, containsPair('confirmation', 'delete'));
    expect(transport.requests.last.method, 'DELETE');
  });

  test('admin mail client uses protected operations contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'admin-token',
    );

    await client.adminMailDashboard(status: 'failed', type: 'invoice.created');
    await client.adminUpdateMailPreferences({
      'invoice_primary_category': 'billing',
    });
    await client.adminUpdateMailSender('system', {
      'from_address': 'system@example.test',
    });
    await client.adminTestMailSender('system');
    await client.adminResendMailDelivery(7, 'billing');
    await client.adminResolveMailDelivery(7);

    expect(transport.paths, [
      '/api/v1/admin/mail',
      '/api/v1/admin/mail/preferences',
      '/api/v1/admin/mail/senders/system',
      '/api/v1/admin/mail/senders/system/test',
      '/api/v1/admin/mail/deliveries/7/resend',
      '/api/v1/admin/mail/deliveries/7/resolve',
    ]);
    expect(transport.requests.first.query, {
      'status': 'failed',
      'type': 'invoice.created',
    });
    expect(transport.requests[2].method, 'PUT');
    expect(transport.requests[4].body, containsPair('category', 'billing'));
  });

  test('admin system client uses protected settings contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'admin-token',
    );

    await client.adminSystemDashboard(month: '2026-07');
    await client.adminUpdateSystemSettings({'maintenance_enabled': true});

    expect(transport.paths, [
      '/api/v1/admin/system',
      '/api/v1/admin/system/settings',
    ]);
    expect(transport.requests.first.query, {'month': '2026-07'});
    expect(transport.requests.last.method, 'PUT');
  });

  test(
    'outfit client uses subscription lifecycle and issue contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.outfitSubscriptions();
      await client.updateOutfitStyleProfile({
        'sizes': ['M'],
      });
      await client.subscribeOutfitPlan(4, {
        'payment_provider': 'bank_transfer',
      });
      await client.pauseOutfitSubscription(8);
      await client.resumeOutfitSubscription(8);
      await client.cancelOutfitSubscription(8);
      await client.reportOutfitDeliveryIssue(
        12,
        type: 'wrong_item',
        description: 'Wrong size',
      );

      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/outfit-subscriptions',
          '/api/v1/outfit-subscriptions/style-profile',
          '/api/v1/outfit-subscriptions/plans/4',
          '/api/v1/outfit-subscriptions/8/pause',
          '/api/v1/outfit-subscriptions/8/resume',
          '/api/v1/outfit-subscriptions/8/cancel',
          '/api/v1/outfit-deliveries/12/issue',
        ]),
      );
      expect(transport.requests[0].method, 'GET');
      expect(transport.requests[1].method, 'PUT');
      expect(transport.requests[2].method, 'POST');
      expect(transport.requests[6].method, 'POST');
    },
  );

  test(
    'privacy client uses the minimized center and GDPR rights contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.privacyCenter();
      await client.updateSettings({
        'country': 'DE',
        'profile_visibility': 'private',
      });
      await client.privacyExport();
      await client.correctPrivacy({'city': 'Berlin'});
      await client.withdrawPrivacyConsents([
        'ads_personalization',
        'ads_measurement',
      ]);

      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/privacy',
          '/api/v1/settings',
          '/api/v1/privacy/export',
          '/api/v1/privacy/correction',
          '/api/v1/privacy/withdraw-consents',
        ]),
      );
      expect(transport.requests[0].method, 'GET');
      expect(transport.requests[1].method, 'PATCH');
      expect(transport.requests[2].method, 'GET');
      expect(transport.requests[3].method, 'PATCH');
      expect(transport.requests[4].method, 'POST');
      expect(
        transport.requests[4].body,
        containsPair('consents', ['ads_personalization', 'ads_measurement']),
      );
    },
  );

  test(
    'subscription client covers plans, checkout and lifecycle contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.subscriptionPlans(targetActor: 'sportler');
      await client.subscriptions();
      await client.startSubscriptionCheckout(
        5,
        provider: 'bank_transfer',
        billingInterval: 'monthly',
        acceptedTerms: true,
        clubId: 2,
      );
      await client.subscriptionCheckout(7);
      await client.cancelSubscriptionCheckout(7);
      await client.cancelUserSubscription(8);
      await client.renewUserSubscription(8, months: 2);
      await client.cancelClubSubscription(2, 9);
      await client.renewClubSubscription(2, 9, months: 3);

      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/subscription-plans',
          '/api/v1/subscriptions',
          '/api/v1/subscription-plans/5/checkout',
          '/api/v1/subscription-checkouts/7',
          '/api/v1/subscription-checkouts/7/cancel',
          '/api/v1/subscriptions/user/8/cancel',
          '/api/v1/subscriptions/user/8/renew',
          '/api/v1/clubs/2/subscriptions/9/cancel',
          '/api/v1/clubs/2/subscriptions/9/renew',
        ]),
      );
      expect(
        transport.requests[0].query,
        containsPair('target_actor', 'sportler'),
      );
      expect(transport.requests[2].method, 'POST');
      expect(transport.requests[2].body, containsPair('accepted_terms', true));
      expect(transport.requests[5].body, containsPair('mode', 'period_end'));
      expect(transport.requests[6].body, containsPair('months', 2));
      expect(transport.requests[8].body, containsPair('months', 3));
    },
  );

  testWidgets('training plans and logs screen has real empty states', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1600));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body: '{"data":[],"capabilities":{"can_manage_training_plans":true}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TrainingPlansLogsScreen(),
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('Trainingspläne & Logs'), findsWidgets);
    expect(find.text('Noch keine Trainingspläne vorhanden.'), findsOneWidget);
    expect(find.text('KI-Trainingsplan erstellen'), findsOneWidget);

    await tester.tap(find.text('Durchgeführt').first);
    await tester.pump();

    expect(
      find.text('Noch keine Trainingseinheiten dokumentiert.'),
      findsOneWidget,
    );
    expect(
      transport.paths.where((path) => path == '/api/v1/training/plans'),
      isNotEmpty,
    );
    expect(
      transport.paths.where((path) => path == '/api/v1/training/logs'),
      isNotEmpty,
    );
  });

  testWidgets('training templates screen renders an honest empty state', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TrainingPlanTemplatesScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Vorlagen'), findsWidgets);
    expect(
      find.text('Noch keine Trainingsplan-Vorlagen vorhanden.'),
      findsOneWidget,
    );
    expect(transport.paths, ['/api/v1/training/templates']);
  });

  testWidgets('legacy event and plan routes forward to API-backed workspaces', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const ClubEventAttendanceScreen(initialTab: 'Warteliste'),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('الفعاليات والتدريب'), findsWidgets);
    expect(find.text('Saisonauftakt ZBB'), findsNothing);
    expect(
      transport.paths.where((path) => path == '/api/v1/events'),
      isNotEmpty,
    );
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TrainingPlanDetailScreen(
        title: 'Legacy plan',
        body: 'Legacy body',
        status: 'Plan',
        icon: Icons.calendar_month_outlined,
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Trainingspläne & Logs'), findsWidgets);
    expect(find.text('Plan, Zuweisung, Regeln und Log-Pflicht'), findsNothing);
    expect(
      transport.paths.where((path) => path == '/api/v1/training/plans'),
      isNotEmpty,
    );
    expect(
      transport.paths.where((path) => path == '/api/v1/training/logs'),
      isNotEmpty,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('feed stays Arabic and usable with large RTL text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const FeedCenterScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('ما الجديد؟'), findsOneWidget);
    expect(find.text('لا توجد منشورات ظاهرة بعد.'), findsOneWidget);
    expect(find.text('What would you like to share?'), findsNothing);
    expect(transport.paths.where((path) => path == '/api/v1/feed'), isNotEmpty);
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('feed engagement summary wraps for a long Arabic post', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1700));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":8,"user_id":3,"content":"جلسة تدريب طويلة ومفيدة","visibility":"public","moderation_status":"approved","post_type":"normal","content_origin":"self","user":{"id":3,"name":"ميرا العداءة"},"created_at":"2026-07-24T10:00:00Z","comments_count":12,"likes_count":34,"helpfuls_count":5,"liked_by_me":false,"helpful_by_me":false,"can_update":false,"can_delete":false}],"meta":{"current_page":1,"last_page":1}}',
      ),
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":7,"name":"الجري","slug":"running","skills":[{"id":8,"name":"السرعة"}]}]}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const FeedCenterScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('جلسة تدريب طويلة ومفيدة'), findsOneWidget);
    expect(find.text('34 الإعجابات'), findsOneWidget);
    await tester.tap(find.text('ما الجديد؟'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('الجمهور والرياضة والنوع'));
    await tester.pumpAndSettle();
    expect(find.text('رياضة هذا المنشور'), findsOneWidget);
    await tester.tap(find.byType(DropdownButtonFormField<int?>).last);
    await tester.pumpAndSettle();
    expect(find.text('الجري'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('guest portal stays localized and readable in Arabic', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const GuestPortalScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('Airmius للزوار'), findsOneWidget);
    expect(find.text('اكتشف الأندية'), findsOneWidget);
    expect(find.text('Airmius for visitors'), findsNothing);
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'public location form wraps translated type choices on small RTL screens',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 1200));

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(),
        const PublicLocationSubmissionScreen(),
        language: AirmiusLanguage.ar,
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('نادي'), findsOneWidget);
      expect(find.text('منشأة رياضية'), findsOneWidget);
      expect(find.byType(ChoiceChip), findsNWidgets(4));
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('nutrition screen renders the real empty API state', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"selected_date":"2026-07-24","goal":{"goal_type":"maintain","diet_style":"balanced","daily_calories_target":2200,"protein_target_g":120,"carbs_target_g":260,"fat_target_g":75,"water_target_ml":2500,"water_target_mode":"manual"},"meals":[],"summary":{"calories":0,"protein_g":0,"carbs_g":0,"fat_g":0,"water_ml":0},"weekly_summaries":[],"water_recommendation":{"target_ml":2500,"source_label":"Manuell festgelegt"},"catalog":{"meal_types":[{"key":"breakfast","label":"Frühstück"},{"key":"snack","label":"Snack"}],"goal_types":[{"key":"maintain","label":"Gewicht halten"}],"diet_styles":[{"key":"balanced","label":"Ausgewogen"}],"source_types":[{"key":"manual","label":"Manuell"}]},"recipes":[],"tips":[],"ai_capabilities":{"privacy":{"exif_removed":true,"store_uploads":false,"max_image_kb":5120},"nutrition_image_analysis":{"available":true}}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const NutritionCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(
      find.text('Für diesen Tag gibt es noch keine Mahlzeiten oder Getränke.'),
      findsOneWidget,
    );
    expect(find.byTooltip('Eintrag hinzufügen'), findsOneWidget);
    expect(find.text('Lebensmittel suchen'), findsNothing);
    expect(find.text('Barcode nachschlagen'), findsNothing);
    await tester.tap(find.byIcon(Icons.add_circle_outline));
    await tester.pumpAndSettle();

    expect(find.text('Was möchtest du erfassen?'), findsOneWidget);
    expect(find.text('Mahlzeit erfassen'), findsOneWidget);
    expect(find.text('Lebensmittel suchen'), findsOneWidget);
    expect(find.text('Barcode nachschlagen'), findsOneWidget);
    expect(find.text('Mahlzeit per Foto schätzen'), findsOneWidget);
    expect(find.text('1840'), findsNothing);
    expect(transport.paths, contains('/api/v1/nutrition'));
  });

  testWidgets('water updates inline and can be undone without reloading', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1200));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"selected_date":"2026-07-28","goal":{"water_target_ml":2500,"water_target_mode":"manual"},"meals":[],"summary":{"water_ml":400},"weekly_summaries":[],"water_recommendation":{"target_ml":2500,"source_label":"Manuell festgelegt"},"catalog":{},"recipes":[],"tips":[],"ai_capabilities":{}}}',
      ),
      const AirmiusApiResponse(
        statusCode: 201,
        body:
            '{"data":{"id":91,"water_ml":250,"water_total_ml":650},"message":"Trinken gespeichert."}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body: '{"data":{"deleted":true}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const NutritionCenterScreen(initialSection: NutritionSection.drink),
    );
    await tester.pumpAndSettle();

    expect(find.text('400 / 2500 ml'), findsOneWidget);
    await tester.tap(find.text('250 ml'));
    await tester.pumpAndSettle();

    expect(find.text('650 / 2500 ml'), findsOneWidget);
    expect(find.text('Rückgängig'), findsOneWidget);
    expect(
      transport.requests
          .where((request) => request.path == '/api/v1/nutrition')
          .length,
      1,
    );

    await tester.tap(find.text('Rückgängig'));
    await tester.pumpAndSettle();

    expect(find.text('400 / 2500 ml'), findsOneWidget);
    expect(
      transport.requests.map((request) => request.path),
      containsAllInOrder([
        '/api/v1/nutrition',
        '/api/v1/nutrition/water',
        '/api/v1/nutrition/meals/91',
      ]),
    );
    expect(
      transport.requests
          .where((request) => request.path == '/api/v1/nutrition')
          .length,
      1,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('badges screen renders a real personal award', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":7,"reason":"Erstes Training","meta":{"xp":25,"level":2},"awarded_at":"2026-07-24T10:00:00Z","badge":{"id":3,"key":"first-training","name":"Trainingsstart","description":"Erstes Training erfolgreich dokumentiert.","actor_type":"sportler","trigger":"training_logged"}}]}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const BadgesCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Meine Badges'), findsWidgets);
    expect(find.text('Trainingsstart'), findsOneWidget);
    expect(find.text('+25 XP'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/badges'));
  });

  testWidgets('sports screen renders real profile readiness', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1200));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"has_profile":true,"sport":{"id":3,"name":"Laufen","slug":"running","category":"endurance"},"status":"active","experience_level":"intermediate","visibility":"trainer","metrics":{"weekly_km":35},"metric_visibility":{"weekly_km":"trainer"},"fields":[],"readiness":{"ready":false,"score":75,"missing":[{"key":"injuries","label":"Verletzungen"}]}}]}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"headline":"Ausdauerprofil","profile_quality":{"score":75},"top_skills":[]}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const SportsCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Sportarten'), findsWidgets);
    expect(find.text('Ausdauerprofil'), findsOneWidget);
    expect(find.text('Laufen'), findsOneWidget);
    expect(find.text('75%'), findsWidgets);
  });

  testWidgets('learning screen renders real enrolled courses', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"catalog":[{"id":4,"title":"Sicher im Sportverein","subtitle":"Datenschutz verständlich","level":"beginner","is_free":true,"lessons_count":2,"is_enrolled":true,"progress_percent":50,"tutor":{"name":"Mina Coach"}}],"enrollments":[{"id":2,"status":"active","progress_percent":50,"course":{"id":4,"title":"Sicher im Sportverein","subtitle":"Datenschutz verständlich","level":"beginner","is_free":true,"lessons_count":2,"is_enrolled":true,"progress_percent":50,"tutor":{"name":"Mina Coach"}}}],"certificates":[]}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const LearningScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Kurse'), findsWidgets);
    expect(find.text('Sicher im Sportverein'), findsOneWidget);
    expect(find.text('50%'), findsWidgets);
    expect(transport.paths, contains('/api/v1/learning'));
  });

  testWidgets('learning studio renders real creator course data', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1400));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"courses":[{"id":4,"title":"Trainer-Akademie","status":"draft"}],"selectedCourse":{"id":4,"title":"Trainer-Akademie","subtitle":"Sicheres Coaching","status":"draft","sections":[{"id":2,"title":"Grundlagen","lessons":[{"id":8,"learning_course_section_id":2,"title":"Sicheres Warm-up","type":"video","duration_minutes":12}]}],"enrollments":[{"id":9,"status":"active","progress_percent":60,"user":{"name":"Mina Sport","email":"mina@example.test"}}],"questions":[{"id":11,"lesson_title":"Sicheres Warm-up","body":"Wie oft üben?","status":"open","user":{"name":"Mina Sport"},"replies":[]}],"publish_checklist":{"items":[{"key":"content","label":"Mindestens eine Lektion","done":true}],"score":10},"analytics":{"average_progress":60,"open_questions":2,"sales_count":1,"net_revenue_cents":990}}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const LearningStudioCourseSuiteScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Learning Studio'), findsWidgets);
    expect(find.text('Trainer-Akademie'), findsWidgets);
    expect(find.text('Sicheres Warm-up'), findsOneWidget);
    expect(find.text('60%'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/learning-studio'));

    await tester.tap(find.widgetWithText(ChoiceChip, 'Teilnehmende'));
    await tester.pumpAndSettle();
    expect(find.text('Mina Sport'), findsOneWidget);
    expect(find.text('mina@example.test'), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Fragen'));
    await tester.pumpAndSettle();
    expect(find.text('Wie oft üben?'), findsOneWidget);
  });

  testWidgets('trainer cockpit renders scoped team risk and feedback data', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"summary":{"teams":1,"athletes":2,"upcoming_events":1,"planned_items":1,"feedback_open":1,"overdue_items":1,"readiness_score":68,"risk_athletes":1},"teams":[{"id":4,"name":"U18 Performance","sport_type":"running","club":{"id":3,"name":"Airmius Club"},"stats":{"athletes":2,"events":1,"plans":1},"athletes":[{"id":1,"name":"Mina Coach","role":"Coach"},{"id":2,"name":"Mira Runner","role":"Player"}]}],"feedbackOpen":[{"id":17,"title":"Hard intervals","performed_at":"2026-07-24T10:00:00Z","athlete":{"id":2,"name":"Mira Runner"},"team":{"id":4,"name":"U18 Performance"}}],"overdueItems":[{"id":7,"title":"Recovery run","scheduled_at":"2026-07-23T10:00:00Z","duration_minutes":35,"plan":{"id":8,"title":"Race week","team":{"id":4,"name":"U18 Performance"}}}],"plannedItems":[],"upcomingEvents":[{"id":9,"title":"Team training"}],"plans":[{"id":8,"title":"Race week"}],"coachWeekly":{"readiness_score":68,"risk_level":"watch","current_week":{"session_count":1,"duration_minutes":55,"average_rpe":9},"trend":{"sessions_percent":0},"risk_athletes":[{"id":2,"name":"Mira Runner","team":{"id":4,"name":"U18 Performance"},"pain":3,"rpe":9,"high_load_sessions":1}],"actions":[{"key":"checkRiskAthletes","tone":"danger","count":1},{"key":"answerFeedback","tone":"warning","count":1}]}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TrainerCockpitScreen(),
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('Trainer-Cockpit'), findsWidgets);
    expect(find.text('68%'), findsOneWidget);
    expect(find.text('Mira Runner'), findsOneWidget);
    expect(find.text('RPE 9'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/trainer-cockpit'));

    await tester.tap(find.widgetWithText(ChoiceChip, 'Feedback'));
    await tester.pumpAndSettle();
    expect(find.text('Hard intervals'), findsOneWidget);
    expect(find.text('Feedback geben'), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Teams'));
    await tester.pumpAndSettle();
    expect(find.text('U18 Performance'), findsOneWidget);
    expect(find.text('Airmius Club · running'), findsOneWidget);
  });

  testWidgets('club cockpit renders real management data accessibly', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","members_count":2,"teams_count":1,"can_manage":true}]}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","sport_type":"running","members_count":2,"teams_count":1,"can_manage":true,"management":{"can_manage":true,"summary":{"active_members_count":2,"pending_membership_requests_count":1,"pending_team_join_requests_count":1,"open_invoices_count":2,"open_invoice_amount":120.5,"total_balance":3400,"income_period_total":1200,"expense_period_total":450,"sepa_ready_members_count":1},"members":[{"id":2,"name":"Mira Member","email":"mira@example.test","membership":{"role":"member","status":"active"}}]}}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const ClubCockpitScreen(),
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('Vereins-Cockpit'), findsWidgets);
    expect(find.text('Airmius Club'), findsOneWidget);
    expect(find.text('Offene Mitgliedsanträge'), findsOneWidget);
    expect(find.text('Mitglieder & Beiträge'), findsOneWidget);
    expect(find.text('Teamverwaltung'), findsOneWidget);
    expect(find.text('Mira Member'), findsOneWidget);
    expect(find.text('mira@example.test'), findsOneWidget);
    expect(
      transport.requests.map((request) => request.path),
      containsAllInOrder(['/api/v1/clubs', '/api/v1/clubs/4']),
    );
    expect(transport.requests.first.query, containsPair('mine', '1'));
  });

  testWidgets('club membership finance stays usable at 390px and large text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","members_count":2,"teams_count":1,"can_manage":true}]}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","sport_type":"running","members_count":2,"teams_count":1,"can_manage":true,"management":{"can_manage":true,"settings":{"sepa_creditor_id":"DE98ZZZ","datev_client_number":"42"},"summary":{"active_members_count":2,"linked_people_count":2,"open_invoices_count":1,"open_invoice_amount":24.5,"total_balance":3400,"income_period_total":1200,"expense_period_total":450,"sepa_ready_members_count":1},"members":[{"id":2,"name":"Mira Member","email":"mira@example.test","membership":{"role":"member","status":"active","member_number":"M-2","contribution_amount":"24.50","contribution_interval":"monthly"}}],"invoices":[{"id":12,"user_id":2,"title":"Monatsbeitrag","amount":"24.50","status":"open","user":{"id":2,"name":"Mira Member"}}],"bank_transactions":[{"id":15,"invoice_id":12,"status":"suggested","amount":"24.50","debtor_name":"Mira Member","purpose":"INV-12"}]}}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const ClubMembershipManagementScreen(initialClubId: 4),
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('Mitglieder & Beiträge'), findsWidgets);
    expect(find.text('AKTIVE MITGLIEDER'), findsOneWidget);
    expect(find.text('24,50 EUR'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('club membership management supports large RTL text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","members_count":2,"teams_count":1,"can_manage":true}]}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","sport_type":"running","members_count":2,"teams_count":1,"can_manage":true,"management":{"can_manage":true,"summary":{"active_members_count":2,"linked_people_count":2,"open_invoices_count":1,"open_invoice_amount":24.5,"total_balance":3400,"income_period_total":1200,"expense_period_total":450,"sepa_ready_members_count":1},"members":[{"id":2,"name":"Mira Member","email":"mira@example.test","membership":{"role":"member","status":"active","member_number":"M-2","contribution_amount":"24.50","contribution_interval":"monthly"}}]}}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const ClubMembershipManagementScreen(initialClubId: 4),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('الأعضاء والاشتراكات'), findsWidgets);
    expect(find.text('نظرة عامة'), findsOneWidget);
    expect(find.text('الأعضاء'), findsOneWidget);
    expect(find.text('المالية'), findsOneWidget);
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );
    expect(tester.takeException(), isNull);
  });

  test('named club membership keeps its scoped role', () {
    final item = AirmiusNamedItem.fromJson({
      'id': 4,
      'name': 'Airmius Club',
      'membership': {'role': 'financial_controller'},
    });

    expect(item.membershipRole, 'financial_controller');
  });

  test('club membership client uses complete web-parity contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'club-admin-token',
    );

    await client.updateClubMember(4, 8, {'membership_status': 'active'});
    await client.clubMemberPermissions(4, 8);
    await client.updateClubMemberPermissions(4, 8, {
      'permissions': {'finance.view': true},
    });
    await client.updateClubExternalMember(4, 17, {
      'membership_status': 'active',
      'family_group_key': 'home-1',
    });
    await client.inviteClubExternalMember(4, 17);
    await client.removeClubExternalMember(4, 17, reason: 'duplicate');
    await client.generateClubMemberNumber(4, 8);
    await client.createClubMemberInvoice(4, 8, {
      'title': 'Beitrag',
      'amount': 20,
      'due_date': '2026-08-01',
    });
    await client.updateClubInvoiceStatus(4, 12, 'overdue');
    await client.sendClubInvoiceReminder(4, 12);
    await client.updateClubSepaSettings(4, {'sepa_iban': 'DE00'});
    await client.updateClubDatevSettings(4, {'datev_client_number': '42'});
    await client.confirmClubBankTransaction(4, 15);
    await client.removeClubMember(4, 8, reason: 'requested');
    await client.requestClubMembershipPause(4, {
      'requested_pause_from': '2026-08-01',
    });
    await client.requestClubMembershipTermination(4, {
      'requested_termination_on': '2026-08-01',
    });
    await client.clubMemberCard(4);
    await client.rotateClubMemberCard(4);
    await client.verifyClubMemberCard(4, {'token': 'card-token'});

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/clubs/4/members/8',
        '/api/v1/clubs/4/members/8/permissions',
        '/api/v1/clubs/4/members/8/permissions',
        '/api/v1/clubs/4/external-members/17',
        '/api/v1/clubs/4/external-members/17/invite',
        '/api/v1/clubs/4/external-members/17',
        '/api/v1/clubs/4/members/8/member-number',
        '/api/v1/clubs/4/members/8/invoices',
        '/api/v1/clubs/4/membership-invoices/12/status',
        '/api/v1/clubs/4/membership-invoices/12/reminder',
        '/api/v1/clubs/4/membership/sepa-settings',
        '/api/v1/clubs/4/membership/datev-settings',
        '/api/v1/clubs/4/bank-transactions/15/confirm',
        '/api/v1/clubs/4/members/8',
        '/api/v1/clubs/4/pause-requests',
        '/api/v1/clubs/4/termination-requests',
        '/api/v1/clubs/4/member-card',
        '/api/v1/clubs/4/member-card/rotate',
        '/api/v1/clubs/4/member-card/verify',
      ]),
    );
    expect(transport.requests.first.method, 'PUT');
    expect(transport.requests[4].method, 'POST');
    expect(transport.requests[5].method, 'DELETE');
    expect(transport.requests[13].method, 'DELETE');
  });

  testWidgets('learning room renders protected lesson content', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"course":{"id":4,"title":"Sicher im Sportverein","subtitle":"Datenschutz verständlich"},"enrollment":{"id":2,"status":"active","progress_percent":50},"sections":[{"id":1,"title":"Grundlagen","lessons":[{"id":8,"title":"Einwilligungen","summary":"Sicher dokumentieren","content":"Nur notwendige Daten erfassen.","duration_minutes":10,"locked":false,"completed":false,"notes":[],"comments":[]}]}],"quizzes":[],"assignments":[],"completion_requirements":{"lessons":{"total":1,"completed":0},"quizzes":{"total":0,"completed":0},"assignments":{"total":0,"completed":0}}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const LessonDetailScreen(
        courseId: 4,
        initialTitle: 'Sicher im Sportverein',
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.text('Einwilligungen'));
    await tester.pumpAndSettle();

    expect(find.text('Nur notwendige Daten erfassen.'), findsOneWidget);
    expect(find.text('Als abgeschlossen markieren'), findsOneWidget);
  });

  testWidgets('carpool screen renders real rides without private address', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1400));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"rides":[{"id":5,"driver":{"id":1,"name":"Mina Fahrer"},"visibility":"public","from":"Koeln","to":"Bonn","pickup_name":"Sporthalle","pickup_street":null,"pickup_house_number":null,"pickup_postal_code":"50667","pickup_city":"Koeln","pickup_public_label":"Sporthalle - 50667 Koeln","pickup_private_label":null,"departure_time":"2099-07-25T16:00:00Z","seats":3,"contact_details":null,"users":[],"participants_count":1,"pending_requests":[],"is_driver":false,"is_joined":false,"has_pending_request":false,"can_join":true,"join_block_reason":null,"can_update":false,"can_delete":false}],"clubs":[],"teams":[],"visibilities":["public","friends","club","team"]}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const CarpoolCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Fahrgemeinschaften'), findsWidgets);
    expect(find.text('Koeln → Bonn'), findsOneWidget);
    expect(find.text('Mina Fahrer'), findsOneWidget);
    expect(find.textContaining('1 von 3'), findsOneWidget);
    expect(find.textContaining('Trainingsweg'), findsNothing);
    expect(transport.paths, contains('/api/v1/rides'));
  });

  testWidgets('blog screen renders only API-provided published articles', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1400));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"slug":"sicher-im-verein","title":"Sicher im Vereinsalltag","excerpt":"Praktische Hinweise.","cover_image_url":null,"category":{"name":"Sicherheit","slug":"sicherheit"},"author":{"name":"Mina Redaktion"},"reading_time_minutes":4}],"categories":[{"id":2,"name":"Sicherheit","slug":"sicherheit","posts_count":1}],"meta":{"total":1}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const BlogMediaCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Blog & Wissen'), findsWidgets);
    expect(find.text('Sicher im Vereinsalltag'), findsOneWidget);
    expect(find.text('Mina Redaktion'), findsOneWidget);
    expect(find.textContaining('4 Min.'), findsOneWidget);
  });

  testWidgets('sponsor screen renders active public partner data', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1400));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":3,"name":"Sport Partner","website":"https://partner.example.test","logo_url":null,"scope":"platform","club":null}],"stats":{"total":1,"platform":1,"outfit_subscription":0,"club":0}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const SponsorsCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Sponsoren'), findsWidgets);
    expect(find.text('Sport Partner'), findsOneWidget);
    expect(find.text('Airmius Partner'), findsOneWidget);
    expect(find.text('Website öffnen'), findsOneWidget);
  });

  testWidgets('editorial management renders permissions and quality status', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1400));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"title":"Sicherer Vereinsalltag","excerpt":"Praxiswissen","status":"review","seo_score":72,"revisions_count":2,"content_text":"Inhalt"}],"categories":[],"can":{"create":true,"update":true,"delete":true,"publish":false,"manage_categories":false},"meta":{"total":1}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const EditorialManagementScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Redaktion'), findsWidgets);
    expect(find.text('Sicherer Vereinsalltag'), findsOneWidget);
    expect(find.text('Qualität 72%'), findsOneWidget);
    expect(find.text('2 Revisionen'), findsOneWidget);
    expect(find.text('Neuer Artikel'), findsOneWidget);
  });

  testWidgets('sponsor management renders only manageable partner records', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1400));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":3,"name":"Eigener Vereinspartner","scope":"club","email":"intern@example.test","club_id":2,"club":{"id":2,"name":"Sportclub"}}],"clubs":[{"id":2,"name":"Sportclub"}],"stats":{"total":1,"platform":0,"outfit_subscription":0,"club":1}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const SponsorManagementScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Sponsorverwaltung'), findsWidgets);
    expect(find.text('Eigener Vereinspartner'), findsOneWidget);
    expect(find.text('intern@example.test'), findsOneWidget);
    expect(find.text('Sponsor anlegen'), findsOneWidget);
  });

  testWidgets('sport map screen renders real API empty states', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const SportMapCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(
      find.text('Noch keine sichtbaren Routen vorhanden.'),
      findsOneWidget,
    );
    expect(transport.paths, contains('/api/v1/sport-routes'));
    expect(transport.paths, contains('/api/v1/sport-tracks'));
    expect(transport.paths, contains('/api/v1/sport-places'));
  });

  testWidgets('friends screen renders real API empty states', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"friends":[],"receivedInvitations":[],"sentInvitations":[]}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const FriendsSocialGraphScreen(),
    );
    await tester.pumpAndSettle();

    expect(
      find.text('Du hast noch keine bestätigten Freunde.'),
      findsOneWidget,
    );
    expect(transport.paths, contains('/api/v1/friends'));
  });

  testWidgets('marketplace screen renders real API empty states', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const MarketplaceScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Keine passenden Produkte gefunden.'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/commerce/products'));
    expect(transport.paths, contains('/api/v1/commerce/wishlist'));
    expect(transport.paths, contains('/api/v1/commerce/orders'));
    expect(transport.paths, contains('/api/v1/commerce/cart'));
  });

  testWidgets('marketplace stays readable in a light trail palette', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1600));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const MarketplaceScreen(),
      language: AirmiusLanguage.fr,
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.trail,
      textScaler: const TextScaler.linear(1.3),
    );
    await tester.pumpAndSettle();

    expect(find.text('Marketplace'), findsWidgets);
    expect(find.text('Aucun produit correspondant.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('marketplace screen exposes ergonomic cart and checkout entry', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1400));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"title":"Airmius Shirt","description":"Atmungsaktiv","category":"equipment","price_cents":3900,"currency":"EUR","availability":{"is_available":true},"viewer":{"is_wishlisted":false},"provider_profile":{"display_name":"Airmius Shop","verified":true},"seller_trust":{"seller_verified":true},"review_summary":{"rating_avg":5,"rating_count":2}}]}',
      ),
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"cart":{"id":2,"items_count":1,"items":[{"id":8,"quantity":1,"line_total_cents":3900,"product":{"id":4,"title":"Airmius Shirt","price_cents":3900,"currency":"EUR"}}],"summary":{"item_gross_cents":3900,"shipping_cents":0,"tax_cents":623,"amount_cents":3900,"currency":"EUR"}},"checkout_address":{"country":"DE","postal_code":"10115","city":"Berlin","street":"Sportweg","house_number":"7"},"payment_providers":["stripe","paypal","bank_transfer"]}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const MarketplaceScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Airmius Shirt'), findsOneWidget);
    expect(find.byTooltip('In den Warenkorb'), findsWidgets);
    expect(find.byTooltip('Warenkorb (1)'), findsOneWidget);

    await tester.tap(find.byTooltip('Warenkorb (1)'));
    await tester.pumpAndSettle();

    expect(find.text('Sicher zur Kasse'), findsOneWidget);
    expect(find.text('Enthaltene Steuer'), findsOneWidget);
  });

  testWidgets('seller commerce screen renders real shop data', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"seller_can_sell":true,"seller_application":{"status":"approved"},"products":[{"id":4,"title":"Mobiles Trainingsband","price_cents":2490,"status":"published","moderation_status":"approved","manages_stock":true,"stock_quantity":12}],"orders":[{"id":8,"reference":"AIR-000008","status":"completed","shipping_status":"open","seller_gross_cents":2490,"items":[{"id":3,"product_id":4,"title":"Mobiles Trainingsband","quantity":1,"total_cents":2490}]}],"payout_summary":{"eligible_orders":1,"waiting_orders":0,"amount_cents":2241,"commission_cents":249},"payouts":[],"provider_profile":{"display_name":"Airmius Sportshop","status":"active"},"provider_locations":[],"campaigns":[{"id":12,"name":"Sommerlauf","headline":"Gemeinsam schneller","status":"active","budget_cents":5000,"spent_cents":1200,"impressions":3400,"clicks":125,"payment_completed":true}],"website_requests":[{"id":5,"domain":"airmius-club.example","goals":"Teams und Termine zeigen","status":"in_progress"}],"clubs":[{"id":3,"name":"Airmius Club"}],"ads_min_budget_cents":1000}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const CommerceCenterScreen(),
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.trail,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('Shop-Verwaltung'), findsWidgets);
    expect(find.text('AIR-000008'), findsOneWidget);
    expect(find.text('Shop freigegeben'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/commerce/seller'));

    await tester.tap(find.widgetWithText(ChoiceChip, 'Produkte'));
    await tester.pumpAndSettle();
    expect(find.text('Mobiles Trainingsband'), findsOneWidget);

    final adsChip = find.widgetWithText(ChoiceChip, 'Werbung');
    await tester.ensureVisible(adsChip);
    await tester.tap(adsChip);
    await tester.pumpAndSettle();
    expect(find.text('Sommerlauf'), findsOneWidget);
    expect(find.text('Gemeinsam schneller'), findsOneWidget);
    expect(find.text('125 Klicks'), findsOneWidget);

    final websiteChip = find.widgetWithText(ChoiceChip, 'Website');
    await tester.ensureVisible(websiteChip);
    await tester.tap(websiteChip);
    await tester.pumpAndSettle();
    expect(find.text('airmius-club.example'), findsOneWidget);
    expect(find.text('Teams und Termine zeigen'), findsOneWidget);
  });

  testWidgets('outfit center renders real plan and subscription data', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1400));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"plans":[{"id":4,"name":"Runner Box","description":"Monatliche Lauf-Outfits","effective_monthly_price_cents":2490,"sponsor_discount_cents":500,"items_per_box":3,"sizes":["S","M","L"],"contract_rules":{"minimum_term_months":3,"cancellation_notice_days":14}}],"styleProfile":{"sport_focus":"Laufen","sizes":["M"],"fit_preference":"regular"},"subscriptions":[{"id":8,"status":"active","payment_status":"paid","monthly_price_cents":2490,"plan":{"id":4,"name":"Runner Box"},"deliveries":[]}],"contractRules":{"minimum_term_months":3}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const OutfitSubscriptionCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Outfit-Abos'), findsWidgets);
    expect(find.text('Runner Box'), findsOneWidget);
    expect(find.textContaining('Laufen'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/outfit-subscriptions'));
  });

  testWidgets('outfit checkout stacks address fields for compact Arabic text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"plans":[{"id":4,"name":"Runner Box","description":"Monatliche Lauf-Outfits","effective_monthly_price_cents":2490,"items_per_box":3,"sizes":["S","M","L"],"contract_rules":{"minimum_term_months":3,"cancellation_notice_days":14}}],"subscriptions":[],"styleProfile":{}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const OutfitSubscriptionCenterScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('اختيار الخطة'));
    await tester.pumpAndSettle();

    expect(find.text('الدولة'), findsOneWidget);
    expect(find.text('الرمز البريدي'), findsOneWidget);
    expect(find.text('الشارع'), findsOneWidget);
    expect(find.text('الرقم'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('conversation cards use API member counts in Arabic', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1600));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":12,"title":"فريق الجري","kind":"direct","latest_message":{"message":"نلتقي اليوم"},"updated_at":"2026-07-25T12:00:00Z","members_count":5,"users":[]}],"meta":{"total":1}}',
      ),
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const ConversationsCenterScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('5 أعضاء'), findsOneWidget);
    expect(find.textContaining('2 أعضاء'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('privacy center renders server settings and real rights', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 2200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"user":{"first_name":"Ada","last_name":"Lovelace","email":"ada@example.test","country":"DE"},"profile_address":{"country":"DE","postal_code":"10115","city":"Berlin"},"privacy_settings":{"profile_visibility":"private","direct_message_privacy":"friends","friend_request_privacy":"everyone","ads_personalization_consent":true,"ads_measurement_consent":false,"product_analytics_consent":true},"connected_providers":{"summary":{"total":2,"login":1,"sport":1},"items":[{"kind":"login","provider":"google","status":"connected"},{"kind":"sport","provider":"strava","status":"connected"}]},"data_erasure":{"uses_social_login":true,"account_email":"ada@example.test","category_keys":["profile","content","messages","files","sport_and_health","social_and_integrations","commerce"]}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const PrivacyConsentCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Datenschutz & Einwilligungen'), findsWidgets);
    expect(find.text('Personalisierte Empfehlungen'), findsOneWidget);
    expect(find.text('Anonyme Produktverbesserung'), findsOneWidget);
    expect(find.text('VERBUNDENE ANBIETER'), findsOneWidget);
    expect(find.text('Google'), findsOneWidget);
    expect(find.text('Strava'), findsOneWidget);
    expect(find.text('Datenauskunft exportieren'), findsOneWidget);
    expect(find.text('Personendaten korrigieren'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/privacy'));

    final erasureAction = find.text('Daten löschen, Konto behalten');
    await tester.ensureVisible(erasureAction);
    await tester.tap(erasureAction);
    await tester.pumpAndSettle();

    expect(
      find.text(
        'Du meldest dich mit einem verbundenen Konto an. Bestätige die Anfrage mit deiner Konto-E-Mail-Adresse.',
      ),
      findsOneWidget,
    );
    expect(
      find.text('Ich melde mich mit Google oder Microsoft an'),
      findsNothing,
    );
    expect(find.widgetWithText(TextField, 'E-Mail'), findsOneWidget);
    expect(find.text('ada@example.test'), findsOneWidget);
  });

  testWidgets('two-factor security stays readable in Arabic light mode', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"enabled":true,"pending_confirmation":false,"recovery_codes":[]}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TwoFactorSecurityScreen(),
      language: AirmiusLanguage.ar,
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.trail,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('الأمان بخطوتين'), findsOneWidget);
    expect(find.text('مفعّلة'), findsOneWidget);
    expect(find.text('تعطيل الحماية بخطوتين'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('maturity center renders the account-scoped Arabic overview', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"maturity_score":72,"overview":{"weekly_trainings":3,"friend_connections":4,"completed_routes":2,"approved_posts":5,"xp_total":180},"scores":{"onboarding":80,"social":60,"training":70,"maps":55,"content":75,"safety":100,"xp":40},"next_actions":[{"key":"profile","label":"أكمل ملفك الشخصي","done":false}]}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const MaturityCenterScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('تقدمي الشخصي'), findsWidgets);
    expect(find.text('أكمل ملفك الشخصي'), findsOneWidget);
    expect(find.text('72%'), findsWidgets);
    expect(transport.paths, contains('/api/v1/maturity/overview'));
    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Directionality &&
            widget.textDirection == TextDirection.rtl,
      ),
      findsWidgets,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('media guidelines remain readable and actionable in Arabic', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const MediaGuidelinesScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('الوسائط والخصوصية'), findsWidgets);
    expect(find.text('فتح مدير الملفات'), findsOneWidget);
    expect(find.text('حماية يفرضها الخادم'), findsOneWidget);
    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Directionality &&
            widget.textDirection == TextDirection.rtl,
      ),
      findsWidgets,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('global search uses the active palette and Arabic RTL layout', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1700));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":100001,"type":"module","module_key":"training","title":"التدريب والتوثيق","subtitle":"فتح هذا القسم مباشرة."},{"id":4,"type":"club","title":"نادي الجري","subtitle":"برلين","logo_url":"/storage/club.webp"},{"id":5,"type":"event","title":"تدريب المساء","subtitle":"اليوم"},{"id":6,"type":"course","title":"دورة الجري","subtitle":"تعلم"},{"id":7,"type":"product","title":"حذاء الجري","subtitle":"متجر"},{"id":8,"type":"file","title":"خطة.pdf","subtitle":"ملف"}],"meta":{"current_page":1,"last_page":1,"total":6}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const GlobalSearchScreen(),
      language: AirmiusLanguage.ar,
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.champion,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(transport.paths, isNot(contains('/api/v1/search')));
    await tester.enterText(find.byType(TextField).first, 'جري');
    await tester.pump(const Duration(milliseconds: 319));
    expect(transport.paths, isNot(contains('/api/v1/search')));
    await tester.pump(const Duration(milliseconds: 1));
    await tester.pumpAndSettle();

    expect(find.text('نادي الجري'), findsOneWidget);
    expect(find.text('البحث العام'), findsWidgets);
    expect(find.text('فعالية'), findsWidgets);
    expect(find.text('دورة'), findsWidgets);
    expect(find.text('منتج'), findsWidgets);
    expect(find.text('ملف'), findsWidgets);
    expect(find.text('وظيفة'), findsWidgets);
    expect(transport.paths, contains('/api/v1/search'));
    final fab = tester.widget<FloatingActionButton>(
      find.byType(FloatingActionButton),
    );
    expect(
      fab.backgroundColor,
      AirmiusTheme.light(AirmiusThemePalette.champion).colorScheme.primary,
    );
    expect(
      Directionality.of(tester.element(find.text('نادي الجري'))),
      TextDirection.rtl,
    );
    expect(tester.takeException(), isNull);

    await tester.ensureVisible(find.text('التدريب والتوثيق'));
    await tester.tap(find.text('التدريب والتوثيق'));
    await tester.pumpAndSettle();
    expect(find.byType(TrainingPlansLogsScreen), findsOneWidget);
  });

  testWidgets(
    'directory search stays localized and palette-aware with a debounced query',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 1700));
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":[{"id":4,"type":"club","title":"نادي الجري","subtitle":"برلين","logo_url":"/storage/club.webp"}],"meta":{"current_page":1,"last_page":1,"total":1}}',
        ),
      );

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        const GlobalSearchDirectorySuiteScreen(),
        language: AirmiusLanguage.ar,
        themeMode: ThemeMode.light,
        palette: AirmiusThemePalette.trail,
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('البحث العام'), findsWidgets);
      expect(find.text('اكتشاف'), findsOneWidget);
      expect(find.text('ابحث عن أشخاص أو فرق أو أندية'), findsOneWidget);

      await tester.enterText(find.byType(TextField), 'نادي');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();

      expect(transport.paths, contains('/api/v1/search'));
      expect(find.text('نادي الجري'), findsOneWidget);
      expect(find.text('عرض النادي'), findsOneWidget);
      expect(
        Directionality.of(tester.element(find.text('نادي الجري'))),
        TextDirection.rtl,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('support center renders a real accessible contact form', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const SupportHelpdeskScreen(),
      textScaler: const TextScaler.linear(1.35),
    );

    expect(find.text('Support & Hilfe'), findsWidgets);
    expect(find.text('Support-Anfrage'), findsOneWidget);
    expect(find.text('Betreff'), findsOneWidget);
    expect(find.text('Nachricht'), findsOneWidget);
    expect(find.text('Anfrage senden'), findsOneWidget);
    expect(find.text('Datenrechte öffnen'), findsOneWidget);
  });

  testWidgets('settings expose only real account surfaces accessibly', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const SettingsCenterScreen(),
      textScaler: const TextScaler.linear(1.35),
    );

    expect(find.text('Einstellungen'), findsWidgets);
    expect(find.text('Profil & Kontosicherheit'), findsOneWidget);
    expect(find.text('Datenschutz & Datenrechte'), findsOneWidget);
    expect(find.text('Support & Hilfe'), findsOneWidget);
    expect(find.text('Recht & öffentliche Informationen'), findsOneWidget);
    await tester.tap(find.text('Sprache'));
    await tester.pumpAndSettle();
    expect(find.text('Deutsch'), findsOneWidget);
    expect(find.text('English'), findsOneWidget);
    expect(find.text('Web Parity'), findsNothing);
    expect(find.text('Store Device QA'), findsNothing);
    expect(find.text('System Admin'), findsNothing);
  });

  testWidgets('notification preferences persist local channels accessibly', (
    WidgetTester tester,
  ) async {
    final store = _MemoryPreferencesStore();
    final container = _widgetTestContainer(pushDeviceStore: store);

    await _pumpAirmiusWidget(
      tester,
      container,
      const NotificationPreferencesScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Benachrichtigungen'), findsWidgets);
    final switches = find.byType(SwitchListTile);
    expect(switches, findsNWidgets(6));
    await tester.tap(switches.at(2));
    await tester.pump();
    await tester.tap(find.byTooltip('Einstellungen speichern'));
    await tester.pump();

    final stored = await store.readString('airmius.notifications.channels.v1');
    expect(stored, contains('"chat":false'));
    expect(
      find.text('Benachrichtigungseinstellungen gespeichert.'),
      findsOneWidget,
    );
  });

  testWidgets('legal hub exposes official public documents accessibly', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 2200));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const LegalStatusCenterScreen(),
      textScaler: const TextScaler.linear(1.35),
    );

    expect(find.text('Recht & Informationen'), findsWidgets);
    expect(find.text('Impressum'), findsOneWidget);
    expect(find.text('Datenschutzerklärung'), findsOneWidget);
    expect(find.text('Nutzungsbedingungen'), findsOneWidget);
    expect(find.text('Community-Richtlinien'), findsOneWidget);
    expect(find.text('Preise & Abos'), findsOneWidget);
    expect(find.text('Jobs bei Airmius'), findsOneWidget);
  });

  test('legal public links stay on the configured HTTP origin', () {
    expect(
      legalPublicUri('https://airmius.com/api/v1', '/agb').toString(),
      'https://airmius.com/agb',
    );
    expect(legalPublicUri('javascript:alert(1)', '/agb'), isNull);
    expect(legalPublicUri('https://user:pass@airmius.com', '/agb'), isNull);
    expect(legalPublicUri('https://airmius.com', '//evil.example'), isNull);
  });

  testWidgets('updates load pending club requests from the API', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":41,"name":"Echter API Verein","city":"Berlin","has_pending_membership_request":true}]}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const Scaffold(body: UpdatesCenterScreen()),
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('Echter API Verein'), findsWidgets);
    expect(find.text('Airmius Running Club'), findsNothing);
    expect(transport.paths, contains('/api/v1/clubs'));
    expect(transport.paths, contains('/api/v1/chat/conversations'));
  });

  testWidgets('admin commerce renders protected platform data accessibly', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"summary":{"products":2,"products_in_review":1,"orders":3,"orders_awaiting_transfer":1,"seller_applications":1,"website_requests":1,"campaigns":1,"payouts_prepared":0,"commission_cents":490,"subscription_revenue_cents":12000},"products":{"data":[{"id":4,"title":"Review Trikot","price_cents":3990,"category":"apparel","status":"review"}]},"orders":{"data":[{"id":8,"reference":"AIR-000008","amount_cents":3990,"status":"awaiting_transfer","shipping_status":"open"}]}}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"seller_applications":[{"id":5,"business_name":"Sportshop Berlin","status":"pending"}],"website_requests":[{"id":6,"domain":"verein.example","status":"new"}],"campaigns":[{"id":7,"name":"Sommerlauf","status":"pending_review"}]}}',
      ),
    ]);

    final container = await _authenticatedWidgetTestContainer(
      const AirmiusUser(
        id: 1,
        name: 'Commerce Admin',
        email: 'commerce-admin@example.test',
        role: 'admin',
        roles: ['admin'],
        permissions: ['marketplace.manage', 'commerce.orders.manage'],
        twoFactorEnabled: true,
      ),
      transport: transport,
    );

    await _pumpAirmiusWidget(
      tester,
      container,
      const AdminCenterScreen(),
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('Admin-Commerce'), findsWidgets);
    expect(find.text('1'), findsWidgets);
    expect(
      transport.requests.map((request) => request.path),
      containsAllInOrder([
        '/api/v1/admin/commerce',
        '/api/v1/admin/commerce/catalog',
      ]),
    );

    await tester.tap(find.widgetWithText(ChoiceChip, 'Produkte'));
    await tester.pumpAndSettle();
    expect(find.text('Review Trikot'), findsOneWidget);
    expect(find.text('Freigeben'), findsOneWidget);

    final reviewsChip = find.widgetWithText(ChoiceChip, 'Prüfungen');
    await tester.ensureVisible(reviewsChip);
    await tester.tap(reviewsChip);
    await tester.pumpAndSettle();
    expect(find.text('Sportshop Berlin'), findsOneWidget);
    expect(find.text('verein.example'), findsOneWidget);
    expect(find.text('Sommerlauf'), findsOneWidget);
  });

  testWidgets(
    'full commerce operations stay usable on a small large-text viewport',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 2600));
      final transport = _SequencedTransport([
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"summary":{"products":1,"orders":1},"products":{"data":[{"id":4,"title":"Ergonomischer Ball","price_cents":2499,"currency":"EUR","category":"equipment","product_type":"single","is_shippable":true,"manages_stock":true,"stock_quantity":3,"low_stock_threshold":2,"status":"draft","commission_percent":8,"return_policy":{"type":"standard","window_days":14}}]},"orders":{"data":[{"id":8,"reference":"AIR-8","amount_cents":2499,"refunded_cents":0,"status":"completed","shipping_status":"open","issue_status":"reported","issue_note":"Paket fehlt"}]},"return_requests":[{"id":11,"commerce_order_id":8,"status":"requested","reason":"Falsche Größe","requested_amount_cents":2499}],"payout_candidates":[{"user_id":12,"name":"Sicherer Shop","orders_count":2,"amount_cents":4500}],"payouts":[],"payout_profiles":[]}}',
        ),
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"coupons":[{"id":1,"code":"APP10","name":"App Rabatt","type":"percent","percent_off":10,"is_active":true}],"addons":[],"tax_rates":[],"shipping_rates":[],"campaigns":[],"commerce_settings":{"company_country":"DE","company_currency":"EUR","marketplace_default_commission_percent":10},"marketplace_visuals":[],"marketplace_commissions":[]}}',
        ),
      ]);

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        const AdminCommerceOperationsScreen(),
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('Commerce-Verwaltung'), findsWidgets);
      expect(find.text('Ergonomischer Ball'), findsOneWidget);
      expect(find.text('Bestand anpassen'), findsOneWidget);
      expect(tester.takeException(), isNull);

      final fulfillment = find.widgetWithText(ChoiceChip, 'Versand & Retouren');
      await tester.ensureVisible(fulfillment);
      await tester.tap(fulfillment);
      await tester.pumpAndSettle();

      expect(find.text('AIR-8'), findsOneWidget);
      expect(find.textContaining('Falsche Größe'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('full commerce operations support Arabic RTL', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 2200));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"summary":{"products":0,"orders":0},"products":{"data":[]},"orders":{"data":[]},"return_requests":[],"payout_candidates":[],"payouts":[],"payout_profiles":[]}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"coupons":[],"addons":[],"tax_rates":[],"shipping_rates":[],"campaigns":[],"commerce_settings":{},"marketplace_visuals":[],"marketplace_commissions":[]}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const AdminCommerceOperationsScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('إدارة التجارة'), findsWidgets);
    expect(find.text('الكتالوج'), findsOneWidget);
    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Directionality &&
            widget.textDirection == TextDirection.rtl,
      ),
      findsWidgets,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('platform admin renders real data responsively', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 2200));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"summary":{"users":2,"users_suspended":1,"clubs_pending":1,"sports":1,"sports_active":1,"badges":1,"roles":1,"moderation_open":2,"gamification_active":1},"abilities":{"users_view":true,"users_edit":true,"roles_assign":true,"permissions_create":true,"system_manage":true},"users":[{"id":9,"name":"Mina Admin Target","email":"target@example.test","account_status":"active","email_verified":true,"two_factor_enabled":false,"roles":["member"],"can_change_status":true}],"clubs":[{"id":4,"name":"Pending Club","city":"Berlin","verification_status":"pending","requested_official_club_number":"VR-4","owner":{"name":"Owner"}}],"sports":[{"id":3,"name":"Laufen","slug":"laufen","category":"Ausdauer","is_active":true,"usage_count":2,"skills_count":4}],"badges":[{"id":5,"key":"starter","name":"Starter","actor_type":"sportler","trigger":"xp","threshold":10,"users_count":0}],"roles":[{"id":6,"name":"mobile_moderator","description":"Mobile Moderation","users_count":0,"is_system":false,"can_edit":true,"can_delete":true,"permissions":[{"id":7,"name":"reports.review","description":"Meldungen prüfen"}]}],"permission_groups":[{"group":"reports","items":[{"id":7,"name":"reports.review","description":"Meldungen prüfen"}]}],"moderation":{"flags":[{"id":11,"source":"automatic","severity":"medium","status":"open","categories":["spam"],"content":{"text":"Automatisch erkannter Inhalt","author":"Mina"}}],"reports":[{"id":12,"reason":"harassment","details":"Bitte prüfen","status":"open","appeal_status":"pending","reporter":{"name":"Alex"},"content":{"text":"Gemeldeter Inhalt","author":"Chris"}}],"warnings":[]},"gamification_rules":[{"id":13,"key":"helpful_post","actor_type":"sportler","category":"community","label":"Hilfreicher Beitrag","description":"Belohnt Qualität","xp_amount":8,"daily_limit":2,"trust_delta":1,"is_penalty":false,"is_active":true}]}}',
      ),
    );

    final container = await _authenticatedWidgetTestContainer(
      const AirmiusUser(
        id: 1,
        name: 'Platform Admin',
        email: 'platform-admin@example.test',
        role: 'admin',
        roles: ['admin'],
        permissions: ['system.manage', 'users.view', 'users.assign_roles'],
        twoFactorEnabled: true,
      ),
      transport: transport,
    );

    await _pumpAirmiusWidget(
      tester,
      container,
      const PlatformAdminScreen(),
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('Plattformverwaltung'), findsWidgets);
    expect(find.text('Mina Admin Target'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/admin/platform'));
    expect(tester.takeException(), isNull);

    await tester.tap(find.widgetWithText(ChoiceChip, 'Vereinsprüfung'));
    await tester.pumpAndSettle();
    expect(find.text('Pending Club'), findsOneWidget);

    final sportsChip = find.widgetWithText(ChoiceChip, 'Sportarten');
    await tester.ensureVisible(sportsChip);
    await tester.tap(sportsChip);
    await tester.pumpAndSettle();
    expect(find.text('Laufen'), findsOneWidget);

    final badgesChip = find.widgetWithText(ChoiceChip, 'Badges');
    await tester.ensureVisible(badgesChip);
    await tester.tap(badgesChip);
    await tester.pumpAndSettle();
    expect(find.text('Starter'), findsOneWidget);

    final rolesChip = find.widgetWithText(ChoiceChip, 'Rollen & Rechte');
    await tester.ensureVisible(rolesChip);
    await tester.tap(rolesChip);
    await tester.pumpAndSettle();
    expect(find.text('mobile_moderator'), findsOneWidget);

    final moderationChip = find.widgetWithText(ChoiceChip, 'Moderation');
    await tester.ensureVisible(moderationChip);
    await tester.tap(moderationChip);
    await tester.pumpAndSettle();
    expect(find.text('Automatisch erkannter Inhalt'), findsOneWidget);
    expect(find.text('Gemeldeter Inhalt'), findsOneWidget);

    final gamificationChip = find.widgetWithText(ChoiceChip, 'Gamification');
    await tester.ensureVisible(gamificationChip);
    await tester.tap(gamificationChip);
    await tester.pumpAndSettle();
    expect(find.text('Hilfreicher Beitrag'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'admin backoffice renders real finance data on a small large-text viewport',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 2400));
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"summary":{"plans":1,"active_user_subscriptions":1,"active_club_subscriptions":1,"pending_transfers":1,"open_subscription_invoices":1,"subscription_revenue_cents":1299,"payments":1,"payment_revenue_cents":2500,"open_invoices":1,"contracts":1,"monthly_contract_cost_cents":1000},"abilities":{"subscriptions_manage":true,"billing_manage":true,"finance_view":true,"finance_edit":true},"plans":[{"id":2,"name":"Airmius Pro","description":"Mehr Funktionen für den Sport.","target_actor":"sportler","monthly_price_cents":1299,"yearly_price_cents":12990,"storage_gb":5,"minimum_term_months":0,"cancellation_notice_days":0,"is_public":true,"is_active":true,"user_subscriptions_count":1,"club_subscriptions_count":1,"country_prices":[]}],"user_subscriptions":[{"id":5,"status":"active","payment_provider":"manual","billing_interval":"monthly","current_period_ends_at":"2026-08-24T00:00:00Z","user":{"id":3,"name":"Mina Mitglied","email":"mina@example.test"},"plan":{"id":2,"name":"Airmius Pro"}}],"club_subscriptions":[{"id":6,"status":"active","payment_provider":"manual","billing_interval":"monthly","current_period_ends_at":"2026-08-24T00:00:00Z","club":{"id":4,"name":"Sportverein Mitte"},"plan":{"id":2,"name":"Airmius Pro"}}],"pending_transfers":[{"id":7,"payment_reference":"AIR-TRANSFER-7","amount_cents":1299,"currency":"EUR","status":"awaiting_transfer","due_at":"2026-08-01T00:00:00Z","user":{"id":3,"name":"Mina Mitglied"},"plan":{"id":2,"name":"Airmius Pro"}}],"subscription_invoices":[{"id":8,"number":"SUB-2026-8","title":"Airmius Pro","amount_cents":1299,"currency":"EUR","status":"open","due_at":"2026-08-01T00:00:00Z","user":{"id":3,"name":"Mina Mitglied"}}],"invoices":[{"id":10,"number":"MAN-2026-10","title":"Individuelle Beratung","amount":25.0,"paid_amount":0,"status":"open","due_date":"2026-08-02T00:00:00Z","user":{"id":3,"name":"Mina Mitglied"},"can_delete":true}],"payments":[{"id":9,"amount":25.0,"status":"paid","method":"bank_transfer","reference":"PAY-9","paid_at":"2026-07-24T12:00:00Z","user":{"id":3,"name":"Mina Mitglied"}}],"contracts":[{"id":11,"name":"Hosting Vertrag","vendor":"Hoster AG","category":"hosting","status":"active","amount":120.0,"currency":"EUR","billing_interval":"yearly","payment_method":"invoice","monthly_equivalent":10.0,"next_due_on":"2026-08-24","auto_renews":true}],"users":[{"id":3,"name":"Mina Mitglied","email":"mina@example.test"}],"clubs":[{"id":4,"name":"Sportverein Mitte"}],"options":{"invoice_sources":["custom"],"contract_statuses":["active","paused","cancelled","ended"],"contract_categories":["hosting","other"],"billing_intervals":["monthly","yearly"],"payment_methods":["invoice","bank_transfer"]}}}',
        ),
      );

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        const AdminBackofficeScreen(),
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('Abos, Finanzen & Verträge'), findsWidgets);
      expect(find.text('Aktive Abos'), findsOneWidget);
      expect(transport.paths, contains('/api/v1/admin/backoffice'));
      expect(tester.takeException(), isNull);

      final subscriptionChip = find.widgetWithText(ChoiceChip, 'Abos');
      await tester.ensureVisible(subscriptionChip);
      await tester.tap(subscriptionChip);
      await tester.pumpAndSettle();
      expect(find.text('Airmius Pro'), findsWidgets);
      expect(find.textContaining('AIR-TRANSFER-7'), findsOneWidget);
      final confirmTransfer = find.widgetWithText(
        FilledButton,
        'Zahlung bestätigen',
      );
      await tester.ensureVisible(confirmTransfer);
      await tester.tap(confirmTransfer);
      await tester.pumpAndSettle();
      expect(find.text('Zahlung wirklich bestätigen?'), findsOneWidget);
      final confirmDialogButton = find.descendant(
        of: find.byType(AlertDialog),
        matching: find.widgetWithText(FilledButton, 'Zahlung bestätigen'),
      );
      await tester.tap(confirmDialogButton);
      await tester.pumpAndSettle();
      expect(
        transport.paths,
        contains('/api/v1/admin/backoffice/transfers/7/mark-paid'),
      );

      final billingChip = find.widgetWithText(
        ChoiceChip,
        'Rechnungen & Zahlungen',
      );
      await tester.ensureVisible(billingChip);
      await tester.tap(billingChip);
      await tester.pumpAndSettle();
      expect(find.text('SUB-2026-8'), findsOneWidget);
      expect(find.text('MAN-2026-10'), findsOneWidget);

      final contractsChip = find.widgetWithText(ChoiceChip, 'Verträge');
      await tester.ensureVisible(contractsChip);
      await tester.tap(contractsChip);
      await tester.pumpAndSettle();
      expect(find.text('Hosting Vertrag'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('admin backoffice supports Arabic RTL and large text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1700));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"summary":{"active_user_subscriptions":0,"active_club_subscriptions":0,"pending_transfers":0,"open_subscription_invoices":0,"subscription_revenue_cents":0,"payment_revenue_cents":0,"open_invoices":0,"monthly_contract_cost_cents":1000},"abilities":{"subscriptions_manage":false,"billing_manage":false,"finance_view":true,"finance_edit":false},"plans":[],"user_subscriptions":[],"club_subscriptions":[],"pending_transfers":[],"subscription_invoices":[],"invoices":[],"payments":[],"contracts":[{"id":11,"name":"عقد الاستضافة","vendor":"مزود آمن","category":"hosting","status":"active","amount":120.0,"currency":"EUR","billing_interval":"yearly","monthly_equivalent":10.0,"auto_renews":true}],"users":[],"clubs":[],"options":{"contract_statuses":["active"],"contract_categories":["hosting"],"billing_intervals":["yearly"],"payment_methods":["invoice"]}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const AdminBackofficeScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    final title = find.text('الاشتراكات والمالية والعقود').first;
    expect(title, findsOneWidget);
    expect(Directionality.of(tester.element(title)), TextDirection.rtl);
    final contractsChip = find.widgetWithText(ChoiceChip, 'العقود');
    await tester.ensureVisible(contractsChip);
    await tester.tap(contractsChip);
    await tester.pumpAndSettle();
    expect(find.text('عقد الاستضافة'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'admin outfit renders real data and confirms payment on a small large-text viewport',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 2300));
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"summary":{"plans":1,"active_plans":1,"subscriptions":1,"active_subscriptions":0,"pending_payments":1,"deliveries":1,"open_delivery_issues":1},"abilities":{"manage":true},"plans":[{"id":2,"name":"Team Box","description":"Drei Artikel für das Team.","monthly_price_cents":2990,"sponsor_discount_cents":500,"effective_monthly_price_cents":2490,"currency":"EUR","items_per_box":3,"branding_type":"club","target_gender":"all","sizes":["M","L"],"sports":["football"],"contract_terms":[],"is_public":true,"is_active":true}],"subscriptions":[{"id":3,"status":"pending_payment","payment_status":"pending","payment_provider":"bank_transfer","payment_reference":"OUTFIT-3","payment_due_at":"2026-08-01","payment_reminders_sent":0,"can_send_payment_reminder":true,"monthly_price_cents":2990,"sponsor_discount_cents":500,"total_cents":2490,"currency":"EUR","deliveries_count":1,"user":{"id":7,"name":"Mina Mitglied","email":"mina@example.test"},"plan":{"id":2,"name":"Team Box"},"shipping_address":{"name":"Mina Mitglied","country":"DE","street":"Sportweg","house_number":"7","postal_code":"10115","city":"Berlin"}}],"deliveries":[{"id":4,"status":"preparing","delivery_month":"2026-08-01","tracking_number":null,"items":["Trikot"],"issue":{"status":"open","description":"Falsche Größe"},"subscription":{"id":3,"user":{"id":7,"name":"Mina Mitglied","email":"mina@example.test"},"plan":{"id":2,"name":"Team Box"}}}],"visuals":{"hero":{"source":"/images/outfit.webp","recommended_size":"1920 x 1080 px","formats":"WebP, JPG, PNG"}}}}',
        ),
      );

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        const OutfitOperationsScreen(),
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('Outfit-Verwaltung'), findsWidgets);
      expect(find.text('Team Box'), findsOneWidget);
      expect(find.textContaining('Mina Mitglied'), findsOneWidget);
      expect(tester.takeException(), isNull);

      final paidButton = find.widgetWithText(
        OutlinedButton,
        'Bezahlt markieren',
      );
      await tester.ensureVisible(paidButton);
      await tester.tap(paidButton);
      await tester.pumpAndSettle();
      expect(find.text('Bezahlt markieren'), findsWidgets);
      await tester.tap(
        find.descendant(
          of: find.byType(AlertDialog),
          matching: find.widgetWithText(FilledButton, 'Bestätigen'),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        transport.paths,
        contains('/api/v1/admin/outfits/subscriptions/3/mark-paid'),
      );

      final deliveriesChip = find.widgetWithText(ChoiceChip, 'Lieferungen');
      await tester.ensureVisible(deliveriesChip);
      await tester.tap(deliveriesChip);
      await tester.pumpAndSettle();
      expect(find.text('In Vorbereitung'), findsOneWidget);
      expect(find.text('Problem'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('admin outfit supports Arabic RTL and large text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"summary":{"active_subscriptions":0,"pending_payments":0,"deliveries":0,"open_delivery_issues":0},"plans":[],"subscriptions":[],"deliveries":[],"visuals":{"hero":{"source":"/images/outfit.webp"}}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const OutfitOperationsScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    final title = find.text('إدارة الملابس').first;
    expect(title, findsOneWidget);
    expect(Directionality.of(tester.element(title)), TextDirection.rtl);
    expect(find.text('لا توجد اشتراكات ملابس.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'admin mail center renders real failures and resolves them on a small large-text viewport',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 1900));
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"summary":{"total":1,"sent":0,"failed":1,"skipped":0,"resolved":0,"last_24h":1},"queue":{"pending_jobs":2,"failed_jobs":1,"recent_failed_jobs":[]},"deliveries":[{"id":7,"mail_type":"invoice.created","status":"failed","recipient":{"name":"Mina Mitglied","email":"mina@example.test"},"primary_category":"billing","error_message":"SMTP unavailable","resendable":true,"created_at":"2026-07-24T12:00:00Z"}],"senders":[{"category":"system","mailer":"smtp","address":"system@example.test","name":"Airmius","host":"smtp.example.test","port":587,"ready":true,"active":true}],"preferences":{"invoice_primary_category":"billing","invoice_fallback_category":"support","disabled_categories":[]},"categories":["system","billing","support"],"abilities":{"manage":true,"manage_secrets":false}}}',
        ),
      );

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        const AdminMailCenterScreen(),
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('Mail-Center'), findsWidgets);
      expect(find.text('invoice.created'), findsOneWidget);
      expect(find.textContaining('SMTP unavailable'), findsOneWidget);
      expect(tester.takeException(), isNull);

      final resolve = find.widgetWithText(
        OutlinedButton,
        'Als erledigt markieren',
      );
      await tester.ensureVisible(resolve);
      await tester.tap(resolve);
      await tester.pumpAndSettle();
      await tester.tap(
        find.descendant(
          of: find.byType(AlertDialog),
          matching: find.widgetWithText(FilledButton, 'Bestätigen'),
        ),
      );
      await tester.pumpAndSettle();
      expect(
        transport.paths,
        contains('/api/v1/admin/mail/deliveries/7/resolve'),
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'admin system renders real settings and provider costs on a small large-text viewport',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 2400));
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"settings":{"maintenance":{"enabled":false,"title":"Airmius Wartung","message":"Wir sind gleich wieder da."},"billing":{"brand_name":"Airmius","company_name":"Airmius GmbH","legal_name":"Airmius GmbH","company_street":"Sportweg 1","company_postal_code":"10115","company_city":"Berlin","company_country":"Deutschland","company_email":"rechnung@example.test","company_website":"airmius.test","tax_number":"TAX-1","vat_id":"DE123","court":"Berlin","registration_number":"HRB 1","managing_director":"Ari Admin","small_business_notice":"","invoice_note":"Danke","bank_account_holder":"Airmius GmbH","bank_name":"Testbank","iban":"DE89370400440532013000","bic":"COBADEFFXXX","payment_terms_days":14},"email_templates":[{"key":"account_welcome","label":"Konto: Willkommen","description":"Nach Registrierung","variables":["name"],"template":{"subject":"Willkommen","greeting":"Hallo","body":"Willkommen bei Airmius","action_label":"Öffnen"}}],"ai_token_status":{"providers":[{"key":"safe_ai","label":"Safe AI","model":"safe-model","has_api_key":true,"status_label":"Bereit","severity":"success","message":"API-Key ist gesetzt."}],"alerts":[]}},"provider_costs":{"period":{"month":"2026-07","label":"Juli 2026"},"totals":{"events":25,"users":3,"estimated_cost_eur":4.5},"cards":[{"label":"Routenplanung","value":25,"unit":"Requests","status":"ok"}],"usageRows":[{"area":"routing","provider":"graphhopper","service":"directions","operation":"route","requests":25,"users":3}],"comparisons":[{"label":"Routenplanung","area":"routing","units":25,"unit_label":"Requests","cheapest_plan":{"label":"GraphHopper Basic","estimated_cost_eur":4.5}}],"recommendations":[{"level":"ok","title":"Noch entspannt","body":"Die Nutzung liegt unter den Warnschwellen."}]}}}',
        ),
      );

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        const AdminPlatformSettingsScreen(),
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('System & Provider'), findsWidgets);
      expect(find.text('Airmius Wartung'), findsOneWidget);
      expect(find.text('Safe AI'), findsOneWidget);
      expect(tester.takeException(), isNull);

      final providers = find.widgetWithText(ChoiceChip, 'Providerkosten');
      await tester.ensureVisible(providers);
      await tester.tap(providers);
      await tester.pumpAndSettle();
      expect(find.text('Routenplanung'), findsWidgets);
      expect(find.textContaining('GraphHopper Basic'), findsOneWidget);
      expect(find.textContaining('graphhopper'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('subscription center renders real API empty states', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1400));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const SubscriptionCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Abos & Rechnungen'), findsWidgets);
    expect(find.text('Du hast noch kein Abo.'), findsOneWidget);
    expect(transport.paths, contains('/api/v1/subscription-plans'));
    expect(transport.paths, contains('/api/v1/subscriptions'));
    expect(transport.paths, contains('/api/v1/clubs'));
  });

  test('me uses only api v1 profile endpoint when server rejects it', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 422,
        body: '{"message":"Invalid request"}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    await expectLater(client.me(), throwsA(isA<AirmiusApiException>()));

    expect(transport.paths, ['/api/v1/me']);
  });

  test('auth repository exposes api validation error details', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 422,
        body:
            '{"message":"Validation failed","errors":{"email":["E-Mail ist ungültig."]},"code":"VALIDATION_ERROR"}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await expectLater(
      AirmiusApiAuthRepository(client).currentUser(),
      throwsA(
        isA<AirmiusApiException>()
            .having((error) => error.statusCode, 'statusCode', 422)
            .having((error) => error.path, 'path', '/api/v1/me')
            .having(
              (error) => error.userMessage,
              'userMessage',
              'E-Mail ist ungültig.',
            ),
      ),
    );

    expect(transport.requests.single.method, 'GET');
    expect(
      transport.requests.single.headers['Authorization'],
      'Bearer auth-token',
    );
  });

  test(
    'api errors hide framework and database diagnostics from user messages',
    () {
      const exception = AirmiusApiException(
        statusCode: 500,
        body: '{"message":"SQLSTATE[HY000]: select * from users"}',
        path: '/api/v1/me',
      );

      expect(exception.userMessage, 'Serverfehler (500).');
    },
  );

  test(
    'logout uses only api v1 logout endpoint when server rejects it',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 405,
          body: '{"message":"Method not allowed"}',
        ),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
      );

      await expectLater(client.logout(), throwsA(isA<AirmiusApiException>()));

      expect(transport.paths, ['/api/v1/auth/logout']);
    },
  );

  test('updateClub writes to api v1 club endpoint', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"ZBB","city":"Kleinblittersdorf","country":"DE"}}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    await client.updateClub(7, {
      'name': 'ZBB',
      'country': 'DE',
      'city': 'Kleinblittersdorf',
    });

    expect(transport.requests.single.method, 'PUT');
    expect(transport.requests.single.path, '/api/v1/clubs/7');
    expect(transport.requests.single.body, containsPair('country', 'DE'));
  });

  test('api meta reads visible version and feature flag endpoint', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"api_version":"v1","minimum_app_version":"1.0.0","feature_flags":{"mvp_surface":true}}}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    final json = await client.apiMeta();

    expect(transport.requests.single.method, 'GET');
    expect(transport.requests.single.path, '/api/v1/meta');
    expect(json['data'], isA<Map<String, dynamic>>());
  });

  test('account security client uses the protected api v1 contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.updatePassword(
      currentPassword: 'old-password',
      password: 'new-password',
      passwordConfirmation: 'new-password',
    );
    await client.accountSessions();
    await client.endAccountSession(42);
    await client.endOtherAccountSessions();
    await client.deleteProfilePhoto();
    await client.requestAccountDeletionCode(password: 'old-password');
    await client.deleteAccount(code: '123456');

    expect(
      transport.requests
          .map((request) => '${request.method} ${request.path}')
          .toList(),
      [
        'PUT /api/v1/me/password',
        'GET /api/v1/me/sessions',
        'DELETE /api/v1/me/sessions/42',
        'DELETE /api/v1/me/sessions/others',
        'DELETE /api/v1/me/profile-photo',
        'POST /api/v1/account/deletion-code',
        'DELETE /api/v1/account',
      ],
    );
    expect(
      transport.requests.first.body,
      containsPair('password_confirmation', 'new-password'),
    );
    expect(transport.requests.last.body, containsPair('code', '123456'));
    expect(
      transport.requests.every(
        (request) => request.headers['Authorization'] == 'Bearer auth-token',
      ),
      isTrue,
    );
  });

  test('MVP surface hides developer suite modules by default', () {
    final events = appModules.firstWhere(
      (module) => module.title == 'Events & Training',
    );
    final nutrition = appModules.firstWhere(
      (module) => module.title == 'Ernährung',
    );
    final sportMap = appModules.firstWhere(
      (module) => module.title == 'Sportkarte',
    );
    final friends = appModules.firstWhere(
      (module) => module.title == 'Freunde',
    );
    final marketplace = appModules.firstWhere(
      (module) => module.title == 'Marketplace',
    );
    final badges = appModules.firstWhere((module) => module.title == 'Badges');
    final sports = appModules.firstWhere(
      (module) => module.title == 'Sportarten',
    );
    final sportIntegrations = appModules.firstWhere(
      (module) => module.title == 'Sport-Apps & Gesundheitsdaten',
    );
    final learning = appModules.firstWhere((module) => module.title == 'Kurse');
    final rides = appModules.firstWhere(
      (module) => module.title == 'Fahrgemeinschaften',
    );
    final sponsors = appModules.firstWhere(
      (module) => module.title == 'Sponsoren',
    );
    final blog = appModules.firstWhere(
      (module) => module.title == 'Blog & Medien',
    );
    final subscriptions = appModules.firstWhere(
      (module) => module.title == 'Abos & Rechnungen',
    );
    final coach = appModules.firstWhere(
      (module) => module.title == 'Trainer-Cockpit',
    );
    final commerce = appModules.firstWhere(
      (module) => module.title == 'Commerce',
    );
    final admin = appModules.firstWhere((module) => module.title == 'Admin');

    expect(AirmiusMvpSurface.showDeveloperSuites, isFalse);
    expect(AirmiusMvpSurface.isModuleVisible(events), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(nutrition), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(sportMap), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(friends), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(marketplace), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(badges), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(sports), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(sportIntegrations), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(learning), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(rides), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(sponsors), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(blog), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(subscriptions), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(coach), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(commerce), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(admin), isTrue);
    expect(AirmiusMvpSurface.isDashboardWidgetVisible('files'), isTrue);
    expect(AirmiusMvpSurface.isDashboardWidgetVisible('sport_map'), isFalse);
  });

  test('every visible MVP module has a production navigation destination', () {
    expect(
      AirmiusModuleDestination.supportedTitles,
      unorderedEquals(AirmiusMvpSurface.mvpModuleTitles),
    );
  });

  test('module access limits athletes to personal sport areas', () {
    const athlete = AirmiusUser(
      id: 10,
      name: 'Mina Sport',
      email: 'mina@example.test',
      role: 'player',
      roles: ['player'],
      permissions: [
        'event.join',
        'post.create',
        'file.view',
        'clubs.view',
        'teams.view',
        'training.view',
      ],
    );

    expect(AirmiusModuleAccess.canOpen(athlete, 'Events & Training'), isTrue);
    expect(AirmiusModuleAccess.canOpen(athlete, 'Sport-Matching'), isTrue);
    expect(AirmiusModuleAccess.canOpen(athlete, 'Dateien'), isTrue);
    expect(AirmiusModuleAccess.canOpen(athlete, 'Trainer-Cockpit'), isFalse);
    expect(AirmiusModuleAccess.canOpen(athlete, 'Vereins-Cockpit'), isFalse);
    expect(AirmiusModuleAccess.canOpen(athlete, 'Commerce'), isFalse);
    expect(AirmiusModuleAccess.canOpen(athlete, 'Sponsoren'), isFalse);
    expect(AirmiusModuleAccess.canOpen(athlete, 'Blog & Medien'), isFalse);
    expect(AirmiusModuleAccess.canOpen(athlete, 'Nutzer'), isFalse);
    expect(AirmiusModuleAccess.canOpen(athlete, 'Admin'), isFalse);
  });

  test('module access grants only role-specific operational areas', () {
    const coach = AirmiusUser(
      id: 11,
      name: 'Coach',
      email: 'coach@example.test',
      role: 'coach',
      roles: ['coach'],
      permissions: ['training.view', 'training.create', 'file.view'],
    );
    const clubAdmin = AirmiusUser(
      id: 12,
      name: 'Club Admin',
      email: 'club@example.test',
      role: 'club_admin',
      roles: ['club_admin'],
      permissions: [
        'finance.view',
        'subscriptions.manage',
        'clubs.manage_members',
      ],
      clubs: [
        AirmiusNamedItem(
          id: 7,
          name: 'Scoped Club',
          membershipRole: 'admin',
          canManage: true,
        ),
      ],
    );
    const guardian = AirmiusUser(
      id: 13,
      name: 'Guardian',
      email: 'guardian@example.test',
      role: 'guardian',
      roles: ['guardian'],
      permissions: ['guardians.children.view'],
    );
    const marketplaceManager = AirmiusUser(
      id: 14,
      name: 'Marketplace',
      email: 'market@example.test',
      role: 'marketplace_manager',
      roles: ['marketplace_manager'],
      permissions: ['marketplace.manage', 'commerce.orders.manage'],
    );

    expect(AirmiusModuleAccess.canOpen(coach, 'Trainer-Cockpit'), isTrue);
    expect(AirmiusModuleAccess.canOpen(coach, 'Vereins-Cockpit'), isFalse);
    expect(AirmiusModuleAccess.canOpen(coach, 'Admin'), isFalse);

    expect(AirmiusModuleAccess.canOpen(clubAdmin, 'Vereins-Cockpit'), isTrue);
    expect(AirmiusModuleAccess.canOpen(clubAdmin, 'Trainer-Cockpit'), isFalse);
    expect(AirmiusModuleAccess.canOpen(clubAdmin, 'Sponsoren'), isFalse);
    expect(AirmiusModuleAccess.canOpen(clubAdmin, 'Commerce'), isFalse);
    expect(AirmiusModuleAccess.canOpen(clubAdmin, 'Admin'), isFalse);

    expect(
      AirmiusModuleAccess.canOpen(guardian, 'Eltern & Jugendschutz'),
      isTrue,
    );
    expect(AirmiusModuleAccess.canOpen(guardian, 'Trainer-Cockpit'), isFalse);

    expect(AirmiusModuleAccess.canOpen(marketplaceManager, 'Commerce'), isTrue);
    expect(AirmiusModuleAccess.canOpen(marketplaceManager, 'Admin'), isTrue);
    expect(
      AirmiusModuleAccess.canOpen(marketplaceManager, 'Gamification-Regeln'),
      isFalse,
    );
  });

  test('platform administration remains hidden until 2FA is enabled', () {
    const withoutTwoFactor = AirmiusUser(
      id: 15,
      name: 'Admin',
      email: 'admin@example.test',
      role: 'admin',
      roles: ['admin'],
      permissions: ['system.manage', 'users.view', 'users.assign_roles'],
    );
    const withTwoFactor = AirmiusUser(
      id: 15,
      name: 'Admin',
      email: 'admin@example.test',
      role: 'admin',
      roles: ['admin'],
      permissions: ['system.manage', 'users.view', 'users.assign_roles'],
      twoFactorEnabled: true,
    );

    expect(AirmiusModuleAccess.canOpen(withoutTwoFactor, 'Admin'), isFalse);
    expect(
      AirmiusModuleAccess.canOpen(withoutTwoFactor, 'Rollen & Rechte'),
      isFalse,
    );
    expect(AirmiusModuleAccess.canOpen(withTwoFactor, 'Admin'), isTrue);
    expect(
      AirmiusModuleAccess.canOpen(withTwoFactor, 'Rollen & Rechte'),
      isTrue,
    );
    expect(
      AirmiusModuleAccess.canOpen(withTwoFactor, 'Gamification-Regeln'),
      isTrue,
    );
  });

  testWidgets('module overview avoids placeholder metrics in the MVP surface', (
    WidgetTester tester,
  ) async {
    final container = await _authenticatedWidgetTestContainer(
      const AirmiusUser(
        id: 1,
        name: 'Club Member',
        email: 'club-member@example.test',
        role: 'player',
        roles: ['player'],
        clubs: [
          AirmiusNamedItem(
            id: 1,
            name: 'Airmius Club',
            membershipRole: 'member',
          ),
        ],
      ),
    );
    await _pumpAirmiusWidget(
      tester,
      container,
      ModuleScreen(module: appModules.first),
    );

    expect(find.byType(MetricCard), findsNothing);
    expect(find.text('Arbeitsbereiche öffnen'), findsOneWidget);
  });

  testWidgets('messages module opens the chat inbox instead of notifications', (
    WidgetTester tester,
  ) async {
    final container = await _authenticatedWidgetTestContainer(
      const AirmiusUser(
        id: 20,
        name: 'Mina Sport',
        email: 'mina@example.test',
        role: 'player',
        roles: ['player'],
        permissions: ['training.view', 'event.join', 'file.view'],
      ),
    );
    await _pumpAirmiusWidget(
      tester,
      container,
      const ShellScreen(),
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.air,
    );
    await tester.pump();

    await tester.tap(find.byTooltip('Menü'));
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView).last, const Offset(0, -300));
    await tester.pumpAndSettle();
    final messageEntry = find.text('Nachrichten').last;
    expect(messageEntry, findsOneWidget);

    await tester.tap(messageEntry);
    await tester.pumpAndSettle();

    expect(find.byType(ConversationsCenterScreen), findsOneWidget);
    expect(find.byType(NotificationsCenterScreen), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('top bar shows the unread chat count on the message icon', (
    WidgetTester tester,
  ) async {
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const Scaffold(
        appBar: AirmiusTopBar(
          title: 'Feed',
          messageCount: 7,
          notificationCount: 2,
        ),
      ),
    );

    expect(find.text('7'), findsOneWidget);
    expect(find.text('2'), findsOneWidget);
    expect(find.bySemanticsLabel(RegExp(r'Nachrichten, 7')), findsOneWidget);
  });

  testWidgets('chat polling keeps messages visible without a loading flash', (
    WidgetTester tester,
  ) async {
    final transport = _ChatPollingTransport();
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const ChatDetailScreen(
        conversationId: 12,
        title: 'Lena Lauf',
        kind: 'Direkt',
      ),
    );
    await tester.pump();

    expect(find.text('Neue Nachricht 12'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsNothing);

    await tester.pump(const Duration(seconds: 4));
    await tester.pump(const Duration(seconds: 1));

    expect(find.text('Neue Nachricht 13'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsNothing);
    expect(transport.messageRequests, 2);
    expect(tester.takeException(), isNull);
  });

  testWidgets('chat scrolls to the latest received message automatically', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 700));
    final transport = _ChatPollingTransport();
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const ChatDetailScreen(
        conversationId: 12,
        title: 'Lena Lauf',
        kind: 'Direkt',
      ),
    );
    await tester.pump();
    await tester.pump();

    final scrollable = tester.state<ScrollableState>(
      find.descendant(
        of: find.byType(ListView),
        matching: find.byType(Scrollable),
      ),
    );
    expect(
      scrollable.position.pixels,
      closeTo(scrollable.position.minScrollExtent, 0.1),
    );

    scrollable.position.jumpTo(200);
    await tester.pump();
    expect(
      scrollable.position.pixels,
      greaterThan(scrollable.position.minScrollExtent),
    );

    await tester.pump(const Duration(seconds: 4));
    await tester.pump(const Duration(seconds: 1));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 250));

    expect(find.text('Neue Nachricht 13'), findsOneWidget);
    expect(
      scrollable.position.pixels,
      closeTo(scrollable.position.minScrollExtent, 0.1),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('authenticated app shell opens on the feed', (
    WidgetTester tester,
  ) async {
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const ShellScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.byType(FeedCenterScreen), findsOneWidget);
    expect(find.byType(TrainingCenterScreen), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('app shell loads a personal three-item footer navigation', (
    WidgetTester tester,
  ) async {
    final store = _MemoryPreferencesStore();
    final preferences = AirmiusPreferences(store: store);
    const user = AirmiusUser(
      id: 31,
      name: 'Mina Sport',
      email: 'mina@example.test',
      role: 'player',
      roles: ['player'],
      permissions: ['training.view', 'event.join'],
    );
    await preferences.writeFooterNavigation(user.id, const [
      FooterNavigationDestination.messages,
      FooterNavigationDestination.drink,
      FooterNavigationDestination.feed,
    ]);
    final container = await _authenticatedWidgetTestContainer(user);

    await _pumpAirmiusWidget(
      tester,
      container,
      ShellScreen(preferences: preferences),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('Nachrichten'), findsOneWidget);
    expect(find.text('Trinken'), findsOneWidget);
    expect(find.text('Training'), findsNothing);
    expect(find.text('Teams'), findsNothing);

    await tester.tap(find.text('Trinken'));
    await tester.pumpAndSettle();

    expect(find.byType(NutritionCenterScreen), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  test('club finance permission does not turn a user into a sponsor', () {
    const user = AirmiusUser(
      id: 32,
      name: 'Club Finance',
      email: 'finance@example.test',
      role: 'financial_controller',
      roles: ['financial_controller'],
      permissions: ['finance.edit', 'sponsors.view'],
    );

    expect(AirmiusPersonaResolver.primary(user), AirmiusPersona.club);
    expect(
      FooterNavigationDestination.defaultsFor(user),
      isNot(contains(FooterNavigationDestination.sponsors)),
    );
  });

  testWidgets('sport integrations open directly from the module drawer', (
    WidgetTester tester,
  ) async {
    final container = await _authenticatedWidgetTestContainer(
      const AirmiusUser(
        id: 21,
        name: 'Mina Sport',
        email: 'mina@example.test',
        role: 'player',
        roles: ['player'],
        permissions: ['training.view', 'event.join', 'file.view'],
      ),
    );
    await _pumpAirmiusWidget(
      tester,
      container,
      const ShellScreen(),
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.air,
    );
    await tester.pump();

    await tester.tap(find.byTooltip('Menü'));
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView).last, const Offset(0, -300));
    await tester.pumpAndSettle();
    final integrationsEntry = find.text('Sport-Apps & Gesundheitsdaten').last;
    expect(integrationsEntry, findsOneWidget);

    await tester.tap(integrationsEntry);
    await tester.pumpAndSettle();

    expect(find.byType(SportIntegrationsScreen), findsOneWidget);
    expect(find.text('Sport-Apps & Gesundheitsdaten'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('athlete sidebar hides operational and admin modules', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 2200));
    final container = await _authenticatedWidgetTestContainer(
      const AirmiusUser(
        id: 22,
        name: 'Alex Athlete',
        email: 'alex@example.test',
        role: 'player',
        roles: ['player'],
        permissions: [
          'event.join',
          'post.create',
          'file.view',
          'clubs.view',
          'teams.view',
          'training.view',
        ],
      ),
    );
    await _pumpAirmiusWidget(tester, container, const ShellScreen());
    await tester.pump();

    await tester.tap(find.byTooltip('Menü'));
    await tester.pumpAndSettle();

    expect(find.text('Events & Training'), findsWidgets);
    expect(find.text('Sport-Matching'), findsWidgets);
    expect(find.text('Dateien'), findsWidgets);
    expect(find.text('Trainer-Cockpit'), findsNothing);
    expect(find.text('Vereins-Cockpit'), findsNothing);
    expect(find.text('Commerce'), findsNothing);
    expect(find.text('Sponsoren'), findsNothing);
    expect(find.text('Medienrichtlinien'), findsNothing);
    expect(find.text('Blog & Medien'), findsNothing);
    expect(find.text('Nutzer'), findsNothing);
    expect(find.text('Rollen & Rechte'), findsNothing);
    expect(find.text('Admin'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'club-scoped finance does not expose trainer sponsor or platform admin',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 2200));
      final container = await _authenticatedWidgetTestContainer(
        const AirmiusUser(
          id: 23,
          name: 'Club Scoped User',
          email: 'club-scoped@example.test',
          role: 'club_admin',
          roles: ['club_admin'],
          permissions: [
            'clubs.manage_members',
            'finance.view',
            'subscriptions.manage',
            'training.view',
            'file.view',
          ],
          clubs: [
            AirmiusNamedItem(
              id: 8,
              name: 'Scoped Club',
              membershipRole: 'admin',
              canManage: true,
            ),
          ],
        ),
      );
      await _pumpAirmiusWidget(tester, container, const ShellScreen());
      await tester.pumpAndSettle();

      expect(find.byType(ClubCockpitScreen), findsOneWidget);
      await tester.pageBack();
      await tester.pumpAndSettle();

      await tester.tap(find.byTooltip('Menü'));
      await tester.pumpAndSettle();

      expect(find.text('Arbeitsbereiche'), findsWidgets);
      expect(find.text('Vereins-Cockpit'), findsWidgets);
      expect(find.text('Trainer-Cockpit'), findsNothing);
      expect(find.text('Sponsoren'), findsNothing);
      expect(find.text('Commerce'), findsNothing);
      expect(find.text('Admin'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('compatibility admin entries open their matching section', (
    WidgetTester tester,
  ) async {
    final container = await _authenticatedWidgetTestContainer(
      const AirmiusUser(
        id: 1,
        name: 'Platform Admin',
        email: 'platform-admin@example.test',
        role: 'admin',
        roles: ['admin'],
        permissions: ['system.manage', 'users.view', 'users.assign_roles'],
        twoFactorEnabled: true,
      ),
    );
    await _pumpAirmiusWidget(
      tester,
      container,
      const RolesPermissionsScreen(),
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.air,
    );
    await tester.pumpAndSettle();

    final roleChip = find.widgetWithText(ChoiceChip, 'Rollen & Rechte');
    expect(roleChip, findsOneWidget);
    expect(tester.widget<ChoiceChip>(roleChip).selected, isTrue);
    expect(find.text('Nutzer'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'localized module launcher stays ergonomic in Arabic light mode',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 1200));
      final container = await _authenticatedWidgetTestContainer(
        const AirmiusUser(
          id: 1,
          name: 'Mina Sport',
          email: 'mina@example.test',
          role: 'player',
          roles: ['player'],
        ),
      );
      await _pumpAirmiusWidget(
        tester,
        container,
        ModuleScreen(
          module: appModules.firstWhere(
            (module) => module.title == 'Sport-Apps & Gesundheitsdaten',
          ),
        ),
        language: AirmiusLanguage.ar,
        themeMode: ThemeMode.light,
        palette: AirmiusThemePalette.air,
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('فتح'), findsWidgets);
      expect(
        tester
            .widgetList<Directionality>(find.byType(Directionality))
            .map((widget) => widget.textDirection),
        contains(TextDirection.rtl),
      );
      expect(tester.takeException(), isNull);
    },
  );

  test('offline queue persists requests across transport restarts', () async {
    final store = _MemoryPreferencesStore();
    final firstTransport = AirmiusQueuedTransport(
      inner: _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"ok":true}'),
      ),
      timeout: const Duration(seconds: 1),
      store: store,
    )..offline = true;

    await firstTransport.send(
      const AirmiusApiRequest(
        method: 'POST',
        path: '/api/v1/mobile/sync',
        body: {'action': 'create_event'},
      ),
    );

    expect(firstTransport.queue, hasLength(1));
    expect(
      await store.readString(AirmiusQueuedTransport.defaultStorageKey),
      contains('/api/v1/mobile/sync'),
    );

    final replayTransport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"synced":true}'),
    );
    final restoredTransport = AirmiusQueuedTransport(
      inner: replayTransport,
      timeout: const Duration(seconds: 1),
      store: store,
    );

    final responses = await restoredTransport.flush();

    expect(responses.single.statusCode, 200);
    expect(replayTransport.requests.single.method, 'POST');
    expect(replayTransport.requests.single.path, '/api/v1/mobile/sync');
    expect(
      replayTransport.requests.single.body,
      containsPair('action', 'create_event'),
    );
    expect(restoredTransport.queue, isEmpty);
    expect(
      await store.readString(AirmiusQueuedTransport.defaultStorageKey),
      '[]',
    );
  });

  test('offline queue refuses credential and session mutations', () async {
    final store = _MemoryPreferencesStore();
    final transport = AirmiusQueuedTransport(
      inner: _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"ok":true}'),
      ),
      timeout: const Duration(seconds: 1),
      store: store,
    )..offline = true;

    final response = await transport.send(
      const AirmiusApiRequest(
        method: 'PUT',
        path: '/api/v1/me/password',
        body: {'current_password': 'secret', 'password': 'new-secret'},
        headers: {'Authorization': 'Bearer token'},
      ),
    );

    expect(response.statusCode, 503);
    expect(response.body, contains('offline_sensitive_request'));
    expect(transport.queue, isEmpty);
    expect(
      await store.readString(AirmiusQueuedTransport.defaultStorageKey),
      isNull,
    );

    final shareResponse = await transport.send(
      const AirmiusApiRequest(
        method: 'POST',
        path: '/api/v1/uploads/42/share',
        body: {'target_user_id': 99},
      ),
    );
    expect(shareResponse.statusCode, 503);
    expect(transport.queue, isEmpty);
  });

  test('api client queues offline mobile sync and flushes later', () async {
    final store = _MemoryPreferencesStore();
    final replayTransport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"synced":true}'),
    );
    final queuedTransport = AirmiusQueuedTransport(
      inner: replayTransport,
      timeout: const Duration(seconds: 1),
      store: store,
    )..offline = true;
    final client = AirmiusApiClient(
      transport: queuedTransport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    final queuedResponse = await client.mobileSync(
      body: {
        'actions': [
          {'type': 'post.create', 'client_id': 'offline-1'},
        ],
      },
    );

    expect(queuedResponse, containsPair('queued', true));
    expect(replayTransport.requests, isEmpty);
    expect(queuedTransport.queue, hasLength(1));

    queuedTransport.offline = false;
    final responses = await queuedTransport.flush();

    expect(responses.single.statusCode, 200);
    expect(replayTransport.requests.single.method, 'POST');
    expect(replayTransport.requests.single.path, '/api/v1/mobile/sync');
    expect(
      replayTransport.requests.single.body?['actions'],
      contains(containsPair('client_id', 'offline-1')),
    );
    expect(
      await store.readString(AirmiusQueuedTransport.defaultStorageKey),
      '[]',
    );
  });

  test(
    'upload retry repeats transient failures and stops on validation errors',
    () async {
      const policy = AirmiusUploadRetryPolicy(baseDelay: Duration.zero);
      var attempts = 0;

      final result = await policy.run((_) async {
        attempts++;
        if (attempts == 1) {
          throw const AirmiusApiException(
            statusCode: 599,
            body: '{"message":"network timeout"}',
            path: '/api/v1/uploads',
          );
        }
        return 'uploaded';
      });

      expect(result, 'uploaded');
      expect(attempts, 2);

      var validationAttempts = 0;
      await expectLater(
        policy.run((_) async {
          validationAttempts++;
          throw const AirmiusApiException(
            statusCode: 422,
            body: '{"message":"file too large"}',
            path: '/api/v1/uploads',
          );
        }),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(validationAttempts, 1);
    },
  );

  test('auth state restore refreshes stored token with current user', () async {
    final tokenStore = AirmiusMemoryTokenStore();
    await tokenStore.write(
      const AirmiusSession(
        token: 'stored-token',
        locale: 'de',
        user: AirmiusUser(
          id: 7,
          name: 'Alter Name',
          email: 'alt@example.test',
          role: 'athlete',
        ),
      ),
    );
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete","first_name":"Mina","last_name":"Sprint","birth_date":"2000-01-01","gender":"female","country":"DE"}}',
      ),
    );
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://airmius.test',
        enableOfflineQueue: false,
      ),
      transport: transport,
      tokenStore: tokenStore,
      pushDeviceStore: _MemoryPreferencesStore(),
    );

    await container.authState.restore();
    final restoredSession = await tokenStore.read();

    expect(container.authState.phase, AirmiusAuthPhase.authenticated);
    expect(container.authState.user?.firstName, 'Mina');
    expect(restoredSession?.token, 'stored-token');
    expect(restoredSession?.user?.email, 'mina@example.test');
    expect(transport.requests.single.path, '/api/v1/me');
    expect(
      transport.requests.single.headers['Authorization'],
      'Bearer stored-token',
    );
  });

  test(
    'push registry registers refreshes and unregisters one device id',
    () async {
      final store = _MemoryPreferencesStore();
      final tokenProvider = _FakePushTokenProvider('push-token-one');
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"status":"updated","device":{"device_id":"device-1","platform":"android","provider":"fcm","token_fingerprint":"abc123"}}}',
        ),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );
      final registry = AirmiusPushDeviceRegistry(
        store: store,
        tokenProvider: tokenProvider,
        deviceIdFactory: () => 'device-1',
      );

      final registration = await registry.setOptIn(
        enabled: true,
        client: client,
      );
      tokenProvider.token = 'push-token-two';
      final refresh = await registry.registerIfOptedIn(client);
      await registry.unregister(client);

      expect(registration.isRegistered, isTrue);
      expect(refresh.isRegistered, isTrue);
      expect(transport.requests[0].method, 'POST');
      expect(transport.requests[0].path, '/api/v1/mobile/push-devices');
      expect(transport.requests[0].body, containsPair('device_id', 'device-1'));
      expect(
        transport.requests[0].body,
        containsPair('token', 'push-token-one'),
      );
      expect(transport.requests[1].body, containsPair('device_id', 'device-1'));
      expect(
        transport.requests[1].body,
        containsPair('token', 'push-token-two'),
      );
      expect(transport.requests[2].method, 'DELETE');
      expect(
        transport.requests[2].path,
        '/api/v1/mobile/push-devices/device-1',
      );
      expect(
        await store.readString(AirmiusPushDeviceRegistry.optInStorageKey),
        'false',
      );
    },
  );

  test('service container deletes push device before logout', () async {
    final pushStore = _MemoryPreferencesStore();
    await pushStore.writeString(
      AirmiusPushDeviceRegistry.deviceIdStorageKey,
      'logout-device',
    );
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://airmius.test',
      ),
      transport: transport,
      tokenStore: AirmiusMemoryTokenStore(),
      pushDeviceStore: pushStore,
    );

    await container.authState.signInWithToken(token: 'auth-token');
    await container.authState.signOut();

    expect(
      transport.paths,
      contains('/api/v1/mobile/push-devices/logout-device'),
    );
    expect(
      transport.paths.indexOf('/api/v1/mobile/push-devices/logout-device'),
      lessThan(transport.paths.indexOf('/api/v1/auth/logout')),
    );
  });

  test('deep link resolver covers MVP sport routes and invitations', () {
    const resolver = AirmiusDeepLinkResolver();

    final club = resolver.resolve('airmius://clubs/7');
    final membershipRequests = resolver.resolve(
      'airmius://clubs/7/membership-requests',
    );
    final membershipApplication = resolver.resolve(
      'airmius://membership-applications/99',
    );
    final team = resolver.resolve('https://app.airmius.com/teams/12');
    final event = resolver.resolve('airmius://events/31');
    final post = resolver.resolve('airmius://feed/44');
    final chat = resolver.resolve('airmius://chat/9');
    final message = resolver.resolve('airmius://messages/17');
    final profile = resolver.resolve('airmius://profile/23');
    final invitation = resolver.resolve(
      'https://app.airmius.com/team-invitations/token/abc123/accept',
    );
    final passwordReset = resolver.resolve(
      'https://app.airmius.com/reset-password'
      '?token=secure-token&email=member%40example.test',
    );
    final pathPasswordReset = resolver.resolve(
      'https://app.airmius.com/reset-password/secure-token'
      '?email=member%40example.test',
    );
    final emailVerification = resolver.resolve(
      'https://app.airmius.com/api/v1/auth/verify-email/7/email-hash'
      '?expires=123&signature=signed-value',
    );

    expect(club.type, AirmiusDeepLinkTargetType.club);
    expect(club.id, 7);
    expect(membershipRequests.type, AirmiusDeepLinkTargetType.club);
    expect(membershipRequests.id, 7);
    expect(membershipRequests.section, 'membership-requests');
    expect(membershipRequests.requiresAuth, isTrue);
    expect(
      membershipApplication.type,
      AirmiusDeepLinkTargetType.membershipApplication,
    );
    expect(membershipApplication.id, 99);
    expect(membershipApplication.requiresAuth, isTrue);
    expect(team.type, AirmiusDeepLinkTargetType.team);
    expect(team.id, 12);
    expect(event.type, AirmiusDeepLinkTargetType.event);
    expect(event.id, 31);
    expect(post.type, AirmiusDeepLinkTargetType.post);
    expect(post.id, 44);
    expect(chat.type, AirmiusDeepLinkTargetType.chat);
    expect(chat.id, 9);
    expect(message.type, AirmiusDeepLinkTargetType.message);
    expect(message.id, 17);
    expect(profile.type, AirmiusDeepLinkTargetType.profile);
    expect(profile.id, 23);
    expect(invitation.type, AirmiusDeepLinkTargetType.invitation);
    expect(invitation.token, 'abc123');
    expect(invitation.requiresAuth, isTrue);
    expect(passwordReset.type, AirmiusDeepLinkTargetType.passwordReset);
    expect(passwordReset.token, 'secure-token');
    expect(passwordReset.query['email'], 'member@example.test');
    expect(passwordReset.requiresAuth, isFalse);
    expect(pathPasswordReset.type, AirmiusDeepLinkTargetType.passwordReset);
    expect(pathPasswordReset.token, 'secure-token');
    expect(pathPasswordReset.query['email'], 'member@example.test');
    expect(emailVerification.type, AirmiusDeepLinkTargetType.emailVerification);
    expect(emailVerification.id, 7);
    expect(emailVerification.token, 'email-hash');
    expect(emailVerification.query['signature'], 'signed-value');
    expect(emailVerification.requiresAuth, isFalse);
  });

  testWidgets('protected deep links do not open API screens for guests', (
    WidgetTester tester,
  ) async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () =>
              AirmiusDeepLinkNavigator.open(context, 'airmius://feed/44'),
          child: const Text('Open link'),
        ),
      ),
    );

    await tester.tap(find.text('Open link'));
    await tester.pumpAndSettle();

    expect(find.text('Anmeldung erforderlich'), findsWidgets);
    expect(find.text('Anmeldung / Sitzung erforderlich'), findsOneWidget);
    expect(transport.paths, isEmpty);
    expect(tester.takeException(), isNull);
  });

  testWidgets('authenticated event deep links load the protected detail', (
    WidgetTester tester,
  ) async {
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
      ),
      const AirmiusApiResponse(
        statusCode: 403,
        body: '{"message":"Dieses Event ist nicht verfügbar."}',
      ),
    ]);
    final container = _widgetTestContainer(transport: transport);
    await container.authState.signInWithToken(token: 'auth-token');

    await _pumpAirmiusWidget(
      tester,
      container,
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () =>
              AirmiusDeepLinkNavigator.open(context, 'airmius://events/31'),
          child: const Text('Open event'),
        ),
      ),
    );

    await tester.tap(find.text('Open event'));
    await tester.pumpAndSettle();

    final paths = transport.requests.map((request) => request.path);
    expect(paths, contains('/api/v1/me'));
    expect(paths, contains('/api/v1/events/31'));
    expect(
      find.text('Detaildaten konnten nicht geladen werden.'),
      findsOneWidget,
    );
    expect(find.text('Dieses Event ist nicht verfügbar.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('authenticated post deep links load the protected detail', (
    WidgetTester tester,
  ) async {
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
      ),
      const AirmiusApiResponse(
        statusCode: 403,
        body: '{"message":"Dieser Beitrag ist nicht verfügbar."}',
      ),
    ]);
    final container = _widgetTestContainer(transport: transport);
    await container.authState.signInWithToken(token: 'auth-token');

    await _pumpAirmiusWidget(
      tester,
      container,
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () =>
              AirmiusDeepLinkNavigator.open(context, 'airmius://feed/44'),
          child: const Text('Open post'),
        ),
      ),
    );

    await tester.tap(find.text('Open post'));
    await tester.pumpAndSettle();

    final paths = transport.requests.map((request) => request.path);
    expect(paths, contains('/api/v1/me'));
    expect(paths, contains('/api/v1/posts/44'));
    expect(
      find.text('Detaildaten konnten nicht geladen werden.'),
      findsOneWidget,
    );
    expect(find.text('Dieser Beitrag ist nicht verfügbar.'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'authenticated chat deep links resolve the protected conversation',
    (WidgetTester tester) async {
      final transport = _SequencedTransport([
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
        ),
        const AirmiusApiResponse(
          statusCode: 403,
          body: '{"message":"Diese Konversation ist nicht verfügbar."}',
        ),
      ]);
      final container = _widgetTestContainer(transport: transport);
      await container.authState.signInWithToken(token: 'auth-token');

      await _pumpAirmiusWidget(
        tester,
        container,
        Builder(
          builder: (context) => ElevatedButton(
            onPressed: () =>
                AirmiusDeepLinkNavigator.open(context, 'airmius://chat/9'),
            child: const Text('Open chat'),
          ),
        ),
      );

      await tester.tap(find.text('Open chat'));
      await tester.pumpAndSettle();

      final paths = transport.requests.map((request) => request.path);
      expect(paths, contains('/api/v1/me'));
      expect(paths, contains('/api/v1/chat/conversations/9'));
      expect(
        find.text('Detaildaten konnten nicht geladen werden.'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'authenticated message deep links resolve the protected message context',
    (WidgetTester tester) async {
      final transport = _SequencedTransport([
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
        ),
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"id":17,"conversation_id":9,"sender":{"name":"Alex Sport"},"message":"Laufplan für Montag","created_at":"2026-07-26T07:00:00Z","status":"sent"}}',
        ),
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"id":9,"name":"Laufgruppe","type":"group","users":[]}}',
        ),
      ]);
      final container = _widgetTestContainer(transport: transport);
      await container.authState.signInWithToken(token: 'auth-token');

      await _pumpAirmiusWidget(
        tester,
        container,
        Builder(
          builder: (context) => ElevatedButton(
            onPressed: () =>
                AirmiusDeepLinkNavigator.open(context, 'airmius://messages/17'),
            child: const Text('Open message'),
          ),
        ),
      );

      await tester.tap(find.text('Open message'));
      await tester.pumpAndSettle();

      final paths = transport.requests.map((request) => request.path);
      expect(paths, contains('/api/v1/chat/messages/17'));
      expect(paths, contains('/api/v1/chat/conversations/9'));
      expect(find.text('Laufplan für Montag'), findsOneWidget);
      expect(find.text('Nachricht oder Konversation'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'authenticated notification deep links resolve the protected item',
    (WidgetTester tester) async {
      final transport = _SequencedTransport([
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
        ),
        const AirmiusApiResponse(
          statusCode: 403,
          body: '{"message":"Diese Benachrichtigung ist nicht verfügbar."}',
        ),
      ]);
      final container = _widgetTestContainer(transport: transport);
      await container.authState.signInWithToken(token: 'auth-token');

      await _pumpAirmiusWidget(
        tester,
        container,
        Builder(
          builder: (context) => ElevatedButton(
            onPressed: () => AirmiusDeepLinkNavigator.open(
              context,
              'airmius://notifications/11',
            ),
            child: const Text('Open notification'),
          ),
        ),
      );

      await tester.tap(find.text('Open notification'));
      await tester.pumpAndSettle();

      final paths = transport.requests.map((request) => request.path);
      expect(paths, contains('/api/v1/me'));
      expect(paths, contains('/api/v1/notifications/11'));
      expect(
        find.text('Detaildaten konnten nicht geladen werden.'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('authenticated profile deep links load the privacy-filtered profile', (
    WidgetTester tester,
  ) async {
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"user_id":23,"profile":{"id":23,"name":"Alex Sport","bio":"Läuft gern","profile_visibility":"public"},"visibility":"public","headline":"Laufen"}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"friends":[],"receivedInvitations":[],"sentInvitations":[]}}',
      ),
    ]);
    final container = _widgetTestContainer(transport: transport);
    await container.authState.signInWithToken(token: 'auth-token');

    await _pumpAirmiusWidget(
      tester,
      container,
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () =>
              AirmiusDeepLinkNavigator.open(context, 'airmius://profile/23'),
          child: const Text('Open profile'),
        ),
      ),
    );

    await tester.tap(find.text('Open profile'));
    await tester.pumpAndSettle();

    final paths = transport.requests.map((request) => request.path);
    expect(paths, contains('/api/v1/users/23/sport-cv'));
    expect(find.text('Alex Sport'), findsWidgets);
    expect(find.text('Läuft gern'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('membership deep links load the matching protected application', (
    WidgetTester tester,
  ) async {
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":42,"club_id":4,"status":"pending","submitted_at":"2026-07-25T12:00:00Z"}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":42,"club_id":4,"user_id":7,"type":"membership","status":"pending","club":{"name":"Airmius Club"},"application_data":{"first_name":"Mina","last_name":"Sprint"},"created_at":"2026-07-25T12:00:00Z"}],"meta":{"total":1}}',
      ),
    ]);
    final container = _widgetTestContainer(transport: transport);
    await container.authState.signInWithToken(token: 'auth-token');

    await _pumpAirmiusWidget(
      tester,
      container,
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => AirmiusDeepLinkNavigator.open(
            context,
            'airmius://membership-applications/42',
          ),
          child: const Text('Open application'),
        ),
      ),
    );

    await tester.tap(find.text('Open application'));
    await tester.pumpAndSettle();

    final paths = transport.requests.map((request) => request.path);
    expect(paths, contains('/api/v1/me'));
    expect(paths, contains('/api/v1/membership-applications/42'));
    expect(paths, contains('/api/v1/clubs/4/membership-requests'));
    expect(find.text('Meine Mitgliedsanfrage'), findsOneWidget);
    expect(find.textContaining('Airmius Club'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('team invitation token deep links load the protected invitation', (
    WidgetTester tester,
  ) async {
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":12,"team_id":9,"role":"Player","status":"pending","team":{"id":9,"club_id":4,"name":"Token Team","sport_type":"fussball","club":{"id":4,"name":"Airmius Club"}},"inviter":{"id":1,"name":"Coach"}}}',
      ),
    ]);
    final container = _widgetTestContainer(transport: transport);
    await container.authState.signInWithToken(token: 'auth-token');

    await _pumpAirmiusWidget(
      tester,
      container,
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => AirmiusDeepLinkNavigator.open(
            context,
            'https://app.airmius.com/team-invitations/token/abc123/accept',
          ),
          child: const Text('Open invitation'),
        ),
      ),
    );

    await tester.tap(find.text('Open invitation'));
    await tester.pumpAndSettle();

    final paths = transport.requests.map((request) => request.path);
    expect(paths, contains('/api/v1/me'));
    expect(paths, contains('/api/v1/team-invitations/token/abc123'));
    expect(find.text('Token Team'), findsOneWidget);
    expect(find.textContaining('Airmius Club'), findsOneWidget);
    expect(find.text('Annehmen'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('friend invitation token deep links load the protected request', (
    WidgetTester tester,
  ) async {
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":21,"status":"pending","sender":{"id":4,"name":"Alex Sport"}}}',
      ),
    ]);
    final container = _widgetTestContainer(transport: transport);
    await container.authState.signInWithToken(token: 'auth-token');

    await _pumpAirmiusWidget(
      tester,
      container,
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => AirmiusDeepLinkNavigator.open(
            context,
            'https://app.airmius.com/friends/invitations/token/abc123/accept',
          ),
          child: const Text('Open friend invitation'),
        ),
      ),
    );

    await tester.tap(find.text('Open friend invitation'));
    await tester.pumpAndSettle();

    final paths = transport.requests.map((request) => request.path);
    expect(paths, contains('/api/v1/me'));
    expect(paths, contains('/api/v1/friends/invitations/token/abc123'));
    expect(find.text('Alex Sport'), findsOneWidget);
    expect(find.text('Freundschaftsanfrage'), findsOneWidget);
    expect(find.text('Annehmen'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('club invitation token deep links load the protected membership', (
    WidgetTester tester,
  ) async {
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete"}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":33,"status":"pending","role":"trainer","expires_at":"2026-08-01T23:59:59Z","club":{"id":4,"name":"Airmius Club"}}}',
      ),
    ]);
    final container = _widgetTestContainer(transport: transport);
    await container.authState.signInWithToken(token: 'auth-token');

    await _pumpAirmiusWidget(
      tester,
      container,
      Builder(
        builder: (context) => ElevatedButton(
          onPressed: () => AirmiusDeepLinkNavigator.open(
            context,
            'https://app.airmius.com/club-member-invitations/token/abc123/accept',
          ),
          child: const Text('Open club invitation'),
        ),
      ),
    );

    await tester.tap(find.text('Open club invitation'));
    await tester.pumpAndSettle();

    final paths = transport.requests.map((request) => request.path);
    expect(paths, contains('/api/v1/me'));
    expect(paths, contains('/api/v1/club-external-invitations/abc123'));
    expect(find.text('Airmius Club'), findsOneWidget);
    expect(find.text('Vereins-Einladung'), findsOneWidget);
    expect(find.text('Beitreten'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('deep link fallback localizes unknown links in Arabic', (
    WidgetTester tester,
  ) async {
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const AirmiusDeepLinkFallbackScreen(
        target: AirmiusDeepLinkTarget(
          type: AirmiusDeepLinkTargetType.unknown,
          path: '',
        ),
      ),
      language: AirmiusLanguage.ar,
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.arena,
    );
    await tester.pumpAndSettle();

    expect(find.text('رابط غير معروف'), findsOneWidget);
    expect(find.text('مسار آمن بديل'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  test('chat realtime client sends read and typing signals', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{"ok":true}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.markConversationRead(42);
    await client.sendConversationTyping(42, true);
    await client.sendConversationTyping(42, false);

    expect(transport.requests[0].method, 'POST');
    expect(transport.requests[0].path, '/api/v1/chat/conversations/42/read');
    expect(transport.requests[1].path, '/api/v1/chat/conversations/42/typing');
    expect(transport.requests[1].body, containsPair('typing', true));
    expect(transport.requests[2].path, '/api/v1/chat/conversations/42/typing');
    expect(transport.requests[2].body, containsPair('typing', false));
  });

  test('chat client covers complete group management contracts', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body: '{"data":{"id":42,"type":"group","name":"Laufgruppe"}}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.updateConversation(42, {'name': 'Neue Laufgruppe'});
    await client.muteConversation(42, 480);
    await client.inviteConversationMembers(42, [8, 9]);
    await client.removeConversationMember(42, 8);
    await client.transferConversationOwner(42, 9);
    await client.leaveConversation(42);
    await client.conversationInvitations();
    await client.acceptConversationInvitation(17);
    await client.declineConversationInvitation(18);

    expect(
      transport.paths,
      containsAllInOrder([
        '/api/v1/chat/conversations/42',
        '/api/v1/chat/conversations/42/mute',
        '/api/v1/chat/conversations/42/members',
        '/api/v1/chat/conversations/42/members/8',
        '/api/v1/chat/conversations/42/owner',
        '/api/v1/chat/conversations/42/leave',
        '/api/v1/chat/conversation-invitations',
        '/api/v1/chat/conversation-invitations/17/accept',
        '/api/v1/chat/conversation-invitations/18/decline',
      ]),
    );
    expect(transport.requests[0].method, 'PUT');
    expect(transport.requests[3].method, 'DELETE');
    expect(transport.requests[5].method, 'DELETE');
    expect(transport.requests.last.method, 'POST');
  });

  test(
    'chat client creates direct and group conversations via api v1',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 201,
          body: '{"data":{"id":7,"type":"group","name":"Laufgruppe"}}',
        ),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.createConversation(
        type: 'direct',
        participantIds: [12],
        message: 'Hallo Team.',
      );
      await client.createConversation(
        type: 'group',
        participantIds: [12, 13],
        name: 'Laufgruppe',
        description: 'Montagstraining',
      );

      expect(transport.requests[0].method, 'POST');
      expect(transport.requests[0].path, '/api/v1/chat/conversations');
      expect(transport.requests[0].body, containsPair('type', 'direct'));
      expect(transport.requests[0].body, containsPair('participant_ids', [12]));
      expect(
        transport.requests[0].body,
        containsPair('message', 'Hallo Team.'),
      );
      expect(transport.requests[1].path, '/api/v1/chat/conversations');
      expect(transport.requests[1].body, containsPair('type', 'group'));
      expect(
        transport.requests[1].body,
        containsPair('participant_ids', [12, 13]),
      );
      expect(transport.requests[1].body, containsPair('name', 'Laufgruppe'));
      expect(
        transport.requests[1].body,
        containsPair('description', 'Montagstraining'),
      );
    },
  );

  test(
    'notification repository maps detail data and paginates list requests',
    () async {
      final listTransport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":[],"meta":{"current_page":2,"per_page":20,"total":0},"links":{}}',
        ),
      );
      final listClient = AirmiusApiClient(
        transport: listTransport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await AirmiusApiNotificationRepository(listClient).notifications(page: 2);

      expect(listTransport.requests.single.method, 'GET');
      expect(listTransport.requests.single.path, '/api/v1/notifications');
      expect(listTransport.requests.single.query, containsPair('page', '2'));

      final detailTransport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"id":7,"type":"event.reminder","title":"Training heute","body":"18:00 Uhr","read":false,"action_url":"/events/7","data":{"event_id":7}}}',
        ),
      );
      final detailClient = AirmiusApiClient(
        transport: detailTransport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      final notification = await AirmiusApiNotificationRepository(
        detailClient,
      ).notification(7);

      expect(detailTransport.requests.single.path, '/api/v1/notifications/7');
      expect(notification.id, 7);
      expect(notification.title, 'Training heute');
      expect(notification.unread, isTrue);
      expect(notification.actionUrl, 'https://airmius.test/events/7');
      expect(notification.data, containsPair('event_id', 7));
    },
  );

  test(
    'secure token store migrates legacy tokens without unsafe fallback writes',
    () async {
      final legacyStore = AirmiusMemoryTokenStore();
      await legacyStore.write(
        const AirmiusSession(token: 'legacy-token', locale: 'de'),
      );
      final secureStorage = _MemorySecureSessionStorage();
      final tokenStore = AirmiusSecureTokenStore(
        secureStorage: secureStorage,
        fallback: legacyStore,
      );

      final restored = await tokenStore.read();

      expect(restored?.token, 'legacy-token');
      expect(secureStorage.value, contains('legacy-token'));
      expect(await legacyStore.read(), isNull);
    },
  );

  test(
    'secure token store never writes auth tokens to fallback storage',
    () async {
      final legacyStore = AirmiusMemoryTokenStore();
      final secureStorage = _MemorySecureSessionStorage(failWrites: true);
      final tokenStore = AirmiusSecureTokenStore(
        secureStorage: secureStorage,
        fallback: legacyStore,
      );

      await expectLater(
        tokenStore.write(
          const AirmiusSession(token: 'auth-token', locale: 'de'),
        ),
        throwsA(isA<StateError>()),
      );

      expect(await legacyStore.read(), isNull);
    },
  );

  test(
    'secure token store recreates an invalid Android encrypted entry once',
    () async {
      final legacyStore = AirmiusMemoryTokenStore();
      final secureStorage = _MemorySecureSessionStorage(failWriteCount: 1)
        ..value = 'unreadable-old-entry';
      final tokenStore = AirmiusSecureTokenStore(
        secureStorage: secureStorage,
        fallback: legacyStore,
      );

      await tokenStore.write(
        const AirmiusSession(token: 'fresh-social-token', locale: 'de'),
      );

      expect(secureStorage.deleteCount, 1);
      expect(secureStorage.value, contains('fresh-social-token'));
      expect(await legacyStore.read(), isNull);
    },
  );

  test('guardian client uses only protected api v1 endpoints', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'guardian-token',
    );

    await client.guardianChildren();
    await client.guardianChildOverview(12);
    await client.approveGuardianChild(12);
    await client.revokeGuardianChild(12);
    await client.resendGuardianChildConsent(12);
    await client.guardianConsentStatus();
    await client.resendOwnGuardianConsent();

    expect(transport.requests.map((request) => request.path), [
      '/api/v1/guardian/children',
      '/api/v1/guardian/children/12',
      '/api/v1/guardian/children/12/approve',
      '/api/v1/guardian/children/12/revoke',
      '/api/v1/guardian/children/12/resend',
      '/api/v1/guardian/consent',
      '/api/v1/guardian/consent/resend',
    ]);
    expect(transport.requests.first.method, 'GET');
    expect(transport.requests.map((request) => request.method), [
      'GET',
      'GET',
      'POST',
      'POST',
      'POST',
      'GET',
      'POST',
    ]);
  });

  test('guardian models map protected child state without secret data', () {
    final workspace = AirmiusGuardianWorkspace.fromJson({
      'data': {
        'guardian': {'name': 'Parent Example', 'email': 'parent@example.test'},
        'can_manage': true,
        'children': [
          {
            'id': 12,
            'name': 'Junior Example',
            'email': 'junior@example.test',
            'age': 13,
            'status': 'pending',
            'resend_available_in': 22,
            'guardian_consent_token': 'must-not-be-consumed',
            'privacy': {
              'profile_visibility': 'private',
              'direct_message_privacy': 'friends',
              'friend_request_privacy': 'friends',
              'direct_messages_enabled': false,
            },
          },
        ],
      },
    });

    expect(workspace.guardianName, 'Parent Example');
    expect(workspace.canManage, isTrue);
    expect(workspace.children.single.id, 12);
    expect(workspace.children.single.status, 'pending');
    expect(workspace.children.single.resendAvailableIn, 22);
    expect(workspace.children.single.profileVisibility, 'private');
    expect(workspace.children.single.directMessagesEnabled, isFalse);
  });

  test('guardian overview model keeps only safe aggregate fields', () {
    final overview = AirmiusGuardianChildOverview.fromJson({
      'data': {
        'child': {
          'id': 12,
          'name': 'Junior Example',
          'email': 'junior@example.test',
          'age': 13,
          'status': 'approved',
          'privacy': {'profile_visibility': 'private'},
        },
        'access': {
          'approved': true,
          'scope': 'safe_aggregates',
          'private_details_hidden': true,
          'notes_visible': false,
        },
        'upcoming_events': [
          {
            'id': 7,
            'title': 'Training',
            'type': 'training',
            'start_time': '2026-07-27T18:00:00Z',
            'location_name': 'Sporthalle',
            'location_city': 'Berlin',
            'team': {'id': 2, 'name': 'U16'},
            'participation_status': 'yes',
            'notes': 'must not be surfaced',
          },
        ],
        'training': {
          'period_days': 28,
          'sessions': 2,
          'duration_minutes': 90,
          'distance_meters': 12000,
          'calories': 600,
          'last_performed_at': '2026-07-25T12:00:00Z',
          'private_notes_hidden': true,
        },
      },
    });

    expect(overview.approved, isTrue);
    expect(overview.scope, 'safe_aggregates');
    expect(overview.events.single.title, 'Training');
    expect(overview.training.sessions, 2);
    expect(overview.training.distanceMeters, 12000);
    expect(overview.privateDetailsHidden, isTrue);
  });

  test(
    'sport integrations client uses protected normalized import contracts',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'sport-token',
      );

      await client.sportIntegrations();
      await client.requestSportIntegration('garmin');
      await client.syncSportIntegration(8);
      await client.disconnectSportIntegration(8);
      await client.importSportActivity(
        provider: 'strava',
        externalId: 'activity-1',
        startedAt: '2026-07-26T08:00:00Z',
        title: 'Morgenlauf',
        durationSeconds: 1800,
        distanceMeters: 5000,
      );

      expect(transport.requests.map((request) => request.path), [
        '/api/v1/sport-integrations',
        '/api/v1/sport-integrations/garmin/request',
        '/api/v1/sport-integrations/accounts/8/sync',
        '/api/v1/sport-integrations/accounts/8',
        '/api/v1/sport-integrations/activities/import',
      ]);
      expect(transport.requests.map((request) => request.method), [
        'GET',
        'POST',
        'POST',
        'DELETE',
        'POST',
      ]);
      expect(transport.requests.last.body, containsPair('provider', 'strava'));
      expect(
        transport.requests.last.body,
        containsPair('external_id', 'activity-1'),
      );
    },
  );

  test(
    'sport integration models preserve provider capabilities and activity summaries',
    () {
      final bundle = AirmiusSportIntegrationBundle.fromJson({
        'data': {
          'providers': [
            {
              'key': 'strava',
              'label': 'Strava',
              'status': 'live_oauth',
              'connection_mode': 'oauth_import_gpx_export',
              'direction': ['import', 'export_gpx'],
              'supports_gps_samples': true,
              'supports_background_sync': true,
              'scopes': ['read'],
              'account': {
                'id': 8,
                'status': 'connected',
                'display_name': 'Strava',
                'last_synced_at': '2026-07-26T08:00:00Z',
                'sync_summary': {'message': 'Aktivität importiert.'},
              },
            },
          ],
          'activities': [
            {
              'id': 4,
              'provider': 'strava',
              'activity_type': 'Run',
              'title': 'Morgenlauf',
              'started_at': '2026-07-26T08:00:00Z',
              'duration_seconds': 1800,
              'distance_meters': 5000,
              'calories': 300,
            },
          ],
        },
      });

      expect(bundle.providers.single.isConnected, isTrue);
      expect(bundle.providers.single.supportsGpsSamples, isTrue);
      expect(bundle.providers.single.direction, contains('export_gpx'));
      expect(bundle.activities.single.title, 'Morgenlauf');
      expect(bundle.activities.single.distanceMeters, 5000);
    },
  );

  testWidgets('guardian center renders real children and approves safely', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(900, 1500));
    const workspaceResponse = AirmiusApiResponse(
      statusCode: 200,
      body:
          '{"data":{"guardian":{"id":1,"name":"Parent Example","email":"parent@example.test"},"can_manage":true,"children":[{"id":12,"name":"Junior Example","email":"junior@example.test","age":13,"status":"pending","requested_at":"2026-07-24T10:00:00Z","resend_available_in":0,"privacy":{"profile_visibility":"private","direct_message_privacy":"friends","friend_request_privacy":"friends","direct_messages_enabled":false}}]}}',
    );
    final transport = _SequencedTransport([
      workspaceResponse,
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"message":"guardian_consent_approved","data":{"id":12,"name":"Junior Example","email":"junior@example.test","age":13,"status":"approved","privacy":{"profile_visibility":"private","direct_message_privacy":"friends","friend_request_privacy":"friends","direct_messages_enabled":true}}}',
      ),
      workspaceResponse,
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const GuardianCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Hallo Parent Example'), findsOneWidget);
    expect(find.text('Junior Example'), findsOneWidget);
    expect(find.text('Privates Profil'), findsOneWidget);
    expect(find.text('Nachrichten gesperrt'), findsOneWidget);

    await tester.ensureVisible(find.text('Zustimmen'));
    await tester.tap(find.text('Zustimmen'));
    await tester.pumpAndSettle();
    expect(find.text('Kinderkonto freigeben?'), findsOneWidget);

    final confirmationButton = find.descendant(
      of: find.byType(AlertDialog),
      matching: find.widgetWithText(FilledButton, 'Zustimmen'),
    );
    await tester.tap(confirmationButton);
    await tester.pumpAndSettle();

    expect(
      transport.requests.map((request) => request.path),
      containsAllInOrder([
        '/api/v1/guardian/children',
        '/api/v1/guardian/children/12/approve',
        '/api/v1/guardian/children',
      ]),
    );
  });

  testWidgets('guardian child overview renders consent-safe aggregates', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1300));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"child":{"id":12,"name":"Junior Example","email":"junior@example.test","age":13,"status":"approved","privacy":{"profile_visibility":"private"}},"access":{"approved":true,"scope":"safe_aggregates","private_details_hidden":true,"notes_visible":false},"upcoming_events":[{"id":7,"title":"Training","type":"training","start_time":"2026-07-27T18:00:00Z","location_name":"Sporthalle","location_city":"Berlin","team":{"id":2,"name":"U16"},"participation_status":"yes"}],"training":{"period_days":28,"sessions":2,"duration_minutes":90,"distance_meters":12000,"calories":600,"last_performed_at":"2026-07-25T12:00:00Z","private_notes_hidden":true}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const GuardianChildOverviewScreen(childId: 12),
    );
    await tester.pumpAndSettle();

    expect(find.text('Sicherer Kinderüberblick'), findsOneWidget);
    expect(find.text('Geschützter Datenumfang'), findsOneWidget);
    expect(find.text('Training'), findsOneWidget);
    expect(find.text('12.000 km'), findsNothing);
    expect(find.text('90'), findsOneWidget);
    expect(find.text('Private Gesundheitsnotiz'), findsNothing);
    expect(transport.requests.single.path, '/api/v1/guardian/children/12');
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'sport integrations screen renders honest provider and empty states',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 1300));
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"providers":[{"key":"strava","label":"Strava","status":"live_oauth","connection_mode":"oauth_import_gpx_export","direction":["import","export_gpx"],"supports_gps_samples":true,"supports_background_sync":true,"scopes":["read"],"request_message":"Strava Import ist per OAuth vorbereitet; Export läuft über GPX.","account":null}],"activities":[],"normalized_import":{"endpoint":"/api/v1/sport-integrations/activities/import","max_samples":5000},"gpx":{}}}',
        ),
      );

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        const SportIntegrationsScreen(),
      );
      await tester.pumpAndSettle();

      expect(find.text('Sport-Apps & Gesundheitsdaten'), findsOneWidget);
      expect(find.text('Strava'), findsOneWidget);
      expect(find.text('Verbindung vormerken'), findsOneWidget);
      expect(find.text('Noch keine Aktivitäten importiert.'), findsOneWidget);
      expect(find.text('GPS-Track'), findsOneWidget);
      expect(transport.requests.single.path, '/api/v1/sport-integrations');
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'theme chooser localizes palettes and remains usable with large RTL text',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 1100));
      final container = _widgetTestContainer();

      await _pumpAirmiusWidget(
        tester,
        container,
        const Scaffold(
          body: SingleChildScrollView(
            padding: EdgeInsets.all(16),
            child: AirmiusThemeChooser(),
          ),
        ),
        language: AirmiusLanguage.en,
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('Colour palette'), findsOneWidget);
      expect(find.text('Champion gold'), findsOneWidget);
      expect(find.text('High contrast'), findsOneWidget);
      expect(tester.takeException(), isNull);

      await _pumpAirmiusWidget(
        tester,
        container,
        const Scaffold(
          body: SingleChildScrollView(
            padding: EdgeInsets.all(16),
            child: AirmiusThemeChooser(),
          ),
        ),
        language: AirmiusLanguage.ar,
        textScaler: const TextScaler.linear(1.35),
      );
      await tester.pumpAndSettle();

      expect(find.text('لوحة الألوان'), findsOneWidget);
      expect(find.text('تباين عالٍ'), findsOneWidget);
      expect(
        tester
            .widgetList<Directionality>(find.byType(Directionality))
            .map((widget) => widget.textDirection),
        contains(TextDirection.rtl),
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('theme-aware neutral colors remain readable in light mode', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const TeamsCenterScreen(),
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.air,
    );
    await tester.pumpAndSettle();

    final title = tester.widget<Text>(
      find.text('Teams als mobile Arbeitsbereiche'),
    );
    expect(
      title.style?.color,
      AirmiusTheme.light(AirmiusThemePalette.air).colorScheme.onSurface,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('profile localizes visible controls with large RTL text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1100));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const ProfileScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.air,
    );
    await tester.pumpAndSettle();

    expect(find.text('ضيف'), findsOneWidget);
    expect(find.text('نظرة عامة'), findsOneWidget);
    expect(find.text('الرياضة'), findsOneWidget);
    expect(find.text('الشبكة'), findsOneWidget);
    final activeTab = tester.widget<Text>(find.text('نظرة عامة'));
    expect(
      activeTab.style?.color,
      AirmiusTheme.light(AirmiusThemePalette.air).colorScheme.onPrimary,
    );
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('profile editor localizes fields with large RTL text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1200));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const EditFormScreen(
        title: 'معلومات الملف الشخصي',
        subtitle: 'البيانات الشخصية',
        mode: EditFormMode.profile,
      ),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.champion,
    );
    await tester.pumpAndSettle();

    expect(find.text('تحرير'), findsOneWidget);
    expect(find.text('البيانات الشخصية'), findsWidgets);
    expect(find.text('الاسم الأول'), findsWidgets);
    expect(find.text('اسم العائلة'), findsWidgets);
    expect(find.text('الجنس'), findsWidgets);
    expect(find.text('النبذة الشخصية'), findsOneWidget);
    expect(find.text('حفظ'), findsOneWidget);
    expect(find.text('إلغاء'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('team detail supports large RTL text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const TeamDetailScreen(title: 'U18', mode: 'Profil'),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('الفريق'), findsOneWidget);
    expect(find.text('ملف الفريق'), findsOneWidget);
    expect(find.text('القائمة'), findsWidgets);
    expect(find.text('دعوة'), findsWidgets);
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('team detail exposes the protected team cashbox', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":9,"club_id":2,"name":"Laufteam","can_manage":true,"users_count":1,"events_count":2,"users":[{"id":44,"name":"Ada","email":"ada@example.test","role":"Player"}]}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"can_manage":true,"rules":[{"id":3,"title":"Verspätung","trigger":"late","calculation_type":"fixed","amount":5,"currency":"EUR","is_active":true}],"fees":[{"id":7,"amount":5,"currency":"EUR","status":"open","note":"Training","member":{"id":44,"name":"Ada"}}],"summary":{"open_amount":5,"open_count":1}}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TeamDetailScreen(title: 'Laufteam', mode: 'Profil', teamId: 9),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.widgetWithText(ChoiceChip, 'Strafen'));
    await tester.pumpAndSettle();

    expect(find.text('Team-Strafkasse'), findsOneWidget);
    expect(find.text('Verspätung'), findsOneWidget);
    expect(find.text('Ada'), findsOneWidget);
    expect(find.text('Als bezahlt markieren'), findsOneWidget);
    expect(
      transport.requests.map((request) => request.path),
      contains('/api/v1/teams/9/penalties'),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('team detail loads upcoming calendar events from the API', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":9,"club_id":2,"name":"Laufteam","events_count":1,"users":[]}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[],"calendar_events":[{"id":31,"title":"Morgentraining","starts_at":"2030-01-02T18:00:00Z","type":"training","status":"open","visibility":"private","team_id":9,"location_name":"Sporthalle","location_city":"Berlin"}],"event_stats":{"upcoming":1,"today":0,"cancelled":0}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TeamDetailScreen(title: 'Laufteam', mode: 'Profil', teamId: 9),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Kalender'));
    await tester.pumpAndSettle();

    expect(find.text('TEAMKALENDER'), findsOneWidget);
    expect(find.text('Morgentraining'), findsOneWidget);
    expect(find.textContaining('Sporthalle · Berlin'), findsOneWidget);
    expect(
      transport.requests.map((request) => request.path),
      contains('/api/v1/events'),
    );
    expect(
      transport.requests
          .where((request) => request.path == '/api/v1/events')
          .single
          .query['team_id'],
      '9',
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('team detail loads protected team files from the API', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":9,"club_id":2,"name":"Laufteam","events_count":0,"users":[]}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"scope":{"type":"team"},"folders":[{"id":4,"name":"Trainingspläne","files_count":1}],"files":[{"id":17,"display_name":"Trainingsplan.pdf","type":"application/pdf","size":2048,"url":"https://airmius.test/files/17"}]}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TeamDetailScreen(title: 'Laufteam', mode: 'Profil', teamId: 9),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Dateien'));
    await tester.pumpAndSettle();

    expect(find.text('TEAMDATEIEN'), findsOneWidget);
    expect(find.text('Trainingsplan.pdf'), findsOneWidget);
    expect(find.textContaining('application/pdf'), findsOneWidget);
    expect(
      transport.requests
          .where((request) => request.path == '/api/v1/files')
          .single
          .query['team_id'],
      '9',
    );
    await tester.tap(find.text('Trainingsplan.pdf'));
    await tester.pumpAndSettle();
    expect(find.text('Trainingsplan.pdf'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('team detail loads the team conversation from the API', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":9,"club_id":2,"name":"Laufteam","viewer_is_member":true,"events_count":0,"users":[]}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":55,"type":"team","team_id":9,"name":"Laufteam","members_count":8,"latest_message":{"message":"Bis heute Abend"}}]}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TeamDetailScreen(title: 'Laufteam', mode: 'Profil', teamId: 9),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Chat'));
    await tester.pumpAndSettle();

    expect(find.text('TEAMCHAT'), findsOneWidget);
    expect(find.text('Laufteam'), findsWidgets);
    expect(find.textContaining('Bis heute Abend'), findsOneWidget);
    expect(
      transport.requests.map((request) => request.path),
      contains('/api/v1/chat/conversations'),
    );
    expect(
      transport.requests
          .where((request) => request.path == '/api/v1/chat/conversations')
          .single
          .query['team_id'],
      '9',
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('clubs workspace supports large RTL text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","country":"DE","sport_type":"running","members_count":12,"teams_count":2,"can_manage":true,"can_delete":true}]}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      ClubsScreen(
        requestedClubIds: const {},
        onRequestClub: (_) {},
        onWithdrawClub: (_) {},
      ),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('الأندية والفرق'), findsWidgets);
    expect(find.text('منطقة النادي'.toUpperCase()), findsOneWidget);
    expect(find.text('تسجيل نادٍ'), findsOneWidget);
    expect(find.text('العثور على نادٍ'), findsOneWidget);
    expect(find.text('Airmius Club'), findsOneWidget);
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('clubs workspace follows the active light palette', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","country":"DE","sport_type":"running","members_count":12,"teams_count":2,"can_manage":true,"can_delete":true}]}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      ClubsScreen(
        requestedClubIds: const {},
        onRequestClub: (_) {},
        onWithdrawClub: (_) {},
      ),
      language: AirmiusLanguage.de,
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.trail,
      textScaler: const TextScaler.linear(1.25),
    );
    await tester.pumpAndSettle();

    expect(find.text('Vereine & Teams'), findsWidgets);
    expect(find.text('Airmius Club'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'club membership exposes pause and termination actions from the API',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 1800));
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"id":4,"owner_id":99,"name":"Airmius Club","city":"Berlin","members_count":12,"teams_count":2,"is_member":true,"member_pause_requests_enabled":true,"membership":{"role":"member","status":"active","joined_on":"2025-01-01","pause_requested":false}}}',
        ),
      );

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        ClubProfileScreen(
          club: ClubSummary(
            id: 4,
            name: 'Airmius Club',
            city: 'Berlin',
            members: 12,
            teams: 2,
            posts: 0,
            acceptsMemberships: true,
            hasPendingMembershipRequest: false,
            isMember: true,
            verified: true,
          ),
          requested: false,
          onRequest: _noopClubChange,
          onWithdraw: _noopClubChange,
        ),
      );
      await tester.pumpAndSettle();

      await tester.tap(find.text('Beitritt'));
      await tester.pumpAndSettle();

      expect(find.text('Pause beantragen'), findsOneWidget);
      expect(find.text('Austritt beantragen'), findsOneWidget);
      expect(
        transport.requests.map((request) => request.path),
        contains('/api/v1/clubs/4'),
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('member card renders minimal status and secure rotating code', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1200));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"name":"Airmius Club","city":"Berlin","is_member":true,"can_manage":false}]}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"club":{"id":4,"name":"Airmius Club"},"member":{"id":8,"name":"Mira Member","role":"member","membership_status":"active"},"token":{"value":"a1b2c3d4e5f6","expires_at":"2026-08-01T12:00:00Z","expires_in_seconds":600},"visible_claims":["name","club","role","membership_status","expires_at"],"hidden_claims":["email","address","payment_status"]}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const MemberCardScreen(initialClubId: 4),
    );
    await tester.pumpAndSettle();

    expect(find.text('Mira Member'), findsOneWidget);
    expect(find.text('a1b2c3d4e5f6'), findsOneWidget);
    expect(
      find.text('Nur Name, Verein, Rolle und Status werden gezeigt.'),
      findsOneWidget,
    );
    expect(find.byKey(const ValueKey('memberCardQr')), findsOneWidget);
    expect(find.text('Mitgliedskarte'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'exercise library renders honest empty state and localized actions',
    (WidgetTester tester) async {
      _setTestViewport(tester, const Size(390, 844));
      final transport = _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
      );

      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(transport: transport),
        const ExerciseLibraryScreen(),
      );
      await tester.pumpAndSettle();

      expect(find.text('Übungsbibliothek'), findsNWidgets(2));
      expect(
        find.text('Noch keine passenden Übungen vorhanden.'),
        findsOneWidget,
      );
      expect(find.text('Neue Übung'), findsOneWidget);
      expect(
        transport.paths,
        containsAllInOrder([
          '/api/v1/training/exercises',
          '/api/v1/clubs',
          '/api/v1/teams',
        ]),
      );
    },
  );

  testWidgets('training progress renders a truthful empty analytics state', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 844));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TrainingProgressScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Trainingsfortschritt'), findsNWidgets(2));
    expect(
      find.text('Für diesen Zeitraum liegen noch keine Einheiten vor.'),
      findsOneWidget,
    );
    await tester.scrollUntilVisible(
      find.text('Keine erhöhten Belastungs- oder Schmerzwerte gefunden.'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    expect(
      find.text('Keine erhöhten Belastungs- oder Schmerzwerte gefunden.'),
      findsOneWidget,
    );
    expect(transport.paths, ['/api/v1/training/analytics']);
  });

  testWidgets('training availability renders a private empty state', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 900));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"athlete_id":8,"can_edit":true,"current":null,"history":[],"privacy":{"scope":"self","notes_visible":true}}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TrainingAvailabilityScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Trainingsstatus'), findsWidgets);
    expect(
      find.text('Noch kein Trainingsstatus hinterlegt.'),
      findsNWidgets(2),
    );
    expect(find.text('Status setzen'), findsOneWidget);
    expect(transport.paths, ['/api/v1/training/availability']);
    expect(tester.takeException(), isNull);
  });

  testWidgets('membership application supports large RTL text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1800));
    const club = ClubSummary(
      id: 4,
      name: 'Airmius Club',
      city: 'Berlin',
      members: 12,
      teams: 2,
      posts: 0,
      acceptsMemberships: true,
      hasPendingMembershipRequest: false,
      isMember: false,
      verified: true,
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const ApplicationScreen(club: club),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('طلب عضوية'), findsOneWidget);
    expect(find.text('البيانات الشخصية'), findsOneWidget);
    expect(find.text('بيانات الاتصال'), findsOneWidget);
    expect(find.text('التقدم'), findsOneWidget);
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('teams center keeps teams visible across localized tabs', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":7,"club_id":4,"name":"U18 Falcons","club_name":"Airmius Club","sport_type":"football","visibility":"public","can_manage":true}]}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TeamsCenterScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('U18 Falcons', skipOffstage: false), findsOneWidget);
    expect(find.text('الدعوات'), findsOneWidget);
    await tester.tap(find.text('الدعوات'));
    await tester.pump();
    expect(find.text('U18 Falcons'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('team invitation response stays localized and palette-aware', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":8,"team_id":7,"team_name":"U18 Falcons","club_name":"Airmius Club","role":"coach","status":"pending","sport_type":"football","inviter_name":"Coach Mira"}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TeamInvitationResponseScreen(invitationId: 8),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('دعوة إلى فريق'), findsWidgets);
    expect(find.text('U18 Falcons'), findsOneWidget);
    expect(find.text('قبول'), findsOneWidget);
    expect(
      tester
          .widgetList<Directionality>(find.byType(Directionality))
          .map((widget) => widget.textDirection),
      contains(TextDirection.rtl),
    );
    expect(transport.paths, contains('/api/v1/team-invitations/8'));
    expect(tester.takeException(), isNull);
  });

  testWidgets('team invitation fallback localizes missing API data', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 500, body: '{"message":"offline"}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const TeamInvitationResponseScreen(
        invitationId: 9,
        notification: AirmiusNotification(
          id: 99,
          type: 'team_invitation',
          title: 'دعوة',
          body: 'دعوة إلى فريق',
          timeLabel: 'الآن',
          unread: true,
        ),
      ),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.25),
      themeMode: ThemeMode.light,
      palette: AirmiusThemePalette.arena,
    );
    await tester.pumpAndSettle();

    expect(find.text('فريق'), findsOneWidget);
    expect(find.textContaining('لاعب'), findsOneWidget);
    expect(find.text('دعوة إلى فريق'), findsWidgets);
    expect(tester.takeException(), isNull);
  });

  testWidgets('workspace center renders server-derived clubs and teams', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1500));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"name":"Airmius Club","city":"Berlin","members_count":12,"teams_count":1,"is_member":true}]}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":7,"club_id":4,"name":"U18 Falcons","visibility":"private"}]}',
      ),
      const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}'),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const WorkspaceCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Airmius Club'), findsWidgets);
    expect(find.textContaining('U18 Falcons'), findsWidgets);
    expect(
      transport.requests.map((request) => request.path),
      contains('/api/v1/clubs'),
    );
    expect(
      transport.requests.map((request) => request.path),
      contains('/api/v1/teams'),
    );
    expect(tester.takeException(), isNull);
  });

  test('file share client uses the internal friend-sharing endpoint', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 201,
        body: '{"data":{"file_id":42,"shared":true,"target_user_id":99}}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    final result = await client.shareFile(42, 99);

    expect(transport.requests.single.method, 'POST');
    expect(transport.requests.single.path, '/api/v1/uploads/42/share');
    expect(transport.requests.single.body, containsPair('target_user_id', 99));
    expect(result['data'], containsPair('file_id', 42));

    await client.shareFolder(7, 99);
    expect(transport.requests[1].method, 'POST');
    expect(transport.requests[1].path, '/api/v1/files/folders/7/share');
    expect(transport.requests[1].body, containsPair('target_id', 99));
  });

  testWidgets('shared file access never shows a fabricated token state', (
    WidgetTester tester,
  ) async {
    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const SharedFileAccessScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.text('Noch keine interne Freigabe vorhanden.'), findsWidgets);
    expect(find.byType(SwitchListTile), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('file manager hides storage fallback without server usage data', (
    WidgetTester tester,
  ) async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const FileManagerScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('Speicher:'), findsNothing);
    expect(transport.paths, contains('/api/v1/files'));
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'file preview shows backend metadata without local fake switches',
    (WidgetTester tester) async {
      await _pumpAirmiusWidget(
        tester,
        _widgetTestContainer(),
        const FilePreviewScreen(
          title: 'Training.jpg',
          body: 'https://airmius.test/storage/training.jpg',
          status: 'Backend',
          icon: Icons.image_outlined,
          fileMeta: 'image/jpeg - 1 KB',
          fileUrl: 'https://airmius.test/storage/training.jpg',
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('image/jpeg - 1 KB'), findsOneWidget);
      expect(find.byType(SwitchListTile), findsNothing);
      expect(find.text('Test-Link testen'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('file operations upload opens the real file picker callback', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(800, 1000));
    var openedUploader = false;

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      FileOperationsScreen(onOpenUploader: () => openedUploader = true),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.text('Upload starten'));
    await tester.tap(find.text('Upload starten'));
    await tester.pump();

    expect(openedUploader, isTrue);
    expect(tester.takeException(), isNull);
  });

  testWidgets('membership request inbox stays localized and responsive in Arabic', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1900));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","can_manage":true}]}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":5,"club_id":4,"user_id":7,"type":"membership","status":"pending","application_data":{"first_name":"Mina","last_name":"Sport","email":"mina@example.test"},"accepted_documents":["privacy"],"created_at":"2026-07-25T12:00:00Z"}],"meta":{"total":1}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const ClubRequestInboxScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('صندوق طلبات العضوية'), findsWidgets);
    expect(find.text('راجع الطلبات الجديدة بسرعة.'), findsOneWidget);
    expect(find.text('مقدم الطلب'), findsOneWidget);
    expect(find.text('مراجعة'), findsWidgets);
    expect(find.text('قبول'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('membership admin localizes fields and stays usable in Arabic', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 2100));
    final transport = _SequencedTransport([
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","can_manage":true}]}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":4,"owner_id":1,"name":"Airmius Club","city":"Berlin","can_manage":true,"management":{"can_manage":true,"settings":{"membership_requests_enabled":true,"membership_application_fields":{}}}}}',
      ),
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":5,"club_id":4,"user_id":7,"type":"membership","status":"pending","user":{"name":"Mina Sport","email":"mina@example.test"},"club":{"name":"Airmius Club"},"created_at":"2026-07-25T12:00:00Z"}],"meta":{"total":1}}',
      ),
    ]);

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const ClubMembershipAdminScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('الأعضاء والاشتراكات'), findsWidgets);
    await tester.ensureVisible(find.text('التالي'));
    await tester.tap(find.text('التالي'));
    await tester.pump();
    expect(find.text('الاسم الأول'), findsOneWidget);
    expect(find.textContaining('البيانات الشخصية'), findsWidgets);
    expect(find.text('Mina Sport'), findsOneWidget);
    expect(find.text('قبول'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('notifications center localizes and wraps metrics in Arabic', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1900));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":1,"type":"membership.request","title":"طلب عضوية","body":"يرجى المراجعة","read":false,"time_label":"2026-07-25T12:00:00Z"},{"id":2,"type":"system","title":"تحديث النظام","body":"تم التحديث","read":true,"time_label":"2026-07-25T11:00:00Z"}],"meta":{"total":2}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const NotificationsCenterScreen(),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('الإشعارات'), findsWidgets);
    expect(find.text('الطلبات'), findsWidgets);
    expect(find.text('غير مقروءة'), findsOneWidget);
    expect(find.text('الإشعارات الفورية'), findsOneWidget);
    expect(find.text('طلب عضوية'), findsOneWidget);
    await tester.tap(find.text('تحديد الكل كمقروء'));
    await tester.pumpAndSettle();
    expect(transport.paths, contains('/api/v1/notifications/read-all'));
    expect(tester.takeException(), isNull);
  });

  testWidgets('notifications center scrolls as one page on a phone', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 700));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":1,"type":"system","title":"Benachrichtigung 1","body":"Erste Meldung","read":false,"time_label":"2026-07-25T12:00:00Z"},{"id":2,"type":"system","title":"Benachrichtigung 2","body":"Zweite Meldung","read":true,"time_label":"2026-07-25T11:00:00Z"},{"id":3,"type":"system","title":"Benachrichtigung 3","body":"Dritte Meldung","read":true,"time_label":"2026-07-25T10:00:00Z"},{"id":4,"type":"system","title":"Benachrichtigung 4","body":"Vierte Meldung","read":true,"time_label":"2026-07-25T09:00:00Z"},{"id":5,"type":"system","title":"Benachrichtigung 5","body":"Fünfte Meldung","read":true,"time_label":"2026-07-25T08:00:00Z"},{"id":6,"type":"system","title":"Benachrichtigung 6","body":"Sechste Meldung","read":true,"time_label":"2026-07-25T07:00:00Z"}],"meta":{"total":6}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const NotificationsCenterScreen(),
    );
    await tester.pumpAndSettle();

    expect(find.byType(Scrollable), findsOneWidget);
    final scrollable = tester.state<ScrollableState>(find.byType(Scrollable));
    expect(scrollable.position.pixels, 0);

    await tester.drag(find.text('Benachrichtigung 1'), const Offset(0, -500));
    await tester.pumpAndSettle();

    expect(scrollable.position.pixels, greaterThan(0));
    expect(find.text('Benachrichtigung 6'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('membership request status is localized for Arabic large text', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(390, 1900));
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":[{"id":5,"club_id":4,"user_id":7,"type":"membership","status":"pending","club":{"name":"Airmius Club"},"application_data":{"first_name":"Mina","last_name":"Sport","email":"mina@example.test"},"accepted_documents":["privacy"],"created_at":"2026-07-25T12:00:00Z"}],"meta":{"total":1}}',
      ),
    );

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(transport: transport),
      const MembershipRequestStatusScreen(clubId: 4),
      language: AirmiusLanguage.ar,
      textScaler: const TextScaler.linear(1.35),
    );
    await tester.pumpAndSettle();

    expect(find.text('البيانات الشخصية'), findsWidgets);
    expect(find.text('سجل الحالة'), findsOneWidget);
    expect(find.text('الإجراءات'), findsOneWidget);
    expect(find.text('تم إرسال الطلب'), findsOneWidget);
    expect(find.text('Airmius Club'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpAirmiusWidget(
  WidgetTester tester,
  AirmiusServiceContainer container,
  Widget child, {
  AirmiusLanguage language = AirmiusLanguage.de,
  TextScaler? textScaler,
  ThemeMode themeMode = ThemeMode.dark,
  AirmiusThemePalette palette = AirmiusThemePalette.dark,
}) async {
  await tester.pumpWidget(
    AirmiusServicesScope(
      container: container,
      child: AirmiusScope(
        language: language,
        setLanguage: (_) {},
        child: AirmiusAccessibilityScope(
          textSize: AirmiusTextSize.normal,
          setTextSize: (_) {},
          child: AirmiusThemeModeScope(
            mode: themeMode,
            setMode: (_) {},
            palette: palette,
            setPalette: (_) {},
            child: MaterialApp(
              theme: AirmiusTheme.light(palette),
              darkTheme: AirmiusTheme.dark(palette),
              themeMode: themeMode,
              builder: (context, child) {
                final content = textScaler == null
                    ? child!
                    : MediaQuery(
                        data: MediaQuery.of(
                          context,
                        ).copyWith(textScaler: textScaler),
                        child: child!,
                      );
                return Directionality(
                  textDirection: language.isRtl
                      ? TextDirection.rtl
                      : TextDirection.ltr,
                  child: content,
                );
              },
              home: child,
            ),
          ),
        ),
      ),
    ),
  );
  await tester.pump();
}

AirmiusServiceContainer _widgetTestContainer({
  AirmiusApiTransport? transport,
  AirmiusPreferencesStore? pushDeviceStore,
  AirmiusTokenStore? tokenStore,
}) {
  return AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://airmius.test',
      enableOfflineQueue: false,
    ),
    transport:
        transport ??
        _RecordingTransport(
          const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
        ),
    tokenStore: tokenStore ?? AirmiusMemoryTokenStore(),
    pushDeviceStore: pushDeviceStore ?? _MemoryPreferencesStore(),
  );
}

Future<AirmiusServiceContainer> _authenticatedWidgetTestContainer(
  AirmiusUser user, {
  AirmiusApiTransport? transport,
}) async {
  final tokenStore = AirmiusMemoryTokenStore();
  await tokenStore.write(
    AirmiusSession(token: 'role-test-token', locale: 'de', user: user),
  );
  final container = _widgetTestContainer(
    tokenStore: tokenStore,
    transport: _AuthenticatedShellTransport(user, delegate: transport),
  );
  await container.authState.restore();
  return container;
}

void _setTestViewport(WidgetTester tester, Size size) {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

void _noopClubChange(ClubSummary _) {}

class _RecordingTransport implements AirmiusApiTransport {
  _RecordingTransport(this.response);

  final AirmiusApiResponse response;
  final List<AirmiusApiRequest> requests = <AirmiusApiRequest>[];

  List<String> get paths => requests.map((request) => request.path).toList();

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return response;
  }
}

class _AuthenticatedShellTransport implements AirmiusApiTransport {
  const _AuthenticatedShellTransport(this.user, {this.delegate});

  final AirmiusUser user;
  final AirmiusApiTransport? delegate;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path == '/api/v1/me') {
      return AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': {
            'id': user.id,
            'name': user.name,
            'email': user.email,
            'role': user.role,
            'roles': user.roles,
            'permissions': user.permissions,
            'two_factor_enabled': user.twoFactorEnabled,
            'clubs': [
              for (final club in user.clubs)
                {
                  'id': club.id,
                  'name': club.name,
                  'can_manage': club.canManage,
                  'membership': {'role': club.membershipRole},
                },
            ],
            'teams': [
              for (final team in user.teams)
                {
                  'id': team.id,
                  'name': team.name,
                  'can_manage': team.canManage,
                  'membership': {'role': team.membershipRole},
                },
            ],
          },
        }),
      );
    }
    if (delegate != null) return delegate!.send(request);
    return const AirmiusApiResponse(
      statusCode: 200,
      body: '{"data":[],"meta":{"unread_count":0}}',
    );
  }
}

class _SequencedTransport implements AirmiusApiTransport {
  _SequencedTransport(List<AirmiusApiResponse> responses)
    : _responses = List<AirmiusApiResponse>.from(responses);

  final List<AirmiusApiResponse> _responses;
  final List<AirmiusApiRequest> requests = <AirmiusApiRequest>[];

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (_responses.isEmpty) {
      throw StateError('No queued response for ${request.path}');
    }
    if (_responses.length == 1) return _responses.single;
    return _responses.removeAt(0);
  }
}

class _ChatPollingTransport implements AirmiusApiTransport {
  int messageRequests = 0;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.path == '/api/v1/chat/conversations/12/messages') {
      messageRequests++;
      if (messageRequests > 1) {
        await Future<void>.delayed(const Duration(seconds: 1));
      }
      final messageCount = messageRequests > 1 ? 13 : 12;
      final messages = List.generate(
        messageCount,
        (index) => {
          'id': index + 41,
          'conversation_id': 12,
          'sender_id': 8,
          'current_user_id': 3,
          'message': index == 0
              ? 'Training startet um 18 Uhr.'
              : 'Neue Nachricht ${index + 1}',
          'sender': {'id': 8, 'name': 'Lena Lauf'},
          'status': 'sent',
          'created_at': '2026-07-28T16:${index.toString().padLeft(2, '0')}:00Z',
          'reactions': <Object>[],
        },
      ).reversed.toList();
      return AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': messages,
          'chat': {'typing_users': <Object>[]},
          'meta': {
            'current_page': 1,
            'last_page': 1,
            'per_page': 20,
            'total': messageCount,
          },
        }),
      );
    }
    if (request.path == '/api/v1/chat/conversations/12/read') {
      return const AirmiusApiResponse(
        statusCode: 200,
        body: '{"data":{"read_count":1,"read_message_ids":[41]}}',
      );
    }
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}');
  }
}

class _MemoryPreferencesStore implements AirmiusPreferencesStore {
  final Map<String, String> _values = <String, String>{};

  @override
  Future<String?> readString(String key) async => _values[key];

  @override
  Future<void> writeString(String key, String value) async {
    _values[key] = value;
  }
}

class _FakePushTokenProvider implements AirmiusPushTokenProvider {
  _FakePushTokenProvider(this.token);

  String token;

  @override
  Future<AirmiusPushToken?> currentToken() async =>
      AirmiusPushToken(token: token, provider: 'fcm');

  @override
  Future<AirmiusPushToken?> requestToken() async =>
      AirmiusPushToken(token: token, provider: 'fcm');
}

class _MemorySecureSessionStorage implements AirmiusSecureSessionStorage {
  _MemorySecureSessionStorage({
    this.failWrites = false,
    this.failWriteCount = 0,
  });

  final bool failWrites;
  int failWriteCount;
  int deleteCount = 0;
  String? value;

  @override
  Future<void> delete({required String key}) async {
    deleteCount++;
    value = null;
  }

  @override
  Future<String?> read({required String key}) async => value;

  @override
  Future<void> write({required String key, required String value}) async {
    if (failWrites || failWriteCount > 0) {
      if (failWriteCount > 0) failWriteCount--;
      throw StateError('secure storage unavailable');
    }
    this.value = value;
  }
}
