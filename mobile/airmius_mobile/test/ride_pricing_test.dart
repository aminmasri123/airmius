import 'dart:convert';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/carpool_center_screen.dart';
import 'package:airmius/screens/carpool_ride_screen.dart';
import 'package:airmius/widgets/ride_pricing.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class PricingTransport implements AirmiusApiTransport {
  PricingTransport({bool changed = false, this.mode = 'contribution'}) {
    ride = {
      'id': 12,
      'from': 'Kenitra',
      'to': 'Saragossa',
      'is_driver': false,
      'is_joined': changed,
      'can_join': !changed,
      'departure_time': '2027-02-01T10:00:00Z',
      'driver': {'id': 1, 'name': 'Fahrer'},
      'available_seats': 2,
      'participants_count': 1,
      'seats': 3,
      'visibility': 'public',
      'pending_requests': [],
      'users': [],
      'pricing': {
        'mode': mode,
        'amount': mode == 'free' ? null : '125.50',
        'amount_minor': mode == 'free' ? 0 : 12550,
        'currency': mode == 'free' ? null : 'MAD',
        'digits': 2,
        'version': 2,
        'requires_confirmation': changed,
        'agreed': {
          'mode': 'contribution',
          'amount': '10.00',
          'amount_minor': 1000,
          'currency': 'EUR',
          'digits': 2,
        },
      },
    };
  }
  final String mode;
  late Map<String, dynamic> ride;
  final calls = <AirmiusApiRequest>[];
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    calls.add(request);
    if (request.path.endsWith('/price/confirm')) {
      final pricing = Map<String, dynamic>.from(ride['pricing']);
      pricing['requires_confirmation'] = false;
      pricing['agreed'] = {...pricing}..remove('agreed');
      ride['pricing'] = pricing;
    }
    if (request.path.endsWith('/join')) {
      ride['can_join'] = false;
      ride['has_pending_request'] = true;
    }
    if (request.path.endsWith('/leave')) ride['is_joined'] = false;
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': request.path == '/api/v1/rides'
            ? {
                'rides': [ride],
                'clubs': [],
                'teams': [],
                'events': [],
                'pricing_catalog': {
                  'default_currency': 'MAD',
                  'currencies': {'EUR': 2, 'MAD': 2, 'JPY': 0, 'KWD': 3},
                },
              }
            : ride,
      }),
    );
  }
}

Future<void> mount(
  WidgetTester tester,
  Widget home,
  PricingTransport transport,
) async {
  tester.view.physicalSize = const Size(390, 844);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final container = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
  );
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.de,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: container,
        child: MaterialApp(home: home),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  for (final mode in ['free', 'contribution']) {
    testWidgets(
      'request shows $mode terms and requires explicit acknowledgement',
      (tester) async {
        final transport = PricingTransport(mode: mode);
        await mount(tester, const CarpoolCenterScreen(), transport);
        await tester.scrollUntilVisible(
          find.text('Kenitra → Saragossa'),
          350,
          scrollable: find.byType(Scrollable).first,
        );
        await tester.ensureVisible(find.text('Kenitra → Saragossa'));
        await tester.pumpAndSettle();
        await tester.tap(find.text('Kenitra → Saragossa'));
        await tester.pumpAndSettle();
        await tester.ensureVisible(find.text('Platz anfragen'));
        await tester.tap(find.text('Platz anfragen'));
        await tester.pumpAndSettle();
        final dialog = find.byType(AlertDialog);
        expect(
          find.descendant(
            of: dialog,
            matching: find.textContaining(mode == 'free' ? 'Kostenlos' : 'MAD'),
          ),
          findsOneWidget,
        );
        final send = find.descendant(
          of: dialog,
          matching: find.byType(FilledButton),
        );
        expect(tester.widget<FilledButton>(send).onPressed, isNull);
        expect(transport.calls.where((r) => r.method == 'POST'), isEmpty);
        final checkbox = find.descendant(
          of: dialog,
          matching: find.byType(Checkbox),
        );
        await tester.ensureVisible(checkbox);
        await tester.pumpAndSettle();
        await tester.tap(checkbox);
        await tester.pumpAndSettle();
        await tester.tap(send);
        await tester.pumpAndSettle();
        final request = transport.calls.firstWhere(
          (r) => r.path.endsWith('/join'),
        );
        expect(request.body?['price_version'], 2);
        expect(request.body?['price_acknowledged'], true);
      },
    );
  }

  testWidgets(
    'accepted passenger reviews old and new costs and confirms the current version',
    (tester) async {
      final transport = PricingTransport(changed: true);
      await mount(tester, const CarpoolRideScreen(rideId: 12), transport);
      expect(find.textContaining('EUR'), findsOneWidget);
      expect(find.textContaining('MAD'), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text('Neue Konditionen bestätigen'),
        350,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.text('Neue Konditionen bestätigen'));
      await tester.pumpAndSettle();
      final dialog = find.byType(AlertDialog);
      final confirm = find.descendant(
        of: dialog,
        matching: find.byType(FilledButton),
      );
      expect(tester.widget<FilledButton>(confirm).onPressed, isNull);
      final checkbox = find.descendant(
        of: dialog,
        matching: find.byType(Checkbox),
      );
      await tester.ensureVisible(checkbox);
      await tester.pumpAndSettle();
      await tester.tap(checkbox);
      await tester.pumpAndSettle();
      await tester.tap(confirm);
      await tester.pumpAndSettle();
      final request = transport.calls.firstWhere(
        (r) => r.path.endsWith('/price/confirm'),
      );
      expect(request.body, {'price_version': 2, 'price_acknowledged': true});
      expect(find.textContaining('EUR'), findsNothing);
      expect(find.text('Neue Konditionen bestätigen'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('declining changed costs withdraws participation', (
    tester,
  ) async {
    final transport = PricingTransport(changed: true);
    await mount(tester, const CarpoolRideScreen(rideId: 12), transport);
    await tester.scrollUntilVisible(
      find.text('Neue Konditionen bestätigen'),
      350,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Neue Konditionen bestätigen'));
    await tester.pumpAndSettle();
    await tester.tap(
      find.descendant(
        of: find.byType(AlertDialog),
        matching: find.text('Änderung ablehnen und Platz freigeben'),
      ),
    );
    await tester.pumpAndSettle();
    expect(
      transport.calls.where((r) => r.path.endsWith('/leave')),
      hasLength(1),
    );
    expect(
      transport.calls.where((r) => r.path.endsWith('/price/confirm')),
      isEmpty,
    );
    expect(find.byType(CarpoolCenterScreen), findsOneWidget);
  });

  testWidgets(
    'cost choice is mandatory and suggested MAD currency can be changed',
    (tester) async {
      final amount = TextEditingController();
      addTearDown(amount.dispose);
      String? mode, currency = 'MAD';
      final form = GlobalKey<FormState>();
      await mount(
        tester,
        StatefulBuilder(
          builder: (context, setState) => Scaffold(
            body: Form(
              key: form,
              child: ListView(
                children: [
                  RideCostEditor(
                    catalog: const {
                      'currencies': {'MAD': 2, 'EUR': 2, 'JPY': 0},
                    },
                    mode: mode,
                    currency: currency,
                    amount: amount,
                    onMode: (value) => setState(() => mode = value),
                    onCurrency: (value) => setState(() => currency = value),
                  ),
                ],
              ),
            ),
          ),
        ),
        PricingTransport(),
      );
      expect(form.currentState!.validate(), false);
      await tester.tap(find.byKey(const ValueKey('ride-cost-mode')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Kostenbeteiligung').last);
      await tester.pumpAndSettle();
      expect(find.text('MAD'), findsWidgets);
      await tester.enterText(
        find.byKey(const ValueKey('ride-cost-amount')),
        '12,50',
      );
      final currencyField = find.descendant(
        of: find.byKey(const ValueKey('ride-cost-currency')),
        matching: find.byType(TextField),
      );
      await tester.enterText(currencyField, 'JPY');
      await tester.pumpAndSettle();
      await tester.tap(find.text('JPY').last);
      await tester.pumpAndSettle();
      expect(currency, 'JPY');
      expect(form.currentState!.validate(), true);
    },
  );
}
