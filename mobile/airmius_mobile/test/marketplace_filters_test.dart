import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/marketplace_filters.dart';
import 'package:airmius/core/marketplace_filter_labels.dart';
import 'package:airmius/screens/marketplace_screen.dart';

void main() {
  test(
    'prices preserve cents, decimal comma and Arabic input without rounding',
    () {
      expect(MarketplaceFilters.parsePrice(''), isNull);
      expect(MarketplaceFilters.parsePrice('0'), 0);
      expect(MarketplaceFilters.parsePrice('12,50'), 1250);
      expect(MarketplaceFilters.parsePrice('12.5'), 1250);
      expect(MarketplaceFilters.parsePrice('٢٥٫٥٠'), 2550);
      for (final invalid in ['-1', '1,234', '12 EUR', '1.2.3']) {
        expect(
          () => MarketplaceFilters.parsePrice(invalid),
          throwsFormatException,
        );
      }
      expect(
        marketplaceFilterLabels.values.map((m) => m.keys.toSet()),
        everyElement(marketplaceFilterLabels['de']!.keys.toSet()),
      );
    },
  );

  testWidgets(
    'filter button opens real filters, validates ranges and applies to every page',
    (tester) async {
      final transport = _CatalogTransport();
      await _mount(tester, transport);
      await tester.tap(find.byTooltip('Filter'));
      await tester.pumpAndSettle();
      expect(find.text('Filter & Sortierung'), findsOneWidget);
      await tester.enterText(
        find.byKey(const ValueKey('market-price-min')),
        '50',
      );
      await tester.enterText(
        find.byKey(const ValueKey('market-price-max')),
        '10',
      );
      await tester.tap(find.byKey(const ValueKey('market-apply-filters')));
      await tester.pumpAndSettle();
      expect(
        find.text(
          'Der Höchstpreis muss mindestens dem Mindestpreis entsprechen.',
        ),
        findsOneWidget,
      );
      expect(transport.catalogRequests, hasLength(1));
      await tester.enterText(
        find.byKey(const ValueKey('market-price-min')),
        '10,50',
      );
      await tester.enterText(
        find.byKey(const ValueKey('market-price-max')),
        '50',
      );
      await tester.ensureVisible(
        find.byKey(const ValueKey('market-rating-4.0')),
      );
      await tester.tap(find.byKey(const ValueKey('market-rating-4.0')));
      await tester.ensureVisible(find.byKey(const ValueKey('sort-newest')));
      await tester.tap(find.byKey(const ValueKey('sort-newest')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Preis: aufsteigend').last);
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const ValueKey('category-')));
      await tester.tap(find.byKey(const ValueKey('category-')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sportgeräte & Equipment').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const ValueKey('market-apply-filters')));
      await tester.pumpAndSettle();
      expect(
        transport.catalogRequests.last.query,
        containsPair('min_price_cents', '1050'),
      );
      expect(
        transport.catalogRequests.last.query,
        containsPair('max_price_cents', '5000'),
      );
      expect(
        transport.catalogRequests.last.query,
        containsPair('min_rating', '4.0'),
      );
      expect(
        transport.catalogRequests.last.query,
        containsPair('category', 'equipment'),
      );
      expect(
        transport.catalogRequests.last.query,
        containsPair('sort', 'price_asc'),
      );
      await tester.scrollUntilVisible(
        find.text('Mehr Produkte laden'),
        350,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.ensureVisible(find.text('Mehr Produkte laden'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Mehr Produkte laden'));
      await tester.pumpAndSettle();
      expect(transport.catalogRequests.last.query, containsPair('page', '2'));
      expect(
        transport.catalogRequests.last.query,
        containsPair('min_price_cents', '1050'),
      );
      expect(
        transport.catalogRequests.last.query,
        containsPair('sort', 'price_asc'),
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'dismissed draft does not change applied filters and reset clears them',
    (tester) async {
      final transport = _CatalogTransport();
      await _mount(tester, transport);
      await tester.tap(find.byTooltip('Filter'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('market-price-max')),
        '25',
      );
      await tester.tap(find.byTooltip('Schließen'));
      await tester.pumpAndSettle();
      expect(transport.catalogRequests, hasLength(1));
      await tester.tap(find.byTooltip('Filter'));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<TextFormField>(
              find.byKey(const ValueKey('market-price-max')),
            )
            .controller!
            .text,
        isEmpty,
      );
      await tester.enterText(
        find.byKey(const ValueKey('market-price-max')),
        '25',
      );
      await tester.tap(find.byKey(const ValueKey('market-apply-filters')));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Filter'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Zurücksetzen'));
      await tester.pumpAndSettle();
      expect(
        tester
            .widget<TextFormField>(
              find.byKey(const ValueKey('market-price-max')),
            )
            .controller!
            .text,
        isEmpty,
      );
      await tester.tap(find.byTooltip('Schließen'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Alle Filter zurücksetzen'));
      await tester.pumpAndSettle();
      expect(
        transport.catalogRequests.last.query.containsKey('max_price_cents'),
        isFalse,
      );
      expect(find.text('Alle Filter zurücksetzen'), findsNothing);
      await tester.tap(find.byTooltip('Bereiche'));
      await tester.pumpAndSettle();
      expect(find.text('Filter & Sortierung'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'older search response cannot replace newer results or remove keyboard focus',
    (tester) async {
      final transport = _CatalogTransport();
      await _mount(tester, transport);
      final search = find.byType(TextField).first;
      await tester.enterText(search, 'slow');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.enterText(search, 'new');
      await tester.pump(const Duration(milliseconds: 400));
      await tester.pumpAndSettle();
      expect(find.text('New result'), findsWidgets);
      expect(tester.testTextInput.isVisible, isTrue);
      transport.slow.complete(_CatalogTransport.products('Old result'));
      await tester.pumpAndSettle();
      expect(find.text('Old result'), findsNothing);
      expect(find.text('New result'), findsWidgets);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'a failed catalog refresh can be retried without losing the price filter',
    (tester) async {
      final transport = _CatalogTransport();
      await _mount(tester, transport);
      await tester.tap(find.byTooltip('Filter'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const ValueKey('market-price-max')),
        '25',
      );
      transport.failNext = true;
      await tester.tap(find.byKey(const ValueKey('market-apply-filters')));
      await tester.pumpAndSettle();
      expect(
        find.text(
          'Die Produkte konnten nicht geladen werden. Bitte versuche es erneut.',
        ),
        findsOneWidget,
      );
      await tester.tap(find.text('Erneut versuchen'));
      await tester.pumpAndSettle();
      expect(
        transport.catalogRequests.last.query,
        containsPair('max_price_cents', '2500'),
      );
      expect(
        find.text(
          'Die Produkte konnten nicht geladen werden. Bitte versuche es erneut.',
        ),
        findsNothing,
      );
      expect(tester.takeException(), isNull);
    },
  );

  for (final language in [
    AirmiusLanguage.de,
    AirmiusLanguage.fr,
    AirmiusLanguage.ar,
  ]) {
    testWidgets(
      'filters fit a narrow phone with large text and keyboard: $language',
      (tester) async {
        await _mount(
          tester,
          _CatalogTransport(),
          language: language,
          size: language == AirmiusLanguage.de
              ? const Size(390, 844)
              : const Size(320, 700),
          scale: language == AirmiusLanguage.de ? 1 : 1.4,
        );
        final words = marketplaceFilterLabels[language.locale.languageCode]!;
        await tester.tap(find.byTooltip(words['filter']!));
        await tester.pumpAndSettle();
        expect(
          find.byKey(const ValueKey('market-apply-filters')),
          findsOneWidget,
        );
        expect(tester.takeException(), isNull);
        if (const bool.fromEnvironment('MARKETPLACE_CAPTURE')) {
          await tester.runAsync(() async {
            final boundary = tester.renderObject<RenderRepaintBoundary>(
              find.byKey(const ValueKey('market-filter-canvas')),
            );
            final image = await boundary.toImage(pixelRatio: 1.5);
            final bytes = await image.toByteData(
              format: ui.ImageByteFormat.png,
            );
            await File(
              '../../tmp/marketplace-filters-${language.locale.languageCode}.png',
            ).writeAsBytes(bytes!.buffer.asUint8List());
            image.dispose();
          });
        }
        tester.view.viewInsets = const FakeViewPadding(bottom: 260);
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        await tester.enterText(
          find.byKey(const ValueKey('market-price-min')),
          language == AirmiusLanguage.ar ? '١٠٫٥٠' : '10,50',
        );
        await tester.tap(find.byKey(const ValueKey('market-apply-filters')));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      },
    );
  }
}

Future<void> _mount(
  WidgetTester tester,
  _CatalogTransport transport, {
  AirmiusLanguage language = AirmiusLanguage.de,
  Size size = const Size(390, 844),
  double scale = 1,
}) async {
  if (const bool.fromEnvironment('MARKETPLACE_CAPTURE')) {
    await tester.runAsync(() async {
      final regular = await File(
        '${Platform.environment['WINDIR'] ?? 'C:/Windows'}/Fonts/arial.ttf',
      ).readAsBytes();
      await (FontLoader(
        'MarketplacePreview',
      )..addFont(Future.value(ByteData.sublistView(regular)))).load();
      final icons = await File(
        '${Platform.environment['FLUTTER_ROOT']}/bin/cache/artifacts/material_fonts/MaterialIcons-Regular.otf',
      ).readAsBytes();
      await (FontLoader(
        'MaterialIcons',
      )..addFont(Future.value(ByteData.sublistView(icons)))).load();
    });
  }
  tester.view.devicePixelRatio = 1;
  tester.view.physicalSize = size;
  addTearDown(() {
    tester.view.resetPhysicalSize();
    tester.view.resetDevicePixelRatio();
    tester.view.resetViewInsets();
  });
  final services = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://example.test',
      enableOfflineQueue: false,
    ),
    transport: transport,
  );
  await tester.pumpWidget(
    AirmiusScope(
      language: language,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: services,
        child: RepaintBoundary(
          key: const ValueKey('market-filter-canvas'),
          child: MaterialApp(
            debugShowCheckedModeBanner: false,
            theme: ThemeData(
              fontFamily: const bool.fromEnvironment('MARKETPLACE_CAPTURE')
                  ? 'MarketplacePreview'
                  : null,
            ),
            builder: (context, child) => Directionality(
              textDirection: language == AirmiusLanguage.ar
                  ? TextDirection.rtl
                  : TextDirection.ltr,
              child: MediaQuery(
                data: MediaQuery.of(
                  context,
                ).copyWith(textScaler: TextScaler.linear(scale)),
                child: child!,
              ),
            ),
            home: const MarketplaceScreen(),
          ),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

class _CatalogTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  final slow = Completer<AirmiusApiResponse>();
  bool failNext = false;
  List<AirmiusApiRequest> get catalogRequests =>
      requests.where((r) => r.path == '/api/v1/commerce/products').toList();

  static AirmiusApiResponse products(String title, {int page = 1}) =>
      AirmiusApiResponse(
        statusCode: 200,
        body: jsonEncode({
          'data': [
            {
              'id': page,
              'title': title,
              'price_cents': 2000,
              'currency': 'EUR',
              'category': 'equipment',
            },
          ],
          'meta': {
            'total': 2,
            'last_page': 2,
            'current_page': page,
            'filters': {
              'categories': ['course', 'equipment'],
            },
          },
        }),
      );

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.path == '/api/v1/commerce/products') {
      if (failNext) {
        failNext = false;
        return const AirmiusApiResponse(statusCode: 500, body: '{}');
      }
      if (request.query['search'] == 'slow') return slow.future;
      return products(
        request.query['search'] == 'new' ? 'New result' : 'Training product',
        page: int.parse(request.query['page'] ?? '1'),
      );
    }
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":[]}');
  }
}
