import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';
import 'admin_center_screen.dart';
import 'badges_center_screen.dart';
import 'blog_media_center_screen.dart';
import 'carpool_center_screen.dart';
import 'club_cockpit_screen.dart';
import 'club_membership_admin_screen.dart';
import 'commerce_center_screen.dart';
import 'community_center_screen.dart';
import 'module_item_detail_screen.dart';
import 'file_manager_screen.dart';
import 'event_management_screen.dart';
import 'feed_center_screen.dart';
import 'gamification_rules_screen.dart';
import 'learning_screen.dart';
import 'marketplace_screen.dart';
import 'maturity_center_screen.dart';
import 'media_guidelines_screen.dart';
import 'nutrition_center_screen.dart';
import 'guardian_center_screen.dart';
import 'outfit_subscription_center_screen.dart';
import 'roles_permissions_screen.dart';
import 'settings_center_screen.dart';
import 'sponsors_center_screen.dart';
import 'sport_map_center_screen.dart';
import 'sports_center_screen.dart';
import 'subscription_center_screen.dart';
import 'teams_center_screen.dart';
import 'training_center_screen.dart';
import 'trainer_cockpit_screen.dart';
import 'users_center_screen.dart';
import 'workspace_center_screen.dart';

class ModuleSpecificSection extends StatelessWidget {
  const ModuleSpecificSection({super.key, required this.module, required this.requestedClubIds, this.onWithdrawClub});

  final ModuleDefinition module;
  final Set<int> requestedClubIds;
  final ValueChanged<ClubSummary>? onWithdrawClub;

  @override
  Widget build(BuildContext context) {
    return switch (module.title) {
      'Arbeitsbereiche' => const _WorkspaceSection(),
      'Vereins-Cockpit' => const _ClubCockpitSection(),
      'Vereine & Teams' => _ClubTeamSection(requestedClubIds: requestedClubIds, onWithdrawClub: onWithdrawClub),
      'Teams' => const _TeamsSection(),
      'Rollen & Rechte' => const _RolesPermissionsSection(),
      'Sportarten' => const _SportsSection(),
      'Feed' => const _FeedSection(),
      'Events' => const _EventsSection(),
      'Events & Training' => const _TrainingSection(),
      'Trainer-Cockpit' => const _TrainerCockpitSection(),
      'Ernaehrung' => const _NutritionSection(),
      'Sportkarte' => const _SportMapSection(),
      'Freunde' => const _FriendsSection(),
      'Nachrichten' => const _ChatSection(),
      'Fahrgemeinschaften' => const _CarpoolSection(),
      'Dateien' => const _FilesSection(),
      'Badges' => const _BadgesSection(),
      'Gamification-Regeln' => const _GamificationRulesSection(),
      'Kurse' => const _CoursesSection(),
      'Marketplace' => const _MarketplaceSection(),
      'Commerce' => const _CommerceSection(),
      'Sponsoren' => const _SponsorsSection(),
      'Medienrichtlinien' => const _MediaGuidelinesSection(),
      'Blog & Medien' => const _BlogMediaSection(),
      'Nutzer' => const _UsersSection(),
      'Abos & Rechnungen' => const _SubscriptionSection(),
      'Eltern & Jugendschutz' => const _GuardianSection(),
      'Altersfreigaben' => const _MaturitySection(),
      'Outfit-Abos' => const _OutfitSection(),
      'Einstellungen' => const _SettingsSection(),
      'Admin' => const _AdminSection(),
      _ => const _GenericSection(),
    };
  }
}

class _WorkspaceSection extends StatelessWidget {
  const _WorkspaceSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WorkspaceCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.dashboard_customize_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Arbeitsbereiche oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Gastseite, Dashboard, Vereinsbereich, Trainerbereich, Rollen und Einladungen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _WideStatus(title: 'Gastseite', body: 'Oeffentliche Ansicht, Vorschau und externe Links.', icon: Icons.open_in_new),
      const _WideStatus(title: 'Vereinsbereich', body: 'Admin-Rollen, Teams, Mitglieder, Beitraege und Vereinsprofil.', icon: Icons.apartment_outlined),
      const _WideStatus(title: 'Trainerbereich', body: 'Trainingsplaene, Feedback, Teilnehmer und Termine.', icon: Icons.sports_outlined),
    ]);
  }
}

class _ClubCockpitSection extends StatelessWidget {
  const _ClubCockpitSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubCockpitScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.apartment_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Vereins-Cockpit oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Profil, Teams, Mitglieder, Beitraege, Dokumente und Sichtbarkeit verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.assignment_ind_outlined, title: 'Mitgliedschaftsanfrage', body: 'Formular, Dokumente und Zahlung pruefen.', trailing: 'Offen'),
      const _ListLine(icon: Icons.folder_outlined, title: 'Vereinsdokumente', body: 'Datenschutz, Beitragsordnung und Regeln verknuepfen.', trailing: '3'),
      const _WideStatus(title: 'Public Sichtbarkeit', body: 'Vereinsprofil, Teams und sichtbare Beitraege fuer Gastseite steuern.', icon: Icons.visibility_outlined),
    ]);
  }
}

class _ClubTeamSection extends StatelessWidget {
  const _ClubTeamSection({required this.requestedClubIds, required this.onWithdrawClub});

  final Set<int> requestedClubIds;
  final ValueChanged<ClubSummary>? onWithdrawClub;

  @override
  Widget build(BuildContext context) {
    final requested = demoClubs.where((club) => requestedClubIds.contains(club.id)).toList();
    return _StackedPanels(children: [
      AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Eyebrow('Mitgliedschaften'),
            const SizedBox(height: 10),
            if (requested.isEmpty)
              const Text('Noch keine offenen Vereinsanfragen.', style: TextStyle(color: AirmiusColors.muted)),
            for (final club in requested) ...[
              _ListLine(icon: Icons.pending_actions_outlined, title: club.name, body: '${club.city} - Anfrage gesendet', trailing: 'Offen'),
              AirmiusButton(label: 'Zurueckziehen', icon: Icons.undo_outlined, danger: true, onPressed: onWithdrawClub == null ? null : () => onWithdrawClub!(club)),
            ],
          ],
        ),
      ),
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubMembershipAdminScreen())),
        child: const Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.manage_accounts_outlined, color: AirmiusColors.blue, size: 26),
            SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Mitgliedschaften verwalten', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  SizedBox(height: 4),
                  Text('Anfragen, Formularfelder, Zahlungsregeln und Dokumente konfigurieren.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
      const _WideStatus(title: 'Teambeitritt', body: 'Teamprofile, Einladungen und Join-Requests werden als eigene Karten dargestellt.', icon: Icons.groups_2_outlined),
    ]);
  }
}

class _FeedSection extends StatelessWidget {
  const _FeedSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FeedCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.dynamic_feed_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Echten Feed oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('API-Feed mit echten Beitraegen, Storys, Kommentaren und Reaktionen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FeedCenterScreen())),
        child: const Row(children: [
          CircleAvatar(radius: 26, backgroundColor: AirmiusColors.blue, child: Icon(Icons.add, color: Colors.white)),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Story oder Beitrag erstellen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Keine Fake-Storys: Erstellen laeuft ueber den echten Feed und die Laravel-API.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _WideStatus(title: 'Feed-Daten', body: 'Dieser Modulbereich zeigt keine Demo-Posts mehr. Oeffne den Feed fuer echte API-Daten.', icon: Icons.verified_outlined),
    ]);
  }
}

class _EventsSection extends StatelessWidget {
  const _EventsSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EventManagementScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.event_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Eventverwaltung oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Kalender, Teilnahme, Warteliste, Eventchat, Kommentare und Erinnerungen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.event_available_outlined, title: 'Intervalltraining', body: 'Morgen 18:30 - Teilnahme offen.', trailing: 'Offen'),
      const _ListLine(icon: Icons.pending_actions_outlined, title: 'Warteliste', body: 'Sommerlauf hat zwei wartende Mitglieder.', trailing: '2'),
      const _WideStatus(title: 'Eventchat & Absagen', body: 'Kommentare, Eventchat, Teilnehmerliste und Absage-Status mobil verwalten.', icon: Icons.forum_outlined),
    ]);
  }
}

class _TeamsSection extends StatelessWidget {
  const _TeamsSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TeamsCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.groups_2_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Teams Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Teamprofile, Kader, Rollen, Einladungen, Beitritte, Kalender und Teamchat.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _WideStatus(title: 'Teamprofil', body: 'Name, Sportart, Sichtbarkeit, Beschreibung, Trainer und Teamstatus.', icon: Icons.groups_2_outlined),
      const _ListLine(icon: Icons.badge_outlined, title: 'Kader verwalten', body: 'Mitglieder, Rollen, Captain, Trainer und Einladungen.', trailing: '11'),
      const _ListLine(icon: Icons.person_add_alt_1_outlined, title: 'Teambeitritt', body: 'Beitrittsanfragen, Einladungscodes und Freigaben.', trailing: '3 offen'),
      const _WideStatus(title: 'Teamkalender', body: 'Training, Events, Chat und Dateien direkt am Team verknuepfen.', icon: Icons.calendar_month_outlined),
    ]);
  }
}

class _RolesPermissionsSection extends StatelessWidget {
  const _RolesPermissionsSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => RolesPermissionsScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.admin_panel_settings_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Rollen & Rechte oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Rollenmatrix, Permissions, Sicherheitsregeln und Audit Trail verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.lock_open_outlined, title: 'Berechtigungen', body: 'Lesen, erstellen, bearbeiten, freigeben, exportieren und moderieren.', trailing: '42'),
      const _ListLine(icon: Icons.security_outlined, title: 'Sicherheitsregeln', body: 'Jugendschutz, sensible Daten und Finanzrechte getrennt absichern.', trailing: 'Sicher'),
      const _WideStatus(title: 'Audit Trail', body: 'Aenderungen an Rollen und Rechten nachvollziehbar anzeigen.', icon: Icons.history_outlined),
    ]);
  }
}

class _SportsSection extends StatelessWidget {
  const _SportsSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportsCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.sports_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Sportarten Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Sportprofile, Disziplinen, Leistungsdaten, Ziele und KI-Plan-Voraussetzungen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.directions_run_outlined, title: 'Laufen', body: 'Pace, Distanz, Pulsbereiche und Trainingsziel.', trailing: 'Aktiv'),
      const _ListLine(icon: Icons.fitness_center_outlined, title: 'Krafttraining', body: 'Belastung, Volumen, Uebungen und Regeneration.', trailing: 'Profil'),
      const _WideStatus(title: 'KI-Plan-Voraussetzung', body: 'Fehlende Leistungsdaten vor KI-Trainingsplaenen sichtbar machen.', icon: Icons.auto_awesome_outlined),
    ]);
  }
}

class _TrainingSection extends StatelessWidget {
  const _TrainingSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainingCenterScreen())),
        child: const Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.event_available_outlined, color: AirmiusColors.blue, size: 26),
            SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Training Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Events, Plaene, Logs, Teilnahme und Feedback verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
            Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
      const _WideStatus(title: 'Naechstes Event', body: 'Intervalltraining - Morgen 18:30 - Sportplatz', icon: Icons.event_available_outlined),
      const _ProgressPanel(title: 'Wochenplan', value: 0.62, label: '4 von 7 Einheiten geplant'),
      const _ListLine(icon: Icons.fitness_center, title: 'Kraft & Stabilitaet', body: '45 Minuten - Trainerfeedback offen', trailing: 'Plan'),
      const _ListLine(icon: Icons.directions_run, title: 'Langer Lauf', body: '12 km - Pulsbereich 2', trailing: 'Sonntag'),
    ]);
  }
}


class _TrainerCockpitSection extends StatelessWidget {
  const _TrainerCockpitSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => TrainerCockpitScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.sports_score_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Trainer-Cockpit oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Athleten, Wochenaktionen, Feedback und Belastungsrisiken.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.rate_review_outlined, title: 'Feedback offen', body: '2 Trainingseinheiten warten auf Antwort.', trailing: '2'),
      const _ListLine(icon: Icons.warning_amber_outlined, title: 'Risiko-Athlet', body: 'Hohe Belastung und wenig Regeneration.', trailing: 'Pruefen'),
      const _WideStatus(title: 'Wochenplan freigeben', body: 'Trainingsplan pruefen, duplizieren und fuer Teams sichtbar machen.', icon: Icons.calendar_month_outlined),
    ]);
  }
}
class _NutritionSection extends StatelessWidget {
  const _NutritionSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NutritionCenterScreen())),
        child: const Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.restaurant_menu_outlined, color: AirmiusColors.blue, size: 26),
            SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Ernaehrungscenter oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Makros, Mahlzeiten, Wasser, Barcode und KI-Analyse.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
            Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
      const _ProgressPanel(title: 'Tagesziel Kalorien', value: 0.74, label: '1840 / 2500 kcal'),
      const _ProgressPanel(title: 'Wasser', value: 0.50, label: '1.5 / 3.0 Liter'),
      const _ListLine(icon: Icons.breakfast_dining_outlined, title: 'Fruehstueck', body: 'Haferflocken, Banane, Protein', trailing: '520 kcal'),
      const _ListLine(icon: Icons.camera_alt_outlined, title: 'KI-Mahlzeitenanalyse', body: 'Foto aufnehmen und Makros schaetzen lassen.', trailing: 'Beta'),
    ]);
  }
}

class _SportMapSection extends StatelessWidget {
  const _SportMapSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SportMapCenterScreen())),
        child: const Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.map_outlined, color: AirmiusColors.blue, size: 26),
            SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Sportkarte oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Routen, Tracks, Orte, Live Track und Vorschlaege.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
            Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
      AirmiusPanel(
        gradient: true,
        child: Container(
          height: 180,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            gradient: LinearGradient(colors: [AirmiusColors.blueDeep.withValues(alpha: 0.45), AirmiusColors.green.withValues(alpha: 0.18)]),
          ),
          child: const Center(child: Icon(Icons.map_outlined, color: AirmiusColors.text, size: 56)),
        ),
      ),
      const _ListLine(icon: Icons.route_outlined, title: 'Saar Runde', body: '8.4 km - oeffentlich', trailing: 'Route'),
      const _ListLine(icon: Icons.place_outlined, title: 'Sportplatz Kleinblittersdorf', body: 'Trainingsort - Verein', trailing: 'Ort'),
      const _ListLine(icon: Icons.gps_fixed, title: 'Live Track', body: 'Track starten, Punkte speichern und abschliessen.', trailing: 'Neu'),
    ]);
  }
}

class _FriendsSection extends StatelessWidget {
  const _FriendsSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.people_alt_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Community Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Freunde, Einladungen, Empfehlungen und gemeinsame Vereine.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.person_add_alt, title: 'Freundschaftsanfrage', body: 'Max Mustermann moechte sich verbinden.', trailing: 'Offen'),
      const _ListLine(icon: Icons.recommend_outlined, title: 'Empfehlung', body: 'Gemeinsamer Verein: Airmius Running Club', trailing: 'Match'),
      const _WideStatus(title: 'Einladungslink', body: 'Freunde koennen per Token-Link eingeladen werden.', icon: Icons.link_outlined),
    ]);
  }
}

class _ChatSection extends StatelessWidget {
  const _ChatSection();

  @override
  Widget build(BuildContext context) {
    return const _StackedPanels(children: [
      _ListLine(icon: Icons.chat_bubble_outline, title: 'ZBB Admins', body: 'Neue Mitgliedschaftsanfrage eingegangen.', trailing: 'Jetzt'),
      _ListLine(icon: Icons.group_outlined, title: 'Training Gruppe', body: 'Bitte morgen Laufschuhe fuer Bahn mitbringen.', trailing: '2'),
      _MessageComposer(),
    ]);
  }
}


class _CarpoolSection extends StatelessWidget {
  const _CarpoolSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CarpoolCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.directions_car_filled_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Fahrgemeinschaften oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Mitfahrten, Routen, Treffpunkte und Sicherheit verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.directions_car_filled_outlined, title: 'Zum Training fahren', body: '2 freie Plaetze - Heute 18:00', trailing: 'Angebot'),
      const _ListLine(icon: Icons.account_circle_outlined, title: 'Mitfahrt gesucht', body: 'Saarbruecken Hbf -> ZBB', trailing: 'Gesuch'),
      const _WideStatus(title: 'Sicherheit & Treffpunkte', body: 'Freigaben, Sichtbarkeit und Meldungen fuer sichere Fahrten.', icon: Icons.verified_user_outlined),
    ]);
  }
}
class _FilesSection extends StatelessWidget {
  const _FilesSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FileManagerScreen())),
        child: const Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.cloud_upload_outlined, color: AirmiusColors.blue, size: 26),
            SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Dateimanager oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  SizedBox(height: 4),
                  Text('Ordner, Uploads, Freigaben und Vereinsdokumente verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
      const _ListLine(icon: Icons.folder_outlined, title: 'Vereinsdokumente', body: 'Datenschutz, Regeln, Beitragsordnung', trailing: '3'),
      const _ListLine(icon: Icons.picture_as_pdf_outlined, title: 'Datenschutz.pdf', body: 'Verknuepft mit Mitgliedsantrag', trailing: 'Pflicht'),
      const _ListLine(icon: Icons.description_outlined, title: 'Beitragsordnung.docx', body: 'Sichtbar fuer Antragsteller', trailing: 'Pflicht'),
    ]);
  }
}

class _BadgesSection extends StatelessWidget {
  const _BadgesSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BadgesCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.workspace_premium_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Badges Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Gamification, Fortschritt, Regeln und Auszeichnungen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ProgressPanel(title: 'Naechstes Badge', value: 0.82, label: '82% bis Vereinsstarter'),
      const _BadgeGrid(),
    ]);
  }
}

class _GamificationRulesSection extends StatelessWidget {
  const _GamificationRulesSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GamificationRulesScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.rule_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Gamification-Regeln oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('XP, Badges, Streaks, Leaderboard und Datenschutzregeln verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.workspace_premium_outlined, title: 'Vereinsstarter', body: 'Badge nach erster angenommenen Vereinsmitgliedschaft.', trailing: 'Aktiv'),
      const _ListLine(icon: Icons.local_fire_department_outlined, title: 'Trainings-Streak', body: 'XP fuer dokumentierte Trainingstage in Folge.', trailing: 'XP'),
      const _WideStatus(title: 'Leaderboard Datenschutz', body: 'Anzeige nur mit Opt-in und passender Profil-Sichtbarkeit.', icon: Icons.leaderboard_outlined),
    ]);
  }
}

class _CoursesSection extends StatelessWidget {
  const _CoursesSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LearningScreen())),
        child: const Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.school_outlined, color: AirmiusColors.blue, size: 26),
            SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Learning Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  SizedBox(height: 4),
                  Text('Kurse, Lektionen, Zertifikate und Lernstudio verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
      const _ListLine(icon: Icons.play_circle_outline, title: 'Grundlagen Vereinsverwaltung', body: '4 Lektionen - Zertifikat moeglich', trailing: 'Kurs'),
      const _ListLine(icon: Icons.assignment_turned_in_outlined, title: 'Datenschutz im Sportverein', body: 'Quiz und Abschlussbescheinigung', trailing: '72%'),
      const _WideStatus(title: 'Lernstudio', body: 'Trainer und Admins koennen Kurse, Lektionen und Aufgaben verwalten.', icon: Icons.school_outlined),
    ]);
  }
}

class _MarketplaceSection extends StatelessWidget {
  const _MarketplaceSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MarketplaceScreen())),
        child: const Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(Icons.storefront_outlined, color: AirmiusColors.blue, size: 26),
            SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Marketplace oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  SizedBox(height: 4),
                  Text('Produkte, Anbieter, Warenkorb, Bestellungen und Rueckgaben.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
      const _ListLine(icon: Icons.shopping_bag_outlined, title: 'Airmius Teamshirt', body: 'Groessen, Bestand und Varianten', trailing: '29,90'),
      const _ListLine(icon: Icons.receipt_long_outlined, title: 'Bestellung #A-1024', body: 'Bezahlt - Versand wird vorbereitet', trailing: 'Order'),
      const _WideStatus(title: 'Anbieterprofil', body: 'Vereine und Partner koennen Produkte und Services anbieten.', icon: Icons.storefront_outlined),
    ]);
  }
}

class _CommerceSection extends StatelessWidget {
  const _CommerceSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CommerceCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.store_mall_directory_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Commerce Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Produkte, Orders, Coupons, Inventar, Payouts und Qualitaetsfreigaben.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.receipt_long_outlined, title: 'Bestellung #A-1024', body: 'Bezahlt, Versand wird vorbereitet.', trailing: 'Order'),
      const _ListLine(icon: Icons.inventory_2_outlined, title: 'Produktqualitaet', body: 'Bilder, Beschreibung, Varianten und Fulfillment pruefen.', trailing: 'Gate'),
      const _WideStatus(title: 'Payouts & Coupons', body: 'Auszahlungen, Rabatte, Anbieterabrechnung und Shop-Regeln verwalten.', icon: Icons.payments_outlined),
    ]);
  }
}

class _SponsorsSection extends StatelessWidget {
  const _SponsorsSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorsCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.handshake_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Sponsoren Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Sponsorprofile, Pakete, Kampagnen, Kontaktanfragen und Sichtbarkeit.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.campaign_outlined, title: 'Sponsoring-Kampagne', body: 'Banner, Laufzeit, Zielgruppe und Reporting.', trailing: 'Aktiv'),
      const _ListLine(icon: Icons.inventory_2_outlined, title: 'Sponsor-Paket', body: 'Leistung, Preis, Reichweite und Verknuepfung mit Verein.', trailing: 'Paket'),
      const _WideStatus(title: 'Kontaktanfrage', body: 'Interessenten koennen Sponsoren und Vereine direkt kontaktieren.', icon: Icons.contact_mail_outlined),
    ]);
  }
}

class _MediaGuidelinesSection extends StatelessWidget {
  const _MediaGuidelinesSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MediaGuidelinesScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.policy_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Medienrichtlinien oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Bildrechte, Upload-Regeln, Guardian Consent, Sichtbarkeit und Moderation.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.photo_library_outlined, title: 'Upload-Regeln', body: 'Dateitypen, Rechtehinweis und sensible Inhalte pruefen.', trailing: 'Aktiv'),
      const _ListLine(icon: Icons.family_restroom_outlined, title: 'Guardian Consent', body: 'Medien mit Minderjaehrigen nur mit Zustimmung anzeigen.', trailing: 'Pflicht'),
      const _WideStatus(title: 'Medienfreigabe', body: 'Public, Verein, Team oder Admin-Sichtbarkeit pro Medium festlegen.', icon: Icons.visibility_outlined),
    ]);
  }
}

class _BlogMediaSection extends StatelessWidget {
  const _BlogMediaSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BlogMediaCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.article_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Blog & Medien Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Artikel, Redaktion, Medienbibliothek, Freigaben und Richtlinien verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.edit_note_outlined, title: 'Artikel planen', body: 'Titel, Teaser, Inhalt, Tags, Autor und Veroeffentlichung.', trailing: 'Entwurf'),
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MediaGuidelinesScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.policy_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Medienrichtlinien', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Upload-Regeln, Bildrechte, Altersfreigabe und Datenschutz oeffnen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          StatusPill('Pflicht'),
          SizedBox(width: 6),
          Icon(Icons.chevron_right, color: AirmiusColors.muted, size: 20),
        ]),
      ),
      const _WideStatus(title: 'Medienfreigabe', body: 'Fotos und Videos mit Vereinsregeln und Guardian-Consent verbinden.', icon: Icons.perm_media_outlined),
    ]);
  }
}

class _UsersSection extends StatelessWidget {
  const _UsersSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UsersCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.people_alt_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Nutzercenter oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Personen, Profile, Rollen, Status, Verbindungen und Moderation verwalten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.account_circle_outlined, title: 'ZBB Konto', body: 'Player - Profilvollstaendigkeit 82%', trailing: 'Aktiv'),
      const _ListLine(icon: Icons.verified_user_outlined, title: 'verein airmius', body: 'Admin - Vereinsbereich und Rollen aktiv.', trailing: 'Admin'),
      const _WideStatus(title: 'Profilmoderation', body: 'Sperren, Entsperren, Verifizieren und Datenschutzstatus pruefen.', icon: Icons.gpp_maybe_outlined),
    ]);
  }
}

class _SettingsSection extends StatelessWidget {
  const _SettingsSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SettingsCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.settings_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Einstellungen oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Profil, Sprache, Datenschutz, Push, Sicherheit und Zahlungen konfigurieren.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ProgressPanel(title: 'Profilvollstaendigkeit', value: 0.82, label: '82% - Sportprofil und Notfallkontakt fehlen'),
      const _ListLine(icon: Icons.language_outlined, title: 'Sprache', body: 'Deutsch, Englisch, Franzoesisch, Arabisch', trailing: 'DE'),
      const _ListLine(icon: Icons.privacy_tip_outlined, title: 'Datenschutz', body: 'Sichtbarkeit, Einwilligungen und Kontoexport', trailing: 'Sicher'),
      const _ListLine(icon: Icons.credit_card_outlined, title: 'Zahlungen', body: 'Abos, Rechnungen und offene Zahlungen', trailing: 'Aktiv'),
    ]);
  }
}

class _SubscriptionSection extends StatelessWidget {
  const _SubscriptionSection();

  @override
  Widget build(BuildContext context) {
    return _PlatformOpenSection(
      title: 'Abo- und Rechnungscenter oeffnen',
      body: 'Plaene, Checkouts, offene Zahlungen, Rechnungen und Banktransfer.',
      icon: Icons.receipt_long_outlined,
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SubscriptionCenterScreen())),
    );
  }
}

class _GuardianSection extends StatelessWidget {
  const _GuardianSection();

  @override
  Widget build(BuildContext context) {
    return _PlatformOpenSection(
      title: 'Eltern- und Jugendschutzcenter oeffnen',
      body: 'Guardian Consent, Elternlogin, Kinderkonten und Widerruf.',
      icon: Icons.family_restroom_outlined,
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GuardianCenterScreen())),
    );
  }
}

class _MaturitySection extends StatelessWidget {
  const _MaturitySection();

  @override
  Widget build(BuildContext context) {
    return _PlatformOpenSection(
      title: 'Altersfreigaben oeffnen',
      body: 'Maturity, Content-Gates, Guardian-Freigaben, Altersgruppen und sichere Sichtbarkeit.',
      icon: Icons.visibility_off_outlined,
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MaturityCenterScreen())),
    );
  }
}

class _OutfitSection extends StatelessWidget {
  const _OutfitSection();

  @override
  Widget build(BuildContext context) {
    return _PlatformOpenSection(
      title: 'Outfit-Abo-Center oeffnen',
      body: 'Style-Profil, Plaene, Lieferungen, Pause und Support-Faelle.',
      icon: Icons.checkroom_outlined,
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => OutfitSubscriptionCenterScreen())),
    );
  }
}

class _PlatformOpenSection extends StatelessWidget {
  const _PlatformOpenSection({required this.title, required this.body, required this.icon, required this.onTap});

  final String title;
  final String body;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: onTap,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue, size: 26),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _AdminSection extends StatelessWidget {
  const _AdminSection();

  @override
  Widget build(BuildContext context) {
    return _StackedPanels(children: [
      AirmiusPanel(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AdminCenterScreen())),
        child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(Icons.admin_panel_settings_outlined, color: AirmiusColors.blue, size: 26),
          SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text('Admin Center oeffnen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), SizedBox(height: 4), Text('Nutzer, Rollen, Moderation, Billing, Commerce und System.', style: TextStyle(color: AirmiusColors.muted, height: 1.35))])),
          Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ]),
      ),
      const _ListLine(icon: Icons.verified_user_outlined, title: 'Club-Verifizierungen', body: '1 Verein wartet auf Pruefung', trailing: 'Admin'),
      const _ListLine(icon: Icons.report_outlined, title: 'Moderation', body: 'Flags, Reports und Community-Sicherheit', trailing: '2'),
      const _ListLine(icon: Icons.payments_outlined, title: 'Abos & Rechnungen', body: 'Zahlstatus, Banktransfer und Rechnungen', trailing: 'Billing'),
      const _ListLine(icon: Icons.store_mall_directory_outlined, title: 'Commerce', body: 'Coupons, Produkte, Bestellungen, Payouts', trailing: 'Shop'),
    ]);
  }
}

class _GenericSection extends StatelessWidget {
  const _GenericSection();

  @override
  Widget build(BuildContext context) {
    return const _WideStatus(title: 'Native UI vorbereitet', body: 'Dieses Modul ist in der App-Struktur eingebunden und wird mit API-Daten gefuellt.', icon: Icons.widgets_outlined);
  }
}

class _StackedPanels extends StatelessWidget {
  const _StackedPanels({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var i = 0; i < children.length; i++) ...[
          children[i],
          if (i < children.length - 1) const SizedBox(height: 12),
        ],
      ],
    );
  }
}

class _WideStatus extends StatelessWidget {
  const _WideStatus({required this.title, required this.body, required this.icon});

  final String title;
  final String body;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ModuleItemDetailScreen(
            title: title,
            body: body,
            trailing: 'Detail',
            icon: icon,
          ),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue, size: 26),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted, size: 20),
        ],
      ),
    );
  }
}

class _ListLine extends StatelessWidget {
  const _ListLine({required this.icon, required this.title, required this.body, required this.trailing});

  final IconData icon;
  final String title;
  final String body;
  final String trailing;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ModuleItemDetailScreen(
            title: title,
            body: body,
            trailing: trailing,
            icon: icon,
          ),
        ),
      ),
      padding: const EdgeInsets.all(13),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
              ],
            ),
          ),
          const SizedBox(width: 8),
          StatusPill(trailing),
          const SizedBox(width: 6),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted, size: 20),
        ],
      ),
    );
  }
}

class _ProgressPanel extends StatelessWidget {
  const _ProgressPanel({required this.title, required this.value, required this.label});

  final String title;
  final double value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(value: value, minHeight: 9, backgroundColor: AirmiusColors.cardSoft, valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.blue)),
          ),
          const SizedBox(height: 8),
          Text(label, style: const TextStyle(color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _MessageComposer extends StatelessWidget {
  const _MessageComposer();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        children: [
          const Expanded(child: AirmiusTextField(label: 'Nachricht', hint: 'Schreibe eine Nachricht...')),
          const SizedBox(width: 10),
          AirmiusButton(label: 'Senden', icon: Icons.send_outlined, onPressed: () => openUiAction(context, title: 'Senden', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.send_outlined)),
        ],
      ),
    );
  }
}

class _BadgeGrid extends StatelessWidget {
  const _BadgeGrid();

  @override
  Widget build(BuildContext context) {
    const badges = ['Starter', 'Teamplayer', 'Vereinsheld', 'Coach'];
    return AirmiusPanel(
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          for (final badge in badges)
            Container(
              width: 126,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
              child: Column(children: [const Icon(Icons.workspace_premium_outlined, color: AirmiusColors.amber), const SizedBox(height: 8), Text(badge, textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))]),
            ),
        ],
      ),
    );
  }
}


