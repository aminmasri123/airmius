import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/account_management_screen.dart';
import 'package:airmius/screens/club_deletion_screen.dart';
import 'package:airmius/screens/deletion_guide_screen.dart';
import 'package:airmius/screens/guest_portal_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:intl/date_symbol_data_local.dart';

class _GuideFixtureBundle extends CachingAssetBundle {
  @override
  Future<ByteData> load(String key) {
    final match = RegExp(
      r'(account|club)_(de|en|fr|ar)\.json$',
    ).firstMatch(key);
    if (match == null) throw ArgumentError.value(key, 'key');
    final kind = match.group(1)!;
    final locale = match.group(2)!;
    final firstSection = switch ((kind, locale)) {
      ('account', 'de') => 'Kontolöschung anfordern',
      ('account', 'en') => 'Request Account Deletion',
      ('account', 'fr') => 'Demande de suppression du compte',
      ('account', 'ar') => 'طلب حذف الحساب',
      ('club', 'de') => 'Zwei getrennte Vorgänge',
      ('club', 'en') => 'Two separate processes',
      ('club', 'fr') => 'Deux procédures distinctes',
      _ => 'إجراءان منفصلان',
    };
    final data = jsonEncode({
      'title': 'Bundled $kind $locale',
      'contentLocale': locale,
      'translationComplete': true,
      'sections': List.generate(
        kind == 'club' ? 7 : 8,
        (index) => {
          'title': index == 0 ? firstSection : 'Section $index',
          'body': ['Paragraph $index'],
        },
      ),
    });
    return SynchronousFuture(
      ByteData.sublistView(Uint8List.fromList(utf8.encode(data))),
    );
  }
}

class _MissingGuideBundle extends CachingAssetBundle {
  @override
  Future<ByteData> load(String key) => Future.error(StateError('Missing $key'));
}

Widget _guideApp(Widget home, {AssetBundle? bundle}) => MaterialApp(
  builder: (_, child) => DefaultAssetBundle(
    bundle: bundle ?? _GuideFixtureBundle(),
    child: child!,
  ),
  home: home,
);

Future<void> _settleBundledGuide(WidgetTester tester) async {
  for (var attempt = 0; attempt < 10; attempt++) {
    await tester.runAsync(
      () => Future<void>.delayed(const Duration(milliseconds: 30)),
    );
    await tester.pump();
    if (find.byType(ListView).evaluate().isNotEmpty) return;
  }
  fail('Bundled deletion guide did not finish loading.');
}

void main() {
  setUpAll(() => initializeDateFormatting('de'));

  testWidgets(
    'confirmation gates scheduling; owner can then cancel on a small screen',
    (tester) async {
      tester.view.physicalSize = const Size(360, 740);
      tester.view.devicePixelRatio = 1;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      final transport = _DeletionTransport();
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
            child: const MaterialApp(
              home: ClubDeletionScreen(
                clubId: 7,
                clubName: 'Sportverein Beispiel',
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('Passwort bestätigen'),
        180,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.enterText(find.byType(TextField).first, 'vereins-passwort');
      await tester.pumpAndSettle();
      final sendCode = find.text('E-Mail-Code anfordern');
      await tester.ensureVisible(sendCode);
      await tester.tap(sendCode);
      await tester.pumpAndSettle();
      expect(transport.requests.last.path, '/api/v1/clubs/7/deletion-code');

      final buttonText = find.text('Löschung in 30 Tagen vormerken');
      await tester.scrollUntilVisible(
        buttonText,
        180,
        scrollable: find.byType(Scrollable).first,
      );
      final button = find.ancestor(
        of: buttonText,
        matching: find.byWidgetPredicate((widget) => widget is FilledButton),
      );
      expect(tester.widget<FilledButton>(button).onPressed, isNull);
      await tester.enterText(find.byType(TextField).at(1), '123456');
      await tester.enterText(
        find.byType(TextField).at(2),
        'Ja, ich bin mir sicher',
      );
      await tester.pumpAndSettle();
      await tester.pump(const Duration(seconds: 5));
      await tester.pumpAndSettle();
      await tester.ensureVisible(button);
      await tester.tap(button);
      await tester.pumpAndSettle();
      expect(
        transport.requests.where((r) => r.method == 'DELETE').single.path,
        '/api/v1/clubs/7',
      );
      expect(find.text('Löschung vorgemerkt'), findsOneWidget);
      final cancel = find.text('Löschung zurücknehmen');
      await tester.scrollUntilVisible(
        cancel,
        180,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(cancel);
      await tester.pumpAndSettle();
      expect(transport.requests.last.path, '/api/v1/clubs/7/deletion');
      expect(find.byType(TextField), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  test(
    'deletion requests and cancellations are never queued offline',
    () async {
      final transport = AirmiusQueuedTransport(
        inner: _DeletionTransport(),
        timeout: const Duration(seconds: 5),
      )..offline = true;
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
      );
      await expectLater(
        client.deleteClub(7, confirmation: 'Ja, ich bin mir sicher'),
        throwsA(isA<AirmiusApiException>()),
      );
      await expectLater(
        client.requestClubDeletionCode(7, 'vereins-passwort'),
        throwsA(isA<AirmiusApiException>()),
      );
      await expectLater(
        client.requestClubDeletionReview(7),
        throwsA(isA<AirmiusApiException>()),
      );
      await expectLater(
        client.confirmClubDeletionArchive(7),
        throwsA(isA<AirmiusApiException>()),
      );
      await expectLater(
        client.cancelClubDeletion(7),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(transport.queue, isEmpty);
    },
  );

  testWidgets('blocked owner can request one visible support review', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 740);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final transport = _DeletionTransport()..blocker = 'Buchhaltungsunterlagen';
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
          child: const MaterialApp(
            home: ClubDeletionScreen(
              clubId: 7,
              clubName: 'Sportverein Beispiel',
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();
    final review = find.text('Prüfung beim Support anfordern');
    await tester.scrollUntilVisible(
      review,
      180,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(review);
    await tester.pumpAndSettle();
    expect(transport.requests.last.path, '/api/v1/clubs/7/deletion-review');
    expect(
      find.text(
        'Prüfung angefordert. Der Verein wird dadurch noch nicht gelöscht.',
      ),
      findsOneWidget,
    );
    expect(find.text('Prüfung beim Support anfordern'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets('deletion guides render for guests without a session', (
    tester,
  ) async {
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'not-a-url',
        enableOfflineQueue: false,
      ),
      transport: _DeletionTransport(),
    );

    for (final kind in DeletionGuideKind.values) {
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: container,
            child: _guideApp(DeletionGuideScreen(kind: kind)),
          ),
        ),
      );
      await _settleBundledGuide(tester);
      expect(
        find.text(
          kind == DeletionGuideKind.club ? 'Verein löschen' : 'Konto löschen',
        ),
        findsWidgets,
      );
      expect(
        find.text(
          'Diese Information ist öffentlich. Das Lesen löst keine Löschung aus.',
        ),
        findsOneWidget,
      );
      expect(find.text('Kontakt für Löschanfragen'), findsOneWidget);
      expect(
        find.text(
          kind == DeletionGuideKind.club
              ? 'Zwei getrennte Vorgänge'
              : 'Kontolöschung anfordern',
        ),
        findsOneWidget,
        reason: tester
            .widgetList<Text>(find.byType(Text))
            .map((text) => text.data)
            .toList()
            .toString(),
      );
      expect(tester.takeException(), isNull);
    }
  });

  testWidgets(
    'public deletion guide loads localized server content without login',
    (tester) async {
      final requests = <Uri>[];
      final container = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'https://example.test/api/v1',
          enableOfflineQueue: false,
        ),
        transport: _DeletionTransport(),
      );

      await http.runWithClient(
        () async {
          await tester.pumpWidget(
            AirmiusScope(
              language: AirmiusLanguage.fr,
              setLanguage: (_) {},
              child: AirmiusServicesScope(
                container: container,
                child: _guideApp(
                  const DeletionGuideScreen(kind: DeletionGuideKind.account),
                ),
              ),
            ),
          );
          await tester.pumpAndSettle();
        },
        () => MockClient((request) async {
          requests.add(request.url);
          return http.Response(
            jsonEncode({
              'title': 'Suppression du compte',
              'contentLocale': 'fr',
              'translationComplete': true,
              'sections': List.generate(
                8,
                (index) => {
                  'title': index == 0 ? 'Sauvegardes' : 'Section $index',
                  'body': [
                    index == 0
                        ? 'Les sauvegardes expirent séparément.'
                        : 'Paragraphe $index.',
                  ],
                },
              ),
              'note': 'Information publique',
            }),
            200,
          );
        }),
      );

      expect(requests, hasLength(1));
      expect(requests.single.path, '/konto-loeschen');
      expect(requests.single.queryParameters, {
        'locale': 'fr',
        'format': 'json',
      });
      expect(find.text('Suppression du compte'), findsOneWidget);
      expect(find.text('Sauvegardes'), findsOneWidget);
      expect(find.text('Les sauvegardes expirent séparément.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('incomplete or wrong-language guide is not shown as current', (
    tester,
  ) async {
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test/api/v1',
        enableOfflineQueue: false,
      ),
      transport: _DeletionTransport(),
    );

    await http.runWithClient(
      () async {
        await tester.pumpWidget(
          AirmiusScope(
            language: AirmiusLanguage.fr,
            setLanguage: (_) {},
            child: AirmiusServicesScope(
              container: container,
              child: _guideApp(
                const DeletionGuideScreen(kind: DeletionGuideKind.account),
              ),
            ),
          ),
        );
        await _settleBundledGuide(tester);
      },
      () => MockClient(
        (_) async => http.Response(
          jsonEncode({
            'title': 'Incorrect English guide',
            'contentLocale': 'en',
            'translationComplete': true,
            'sections': List.generate(
              8,
              (index) => {
                'title': 'Section $index',
                'body': ['Paragraph $index.'],
              },
            ),
          }),
          200,
        ),
      ),
    );

    expect(find.text('Incorrect English guide'), findsNothing);
    expect(
      find.text('Demande de suppression du compte'),
      findsOneWidget,
      reason: tester
          .widgetList<Text>(find.byType(Text))
          .map((text) => text.data)
          .toList()
          .toString(),
    );
    expect(
      find.text(
        "La version actuelle est indisponible. Cette copie complète est intégrée à l'application et peut être obsolète.",
      ),
      findsOneWidget,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('offline guide shows its full bundled form and can retry', (
    tester,
  ) async {
    var attempts = 0;
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://example.test/api/v1',
        enableOfflineQueue: false,
      ),
      transport: _DeletionTransport(),
    );

    await http.runWithClient(
      () async {
        await tester.pumpWidget(
          AirmiusScope(
            language: AirmiusLanguage.de,
            setLanguage: (_) {},
            child: AirmiusServicesScope(
              container: container,
              child: _guideApp(
                const DeletionGuideScreen(kind: DeletionGuideKind.club),
              ),
            ),
          ),
        );
        await _settleBundledGuide(tester);
        expect(
          find.text(
            'Die aktuelle Fassung ist gerade nicht erreichbar. Du siehst die vollständige, in der App gespeicherte Fassung; sie kann inzwischen veraltet sein.',
          ),
          findsOneWidget,
        );
        await tester.tap(find.text('Erneut laden'));
        await tester.pumpAndSettle();
      },
      () => MockClient((_) async {
        attempts++;
        if (attempts == 1) return http.Response('Unavailable', 503);
        return http.Response(
          jsonEncode({
            'title': 'Verein löschen',
            'contentLocale': 'de',
            'translationComplete': true,
            'sections': List.generate(
              7,
              (index) => {
                'title': index == 0
                    ? 'Vollständige Fassung'
                    : 'Abschnitt $index',
                'body': [
                  index == 0
                      ? 'Aktueller öffentlicher Inhalt.'
                      : 'Absatz $index.',
                ],
              },
            ),
          }),
          200,
        );
      }),
    );

    expect(attempts, 2);
    expect(find.text('Vollständige Fassung'), findsOneWidget);
    expect(find.text('Aktueller öffentlicher Inhalt.'), findsOneWidget);
    expect(find.text('Erneut laden'), findsNothing);
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'missing online and bundled guide shows no abbreviated legal copy',
    (tester) async {
      final container = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'https://example.test/api/v1',
          enableOfflineQueue: false,
        ),
        transport: _DeletionTransport(),
      );

      await http.runWithClient(() async {
        await tester.pumpWidget(
          AirmiusScope(
            language: AirmiusLanguage.fr,
            setLanguage: (_) {},
            child: AirmiusServicesScope(
              container: container,
              child: _guideApp(
                const DeletionGuideScreen(kind: DeletionGuideKind.account),
                bundle: _MissingGuideBundle(),
              ),
            ),
          ),
        );
        await tester.pumpAndSettle();
      }, () => MockClient((_) async => http.Response('Unavailable', 503)));

      expect(find.text('Ouvrir le guide sur le Web'), findsOneWidget);
      expect(
        find.text(
          'Le guide n’a pas pu être chargé. Ouvrez la version publique sur le Web ou contactez le service de protection des données.',
        ),
        findsOneWidget,
      );
      expect(find.text('Legal basis'), findsNothing);
      expect(find.text('Sources juridiques'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('French and Arabic guides show the full local-language copy', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 640);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'not-a-url',
        enableOfflineQueue: false,
      ),
      transport: _DeletionTransport(),
    );

    for (final (language, title, notice, warning, otherGuide) in [
      (
        AirmiusLanguage.fr,
        'Supprimer un club',
        'Ces informations sont publiques. Leur lecture ne lance aucune suppression.',
        "La version actuelle est indisponible. Cette copie complète est intégrée à l'application et peut être obsolète.",
        'Informations sur la suppression du compte',
      ),
      (
        AirmiusLanguage.ar,
        'حذف النادي',
        'هذه المعلومات متاحة للجميع. قراءتها لا تبدأ عملية الحذف.',
        'النسخة الحالية غير متاحة. هذه نسخة كاملة مرفقة بالتطبيق وقد تكون قديمة.',
        'معلومات عن حذف الحساب',
      ),
    ]) {
      await tester.pumpWidget(
        AirmiusScope(
          language: language,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: container,
            child: _guideApp(
              const DeletionGuideScreen(kind: DeletionGuideKind.club),
            ),
          ),
        ),
      );
      await _settleBundledGuide(tester);
      expect(find.text(title), findsWidgets);
      expect(find.text(notice), findsOneWidget);
      expect(find.text(otherGuide), findsOneWidget);
      await tester.scrollUntilVisible(
        find.text(warning),
        120,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text(warning), findsOneWidget);
      final firstSection = language == AirmiusLanguage.fr
          ? 'Deux procédures distinctes'
          : 'إجراءان منفصلان';
      await tester.scrollUntilVisible(
        find.text(firstSection),
        120,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text(firstSection), findsOneWidget);
      expect(tester.takeException(), isNull);
    }
  });

  testWidgets(
    'guests can open both deletion guides directly from the public portal',
    (tester) async {
      final container = AirmiusServiceContainer(
        environment: const AirmiusAppEnvironment(
          apiBaseUrl: 'not-a-url',
          enableOfflineQueue: false,
        ),
        transport: _DeletionTransport(),
      );
      await tester.pumpWidget(
        AirmiusScope(
          language: AirmiusLanguage.de,
          setLanguage: (_) {},
          child: AirmiusServicesScope(
            container: container,
            child: _guideApp(const GuestPortalScreen()),
          ),
        ),
      );
      await tester.pumpAndSettle();

      for (final (label, kind) in [
        ('Konto löschen', DeletionGuideKind.account),
        ('Verein löschen', DeletionGuideKind.club),
      ]) {
        final link = find.text(label);
        await tester.ensureVisible(link);
        await tester.pumpAndSettle();
        await tester.tap(link);
        await tester.pumpAndSettle();
        expect(find.byType(DeletionGuideScreen), findsOneWidget);
        expect(
          tester
              .widget<DeletionGuideScreen>(find.byType(DeletionGuideScreen))
              .kind,
          kind,
        );
        final otherGuide = kind == DeletionGuideKind.account
            ? 'Informationen zur Vereinslöschung'
            : 'Informationen zur Kontolöschung';
        await tester.tap(find.text(otherGuide));
        await tester.pumpAndSettle();
        expect(
          tester
              .widget<DeletionGuideScreen>(find.byType(DeletionGuideScreen))
              .kind,
          kind == DeletionGuideKind.account
              ? DeletionGuideKind.club
              : DeletionGuideKind.account,
        );
        expect(tester.takeException(), isNull);
        await tester.pageBack();
        await tester.pumpAndSettle();
      }
    },
  );

  testWidgets('club deletion settings open the club deletion guide', (
    tester,
  ) async {
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'not-a-url',
        enableOfflineQueue: false,
      ),
      transport: _DeletionTransport(),
    );
    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: AirmiusServicesScope(
          container: container,
          child: _guideApp(
            const ClubDeletionScreen(
              clubId: 7,
              clubName: 'Sportverein Beispiel',
            ),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    final link = find.text('Informationen zur Vereinslöschung');
    await tester.ensureVisible(link);
    await tester.tap(link);
    await tester.pumpAndSettle();
    expect(find.byType(DeletionGuideScreen), findsOneWidget);
    expect(
      tester.widget<DeletionGuideScreen>(find.byType(DeletionGuideScreen)).kind,
      DeletionGuideKind.club,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('account settings open the account deletion guide', (
    tester,
  ) async {
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'not-a-url',
        enableOfflineQueue: false,
      ),
      transport: _DeletionTransport(),
    );
    await tester.pumpWidget(
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: AirmiusServicesScope(
          container: container,
          child: _guideApp(
            const AccountManagementScreen(initialSection: 'delete'),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    final link = find.text(
      'Ablauf, Folgen, Aufbewahrung und Rechtsgrundlagen der Kontolöschung.',
    );
    await tester.ensureVisible(link);
    await tester.tap(link);
    await tester.pumpAndSettle();
    expect(find.byType(DeletionGuideScreen), findsOneWidget);
    expect(
      tester.widget<DeletionGuideScreen>(find.byType(DeletionGuideScreen)).kind,
      DeletionGuideKind.account,
    );
    expect(tester.takeException(), isNull);
  });
}

class _DeletionTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  String? date;
  String? blocker;
  int? reviewTicketId;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.method == 'DELETE') {
      date = request.path.endsWith('/deletion') ? null : '2026-10-27T12:00:00Z';
    }
    if (request.path.endsWith('/deletion-review')) reviewTicketId = 12;
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({
        'data': {
          'scheduled_at': date,
          'confirmation': 'Ja, ich bin mir sicher',
          'blocker': blocker,
          'can_request': blocker == null,
          'blocked': false,
          'requires_password': true,
          'review_ticket_id': reviewTicketId,
        },
      }),
    );
  }
}
