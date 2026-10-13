import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/club_governance_meeting_forms.dart';
import 'package:airmius/screens/meeting_minutes_decision_log_suite_screen.dart';

void main() {
  test(
    'meeting client scopes all writes and does not retry or queue votes',
    () async {
      final transport = _MeetingsTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
      );
      await client.clubGovernanceMeetings(7);
      await client.clubGovernanceMeeting(7, 4);
      await client.saveClubGovernanceMeeting(7, {'title': 'Vorstand'});
      await client.saveClubGovernanceMeeting(7, {
        'notes': 'Protokoll',
      }, meetingId: 4);
      await client.updateClubGovernanceMeetingDelivery(7, 4, 99, {
        'delivery_status': 'sent',
      });
      await client.createClubGovernanceDecision(7, 4, {'title': 'Antrag'});
      await client.voteClubGovernanceDecision(7, 4, 6, 99, 'yes');
      await client.closeClubGovernanceDecision(7, 4, 6);
      expect(
        transport.requests.map(
          (request) => '${request.method} ${request.path}',
        ),
        [
          'GET /api/v1/clubs/7/governance/meetings',
          'GET /api/v1/clubs/7/governance/meetings/4',
          'POST /api/v1/clubs/7/governance/meetings',
          'PUT /api/v1/clubs/7/governance/meetings/4',
          'PATCH /api/v1/clubs/7/governance/meetings/4/recipients/99/delivery',
          'POST /api/v1/clubs/7/governance/meetings/4/decisions',
          'POST /api/v1/clubs/7/governance/meetings/4/decisions/6/vote',
          'POST /api/v1/clubs/7/governance/meetings/4/decisions/6/close',
        ],
      );
      final failing = _FailingTransport();
      final queued = AirmiusQueuedTransport(
        inner: failing,
        timeout: const Duration(seconds: 1),
      );
      final online = AirmiusApiClient(
        transport: queued,
        baseUrl: 'https://example.test',
      );
      await expectLater(
        online.voteClubGovernanceDecision(7, 4, 6, 99, 'yes'),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(failing.calls, 1);
      queued.offline = true;
      await expectLater(
        online.voteClubGovernanceDecision(7, 4, 6, 99, 'yes'),
        throwsA(isA<AirmiusApiException>()),
      );
      expect(queued.queue, isEmpty);
      expect(failing.calls, 1);
    },
  );

  test(
    'active body assignments include boundary dates and external members',
    () {
      expect(
        governanceActiveBodyKeys({
          'assignments': [
            {'user_id': 9, 'starts_on': '2026-10-13', 'ends_on': '2026-10-13'},
            {'club_external_member_id': 8},
            {'user_id': 10, 'ends_on': '2026-10-12'},
            {'user_id': 11, 'starts_on': '2026-10-14'},
          ],
        }, DateTime(2026, 10, 13)),
        {'user:9', 'external:8'},
      );
    },
  );

  testWidgets(
    'invited member casts only own vote then reloads secret ballot state',
    (tester) async {
      final transport = _MeetingsTransport();
      await _mount(tester, transport);
      expect(find.byTooltip('Sitzung anlegen'), findsNothing);
      await tester.tap(find.text('Vorstand Oktober'));
      await tester.pumpAndSettle();
      expect(find.text('Sitzung bearbeiten'), findsNothing);
      expect(find.text('Beschluss anlegen'), findsNothing);
      await tester.ensureVisible(find.widgetWithText(OutlinedButton, 'Ja'));
      await tester.tap(find.widgetWithText(OutlinedButton, 'Ja'));
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Stimme abgeben'));
      await tester.pumpAndSettle();
      final vote = transport.requests.singleWhere(
        (request) => request.path.endsWith('/vote'),
      );
      expect(vote.body, {'recipient_id': 99, 'choice': 'yes'});
      expect(
        transport.requests
            .where((request) => request.path.endsWith('/meetings/4'))
            .length,
        2,
      );
      expect(find.text('Ihre Stimme wurde abgegeben.'), findsOneWidget);
      expect(find.widgetWithText(OutlinedButton, 'Ja'), findsNothing);
      expect(find.text('Angenommen'), findsNothing);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets('failed vote reloads server state and displays API error', (
    tester,
  ) async {
    final transport = _MeetingsTransport()..voteError = true;
    await _mount(tester, transport);
    await tester.tap(find.text('Vorstand Oktober'));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.widgetWithText(OutlinedButton, 'Nein'));
    await tester.tap(find.widgetWithText(OutlinedButton, 'Nein'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Stimme abgeben'));
    await tester.pumpAndSettle();
    expect(
      transport.requests
          .where((request) => request.path.endsWith('/vote'))
          .length,
      1,
    );
    expect(
      transport.requests
          .where((request) => request.path.endsWith('/meetings/4'))
          .length,
      2,
    );
    expect(find.text('Stimme bereits gespeichert.'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'Ja'), findsNothing);
  });

  testWidgets(
    'manager saves board recipients and rights then reloads persisted meeting',
    (tester) async {
      final transport = _MeetingsTransport()..manager = true;
      await _mount(tester, transport);
      await tester.tap(find.byTooltip('Sitzung anlegen'));
      await tester.pumpAndSettle();
      expect(find.text('Ehemaliges Mitglied'), findsNothing);
      expect(find.text('Normales Mitglied'), findsNothing);
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Titel'),
        'Vorstand November',
      );
      await tester.ensureVisible(find.text('Gremiumsmitglieder auswählen'));
      await tester.tap(find.text('Gremiumsmitglieder auswählen'));
      await tester.pumpAndSettle();
      final voting = find.widgetWithText(CheckboxListTile, 'Stimmrecht');
      expect(voting, findsNWidgets(2));
      await tester.ensureVisible(voting.last);
      await tester.tap(voting.last);
      await tester.pumpAndSettle();
      await tester.tap(find.widgetWithText(FilledButton, 'Speichern'));
      await tester.pumpAndSettle();
      final save = transport.requests.singleWhere(
        (request) =>
            request.method == 'POST' && request.path.endsWith('/meetings'),
      );
      expect(save.body?['participant_scope'], 'body');
      expect(save.body?['club_governance_body_id'], 2);
      expect(save.body?['recipients'], [
        {
          'user_id': 9,
          'delivery_status': 'pending',
          'attendance_eligible': true,
          'voting_eligible': true,
        },
        {
          'club_external_member_id': 8,
          'delivery_status': 'pending',
          'attendance_eligible': true,
          'voting_eligible': false,
        },
      ]);
      expect(
        transport.requests.last.path,
        '/api/v1/clubs/7/governance/meetings/4',
      );
      expect(find.text('Vorstand November'), findsOneWidget);
      expect(find.text('Sitzung bearbeiten'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'save validation retains dialog and user input until successful retry',
    (tester) async {
      final transport = _MeetingsTransport()
        ..manager = true
        ..saveError = true;
      await _mount(tester, transport);
      await tester.tap(find.text('Vorstand Oktober'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Sitzung bearbeiten'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Titel'),
        'Neues Protokoll',
      );
      await tester.ensureVisible(
        find.widgetWithText(TextFormField, 'Protokoll / Notizen'),
      );
      await tester.enterText(
        find.widgetWithText(TextFormField, 'Protokoll / Notizen'),
        'Beschluss wurde diskutiert.',
      );
      await tester.tap(find.widgetWithText(FilledButton, 'Speichern'));
      await tester.pumpAndSettle();
      expect(find.byType(AlertDialog), findsOneWidget);
      expect(find.text('Neues Protokoll'), findsOneWidget);
      expect(find.text('Beschluss wurde diskutiert.'), findsOneWidget);
      expect(
        find.text('Titel konnte nicht gespeichert werden.'),
        findsOneWidget,
      );
      expect(
        transport.requests
            .where(
              (request) =>
                  request.method == 'GET' &&
                  request.path.endsWith('/meetings/4'),
            )
            .length,
        1,
      );
      transport.saveError = false;
      await tester.tap(find.widgetWithText(FilledButton, 'Speichern'));
      await tester.pumpAndSettle();
      expect(find.byType(AlertDialog), findsNothing);
      expect(find.text('Neues Protokoll'), findsOneWidget);
      expect(find.text('Beschluss wurde diskutiert.'), findsOneWidget);
      expect(
        transport.requests
            .where(
              (request) =>
                  request.method == 'GET' &&
                  request.path.endsWith('/meetings/4'),
            )
            .length,
        2,
      );
      final payload = transport.requests
          .lastWhere((request) => request.method == 'PUT')
          .body!;
      expect(payload.containsKey('recipients'), false);
      expect(payload['materials'], [
        {'title': 'Unterlage', 'url': 'https://example.test/doc'},
      ]);
    },
  );

  testWidgets(
    'cancelled meeting and ineligible viewer have no voting controls',
    (tester) async {
      final transport = _MeetingsTransport()
        ..manager = true
        ..cancelled = true;
      await _mount(tester, transport);
      await tester.tap(find.text('Vorstand Oktober'));
      await tester.pumpAndSettle();
      expect(find.widgetWithText(OutlinedButton, 'Ja'), findsNothing);
      expect(find.text('Beschluss anlegen'), findsNothing);
      expect(find.text('Sitzung bearbeiten'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );
}

Future<void> _mount(WidgetTester tester, _MeetingsTransport transport) async {
  tester.view.physicalSize = const Size(430, 900);
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
    AirmiusServicesScope(
      container: container,
      child: const MaterialApp(home: MeetingMinutesDecisionLogSuiteScreen()),
    ),
  );
  await tester.pumpAndSettle();
}

class _MeetingsTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  bool manager = false;
  bool voted = false;
  bool voteError = false;
  bool saveError = false;
  bool cancelled = false;
  String title = 'Vorstand Oktober';
  String notes = 'Altes Protokoll';
  AirmiusJson _meeting() => {
    'id': 4,
    'title': title,
    'type': 'board',
    'status': cancelled ? 'cancelled' : 'invited',
    'club_id': 7,
    'participant_scope': 'body',
    'can_manage': manager,
    'current_recipient_id': 99,
    'viewer_voting_eligible': true,
    'governance_body': {'id': 2, 'name': 'Vorstand'},
    'eligibility_as_of': '2026-10-13',
    'agenda_items': [
      {'title': 'Finanzen', 'description': 'Bericht'},
    ],
    'notes': notes,
    'materials': [
      {'title': 'Unterlage', 'url': 'https://example.test/doc'},
    ],
    'recipients': [
      {
        'id': 99,
        'person': {'name': 'Alex'},
        'voting_eligible': true,
        'delivery_status': 'sent',
      },
    ],
    'decisions': [
      {
        'id': 6,
        'type': 'motion',
        'title': 'Budget freigeben',
        'voting_mode': 'secret',
        'status': 'open',
        'meeting_version': 1,
        'majority_rule': 'simple',
        'eligible_voters': 2,
        'options': ['yes', 'no', 'abstain'],
        'viewer_can_vote': !voted && !cancelled,
        'viewer_has_voted': voted,
        'result': {
          'votes_cast': voted ? 1 : 0,
          'counts': voted ? {'yes': 1} : {},
          'quorum_reached': true,
        },
      },
    ],
    'versions': [
      {
        'id': 1,
        'version': 1,
        'status': 'invited',
        'created_at': '2026-10-13T12:00:00Z',
        'created_by': 'Alex',
        'notes': 'Altes Protokoll',
        'agenda_items': [],
      },
    ],
  };

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    requests.add(request);
    if (request.path.endsWith('/vote')) {
      voted = true;
      if (voteError)
        return const AirmiusApiResponse(
          statusCode: 422,
          body: '{"message":"Stimme bereits gespeichert."}',
        );
      return _response({
        'data': {'id': 5},
      });
    }
    if (request.method == 'PUT' ||
        request.method == 'POST' && request.path.endsWith('/meetings')) {
      if (saveError)
        return const AirmiusApiResponse(
          statusCode: 422,
          body: '{"message":"Titel konnte nicht gespeichert werden."}',
        );
      title = request.body?['title'] as String? ?? title;
      notes = request.body?['notes'] as String? ?? notes;
      return _response({'data': _meeting()});
    }
    if (request.path.endsWith('/clubs'))
      return _response({
        'data': [
          {'id': 7, 'name': 'Testverein'},
        ],
      });
    if (request.path.endsWith('/governance'))
      return _response({
        'data': {
          'bodies': [
            {
              'id': 2,
              'type': 'board',
              'name': 'Vorstand',
              'assignments': [
                {'user_id': 9},
                {'club_external_member_id': 8},
                {'user_id': 10, 'ends_on': '2000-01-01'},
              ],
            },
          ],
          'member_options': [
            {'kind': 'user', 'id': 9, 'name': 'Alex'},
            {'kind': 'external', 'id': 8, 'name': 'Extern'},
            {'kind': 'user', 'id': 10, 'name': 'Ehemaliges Mitglied'},
            {'kind': 'user', 'id': 11, 'name': 'Normales Mitglied'},
          ],
        },
      });
    if (request.path.endsWith('/meetings'))
      return _response({
        'data': [_meeting()],
        'meta': {'can_manage': manager},
      });
    return _response({'data': _meeting()});
  }

  AirmiusApiResponse _response(AirmiusJson data) =>
      AirmiusApiResponse(statusCode: 200, body: jsonEncode(data));
}

class _FailingTransport implements AirmiusApiTransport {
  int calls = 0;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    calls++;
    throw StateError('network unavailable');
  }
}
