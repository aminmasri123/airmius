import 'dart:async';
import 'dart:convert';

import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_auth_state.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/sponsor_cockpit_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

const _profile = <String, dynamic>{
  'id': 7,
  'name': 'Native Brand',
  'legal_name': 'Native Brand GmbH',
  'country_code': 'DE',
  'registration_number': 'HRB 123',
  'vat_id': 'DE123456789',
  'contact_name': 'Sponsor Contact',
  'email': 'sponsor@example.test',
  'website': 'https://example.test',
  'logo': 'https://example.test/logo.png',
  'logo_light': 'https://example.test/light.png',
  'logo_dark': 'https://example.test/dark.png',
  'legal_accuracy_accepted': true,
  'data_privacy_accepted': true,
  'verification_status': 'verified',
};

class _Transport extends AirmiusApiTransport {
  _Transport({this.profile, this.canEdit = true});

  Map<String, dynamic>? profile;
  final bool canEdit;
  final writes = <AirmiusApiRequest>[];
  int reads = 0;
  int loadStatus = 200;
  Future<AirmiusApiResponse> Function(AirmiusApiRequest)? onSave;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    if (request.method == 'GET') {
      expect(request.path, '/api/v1/sponsor-workspace');
      reads++;
      return AirmiusApiResponse(
        statusCode: loadStatus,
        body: jsonEncode({
          'message': 'Workspace unavailable',
          'data': {
            'own_profile': profile,
            'capabilities': {'edit_own_profile': canEdit},
          },
        }),
      );
    }
    expect(request.method, 'PUT');
    expect(request.path, '/api/v1/sponsor-workspace/profile');
    writes.add(request);
    if (onSave != null) return onSave!(request);
    profile = {
      ...?profile,
      ...request.body!,
      'id': 7,
      'legal_accuracy_accepted': request.body!['rule_legal_accuracy'],
      'data_privacy_accepted': request.body!['rule_data_privacy'],
      'verification_status': 'pending_review',
    };
    return const AirmiusApiResponse(statusCode: 200, body: '{"data":{"id":7}}');
  }
}

Finder _field(String key) => find.byKey(ValueKey('sponsor-profile-$key'));

Future<void> _mount(
  WidgetTester tester,
  _Transport transport, {
  AirmiusLanguage language = AirmiusLanguage.en,
  double width = 390,
}) async {
  await tester.binding.setSurfaceSize(Size(width, 844));
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
      language: language,
      setLanguage: (_) {},
      child: AirmiusServicesScope(
        container: services,
        child: MaterialApp(
          builder: (context, child) => Directionality(
            textDirection: language.isRtl
                ? TextDirection.rtl
                : TextDirection.ltr,
            child: child!,
          ),
          home: const SponsorCockpitScreen(),
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

Future<void> _enter(WidgetTester tester, String key, String value) async {
  await tester.ensureVisible(_field(key));
  await tester.enterText(_field(key), value);
  await tester.pumpAndSettle();
}

String _value(WidgetTester tester, String key) =>
    tester.widget<TextFormField>(_field(key)).controller!.text;

void main() {
  testWidgets(
    'create requires legal details and explicit consents, then reloads',
    (tester) async {
      final transport = _Transport();
      await _mount(tester, transport);
      await _tap(tester, find.text('Create profile'));
      expect(_value(tester, 'country_code'), 'DE');
      expect(
        tester.widget<CheckboxListTile>(_field('rule_legal_accuracy')).value,
        false,
      );
      expect(
        tester.widget<CheckboxListTile>(_field('rule_data_privacy')).value,
        false,
      );
      await _tap(tester, _field('save'));
      expect(transport.writes, isEmpty);
      for (final key in ['name', 'legal_name', 'country_code']) {
        await _enter(tester, key, key == 'country_code' ? 'fr' : ' New Brand ');
      }
      await _tap(tester, _field('save'));
      expect(transport.writes, isEmpty);
      await _tap(tester, _field('rule_legal_accuracy'));
      await _tap(tester, _field('save'));
      expect(transport.writes, isEmpty);
      await _tap(tester, _field('rule_data_privacy'));
      await _tap(tester, _field('save'));
      expect(transport.writes, hasLength(1));
      expect(transport.writes.single.body, {
        'name': 'New Brand',
        'legal_name': 'New Brand',
        'country_code': 'FR',
        'registration_number': '',
        'vat_id': '',
        'contact_name': '',
        'email': '',
        'website': '',
        'logo': '',
        'logo_light': '',
        'logo_dark': '',
        'rule_legal_accuracy': true,
        'rule_data_privacy': true,
      });
      expect(transport.reads, 2);
      expect(
        find.text(
          'Your details are under review and remain private until approved.',
        ),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
      await _tap(tester, find.text('Edit profile'));
      expect(_value(tester, 'legal_name'), 'New Brand');
      expect(
        tester.widget<CheckboxListTile>(_field('rule_data_privacy')).value,
        true,
      );
    },
  );

  for (final status in ['pending_review', 'verified', 'rejected', 'legacy']) {
    testWidgets(
      'edit $status preserves fields, exposes corrections and submits consents',
      (tester) async {
        final profile = {
          ..._profile,
          'verification_status': status == 'legacy' ? null : status,
        };
        if (status == 'legacy') {
          profile.remove('legal_accuracy_accepted');
          profile.remove('data_privacy_accepted');
          profile['legal_name'] = null;
          profile['country_code'] = null;
        }
        if (status == 'rejected') {
          profile['verification_note'] = 'Correct registration number';
        }
        final transport = _Transport(profile: profile);
        await _mount(tester, transport);
        if (status == 'rejected') {
          expect(find.text('Correct registration number'), findsOneWidget);
        }
        await _tap(tester, find.text('Edit profile'));
        for (final key in [
          'registration_number',
          'vat_id',
          'contact_name',
          'email',
          'website',
          'logo',
          'logo_light',
          'logo_dark',
        ]) {
          expect(_value(tester, key), _profile[key]);
        }
        if (status == 'legacy') {
          await _tap(tester, _field('save'));
          expect(transport.writes, isEmpty);
          await _enter(tester, 'legal_name', 'Native Brand GmbH');
          await _tap(tester, _field('rule_legal_accuracy'));
          await _tap(tester, _field('rule_data_privacy'));
        }
        await _enter(
          tester,
          'logo_light',
          'https://example.test/new-light.png',
        );
        await _enter(tester, 'logo_dark', 'https://example.test/new-dark.png');
        await _enter(tester, 'registration_number', '');
        await _tap(tester, _field('save'));
        final body = transport.writes.single.body!;
        expect(body['registration_number'], '');
        expect(body['logo_light'], 'https://example.test/new-light.png');
        expect(body['logo_dark'], 'https://example.test/new-dark.png');
        expect(body['vat_id'], _profile['vat_id']);
        expect(body['rule_legal_accuracy'], true);
        expect(body['rule_data_privacy'], true);
        expect(body.keys, isNot(contains('id')));
        expect(body.keys, isNot(contains('verification_status')));
        expect(body.keys, isNot(contains('owner_user_id')));
        expect(transport.reads, 2);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets(
    'view-only accounts never receive profile setup guidance or actions',
    (tester) async {
      final transport = _Transport(canEdit: false);
      await _mount(tester, transport);
      expect(find.text('Create profile'), findsNothing);
      expect(find.text('Edit profile'), findsNothing);
      expect(find.text('Complete your brand profile'), findsNothing);
      expect(transport.writes, isEmpty);
    },
  );

  for (final status in [401, 403, 422, 500]) {
    testWidgets(
      'save $status retains draft, displays error and supports retry',
      (tester) async {
        final transport = _Transport(profile: {..._profile});
        transport.onSave = (_) async => AirmiusApiResponse(
          statusCode: status,
          body: jsonEncode({
            'message': 'Save unavailable',
            if (status == 422)
              'errors': {
                'website': ['The website must be a valid URL.'],
                'legal_name': ['Check legal name.'],
              },
          }),
        );
        await _mount(tester, transport);
        await _tap(tester, find.text('Edit profile'));
        await _enter(tester, 'legal_name', 'Changed legal name');
        await _tap(tester, _field('save'));
        expect(_value(tester, 'legal_name'), 'Changed legal name');
        expect(transport.reads, 1);
        expect(
          find.text(status == 422 ? 'Check legal name.' : 'Save unavailable'),
          findsWidgets,
        );
        transport.onSave = null;
        await _tap(tester, _field('save'));
        expect(transport.writes, hasLength(2));
        expect(transport.reads, 2);
        expect(tester.takeException(), isNull);
      },
    );
  }

  testWidgets('queued save is not reported as persisted', (tester) async {
    final transport = _Transport(profile: {..._profile});
    transport.onSave = (_) async =>
        const AirmiusApiResponse(statusCode: 202, body: '{"queued":true}');
    await _mount(tester, transport);
    await _tap(tester, find.text('Edit profile'));
    await _tap(tester, _field('save'));
    expect(
      find.text(
        'The profile is queued for later delivery. Saving on the server is not yet confirmed.',
      ),
      findsOneWidget,
    );
    expect(transport.reads, 1);
    expect(_value(tester, 'name'), 'Native Brand');
  });

  for (final afterAnimation in [true, false]) {
    for (final fails in [true, false]) {
      testWidgets(
        'dismiss during save is safe; failure: $fails; after animation: $afterAnimation',
        (tester) async {
          final pending = Completer<AirmiusApiResponse>();
          final transport = _Transport(profile: {..._profile});
          transport.onSave = (_) => pending.future;
          await _mount(tester, transport);
          await _tap(tester, find.text('Edit profile'));
          await tester.ensureVisible(_field('save'));
          await tester.tap(_field('save'));
          await tester.pump();
          expect(tester.widget<FilledButton>(_field('save')).onPressed, isNull);
          expect(transport.writes, hasLength(1));
          Navigator.of(tester.element(_field('save'))).pop();
          if (afterAnimation) await tester.pumpAndSettle();
          pending.complete(
            AirmiusApiResponse(
              statusCode: fails ? 422 : 200,
              body: fails ? '{"message":"Save failed"}' : '{"data":{"id":7}}',
            ),
          );
          await tester.pumpAndSettle();
          expect(find.byType(SponsorCockpitScreen), findsOneWidget);
          expect(tester.takeException(), isNull);
        },
      );
    }
  }

  testWidgets('workspace failure retries without exposing editor', (
    tester,
  ) async {
    final transport = _Transport()..loadStatus = 403;
    await _mount(tester, transport);
    expect(find.text('Create profile'), findsNothing);
    expect(find.text('Workspace unavailable'), findsOneWidget);
    transport.loadStatus = 200;
    await _tap(tester, find.byIcon(Icons.refresh));
    expect(find.text('Create profile'), findsOneWidget);
  });

  testWidgets(
    'cancel discards changes and keyboard inset leaves save reachable',
    (tester) async {
      final transport = _Transport(profile: {..._profile});
      await _mount(tester, transport);
      await _tap(tester, find.text('Edit profile'));
      await _enter(tester, 'legal_name', 'Unsaved name');
      await _tap(tester, find.byIcon(Icons.close));
      expect(transport.writes, isEmpty);
      await _tap(tester, find.text('Edit profile'));
      expect(_value(tester, 'legal_name'), _profile['legal_name']);
      tester.view.viewInsets = const FakeViewPadding(bottom: 300);
      addTearDown(tester.view.resetViewInsets);
      await tester.pumpAndSettle();
      await _enter(tester, 'logo_dark', 'https://example.test/keyboard.png');
      await _tap(tester, _field('save'));
      expect(
        transport.writes.single.body!['logo_dark'],
        'https://example.test/keyboard.png',
      );
      expect(tester.takeException(), isNull);
    },
  );

  for (final language in AirmiusLanguage.values) {
    testWidgets(
      'profile remains scrollable on narrow phones in ${language.name}',
      (tester) async {
        final transport = _Transport(profile: {..._profile});
        await _mount(tester, transport, language: language, width: 320);
        await _tap(tester, find.byIcon(Icons.business_outlined));
        await _enter(tester, 'country_code', 'D');
        await _tap(tester, _field('save'));
        expect(transport.writes, isEmpty);
        await _enter(tester, 'country_code', 'DE');
        await _tap(tester, _field('save'));
        expect(transport.writes, hasLength(1));
      },
    );
  }
}
