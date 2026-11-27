import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/airmius_theme.dart';
import 'package:airmius/screens/feed_community_social_suite_screen.dart';
import 'package:airmius/screens/marketplace_screen.dart';
import 'package:airmius/screens/sponsors_center_screen.dart';
import 'package:airmius/widgets/airmius_ad_slot.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

class _AdTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  Map<String, dynamic>? ad = {
    'id': 17,
    'headline': 'Unser Verein wirbt',
    'primary_text': 'Sport gemeinsam erleben.',
    'cta_label': 'Verein entdecken',
    'creative_format': 'feed_square',
    'click_url': 'https://example.test/ads/17/click',
  };
  bool fail = false;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    final isAd = request.path.endsWith('/ads/active');
    return AirmiusApiResponse(
      statusCode: isAd && fail ? 503 : 200,
      body: jsonEncode(isAd ? ad : {'data': []}),
    );
  }
}

Future<void> _pump(
  WidgetTester tester,
  _AdTransport transport,
  Widget child,
) async {
  tester.view.physicalSize = const Size(390, 1800);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
  final services = AirmiusServiceContainer(
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
        container: services,
        child: MaterialApp(
          theme: AirmiusTheme.dark(),
          home: Scaffold(body: SingleChildScrollView(child: child)),
        ),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  for (final format in <String, double>{
    'feed_square': 1,
    'feed_portrait': 4 / 5,
    'story_vertical': 9 / 16,
    'banner_wide': 1200 / 628,
  }.entries) {
    testWidgets('${format.key} preserves its image proportions', (
      tester,
    ) async {
      final transport = _AdTransport();
      transport.ad!.addAll({
        'creative_format': format.key,
        'image_url': 'https://example.test/ad.jpg',
      });
      await _pump(tester, transport, const AirmiusAdSlot(placement: 'feed'));
      final imageBox = tester.widget<AspectRatio>(
        find.descendant(
          of: find.byType(AirmiusAdSlot),
          matching: find.byType(AspectRatio),
        ),
      );
      expect(imageBox.aspectRatio, format.value);
      expect(find.text('Unser Verein wirbt'), findsOneWidget);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    });
  }

  for (final placement in [
    'feed',
    'sidebar',
    'marketplace_card',
    'sponsor_section',
  ]) {
    testWidgets(
      '$placement renders a labelled ad and clears a paused delivery on refresh',
      (tester) async {
        final transport = _AdTransport();
        await _pump(tester, transport, AirmiusAdSlot(placement: placement));
        expect(find.text('Unser Verein wirbt'), findsOneWidget);
        expect(find.text('Gesponsert'), findsOneWidget);
        expect(find.text('Verein entdecken'), findsOneWidget);
        expect(transport.requests.single.query['placement'], placement);
        transport.ad = null;
        await tester.pump(const Duration(minutes: 1));
        await tester.pumpAndSettle();
        expect(find.text('Unser Verein wirbt'), findsNothing);
        await tester.pumpWidget(const SizedBox());
      },
    );
  }

  testWidgets(
    'expired responses never render; a live ad disappears at its end',
    (tester) async {
      final transport = _AdTransport();
      transport.ad!['ends_at'] = DateTime.now()
          .subtract(const Duration(seconds: 1))
          .toIso8601String();
      await _pump(tester, transport, const AirmiusAdSlot(placement: 'feed'));
      expect(find.text('Unser Verein wirbt'), findsNothing);
      await tester.pumpWidget(const SizedBox());
      transport.ad!['ends_at'] = DateTime.now()
          .add(const Duration(seconds: 3))
          .toIso8601String();
      await _pump(tester, transport, const AirmiusAdSlot(placement: 'feed'));
      expect(find.text('Unser Verein wirbt'), findsOneWidget);
      transport.ad = null;
      await tester.pump(const Duration(seconds: 4));
      await tester.pumpAndSettle();
      expect(find.text('Unser Verein wirbt'), findsNothing);
      await tester.pumpWidget(const SizedBox());
    },
  );

  testWidgets('ad errors leave surrounding page content usable', (
    tester,
  ) async {
    final transport = _AdTransport()..fail = true;
    await _pump(
      tester,
      transport,
      const Column(
        children: [
          Text('Mein Feed'),
          AirmiusAdSlot(placement: 'feed'),
        ],
      ),
    );
    expect(find.text('Mein Feed'), findsOneWidget);
    expect(find.text('Unser Verein wirbt'), findsNothing);
    expect(tester.takeException(), isNull);
    await tester.pumpWidget(const SizedBox());
  });

  for (final entry in <(Widget, List<String>)>[
    (const FeedCommunitySocialSuiteScreen(), ['feed', 'sidebar']),
    (const MarketplaceScreen(guest: true), ['marketplace_card']),
    (const SponsorsCenterScreen(), ['sponsor_section']),
  ]) {
    testWidgets('${entry.$1.runtimeType} loads its advertising placements', (
      tester,
    ) async {
      final transport = _AdTransport();
      // These screens already provide their own scrollable content.
      await _pump(tester, transport, SizedBox(height: 1400, child: entry.$1));
      final placements = transport.requests
          .where((r) => r.path.endsWith('/ads/active'))
          .map((r) => r.query['placement']);
      expect(placements, containsAll(entry.$2));
      expect(find.text('Unser Verein wirbt'), findsWidgets);
      expect(tester.takeException(), isNull);
      await tester.pumpWidget(const SizedBox());
    });
  }

  test(
    'signed-in delivery uses the bearer API rather than the guest endpoint',
    () async {
      final transport = _AdTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'test-token',
      );
      await client.publicActiveAd(placement: 'sidebar', device: 'mobile');
      expect(transport.requests.single.path, '/api/v1/ads/active');
      expect(
        transport.requests.single.headers['Authorization'],
        'Bearer test-token',
      );
      expect(
        transport.requests.single.headers['X-Airmius-Ad-Device'],
        'mobile',
      );
    },
  );
}
