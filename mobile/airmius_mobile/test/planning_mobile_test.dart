import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/core/airmius_api_client.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/core/airmius_service_container.dart';
import 'package:airmius/core/airmius_services_scope.dart';
import 'package:airmius/screens/competition_planning_screen.dart';
import 'package:airmius/screens/family_calendar_screen.dart';
import 'package:airmius/screens/planning_widgets.dart';
import 'package:airmius/screens/planning_workspace_screen.dart';

void main() {
  test('planning dates use German display and ISO API formats', () {
    expect(planningDay('2026-10-13'), '13.10.2026');
    expect(planningTime('2026-10-13T21:19:00'), '13.10.2026 21:19');
    expect(planningApiDay('13.10.2026'), '2026-10-13');
    expect(planningApiTime('13.10.2026 21:19'), '2026-10-13 21:19');
    expect(planningDate('13.10.2026'), DateTime(2026, 10, 13));
  });

  test(
    'planning API endpoints retain revision, month, roster and person scope',
    () async {
      final transport = _PlanningTransport();
      final client = AirmiusApiClient(
        transport: transport,
        baseUrl: 'https://example.test',
        token: 'test-token',
      );
      await client.planningWorkspace();
      await client.planningCompetitions(7);
      await client.savePlanningCompetition(7, {'name': 'Cup'});
      await client.savePlanningCompetition(7, {'name': 'Updated'}, id: 4);
      await client.planningCompetition(4);
      await client.addPlanningComponent(4, 'roster', {
        'display_name': 'Player',
      });
      await client.nominatePlanningRoster(4, {
        'roster_ids': [3],
        'start_fee_cents': 0,
      });
      await client.planningRegistrationAction(4, 5, 'eligibility');
      await client.generatePlanningTournament(4, {
        'roster_ids': [3, 4],
        'group_count': 1,
      });
      await client.savePlanningMatch(4, 8, {
        'home_score': 2,
        'away_score': 1,
        'status': 'completed',
        'reason': 'Final score',
      });
      await client.seasonPlans(9);
      await client.saveSeasonPlan(9, {
        'name': 'Season',
        'revision': 2,
        'event_ids': [11],
      }, id: 6);
      await client.familyCalendar('2026-10');
      await client.saveFamilyCalendarEntry({
        'title': 'Private',
        'child_user_id': 12,
        'club_id': 7,
      });
      await client.respondFamilyCalendar(11, 12, {
        'status': 'yes',
        'consent_accepted': true,
      });
      expect(transport.requests.map((r) => '${r.method} ${r.path}'), [
        'GET /api/v1/planning/workspace',
        'GET /api/v1/clubs/7/competitions',
        'POST /api/v1/clubs/7/competitions',
        'PUT /api/v1/competitions/4',
        'GET /api/v1/competitions/4',
        'POST /api/v1/competitions/4/components/roster',
        'POST /api/v1/competitions/4/registrations',
        'POST /api/v1/competitions/4/registrations/5/eligibility',
        'POST /api/v1/competitions/4/tournament',
        'PUT /api/v1/competitions/4/matches/8',
        'GET /api/v1/teams/9/season-plans',
        'PUT /api/v1/teams/9/season-plans/6',
        'GET /api/v1/family-calendar',
        'POST /api/v1/family-calendar/entries',
        'POST /api/v1/family-calendar/events/11/people/12/response',
      ]);
      expect(transport.requests[11].body!['revision'], 2);
      expect(transport.requests[12].query, {'month': '2026-10'});
      expect(
        transport.requests.last.headers['Authorization'],
        'Bearer test-token',
      );
    },
  );

  test(
    'private planning calls are never queued and mutations never retry',
    () async {
      for (final path in [
        '/api/v1/clubs/7/competitions',
        '/api/v1/competitions/4/registrations',
        '/api/v1/teams/9/season-plans',
        '/api/v1/family-calendar/entries',
      ]) {
        final inner = _FailingTransport();
        final transport = AirmiusQueuedTransport(
          inner: inner,
          timeout: const Duration(seconds: 1),
        );
        final request = AirmiusApiRequest(
          method: 'POST',
          path: path,
          body: {'title': 'Private'},
        );
        expect((await transport.send(request)).statusCode, 599);
        expect(inner.calls, 1);
        transport.offline = true;
        expect((await transport.send(request)).statusCode, 503);
        expect(transport.queue, isEmpty);
        expect(inner.calls, 1);
      }
    },
  );

  testWidgets('competition create edit save and GET reload persist', (
    tester,
  ) async {
    final transport = _PlanningTransport();
    await _pump(tester, transport, const CompetitionPlanningScreen());
    await tester.tap(find.byTooltip('Create'));
    await tester.pumpAndSettle();
    await _enter(tester, 'Name', 'October Cup');
    await tester.tap(find.byTooltip('Save'));
    await tester.pumpAndSettle();
    expect(find.text('October Cup'), findsOneWidget);
    expect(transport.competitions.single['name'], 'October Cup');
    await tester.tap(find.text('October Cup'));
    await tester.pumpAndSettle();
    await tester.tap(
      find.descendant(
        of: find.byType(AppBar),
        matching: find.byIcon(Icons.edit_outlined),
      ),
    );
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    expect(find.byType(PlanningEditor), findsOneWidget);
    await _enter(tester, 'Name', 'October Cup Updated');
    await tester.tap(find.byTooltip('Save'));
    await tester.pumpAndSettle();
    expect(find.text('October Cup Updated'), findsOneWidget);
    expect(
      transport.requests.any(
        (r) => r.method == 'PUT' && r.path == '/api/v1/competitions/4',
      ),
      isTrue,
    );
    expect(
      transport.requests
          .where((r) => r.method == 'GET' && r.path == '/api/v1/competitions/4')
          .length,
      greaterThan(1),
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets(
    'season saves phases sport period event links and revision on edit',
    (tester) async {
      final transport = _PlanningTransport();
      await _pump(tester, transport, const SeasonPlanningScreen());
      await tester.tap(find.byTooltip('Create'));
      await tester.pumpAndSettle();
      await _enter(tester, 'Name', 'Autumn Season');
      await _enter(tester, 'Phase', 'Preparation');
      await tester.ensureVisible(find.text('Team training'));
      await tester.tap(find.text('Team training'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Save'));
      await tester.pumpAndSettle();
      expect(find.text('Autumn Season'), findsOneWidget);
      expect(transport.plans.single['sport_year_period_id'], 2);
      expect(transport.plans.single['event_ids'], [11]);
      expect(transport.plans.single['phases'].single['name'], 'Preparation');
      await tester.tap(find.byTooltip('Edit'));
      await tester.pumpAndSettle();
      await _enter(tester, 'Name', 'Season Updated');
      await tester.tap(find.byTooltip('Save'));
      await tester.pumpAndSettle();
      final update = transport.requests.lastWhere((r) => r.method == 'PUT');
      expect(update.body!['revision'], 1);
      expect(transport.plans.single['revision'], 2);
      expect(find.text('Season Updated'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'private family appointment saves reloads edits and scopes child RSVP',
    (tester) async {
      final transport = _PlanningTransport();
      await _pump(tester, transport, const FamilyCalendarScreen());
      await tester.tap(find.byTooltip('Create'));
      await tester.pumpAndSettle();
      await _enter(tester, 'Title', 'Dentist');
      await tester.tap(find.byTooltip('Save'));
      await tester.pumpAndSettle();
      expect(find.text('Dentist'), findsOneWidget);
      expect(transport.entries.single['child_user_id'], isNull);
      await tester.ensureVisible(find.byTooltip('Edit'));
      await tester.tap(find.byTooltip('Edit'));
      await tester.pumpAndSettle();
      await _enter(tester, 'Title', 'Dentist updated');
      await tester.tap(find.byTooltip('Save'));
      await tester.pumpAndSettle();
      expect(find.text('Dentist updated'), findsOneWidget);
      await tester.tap(find.byTooltip('Response'));
      await tester.pumpAndSettle();
      await tester.tap(find.byType(DropdownButtonFormField<dynamic>).first);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Yes').last);
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Save'));
      await tester.pumpAndSettle();
      expect(find.text('Accept the required conditions'), findsOneWidget);
      expect(
        transport.requests.where((r) => r.path.endsWith('/response')),
        isEmpty,
      );
      await tester.ensureVisible(
        find.text('I accept the participation requirements'),
      );
      await tester.tap(find.text('I accept the participation requirements'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(
        find.text('I accept the participation consent'),
      );
      await tester.tap(find.text('I accept the participation consent'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Save'));
      await tester.pumpAndSettle();
      final response = transport.requests.lastWhere(
        (r) => r.path.endsWith('/response'),
      );
      expect(
        response.path,
        '/api/v1/family-calendar/events/11/people/12/response',
      );
      expect(response.body!['consent_version'], 'v2');
      expect(response.body!['requirements_accepted'], isTrue);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'read-only planning hides mutation controls and forbidden GET retries',
    (tester) async {
      final transport = _PlanningTransport()..readOnly = true;
      await _pump(tester, transport, const CompetitionPlanningScreen());
      expect(find.text('Read only'), findsOneWidget);
      expect(find.byTooltip('Create'), findsNothing);
      transport.failure = 403;
      await tester.tap(find.byTooltip('Reload'));
      await tester.pumpAndSettle();
      expect(
        find.text('You do not have access to this action.'),
        findsOneWidget,
      );
      expect(find.byTooltip('Create'), findsNothing);
      transport.failure = null;
      await tester.tap(find.byTooltip('Reload'));
      await tester.pumpAndSettle();
      expect(find.text('Read only'), findsOneWidget);
    },
  );

  testWidgets(
    'validation retains draft; conflict and unknown save require reload',
    (tester) async {
      for (final code in [422, 409, 599, 401]) {
        await tester.pumpWidget(const SizedBox());
        final transport = _PlanningTransport();
        await _pump(tester, transport, const CompetitionPlanningScreen());
        await tester.tap(find.byTooltip('Create'));
        await tester.pumpAndSettle();
        await _enter(tester, 'Name', 'Preserved draft');
        transport.failure = code;
        await tester.tap(find.byTooltip('Save'));
        await tester.pumpAndSettle();
        expect(find.text('Preserved draft'), findsOneWidget);
        if (code == 422) {
          expect(find.text('name: Invalid name'), findsOneWidget);
          expect(
            tester
                .widget<IconButton>(
                  find
                      .ancestor(
                        of: find.byIcon(Icons.save_outlined),
                        matching: find.byType(IconButton),
                      )
                      .first,
                )
                .onPressed,
            isNotNull,
          );
        } else {
          expect(
            tester
                .widget<IconButton>(
                  find
                      .ancestor(
                        of: find.byIcon(Icons.save_outlined),
                        matching: find.byType(IconButton),
                      )
                      .first,
                )
                .onPressed,
            isNull,
          );
          expect(find.widgetWithText(TextButton, 'Reload'), findsOneWidget);
        }
        expect(transport.requests.where((r) => r.method == 'POST').length, 1);
      }
    },
  );

  testWidgets('match result persists through service endpoint and reload', (
    tester,
  ) async {
    final transport = _PlanningTransport();
    transport.competitions.add({
      'id': 4,
      'name': 'Cup',
      'status': 'open',
      'starts_on': '2026-10-01',
      'ends_on': '2026-10-31',
    });
    await _pump(
      tester,
      transport,
      CompetitionPlanningDetailScreen(competitionId: 4, club: transport.club),
    );
    await tester.ensureVisible(find.text('#1 Alice – Bob'));
    final matchTile = find.ancestor(
      of: find.text('#1 Alice – Bob'),
      matching: find.byType(ListTile),
    );
    await tester.tap(
      find.descendant(of: matchTile, matching: find.byType(IconButton)),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byType(DropdownButtonFormField<dynamic>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Completed').last);
    await tester.pumpAndSettle();
    await _enter(tester, 'Home score', '2');
    await _enter(tester, 'Away score', '1');
    await _enter(tester, 'Reason', 'Final score');
    await tester.tap(find.byTooltip('Save'));
    await tester.pumpAndSettle();
    expect(transport.match['home_score'], 2);
    expect(transport.match['status'], 'completed');
    expect(
      transport.requests.any(
        (r) =>
            r.method == 'PUT' && r.path == '/api/v1/competitions/4/matches/8',
      ),
      isTrue,
    );
    expect(tester.takeException(), isNull);
  });

  testWidgets('venue form saves address access details and notes', (
    tester,
  ) async {
    final transport = _PlanningTransport();
    transport.competitions.add({
      'id': 4,
      'name': 'Venue Cup',
      'status': 'open',
      'starts_on': '2026-10-13',
      'ends_on': '2026-10-14',
    });
    await _pump(
      tester,
      transport,
      CompetitionPlanningDetailScreen(competitionId: 4, club: transport.club),
    );
    await tester.ensureVisible(find.text('Venues'));
    final venueHeading = find.ancestor(
      of: find.text('Venues'),
      matching: find.byType(Row),
    );
    await tester.tap(
      find.descendant(of: venueHeading, matching: find.byType(IconButton)),
    );
    await tester.pumpAndSettle();
    await _enter(tester, 'Name', 'Sportpark Mitte');
    await _enter(tester, 'Street', 'Main Street');
    await tester.scrollUntilVisible(
      find.byWidgetPredicate(
        (widget) =>
            widget is TextField &&
            widget.decoration?.labelText == 'Venue notes',
      ),
      250,
      scrollable: find.byType(Scrollable).last,
    );
    await _enter(tester, 'Arrival, access and parking', 'Team bus via gate 2');
    await _enter(tester, 'Venue notes', 'Artificial turf, no studs');
    await tester.tap(find.byTooltip('Save'));
    await tester.pumpAndSettle();

    final request = transport.requests.lastWhere(
      (request) => request.path.endsWith('/components/venues'),
    );
    expect(request.body?['street'], 'Main Street');
    expect(request.body?['access_notes'], 'Team bus via gate 2');
    expect(request.body?['venue_notes'], 'Artificial turf, no studs');
    expect(
      transport.venues.single['metadata']['venue_notes'],
      'Artificial turf, no studs',
    );
    final savedNotes = find.textContaining('Artificial turf, no studs');
    await tester.scrollUntilVisible(
      savedNotes,
      200,
      scrollable: find.byType(Scrollable).last,
    );
    expect(savedNotes, findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pump(
  WidgetTester tester,
  _PlanningTransport transport,
  Widget screen,
) async {
  tester.view.resetPhysicalSize();
  tester.view.physicalSize = const Size(390, 844);
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
    AirmiusServicesScope(
      container: services,
      child: AirmiusScope(
        language: AirmiusLanguage.en,
        setLanguage: (_) {},
        child: MaterialApp(home: screen),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

Future<void> _enter(WidgetTester tester, String label, String value) async {
  final field = find.byWidgetPredicate((w) {
    if (w is TextField) return w.decoration?.labelText == label;
    return false;
  });
  await tester.ensureVisible(field.first);
  await tester.enterText(field.first, value);
  await tester.pumpAndSettle();
}

class _FailingTransport implements AirmiusApiTransport {
  int calls = 0;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    calls++;
    throw StateError('Connection lost');
  }
}

class _PlanningTransport implements AirmiusApiTransport {
  final requests = <AirmiusApiRequest>[];
  final competitions = <AirmiusJson>[],
      plans = <AirmiusJson>[],
      entries = <AirmiusJson>[],
      venues = <AirmiusJson>[];
  int? failure;
  bool readOnly = false;
  final match = <String, dynamic>{
    'id': 8,
    'match_number': 1,
    'home_label': 'Alice',
    'away_label': 'Bob',
    'status': 'planned',
    'home_score': null,
    'away_score': null,
  };
  AirmiusJson get club => {
    'id': 7,
    'name': 'Test Club',
    'can_edit_competitions': !readOnly,
    'can_delete_competitions': false,
    'members': [
      {'id': 12, 'name': 'Child'},
    ],
    'teams': [
      {
        'id': 9,
        'name': 'Youth',
        'sport_year_period_id': 2,
        'can_edit_season': !readOnly,
      },
    ],
    'periods': [
      {
        'id': 2,
        'name': '2026',
        'starts_on': '2026-01-01',
        'ends_on': '2026-12-31',
      },
    ],
  };
  final event = <String, dynamic>{
    'id': 11,
    'title': 'Team training',
    'start_time': '2026-10-14T12:00:00Z',
    'sport_year_period_id': 2,
  };
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest r) async {
    requests.add(r);
    if (failure != null) {
      return AirmiusApiResponse(
        statusCode: failure!,
        body: failure == 422 ? '{"errors":{"name":["Invalid name"]}}' : '{}',
      );
    }
    dynamic result = {};
    if (r.path.endsWith('/planning/workspace')) {
      result = {
        'clubs': [club],
      };
    } else if (r.path == '/api/v1/clubs/7/competitions') {
      if (r.method == 'POST') {
        final row = <String, dynamic>{...r.body!, 'id': 4};
        competitions.add(row);
        result = row;
      } else {
        result = competitions;
      }
    } else if (r.path == '/api/v1/competitions/4') {
      if (r.method == 'PUT' && competitions.isNotEmpty) {
        competitions[0].addAll(r.body!);
      }
      result = {
        ...(competitions.firstOrNull ?? {}),
        'id': 4,
        'can_edit': !readOnly,
        'can_pay_fees': false,
        'can_confirm': false,
        'classes': [],
        'venues': venues,
        'roster_entries': [],
        'registrations': [],
        'tournament_matches': [match],
        'standings': [],
        'lineups': [],
      };
    } else if (r.path == '/api/v1/competitions/4/components/venues' &&
        r.method == 'POST') {
      final body = Map<String, dynamic>.from(r.body!);
      final metadata = {
        for (final key in [
          'venue_type',
          'venue_contact_name',
          'venue_contact_phone',
          'website',
          'access_notes',
          'venue_notes',
        ])
          if ('${body[key] ?? ''}'.isNotEmpty) key: body.remove(key),
      };
      final venue = <String, dynamic>{...body, 'id': 3, 'metadata': metadata};
      venues.add(venue);
      result = venue;
    } else if (r.path.endsWith('/matches/8')) {
      match.addAll(r.body!);
      result = match;
    } else if (r.path.contains('/season-plans')) {
      if (r.method == 'POST') {
        final row = <String, dynamic>{
          ...r.body!,
          'id': 6,
          'revision': 1,
          'events': (r.body!['event_ids'] as List).isEmpty ? [] : [event],
        };
        plans.add(row);
        result = row;
      } else if (r.method == 'PUT' && plans.isNotEmpty) {
        plans[0].addAll({...r.body!, 'revision': plans[0]['revision'] + 1});
        result = plans[0];
      } else {
        result = {
          'plans': plans,
          'events': [event],
          'can_edit': !readOnly,
        };
      }
    } else if (r.path == '/api/v1/family-calendar') {
      result = {
        'self': {'id': 7, 'name': 'Parent'},
        'children': [
          {'id': 12, 'name': 'Child', 'club_id': 7},
        ],
        'entries': entries,
        'events': [
          {
            'id': 11,
            'person_id': 12,
            'person_name': 'Child',
            'title': 'Training',
            'starts_at': '${r.query['month']}-14T12:00:00Z',
            'can_respond': true,
            'status': 'planned',
            'response_status': 'maybe',
            'participation_requirements': 'Sports shoes',
            'participation_consent_required': true,
            'participation_consent_version': 'v2',
          },
        ],
      };
    } else if (r.path == '/api/v1/family-calendar/entries' &&
        r.method == 'POST') {
      final row = <String, dynamic>{...r.body!, 'id': 15};
      entries.add(row);
      result = row;
    } else if (r.path == '/api/v1/family-calendar/entries/15' &&
        r.method == 'PUT') {
      entries[0].addAll(r.body!);
      result = entries[0];
    }
    return AirmiusApiResponse(
      statusCode: 200,
      body: jsonEncode({'data': result}),
    );
  }
}
