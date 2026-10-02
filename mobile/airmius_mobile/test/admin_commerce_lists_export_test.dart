import 'dart:convert';
import 'dart:typed_data';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/admin_commerce_center_screen.dart';
import 'package:airmius/screens/admin_commerce_operations_screen.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

const _csv = '\uFEFForder_id;customer_email\r\n1;"\'=SUM(1;2)"\r\n';

class _Transport extends AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  int exportStatus = 200;
  String contentType = 'text/csv; charset=UTF-8';
  bool failNetwork = false;
  bool rejectFilters = false;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (rejectFilters && (request.query['products_search'] ?? '').isNotEmpty) {
      return const AirmiusApiResponse(
        statusCode: 422,
        body: '{"message":"Invalid filters"}',
      );
    }
    if (request.path.endsWith('/export')) {
      if (failNetwork) throw StateError('Network failure');
      return AirmiusApiResponse(
        statusCode: exportStatus,
        headers: {'Content-Type': contentType},
        body: exportStatus == 200 ? _csv : '{"message":"Export unavailable"}',
      );
    }
    final productsPage = int.parse(request.query['products_page'] ?? '1');
    final data = request.path.endsWith('/catalog')
        ? <String, dynamic>{
            'coupons': [],
            'addons': [],
            'tax_rates': [],
            'shipping_rates': [],
            'seller_applications': [
              {
                'id': 9,
                'business_name': 'Seller application',
                'status': 'pending',
                'user': {'email': 'seller@example.test'},
              },
            ],
            'website_requests': [
              {
                'id': 8,
                'club_name': 'Club website',
                'status': 'new',
                'guest_email': 'club@example.test',
              },
            ],
            'public_contact_requests': [
              {
                'id': 4,
                'name': 'Lead',
                'status': 'new',
                'subject': 'Lead subject',
              },
            ],
            'campaigns': [],
            'audit_logs': [
              {'id': 6, 'action': 'product.updated', 'note': 'Stock checked'},
            ],
            'pagination': {
              for (final key in [
                'coupons',
                'addons',
                'tax_rates',
                'shipping_rates',
                'seller_applications',
                'website_requests',
                'public_contact_requests',
                'campaigns',
                'audit_logs',
              ])
                key: {'current_page': 1, 'last_page': 2, 'total': 21},
            },
          }
        : <String, dynamic>{
            'products': {
              'data': [
                {
                  'id': productsPage,
                  'title': 'Product page $productsPage',
                  'status': 'draft',
                  'currency': 'EUR',
                  'price_cents': 100,
                },
              ],
            },
            'orders': {'data': []},
            'pagination': {
              'products': {
                'current_page': productsPage,
                'last_page': 2,
                'total': 21,
              },
              for (final key in [
                'orders',
                'return_requests',
                'payouts',
                'payout_profiles',
              ])
                key: {'current_page': 1, 'last_page': 2, 'total': 21},
            },
          };
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({'data': data}),
    );
  }
}

class _Picker extends FilePicker {
  Uint8List? savedBytes;
  String? savedName;
  bool cancel = false;
  bool fail = false;

  @override
  Future<String?> saveFile({
    String? dialogTitle,
    String? fileName,
    String? initialDirectory,
    FileType type = FileType.any,
    List<String>? allowedExtensions,
    Uint8List? bytes,
    bool lockParentWindow = false,
  }) async {
    if (fail) throw UnsupportedError('No picker');
    if (cancel) return null;
    savedBytes = bytes;
    savedName = fileName;
    expect(allowedExtensions, ['csv']);
    return '/selected/export.csv';
  }
}

Future<void> _mount(
  WidgetTester tester,
  _Transport transport, {
  bool legacy = false,
}) async {
  await tester.binding.setSurfaceSize(const Size(390, 844));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  final services = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
    tokenStore: AirmiusMemoryTokenStore(),
  );
  addTearDown(services.authState.dispose);
  await tester.pumpWidget(
    AirmiusScope(
      language: AirmiusLanguage.en,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: services,
        child: MaterialApp(
          home: legacy
              ? const AdminCommerceCenterScreen()
              : const AdminCommerceOperationsScreen(),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _tap(WidgetTester tester, Finder finder) async {
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
  await tester.tap(finder);
  await tester.pumpAndSettle();
}

Finder _key(String name) => find.byKey(ValueKey('commerce-$name'));

void main() {
  setUp(() => FilePicker.platform = _Picker());
  test(
    'CSV uses authenticated headers only and exports all filtered rows',
    () async {
      final transport = _Transport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'secret-token',
      );
      expect(
        await client.adminCommerceExportCsv(
          search: ' invoice ',
          status: 'completed',
        ),
        _csv,
      );
      final request = transport.requests.single;
      expect(request.path, '/api/v1/admin/commerce/export');
      expect(request.method, 'GET');
      expect(request.query, {
        'format': 'csv',
        'orders_search': 'invoice',
        'orders_status': 'completed',
      });
      expect(request.headers['Authorization'], 'Bearer secret-token');
      expect(request.headers['Accept'], 'text/csv');
      expect(request.query.toString(), isNot(contains('secret-token')));
      expect(request.path, isNot(contains('secret-token')));
    },
  );

  for (final status in [401, 403, 422, 500, 202]) {
    test('CSV rejects failed or queued response $status', () async {
      final transport = _Transport()..exportStatus = status;
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
      );
      await expectLater(
        client.adminCommerceExportCsv(),
        throwsA(isA<AirmiusApiException>()),
      );
    });
  }

  test(
    'CSV rejects HTML/JSON instead of saving a login page or queue response',
    () async {
      final transport = _Transport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
      );
      for (final contentType in ['text/html', 'application/json', '']) {
        transport.contentType = contentType;
        await expectLater(
          client.adminCommerceExportCsv(),
          throwsA(isA<AirmiusApiException>()),
        );
      }
      transport.failNetwork = true;
      await expectLater(
        client.adminCommerceExportCsv(),
        throwsA(isA<AirmiusApiException>()),
      );
    },
  );

  testWidgets(
    'legacy entrypoint has real pagination; search resets only its list',
    (tester) async {
      final transport = _Transport();
      await _mount(tester, transport, legacy: true);
      expect(find.byType(AdminCommerceOperationsScreen), findsOneWidget);
      expect(
        tester.widget<IconButton>(_key('products-previous')).onPressed,
        isNull,
      );
      await _tap(tester, _key('products-next'));
      expect(find.text('Product page 2'), findsOneWidget);
      expect(
        tester.widget<IconButton>(_key('products-next')).onPressed,
        isNull,
      );
      await tester.ensureVisible(_key('products-search'));
      await tester.enterText(_key('products-search'), 'needle');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(transport.requests.last.query['products_page'], '1');
      expect(transport.requests.last.query['products_search'], 'needle');
      await _tap(
        tester,
        find.byKey(const ValueKey('commerce-products-status-')),
      );
      await _tap(tester, find.text('published').last);
      expect(transport.requests.last.query['products_status'], 'published');
      expect(transport.requests.last.query['products_page'], '1');
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('each section exposes its server pagination and missing lists', (
    tester,
  ) async {
    final transport = _Transport();
    await _mount(tester, transport);
    for (final entry in <String, List<String>>{
      'Requests': ['public_contact_requests'],
      'Shipping & returns': ['orders', 'return_requests'],
      'Payouts': ['payouts', 'payout_profiles'],
      'Campaigns': ['campaigns'],
      'Seller applications': ['seller_applications', 'website_requests'],
      'Audit Timeline': ['audit_logs'],
    }.entries) {
      await _tap(tester, find.widgetWithText(ChoiceChip, entry.key));
      for (final list in entry.value) {
        expect(_key('$list-search'), findsOneWidget);
        await _tap(tester, _key('$list-next'));
        expect(transport.requests.last.query['${list}_page'], '2');
      }
    }
    expect(find.text('Stock checked'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'Unicode search validation never stores an oversized query and 422 has reset recovery',
    (tester) async {
      final transport = _Transport();
      await _mount(tester, transport);
      final search = _key('products-search');
      expect(tester.widget<TextField>(search).maxLength, 200);
      final before = transport.requests.length;
      await tester.ensureVisible(search);
      await tester.enterText(search, List.filled(110, 'a\u0301').join());
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(transport.requests.length, before);
      expect(find.text('Use at most 200 characters.'), findsOneWidget);
      transport.rejectFilters = true;
      await tester.enterText(search, 'needle');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      expect(find.text('Invalid filters'), findsOneWidget);
      await _tap(tester, find.byKey(const ValueKey('commerce-reset-filters')));
      expect(transport.requests.last.query, isEmpty);
      expect(tester.widget<TextField>(search).controller!.text, '');
      expect(find.text('Product page 1'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'CSV file save receives exact UTF8 bytes and current order filters',
    (tester) async {
      final transport = _Transport();
      final previousPicker = FilePicker.platform;
      final picker = _Picker();
      FilePicker.platform = picker;
      addTearDown(() => FilePicker.platform = previousPicker);
      await _mount(tester, transport);
      await _tap(tester, find.widgetWithText(ChoiceChip, 'Shipping & returns'));
      await tester.ensureVisible(_key('orders-search'));
      await tester.enterText(_key('orders-search'), 'invoice');
      await tester.testTextInput.receiveAction(TextInputAction.search);
      await tester.pumpAndSettle();
      await _tap(tester, find.byIcon(Icons.download_outlined));
      expect(picker.savedBytes, utf8.encode(_csv));
      expect(picker.savedName, startsWith('airmius-commerce-orders-'));
      expect(transport.requests.last.query['orders_search'], 'invoice');
      expect(transport.requests.last.query.containsKey('orders_page'), false);
      expect(tester.takeException(), isNull);
    },
  );

  for (final cancel in [true, false]) {
    testWidgets(
      'cancelled or forbidden export never reports a saved file; cancel: $cancel',
      (tester) async {
        final transport = _Transport()..exportStatus = cancel ? 200 : 403;
        final previousPicker = FilePicker.platform;
        final picker = _Picker()..cancel = true;
        FilePicker.platform = picker;
        addTearDown(() => FilePicker.platform = previousPicker);
        await _mount(tester, transport);
        await _tap(tester, find.byIcon(Icons.download_outlined));
        expect(picker.savedBytes, isNull);
        if (!cancel) expect(find.text('Export unavailable'), findsOneWidget);
        expect(tester.takeException(), isNull);
      },
    );
  }
}
