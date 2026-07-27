import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_preferences_store_base.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/auth_flows_screen.dart';
import 'package:airmius/screens/auth_recovery_security_screen.dart';
import 'package:airmius/screens/account_operations_screen.dart';

void main() {
  testWidgets('auth recovery quick actions open real protected flows', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(800, 2200);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final container = _testContainer();

    await tester.pumpWidget(
      AirmiusServicesScope(
        container: container,
        child: AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: const MaterialApp(home: AuthRecoverySecurityScreen()),
        ),
      ),
    );

    await tester.scrollUntilVisible(
      find.text('Passwort-Hilfe'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Passwort-Hilfe'));
    await tester.pumpAndSettle();
    expect(find.text('Passwort sicher zurücksetzen'), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('E-Mail erneut senden'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('E-Mail erneut senden'));
    await tester.pumpAndSettle();
    expect(find.text('E-Mail bestätigen'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('auth flow exposes the localized security overview', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(1000, 1800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      AirmiusServicesScope(
        container: _testContainer(),
        child: AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: const MaterialApp(home: AuthFlowsScreen()),
        ),
      ),
    );

    await tester.scrollUntilVisible(
      find.text('Sicherheitsübersicht öffnen'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Sicherheitsübersicht öffnen'));
    await tester.pumpAndSettle();

    expect(find.text('Sicherheitsübersicht'), findsOneWidget);
    expect(find.text('KONTO-SICHERHEIT'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('security overview stays localized and readable in Arabic RTL', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 1400);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      AirmiusServicesScope(
        container: _testContainer(),
        child: AirmiusScope(
          language: AirmiusLanguage.ar,
          setLanguage: (_) {},
          child: const MaterialApp(home: AuthRecoverySecurityScreen()),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('نظرة عامة على الأمان'), findsOneWidget);
    expect(find.text('أمان الحساب'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets('account operations honor the requested delete section', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(390, 900);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      AirmiusServicesScope(
        container: _testContainer(),
        child: AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: const MaterialApp(
            home: AccountOperationsScreen(initialTab: 'Delete'),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    await tester.pump(const Duration(milliseconds: 350));

    final scrollable = tester.state<ScrollableState>(
      find.byType(Scrollable).first,
    );
    expect(scrollable.position.pixels, greaterThan(0));
    expect(find.text('Konto löschen'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

AirmiusServiceContainer _testContainer() => AirmiusServiceContainer(
  environment: const AirmiusAppEnvironment(
    apiBaseUrl: 'https://airmius.test',
    enableOfflineQueue: false,
  ),
  transport: const AirmiusStaticTransport({}),
  tokenStore: AirmiusMemoryTokenStore(),
  pushDeviceStore: _MemoryPreferencesStore(),
);

class _MemoryPreferencesStore implements AirmiusPreferencesStore {
  final Map<String, String> _values = <String, String>{};

  @override
  Future<String?> readString(String key) async => _values[key];

  @override
  Future<void> writeString(String key, String value) async {
    _values[key] = value;
  }
}
