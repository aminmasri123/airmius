import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/widgets/club_money_accounts_overview.dart';

void main() {
  final accounts = [
    for (var i = 0; i < 5; i++)
      {
        'id': i + 1,
        'type': i < 2 ? 'cash' : 'bank',
        'name': 'Konto ${i + 1}',
        'balance_cents': 12500,
      },
  ];

  Future<void> mount(
    WidgetTester tester, {
    required Future<Map<String, dynamic>> Function() load,
    required Future<void> Function(bool) onOpen,
  }) async {
    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: ClubMoneyAccountsOverview(load: load, onOpen: onOpen),
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets(
    'five accounts are clear at 320px and create refreshes balances',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(320, 900));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      var loads = 0;
      bool? opened;
      await mount(
        tester,
        load: () async {
          loads++;
          return {'available': true, 'can_manage': true, 'accounts': accounts};
        },
        onOpen: (create) async => opened = create,
      );
      expect(find.text('Barkassen: 2'), findsOneWidget);
      expect(find.text('Bankkonten: 3'), findsOneWidget);
      expect(find.text('125.00 EUR'), findsNWidgets(5));
      final create = find.text('Kasse oder Bankkonto anlegen');
      await tester.ensureVisible(create);
      await tester.tap(create);
      await tester.pumpAndSettle();
      expect(opened, true);
      expect(loads, 2);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('read-only finance access cannot create accounts', (
    tester,
  ) async {
    bool? opened;
    await mount(
      tester,
      load: () async => {
        'available': true,
        'can_manage': false,
        'accounts': accounts.take(1).toList(),
      },
      onOpen: (create) async => opened = create,
    );
    expect(find.text('Kasse oder Bankkonto anlegen'), findsNothing);
    await tester.tap(find.text('Kassen und Bankkonten verwalten'));
    await tester.pumpAndSettle();
    expect(opened, false);
    expect(find.text('Konto 2'), findsNothing);
  });

  testWidgets('empty state exposes creation and errors offer a retry', (
    tester,
  ) async {
    var attempts = 0;
    await mount(
      tester,
      load: () async {
        if (attempts++ == 0) throw Exception('offline');
        return {'available': true, 'can_manage': true, 'accounts': []};
      },
      onOpen: (_) async {},
    );
    expect(find.text('Kasse oder Bankkonto anlegen'), findsNothing);
    await tester.tap(find.text('Erneut versuchen'));
    await tester.pumpAndSettle();
    expect(
      find.text('Noch keine Kasse oder kein Bankkonto angelegt.'),
      findsOneWidget,
    );
    expect(find.text('Kasse oder Bankkonto anlegen'), findsOneWidget);
  });
}
