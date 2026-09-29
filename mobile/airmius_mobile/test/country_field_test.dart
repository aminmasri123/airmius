import 'dart:convert';
import 'dart:io';
import 'package:airmius/widgets/country_field.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('long country names fit on a small screen with large text', (tester) async {
    tester.view.physicalSize = const Size(360, 800);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(MaterialApp(
      locale: const Locale('de'),
      supportedLocales: const [Locale('de')],
      localizationsDelegates: GlobalMaterialLocalizations.delegates,
      home: MediaQuery(data: const MediaQueryData(size: Size(360, 800), textScaler: TextScaler.linear(1.5)),
        child: const Scaffold(body: Padding(padding: EdgeInsets.all(16),
          child: CountryField(value: 'GS', label: 'Land')))),
    ));
    await tester.pumpAndSettle();
    expect(find.text('Südgeorgien und die Südlichen Sandwichinseln'), findsOneWidget);
    await tester.tap(find.text('Südgeorgien und die Südlichen Sandwichinseln'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
  testWidgets(
    'country names, code search and selection preserve two-letter values',
    (tester) async {
      final controller = TextEditingController(text: 'DE');
      await tester.pumpWidget(
        MaterialApp(
          locale: const Locale('de'),
          supportedLocales: const [Locale('de'), Locale('en')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          home: Scaffold(
            body: CountryField(controller: controller, label: 'Land'),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Deutschland'), findsOneWidget);
      await tester.tap(find.text('Deutschland'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'JP');
      await tester.pumpAndSettle();
      expect(find.text('Japan'), findsOneWidget);
      await tester.tap(find.text('Japan'));
      await tester.pumpAndSettle();
      expect(controller.text, 'JP');
      expect(find.text('Japan'), findsOneWidget);
      await tester.tap(find.text('Japan'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'nicht-vorhanden');
      await tester.pumpAndSettle();
      expect(find.text('Kein Land gefunden'), findsOneWidget);
      expect(find.text('Land ergänzen'), findsNothing);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
      controller.dispose();
    },
  );

  test('all countries are bundled for offline use', () async {
    final rows =
        jsonDecode(File('assets/data/countries.json').readAsStringSync())
            as List;
    expect(rows.length, 250);
    expect(rows.map((row) => row['code']).toSet().length, 250);
    expect(rows.any((row) => row['code'] == 'XK'), isTrue);
  });
}
