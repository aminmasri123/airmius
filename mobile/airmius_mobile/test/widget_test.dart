import 'package:airmius/airmius_app.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_api_repositories.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_mvp_surface.dart';
import 'package:airmius/core/airmius_preferences_store_base.dart';
import 'package:airmius/core/airmius_push_device_registry.dart';
import 'package:airmius/core/airmius_secure_token_store.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/core/airmius_theme.dart';
import 'package:airmius/core/airmius_theme_mode_scope.dart';
import 'package:airmius/core/airmius_upload_retry_policy.dart';
import 'package:airmius/models/app_tab.dart';
import 'package:airmius/models/module_definition.dart';
import 'package:airmius/screens/auth_flows_screen.dart';
import 'package:airmius/screens/dashboard_screen.dart';
import 'package:airmius/screens/login_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('Airmius app starts', (WidgetTester tester) async {
    await tester.pumpWidget(const AirmiusApp());
    expect(find.byType(AirmiusApp), findsOneWidget);
  });

  testWidgets('login widget submits credentials and social provider', (
    WidgetTester tester,
  ) async {
    final container = _widgetTestContainer();
    String? submittedEmail;
    String? submittedPassword;
    String? socialProvider;

    await _pumpAirmiusWidget(
      tester,
      container,
      LoginScreen(
        authState: container.authState,
        onLogin: (email, password) {
          submittedEmail = email;
          submittedPassword = password;
        },
        onSocialLogin: (provider) => socialProvider = provider,
      ),
    );

    expect(find.text('Einloggen'), findsOneWidget);
    expect(find.text('Google'), findsOneWidget);

    await tester.enterText(
      find.byType(TextField).at(0),
      ' sportler@example.test ',
    );
    await tester.enterText(find.byType(TextField).at(1), 'secret-password');
    await tester.tap(find.text('Einloggen'));
    await tester.pump();

    expect(submittedEmail, 'sportler@example.test');
    expect(submittedPassword, 'secret-password');

    await tester.tap(find.text('Google'));
    await tester.pump();

    expect(socialProvider, 'google');
  });

  testWidgets('registration widget exposes MVP fields and local validation', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(1000, 1600));

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      const AuthFlowsScreen(),
    );

    expect(find.text('Konto & Sicherheit'), findsWidgets);
    expect(find.text('Vorname'), findsOneWidget);
    expect(find.text('Nachname'), findsOneWidget);
    expect(find.text('Land'), findsOneWidget);
    expect(find.text('Geburtsdatum'), findsOneWidget);
    expect(find.text('Geschlecht'), findsOneWidget);
    expect(find.text('Passwort'), findsWidgets);
    expect(find.text('Passwort bestätigen'), findsOneWidget);
    expect(find.text('AGB und Datenschutz akzeptieren'), findsOneWidget);

    await tester.ensureVisible(find.text('AGB und Datenschutz akzeptieren'));
    await tester.tap(find.byType(Checkbox).first);
    await tester.pump();
    await tester.ensureVisible(find.text('Konto erstellen'));
    await tester.tap(find.text('Konto erstellen'));
    await tester.pump();

    expect(find.text('Bitte wähle dein Geschlecht aus.'), findsOneWidget);
  });

  testWidgets('dashboard widget renders athlete MVP home', (
    WidgetTester tester,
  ) async {
    _setTestViewport(tester, const Size(1000, 1600));

    final openedTabs = <AppTab>[];
    final openedModules = <String>[];

    await _pumpAirmiusWidget(
      tester,
      _widgetTestContainer(),
      Scaffold(
        body: DashboardScreen(
          onOpenTab: openedTabs.add,
          onOpenModule: (module) => openedModules.add(module.title),
          requestedClubIds: const {},
        ),
      ),
    );

    expect(find.text('DASHBOARD'), findsOneWidget);
    expect(find.text('Hallo Sportler'), findsOneWidget);
    expect(
      find.text(
        'Deine wichtigsten Werte, Aufgaben und Schnellstarts auf einen Blick.',
      ),
      findsWidgets,
    );
    expect(find.text('Training'), findsWidgets);

    await tester.tap(find.text('Anpassen'));
    await tester.pump();

    expect(find.text('Widgets'), findsOneWidget);
    expect(find.text('Alles zeigen'), findsOneWidget);
    expect(openedTabs, isEmpty);
    expect(openedModules, isEmpty);
  });

  test('login uses only api v1 auth endpoint when server rejects it', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 404,
        body: '{"message":"Not found"}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    await expectLater(
      client.login(email: 'sportler@example.test', password: 'secret'),
      throwsA(isA<AirmiusApiException>()),
    );

    expect(transport.paths, ['/api/v1/auth/login']);
  });

  test('me uses only api v1 profile endpoint when server rejects it', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 422,
        body: '{"message":"Invalid request"}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    await expectLater(client.me(), throwsA(isA<AirmiusApiException>()));

    expect(transport.paths, ['/api/v1/me']);
  });

  test('auth repository exposes api validation error details', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 422,
        body:
            '{"message":"Validation failed","errors":{"email":["E-Mail ist ungültig."]},"code":"VALIDATION_ERROR"}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await expectLater(
      AirmiusApiAuthRepository(client).currentUser(),
      throwsA(
        isA<AirmiusApiException>()
            .having((error) => error.statusCode, 'statusCode', 422)
            .having((error) => error.path, 'path', '/api/v1/me')
            .having(
              (error) => error.userMessage,
              'userMessage',
              'E-Mail ist ungültig.',
            ),
      ),
    );

    expect(transport.requests.single.method, 'GET');
    expect(
      transport.requests.single.headers['Authorization'],
      'Bearer auth-token',
    );
  });

  test(
    'logout uses only api v1 logout endpoint when server rejects it',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 405,
          body: '{"message":"Method not allowed"}',
        ),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
      );

      await expectLater(client.logout(), throwsA(isA<AirmiusApiException>()));

      expect(transport.paths, ['/api/v1/auth/logout']);
    },
  );

  test('updateClub writes to api v1 club endpoint', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"ZBB","city":"Kleinblittersdorf","country":"DE"}}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    await client.updateClub(7, {
      'name': 'ZBB',
      'country': 'DE',
      'city': 'Kleinblittersdorf',
    });

    expect(transport.requests.single.method, 'PUT');
    expect(transport.requests.single.path, '/api/v1/clubs/7');
    expect(transport.requests.single.body, containsPair('country', 'DE'));
  });

  test('api meta reads visible version and feature flag endpoint', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"api_version":"v1","minimum_app_version":"1.0.0","feature_flags":{"mvp_surface":true}}}',
      ),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
    );

    final json = await client.apiMeta();

    expect(transport.requests.single.method, 'GET');
    expect(transport.requests.single.path, '/api/v1/meta');
    expect(json['data'], isA<Map<String, dynamic>>());
  });

  test('MVP surface hides developer suite modules by default', () {
    final events = appModules.firstWhere(
      (module) => module.title == 'Events & Training',
    );
    final nutrition = appModules.firstWhere(
      (module) => module.title == 'Ernährung',
    );

    expect(AirmiusMvpSurface.showDeveloperSuites, isFalse);
    expect(AirmiusMvpSurface.isModuleVisible(events), isTrue);
    expect(AirmiusMvpSurface.isModuleVisible(nutrition), isFalse);
    expect(AirmiusMvpSurface.isDashboardWidgetVisible('files'), isTrue);
    expect(AirmiusMvpSurface.isDashboardWidgetVisible('sport_map'), isFalse);
  });

  test('offline queue persists requests across transport restarts', () async {
    final store = _MemoryPreferencesStore();
    final firstTransport = AirmiusQueuedTransport(
      inner: _RecordingTransport(
        const AirmiusApiResponse(statusCode: 200, body: '{"ok":true}'),
      ),
      timeout: const Duration(seconds: 1),
      store: store,
    )..offline = true;

    await firstTransport.send(
      const AirmiusApiRequest(
        method: 'POST',
        path: '/api/v1/mobile/sync',
        body: {'action': 'create_event'},
      ),
    );

    expect(firstTransport.queue, hasLength(1));
    expect(
      await store.readString(AirmiusQueuedTransport.defaultStorageKey),
      contains('/api/v1/mobile/sync'),
    );

    final replayTransport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"synced":true}'),
    );
    final restoredTransport = AirmiusQueuedTransport(
      inner: replayTransport,
      timeout: const Duration(seconds: 1),
      store: store,
    );

    final responses = await restoredTransport.flush();

    expect(responses.single.statusCode, 200);
    expect(replayTransport.requests.single.method, 'POST');
    expect(replayTransport.requests.single.path, '/api/v1/mobile/sync');
    expect(
      replayTransport.requests.single.body,
      containsPair('action', 'create_event'),
    );
    expect(restoredTransport.queue, isEmpty);
    expect(
      await store.readString(AirmiusQueuedTransport.defaultStorageKey),
      '[]',
    );
  });

  test('api client queues offline mobile sync and flushes later', () async {
    final store = _MemoryPreferencesStore();
    final replayTransport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"synced":true}'),
    );
    final queuedTransport = AirmiusQueuedTransport(
      inner: replayTransport,
      timeout: const Duration(seconds: 1),
      store: store,
    )..offline = true;
    final client = AirmiusApiClient(
      transport: queuedTransport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    final queuedResponse = await client.mobileSync(
      body: {
        'actions': [
          {'type': 'post.create', 'client_id': 'offline-1'},
        ],
      },
    );

    expect(queuedResponse, containsPair('queued', true));
    expect(replayTransport.requests, isEmpty);
    expect(queuedTransport.queue, hasLength(1));

    queuedTransport.offline = false;
    final responses = await queuedTransport.flush();

    expect(responses.single.statusCode, 200);
    expect(replayTransport.requests.single.method, 'POST');
    expect(replayTransport.requests.single.path, '/api/v1/mobile/sync');
    expect(
      replayTransport.requests.single.body?['actions'],
      contains(containsPair('client_id', 'offline-1')),
    );
    expect(
      await store.readString(AirmiusQueuedTransport.defaultStorageKey),
      '[]',
    );
  });

  test(
    'upload retry repeats transient failures and stops on validation errors',
    () async {
      const policy = AirmiusUploadRetryPolicy(baseDelay: Duration.zero);
      var attempts = 0;

      final result = await policy.run((_) async {
        attempts++;
        if (attempts == 1) {
          throw const AirmiusApiException(
            statusCode: 599,
            body: '{"message":"network timeout"}',
            path: '/api/v1/uploads',
          );
        }
        return 'uploaded';
      });

      expect(result, 'uploaded');
      expect(attempts, 2);

      var validationAttempts = 0;
      await expectLater(
        policy.run((_) async {
          validationAttempts++;
          throw const AirmiusApiException(
            statusCode: 422,
            body: '{"message":"file too large"}',
            path: '/api/v1/uploads',
          );
        }),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(validationAttempts, 1);
    },
  );

  test('auth state restore refreshes stored token with current user', () async {
    final tokenStore = AirmiusMemoryTokenStore();
    await tokenStore.write(
      const AirmiusSession(
        token: 'stored-token',
        locale: 'de',
        user: AirmiusUser(
          id: 7,
          name: 'Alter Name',
          email: 'alt@example.test',
          role: 'athlete',
        ),
      ),
    );
    final transport = _RecordingTransport(
      const AirmiusApiResponse(
        statusCode: 200,
        body:
            '{"data":{"id":7,"name":"Mina Sprint","email":"mina@example.test","role":"athlete","first_name":"Mina","last_name":"Sprint","birth_date":"2000-01-01","gender":"female","country":"DE"}}',
      ),
    );
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://airmius.test',
        enableOfflineQueue: false,
      ),
      transport: transport,
      tokenStore: tokenStore,
      pushDeviceStore: _MemoryPreferencesStore(),
    );

    await container.authState.restore();
    final restoredSession = await tokenStore.read();

    expect(container.authState.phase, AirmiusAuthPhase.authenticated);
    expect(container.authState.user?.firstName, 'Mina');
    expect(restoredSession?.token, 'stored-token');
    expect(restoredSession?.user?.email, 'mina@example.test');
    expect(transport.requests.single.path, '/api/v1/me');
    expect(
      transport.requests.single.headers['Authorization'],
      'Bearer stored-token',
    );
  });

  test(
    'push registry registers refreshes and unregisters one device id',
    () async {
      final store = _MemoryPreferencesStore();
      final tokenProvider = _FakePushTokenProvider('push-token-one');
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"status":"updated","device":{"device_id":"device-1","platform":"android","provider":"fcm","token_fingerprint":"abc123"}}}',
        ),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );
      final registry = AirmiusPushDeviceRegistry(
        store: store,
        tokenProvider: tokenProvider,
        deviceIdFactory: () => 'device-1',
      );

      final registration = await registry.setOptIn(
        enabled: true,
        client: client,
      );
      tokenProvider.token = 'push-token-two';
      final refresh = await registry.registerIfOptedIn(client);
      await registry.unregister(client);

      expect(registration.isRegistered, isTrue);
      expect(refresh.isRegistered, isTrue);
      expect(transport.requests[0].method, 'POST');
      expect(transport.requests[0].path, '/api/v1/mobile/push-devices');
      expect(transport.requests[0].body, containsPair('device_id', 'device-1'));
      expect(
        transport.requests[0].body,
        containsPair('token', 'push-token-one'),
      );
      expect(transport.requests[1].body, containsPair('device_id', 'device-1'));
      expect(
        transport.requests[1].body,
        containsPair('token', 'push-token-two'),
      );
      expect(transport.requests[2].method, 'DELETE');
      expect(
        transport.requests[2].path,
        '/api/v1/mobile/push-devices/device-1',
      );
      expect(
        await store.readString(AirmiusPushDeviceRegistry.optInStorageKey),
        'false',
      );
    },
  );

  test('service container deletes push device before logout', () async {
    final pushStore = _MemoryPreferencesStore();
    await pushStore.writeString(
      AirmiusPushDeviceRegistry.deviceIdStorageKey,
      'logout-device',
    );
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
    );
    final container = AirmiusServiceContainer(
      environment: const AirmiusAppEnvironment(
        apiBaseUrl: 'https://airmius.test',
      ),
      transport: transport,
      tokenStore: AirmiusMemoryTokenStore(),
      pushDeviceStore: pushStore,
    );

    await container.authState.signInWithToken(token: 'auth-token');
    await container.authState.signOut();

    expect(
      transport.paths,
      contains('/api/v1/mobile/push-devices/logout-device'),
    );
    expect(
      transport.paths.indexOf('/api/v1/mobile/push-devices/logout-device'),
      lessThan(transport.paths.indexOf('/api/v1/auth/logout')),
    );
  });

  test('deep link resolver covers MVP sport routes and invitations', () {
    const resolver = AirmiusDeepLinkResolver();

    final club = resolver.resolve('airmius://clubs/7');
    final team = resolver.resolve('https://app.airmius.com/teams/12');
    final event = resolver.resolve('airmius://events/31');
    final post = resolver.resolve('airmius://feed/44');
    final chat = resolver.resolve('airmius://chat/9');
    final invitation = resolver.resolve(
      'https://app.airmius.com/team-invitations/token/abc123/accept',
    );

    expect(club.type, AirmiusDeepLinkTargetType.club);
    expect(club.id, 7);
    expect(team.type, AirmiusDeepLinkTargetType.team);
    expect(team.id, 12);
    expect(event.type, AirmiusDeepLinkTargetType.event);
    expect(event.id, 31);
    expect(post.type, AirmiusDeepLinkTargetType.post);
    expect(post.id, 44);
    expect(chat.type, AirmiusDeepLinkTargetType.chat);
    expect(chat.id, 9);
    expect(invitation.type, AirmiusDeepLinkTargetType.invitation);
    expect(invitation.token, 'abc123');
    expect(invitation.requiresAuth, isFalse);
  });

  test('chat realtime client sends read and typing signals', () async {
    final transport = _RecordingTransport(
      const AirmiusApiResponse(statusCode: 200, body: '{"data":{"ok":true}}'),
    );
    final client = AirmiusApiClient(
      transport: transport,
      baseUrl: 'https://airmius.test',
      token: 'auth-token',
    );

    await client.markConversationRead(42);
    await client.sendConversationTyping(42, true);
    await client.sendConversationTyping(42, false);

    expect(transport.requests[0].method, 'POST');
    expect(transport.requests[0].path, '/api/v1/chat/conversations/42/read');
    expect(transport.requests[1].path, '/api/v1/chat/conversations/42/typing');
    expect(transport.requests[1].body, containsPair('typing', true));
    expect(transport.requests[2].path, '/api/v1/chat/conversations/42/typing');
    expect(transport.requests[2].body, containsPair('typing', false));
  });

  test(
    'chat client creates direct and group conversations via api v1',
    () async {
      final transport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 201,
          body: '{"data":{"id":7,"type":"group","name":"Laufgruppe"}}',
        ),
      );
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await client.createConversation(
        type: 'direct',
        participantIds: [12],
        message: 'Hallo Team.',
      );
      await client.createConversation(
        type: 'group',
        participantIds: [12, 13],
        name: 'Laufgruppe',
        description: 'Montagstraining',
      );

      expect(transport.requests[0].method, 'POST');
      expect(transport.requests[0].path, '/api/v1/chat/conversations');
      expect(transport.requests[0].body, containsPair('type', 'direct'));
      expect(transport.requests[0].body, containsPair('participant_ids', [12]));
      expect(
        transport.requests[0].body,
        containsPair('message', 'Hallo Team.'),
      );
      expect(transport.requests[1].path, '/api/v1/chat/conversations');
      expect(transport.requests[1].body, containsPair('type', 'group'));
      expect(
        transport.requests[1].body,
        containsPair('participant_ids', [12, 13]),
      );
      expect(transport.requests[1].body, containsPair('name', 'Laufgruppe'));
      expect(
        transport.requests[1].body,
        containsPair('description', 'Montagstraining'),
      );
    },
  );

  test(
    'notification repository maps detail data and paginates list requests',
    () async {
      final listTransport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":[],"meta":{"current_page":2,"per_page":20,"total":0},"links":{}}',
        ),
      );
      final listClient = AirmiusApiClient(
        transport: listTransport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      await AirmiusApiNotificationRepository(listClient).notifications(page: 2);

      expect(listTransport.requests.single.method, 'GET');
      expect(listTransport.requests.single.path, '/api/v1/notifications');
      expect(listTransport.requests.single.query, containsPair('page', '2'));

      final detailTransport = _RecordingTransport(
        const AirmiusApiResponse(
          statusCode: 200,
          body:
              '{"data":{"id":7,"type":"event.reminder","title":"Training heute","body":"18:00 Uhr","read":false,"action_url":"/events/7","data":{"event_id":7}}}',
        ),
      );
      final detailClient = AirmiusApiClient(
        transport: detailTransport,
        baseUrl: 'https://airmius.test',
        token: 'auth-token',
      );

      final notification = await AirmiusApiNotificationRepository(
        detailClient,
      ).notification(7);

      expect(detailTransport.requests.single.path, '/api/v1/notifications/7');
      expect(notification.id, 7);
      expect(notification.title, 'Training heute');
      expect(notification.unread, isTrue);
      expect(notification.actionUrl, 'https://airmius.test/events/7');
      expect(notification.data, containsPair('event_id', 7));
    },
  );

  test(
    'secure token store migrates legacy tokens without unsafe fallback writes',
    () async {
      final legacyStore = AirmiusMemoryTokenStore();
      await legacyStore.write(
        const AirmiusSession(token: 'legacy-token', locale: 'de'),
      );
      final secureStorage = _MemorySecureSessionStorage();
      final tokenStore = AirmiusSecureTokenStore(
        secureStorage: secureStorage,
        fallback: legacyStore,
      );

      final restored = await tokenStore.read();

      expect(restored?.token, 'legacy-token');
      expect(secureStorage.value, contains('legacy-token'));
      expect(await legacyStore.read(), isNull);
    },
  );

  test(
    'secure token store never writes auth tokens to fallback storage',
    () async {
      final legacyStore = AirmiusMemoryTokenStore();
      final secureStorage = _MemorySecureSessionStorage(failWrites: true);
      final tokenStore = AirmiusSecureTokenStore(
        secureStorage: secureStorage,
        fallback: legacyStore,
      );

      await expectLater(
        tokenStore.write(
          const AirmiusSession(token: 'auth-token', locale: 'de'),
        ),
        throwsA(isA<StateError>()),
      );

      expect(await legacyStore.read(), isNull);
    },
  );
}

Future<void> _pumpAirmiusWidget(
  WidgetTester tester,
  AirmiusServiceContainer container,
  Widget child,
) async {
  await tester.pumpWidget(
    AirmiusServicesScope(
      container: container,
      child: AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: (_) {},
        child: AirmiusThemeModeScope(
          mode: ThemeMode.dark,
          setMode: (_) {},
          palette: AirmiusThemePalette.dark,
          setPalette: (_) {},
          child: MaterialApp(
            theme: AirmiusTheme.light(),
            darkTheme: AirmiusTheme.dark(),
            themeMode: ThemeMode.dark,
            home: child,
          ),
        ),
      ),
    ),
  );
  await tester.pump();
}

AirmiusServiceContainer _widgetTestContainer({AirmiusApiTransport? transport}) {
  return AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(
      apiBaseUrl: 'https://airmius.test',
      enableOfflineQueue: false,
    ),
    transport:
        transport ??
        _RecordingTransport(
          const AirmiusApiResponse(statusCode: 200, body: '{"data":{}}'),
        ),
    tokenStore: AirmiusMemoryTokenStore(),
    pushDeviceStore: _MemoryPreferencesStore(),
  );
}

void _setTestViewport(WidgetTester tester, Size size) {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);
}

class _RecordingTransport implements AirmiusApiTransport {
  _RecordingTransport(this.response);

  final AirmiusApiResponse response;
  final List<AirmiusApiRequest> requests = <AirmiusApiRequest>[];

  List<String> get paths => requests.map((request) => request.path).toList();

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    return response;
  }
}

class _MemoryPreferencesStore implements AirmiusPreferencesStore {
  final Map<String, String> _values = <String, String>{};

  @override
  Future<String?> readString(String key) async => _values[key];

  @override
  Future<void> writeString(String key, String value) async {
    _values[key] = value;
  }
}

class _FakePushTokenProvider implements AirmiusPushTokenProvider {
  _FakePushTokenProvider(this.token);

  String token;

  @override
  Future<AirmiusPushToken?> currentToken() async =>
      AirmiusPushToken(token: token, provider: 'fcm');

  @override
  Future<AirmiusPushToken?> requestToken() async =>
      AirmiusPushToken(token: token, provider: 'fcm');
}

class _MemorySecureSessionStorage implements AirmiusSecureSessionStorage {
  _MemorySecureSessionStorage({this.failWrites = false});

  final bool failWrites;
  String? value;

  @override
  Future<void> delete({required String key}) async {
    value = null;
  }

  @override
  Future<String?> read({required String key}) async => value;

  @override
  Future<void> write({required String key, required String value}) async {
    if (failWrites) throw StateError('secure storage unavailable');
    this.value = value;
  }
}
