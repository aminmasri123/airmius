import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TeamOperationsScreen extends StatefulWidget {
  const TeamOperationsScreen({super.key});

  @override
  State<TeamOperationsScreen> createState() => _TeamOperationsScreenState();
}

class _TeamOperationsScreenState extends State<TeamOperationsScreen> {
  String _tab = 'Kader';
  bool _notify = true;
  bool _guardianGate = true;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _tab == 'Alle' || item.tab == _tab).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Team Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Team Operations',
        subtitle: 'Kader, Rollen, Einladungen, Join-Requests, Kalender, Dateien, Chat und Jugendschutz',
        trailing: StatusPill(_tab),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Teamspace Verwaltung'),
                  const SizedBox(height: 8),
                  const Text('Die App bildet Teamarbeit als eigenen Arbeitsbereich ab: Kader verwalten, Rollen vergeben, Einladungen versenden, Beitritte prüfen, Events verknuepfen, Dateien teilen und Teamchat steuern.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in const ['Kader', 'Rollen', 'Einladungen', 'Kalender', 'Dateien', 'Chat', 'Alle'])
                        ChoiceChip(
                          selected: _tab == tab,
                          label: Text(tab),
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '24', label: 'Kader')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Invites')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Join'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Team-Regeln'),
                  SwitchListTile(value: _notify, onChanged: (value) => setState(() => _notify = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Team informieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kader-, Rollen-, Kalender- und Chat-Änderungen erzeugen später Benachrichtigungen.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _guardianGate, onChanged: (value) => setState(() => _guardianGate = value), activeThumbColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Jugendschutz-Gate aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Minderjaehrige, Medien, Chats und Fahrten werden mit Guardian Consent geprüft.', style: TextStyle(color: AirmiusColors.muted))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _TeamOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _TeamOperationCard extends StatelessWidget {
  const _TeamOperationCard({required this.item});

  final _TeamOperation item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: item.danger ? AirmiusColors.red.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(item.icon, color: item.danger ? AirmiusColors.red : AirmiusColors.blue, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    const SizedBox(height: 9),
                    Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.tab), StatusPill(item.status, color: item.danger ? AirmiusColors.red : AirmiusColors.blue)]),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, secondary: !item.danger, onPressed: () => _run(context, item)),
              AirmiusButton(label: 'Team-Kontext', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Kontext', body: 'Team, Verein, Rolle, Guardian-Gate, Sichtbarkeit und Audit für ${item.title} anzeigen.', status: 'Context', icon: Icons.manage_search_outlined)),
            ],
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _TeamOperation item) {
    void action() => openUiAction(context, title: item.action, body: '${item.action}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.action}?', 'Diese Team-Aktion kann Rollen, Kader oder Zugriff beeinflussen und wird später auditiert.', item.action, action);
      return;
    }
    action();
  }
}

class _TeamOperation {
  const _TeamOperation({required this.tab, required this.title, required this.body, required this.status, required this.icon, required this.action, this.danger = false});

  final String tab;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String action;
  final bool danger;
}

const _items = [
  _TeamOperation(tab: 'Kader', title: 'Mitglied zum Team hinzufuegen', body: 'Vereinsmitglied suchen, Rolle wählen und Teamzugriff aktivieren.', status: 'Add', icon: Icons.person_add_alt_1_outlined, action: 'Hinzufuegen'),
  _TeamOperation(tab: 'Kader', title: 'Kaderrolle ändern', body: 'Spieler, Captain, Trainer oder Gastrolle mit Audit aktualisieren.', status: 'Role', icon: Icons.badge_outlined, action: 'Rolle setzen'),
  _TeamOperation(tab: 'Kader', title: 'Mitglied aus Team entfernen', body: 'Teamzugriff entfernen, Chat/File-Rechte entziehen und Team informieren.', status: 'Remove', icon: Icons.person_remove_outlined, action: 'Entfernen', danger: true),
  _TeamOperation(tab: 'Rollen', title: 'Captain ernennen', body: 'Captain-Rechte für Anwesenheit, Chatmoderation und Teamorganisation vergeben.', status: 'Captain', icon: Icons.workspace_premium_outlined, action: 'Captain setzen'),
  _TeamOperation(tab: 'Rollen', title: 'Trainer zuweisen', body: 'Coach-Rechte für Trainingsplaene, Feedback, Events und Kader freigeben.', status: 'Coach', icon: Icons.sports_score_outlined, action: 'Trainer zuweisen'),
  _TeamOperation(tab: 'Rollen', title: 'Teamrechte prüfen', body: 'Teamrechte gegen Vereinsrollen, Guardian-Regeln und sensible Bereiche abgleichen.', status: 'Audit', icon: Icons.security_outlined, action: 'Rechte prüfen'),
  _TeamOperation(tab: 'Einladungen', title: 'Team-Einladung senden', body: 'E-Mail, Rolle, Ablaufdatum, Team und Token für Einladung vorbereiten.', status: 'Invite', icon: Icons.send_outlined, action: 'Einladung senden'),
  _TeamOperation(tab: 'Einladungen', title: 'Join-Request annehmen', body: 'Beitrittsanfrage bestätigen, Rolle setzen und Teamchat freigeben.', status: 'Accept', icon: Icons.check_circle_outline, action: 'Annehmen'),
  _TeamOperation(tab: 'Einladungen', title: 'Join-Request ablehnen', body: 'Beitrittsanfrage ablehnen, Begruendung speichern und User informieren.', status: 'Reject', icon: Icons.cancel_outlined, action: 'Ablehnen', danger: true),
  _TeamOperation(tab: 'Kalender', title: 'Team-Event verknuepfen', body: 'Training oder Event am Teamkalender verknuepfen und Teilnehmerrechte prüfen.', status: 'Event', icon: Icons.event_available_outlined, action: 'Event verknuepfen'),
  _TeamOperation(tab: 'Kalender', title: 'Anwesenheit freigeben', body: 'Teilnahme, Warteliste, Abwesenheit und Trainerhinweis für Team erfassen.', status: 'Attendance', icon: Icons.fact_check_outlined, action: 'Anwesenheit speichern'),
  _TeamOperation(tab: 'Dateien', title: 'Teamdatei verknuepfen', body: 'Datei aus Vereins-Dateimanager an Team haengen und Sichtbarkeit setzen.', status: 'File', icon: Icons.folder_shared_outlined, action: 'Datei verknuepfen'),
  _TeamOperation(tab: 'Dateien', title: 'Teamdatei entfernen', body: 'Verknuepfung entfernen, ohne die Datei aus dem Vereinsdateimanager zu löschen.', status: 'Unlink', icon: Icons.link_off_outlined, action: 'Verknuepfung entfernen', danger: true),
  _TeamOperation(tab: 'Chat', title: 'Teamchat erstellen', body: 'Teamchat mit Kader, Trainern, Captain und Benachrichtigungsregeln erzeugen.', status: 'Chat', icon: Icons.chat_bubble_outline, action: 'Chat erstellen'),
  _TeamOperation(tab: 'Chat', title: 'Chatrechte synchronisieren', body: 'Kaderwechsel auf Chatmitglieder, Rollen und Schreibrechte übertragen.', status: 'Sync', icon: Icons.sync_outlined, action: 'Chat sync'),
  _TeamOperation(tab: 'Chat', title: 'Teamchat archivieren', body: 'Alten Teamchat sperren, Historie erhalten und neuen Chat optional vorbereiten.', status: 'Archive', icon: Icons.archive_outlined, action: 'Archivieren', danger: true),
];
