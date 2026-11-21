import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/screens/club_role_permissions_screen.dart';
import 'package:airmius/widgets/airmius_widgets.dart';

void main() {
  for (final width in [320.0, 390.0, 900.0]) {
    for (final scale in [1.0, 1.6]) {
      testWidgets('club roles fit width $width and text scale $scale', (
        tester,
      ) async {
        tester.view.physicalSize = Size(width, 900);
        tester.view.devicePixelRatio = 1;
        addTearDown(tester.view.resetPhysicalSize);
        addTearDown(tester.view.resetDevicePixelRatio);
        await tester.pumpWidget(
          MaterialApp(
            builder: (context, child) => MediaQuery(
              data: MediaQuery.of(
                context,
              ).copyWith(textScaler: TextScaler.linear(scale)),
              child: child!,
            ),
            home: const ClubRolePermissionsScreen(),
          ),
        );
        await tester.pumpAndSettle();
        expect(tester.getSize(find.byType(AirmiusLogo)).width, 42);
        final title = tester.getRect(find.text('Vereinsrollen sauber steuern'));
        expect(title.width, greaterThan(180));
        expect(title.height, lessThan(250));
        final save = tester.getRect(find.text('Speichern'));
        expect(save.left, greaterThanOrEqualTo(0));
        expect(save.right, lessThanOrEqualTo(width));
        await tester.drag(
          find.byType(CustomScrollView),
          const Offset(0, -1600),
        );
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      });
    }
  }
}
