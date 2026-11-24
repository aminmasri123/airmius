import 'dart:convert';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/guest_marketplace_transport.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/marketplace_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _GuestServer extends AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  final product = <String, dynamic>{
    'id': 7,
    'title': 'Gast Trainingsshirt',
    'price_cents': 2500,
    'currency': 'EUR',
    'status': 'published',
    'category': 'equipment',
    'description': 'Für das Training',
    'availability': {'is_available': true},
  };

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    expect(request.path, startsWith('/api/v1/public/commerce/'));
    expect(request.headers.containsKey('Authorization'), false);
    Object data;
    if (request.path.endsWith('/checkout')) {
      expect(request.body!['guest_name'], 'Gast');
      expect(request.headers['Idempotency-Key'], isNotEmpty);
      data = {
        'id': 91,
        'guest_access_token': 'secret-token',
        'status': 'awaiting_transfer',
      };
    } else if (request.path.endsWith('/cart')) {
      final entries = request.body!['items'] as List;
      data = {
        'cart': {
          'items_count': entries.length,
          'items': [
            for (final item in entries)
              {
                'id': item['product_id'],
                'quantity': item['quantity'],
                'product': product,
                'line_total_cents': 2500 * (item['quantity'] as int),
              },
          ],
          'summary': {'amount_cents': 2500, 'currency': 'EUR'},
        },
        'payment_providers': ['bank_transfer'],
      };
    } else if (request.path.endsWith('/products')) {
      data = [product];
    } else if (request.path.endsWith('/reviews')) {
      data = [];
    } else {
      data = product;
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({'data': data}),
    );
  }
}

void main() {
  test(
    'guest cart and wishlist use public requests and guest checkout identity',
    () async {
      final server = _GuestServer();
      final client = AirmiusApiClient(
        transport: GuestMarketplaceTransport(server),
        baseUrl: 'https://example.test',
      );
      await client.commerceProducts();
      await client.addCommerceWishlist(7);
      final wishlist = await client.commerceWishlist();
      expect(wishlist['data'][0]['viewer']['is_wishlisted'], true);
      await client.addCommerceCartItem(7, quantity: 2);
      final cart = await client.commerceCart();
      expect(cart['data']['cart']['items'][0]['quantity'], 2);
      await client.updateCommerceCartItem(7, quantity: 3);
      await client.checkoutCommerceCart({
        'guest_name': 'Gast',
        'guest_email': 'gast@example.test',
      });
      expect((await client.commerceOrders())['data'][0]['id'], 91);
      expect((await client.commerceCart())['data']['cart']['items_count'], 0);
    },
  );

  testWidgets(
    'guest marketplace uses the shared product cards and product detail',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(900, 1200));
      addTearDown(() => tester.binding.setSurfaceSize(null));
      final server = _GuestServer();
      final services = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'https://example.test',
          enableOfflineQueue: false,
        ),
        transport: server,
        tokenStore: AirmiusMemoryTokenStore(),
      );
      addTearDown(services.authState.dispose);
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: services,
            child: const MaterialApp(home: MarketplaceScreen(guest: true)),
          ),
        ),
      );
      await tester.pumpAndSettle();
      expect(find.text('Gast Trainingsshirt'), findsWidgets);
      await tester.tap(find.text('Gast Trainingsshirt').first);
      await tester.pumpAndSettle();
      expect(
        server.requests.any((request) => request.path.endsWith('/products/7')),
        true,
      );
      expect(tester.takeException(), isNull);

      // Re-entering the marketplace keeps the guest cart in the app session.
      Navigator.of(tester.element(find.byType(DraggableScrollableSheet))).pop();
      await tester.pumpAndSettle();
      final client = AirmiusApiClient(
        transport: services.guestMarketplaceTransport,
        baseUrl: 'https://example.test',
      );
      await client.addCommerceCartItem(7);
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: services,
            child: MaterialApp(
              home: MarketplaceScreen(
                key: UniqueKey(),
                guest: true,
                initialSection: 'cart',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      final checkout = find.text('Sicher zur Kasse');
      await tester.ensureVisible(checkout);
      await tester.pumpAndSettle();
      await tester.tap(checkout);
      await tester.pumpAndSettle();
      for (final entry in {
        'Straße': 'Teststraße',
        'Nr.': '1',
        'PLZ': '10115',
        'Ort': 'Berlin',
        'Name': 'Gast',
        'E-Mail': 'gast@example.test',
      }.entries) {
        final field = find.byWidgetPredicate(
          (widget) =>
              widget is TextField && widget.decoration?.labelText == entry.key,
        );
        await tester.ensureVisible(field);
        await tester.pumpAndSettle();
        await tester.enterText(field, entry.value);
      }
      final terms = find.widgetWithText(
        CheckboxListTile,
        'AGB und Widerrufsbedingungen akzeptieren',
      );
      await tester.ensureVisible(terms);
      await tester.pumpAndSettle();
      await tester.tap(terms);
      final submit = find.text('Zahlungspflichtig bestellen');
      await tester.ensureVisible(submit);
      await tester.pumpAndSettle();
      await tester.tap(submit);
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 1));
      await tester.pumpAndSettle();
      expect(
        server.requests.any((request) => request.path.endsWith('/checkout')),
        true,
      );
      expect(tester.takeException(), isNull);
    },
  );
}
