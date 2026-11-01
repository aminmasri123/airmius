import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/widgets/admin_two_factor_notice.dart';

void main() {
  for (final role in ['super_admin', 'admin', 'system_admin', 'player']) {
    testWidgets('$role sees setup notice only when required', (tester) async {
      for (final enabled in [false, true]) {
        await tester.pumpWidget(
          AirmiusScope(
            language: AirmiusLanguage.de,
            setLanguage: (_) {},
            child: MaterialApp(
              home: Scaffold(
                body: AdminTwoFactorNotice(
                  user: AirmiusUser(
                    id: 1,
                    name: 'Test',
                    email: 'test@example.test',
                    role: role,
                    twoFactorEnabled: enabled,
                  ),
                ),
              ),
            ),
          ),
        );
        expect(
          find.text('Adminrolle aktiv – Sicherheitseinrichtung fehlt'),
          !enabled && role != 'player' ? findsOneWidget : findsNothing,
        );
        expect(tester.takeException(), isNull);
      }
    });
  }
}
