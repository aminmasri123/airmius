import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:airmius/core/airmius_api_models.dart';
import 'package:airmius/core/airmius_deep_links.dart';
import 'package:airmius/navigation/airmius_deep_link_navigator.dart';
import 'package:airmius/screens/team_detail_screen.dart';
import 'package:airmius/core/airmius_l10n.dart';
import 'package:airmius/widgets/team_role_offers_panel.dart';

void main() {
  setUpAll(() => initializeDateFormatting('de'));
  final offer = AirmiusTeamRoleOffer.fromJson({
    'id': 9,
    'recipient_id': 12,
    'recipient_name': 'Testmitglied',
    'role': 'Coach',
    'previous_role': 'Player',
    'expires_at': '2026-12-04T12:00:00Z',
    'can_respond': true,
    'can_cancel': false,
  });
  test('notification opens the team containing the pending offer', () {
    final alert = AirmiusNotification.fromJson({
      'id': 1,
      'type': 'team.role_offered',
      'data': {
        'url': '/teams/3',
        'mobile_url': 'airmius://teams/3',
        'team_id': 3,
        'role_offer_id': 9,
      },
    });
    final target = const AirmiusDeepLinkResolver().resolve(alert.actionUrl!);
    expect(target.type, AirmiusDeepLinkTargetType.team);
    expect(
      (AirmiusDeepLinkNavigator.screenFor(target) as TeamDetailScreen).teamId,
      3,
    );
  });
  test('pending role is independent from active member role', () {
    final team = AirmiusTeam.fromJson({
      'id': 1,
      'club_id': 2,
      'name': 'Test',
      'can_update_member_roles': false,
      'role_offers': [
        {
          'id': 9,
          'recipient_id': 12,
          'role': 'Coach',
          'previous_role': 'Player',
          'can_respond': true,
        },
      ],
      'users': [
        {'id': 12, 'name': 'Test', 'team_role': 'Player'},
      ],
    });
    expect(team.users.single.role, 'Player');
    expect(team.roleOffers.single.role, 'Coach');
    expect(team.roleOffers.single.canRespond, true);
    expect(team.canUpdateMemberRoles, false);
  });
  testWidgets('recipient can accept or decline and sees active role', (
    tester,
  ) async {
    String? decision;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: TeamRoleOffersPanel(
            offers: [offer],
            language: AirmiusLanguage.de,
            onRespond: (o, d) => decision = d,
          ),
        ),
      ),
    );
    expect(find.text('Aktive Rolle: Spieler'), findsOneWidget);
    expect(find.text('Testmitglied · Trainer'), findsOneWidget);
    await tester.tap(find.text('Annehmen'));
    expect(decision, 'accept');
    await tester.tap(find.text('Ablehnen'));
    expect(decision, 'decline');
    expect(find.text('Angebot zurücknehmen'), findsNothing);
  });
  testWidgets('manager can cancel and busy disables responses', (tester) async {
    final managerOffer = AirmiusTeamRoleOffer(
      id: 9,
      recipientId: 12,
      recipientName: 'Test',
      role: 'Coach',
      previousRole: 'Player',
      expiresAt: '2026-12-04',
      canCancel: true,
    );
    String? decision;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: TeamRoleOffersPanel(
            offers: [managerOffer],
            language: AirmiusLanguage.de,
            onRespond: (o, d) => decision = d,
          ),
        ),
      ),
    );
    expect(find.text('Annehmen'), findsNothing);
    await tester.tap(find.text('Angebot zurücknehmen'));
    expect(decision, 'cancel');
    decision = null;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: TeamRoleOffersPanel(
            offers: [offer],
            language: AirmiusLanguage.de,
            busy: true,
            onRespond: (o, d) => decision = d,
          ),
        ),
      ),
    );
    await tester.tap(find.text('Annehmen'));
    expect(decision, isNull);
  });
}
