import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SportsOperationsScreen extends StatefulWidget {
  const SportsOperationsScreen({super.key, this.initialTab = 'Profil'});

  final String initialTab;

  @override
  State<SportsOperationsScreen> createState() => _SportsOperationsScreenState();
}

class _SportsOperationsScreenState extends State<SportsOperationsScreen> {
  String _tab = 'Profil';
  bool _coachVisible = true;
  bool _aiReady = false;
  bool _publicStats = false;

  @override
  void initState() {
    super.initState();
    if (_tabs.contains(widget.initialTab)) _tab = widget.initialTab;
  }

  @override
  Widget build(BuildContext context) {
    final items = _tab == 'Alle' ? _operations : _operations.where((item) => item.tab == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Sport Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Sport Ops',
        subtitle: 'Sportarten, Profil-Sportarten, Ziele, Leistungsdaten und KI-Readiness',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: .42), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Eyebrow('Sportprofil'),
            const SizedBox(height: 8),
            const Text('Sportdaten sind sensibel: Die native UI trennt Profil, Trainerfreigabe, KI-Plan-Voraussetzungen und Admin-Sportarten klar voneinander.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            SwitchListTile(value: _coachVisible, onChanged: (value) => setState(() => _coachVisible = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Mit Trainer teilen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Coach sieht Ziele, Leistungsdaten und Readiness.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _aiReady, onChanged: (value) => setState(() => _aiReady = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('KI-Plan bereit', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Genug Daten für Trainingsplan-Vorschläge vorhanden.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _publicStats, onChanged: (value) => setState(() => _publicStats = value), activeThumbColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Statistiken sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Nur freigegebene Werte im Profil anzeigen.', style: TextStyle(color: AirmiusColors.muted))),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.blue.withValues(alpha: .22), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          Row(children: const [Expanded(child: MetricCard(value: '5', label: 'Sportarten')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Profile')), SizedBox(width: 10), Expanded(child: MetricCard(value: '82%', label: 'Readiness'))]),
          const SizedBox(height: 16),
          for (final item in items) ...[_SportsOperationCard(item: item), const SizedBox(height: 12)],
        ]),
      ),
    );
  }
}

class _SportsOperationCard extends StatelessWidget {
  const _SportsOperationCard({required this.item});
  final _SportsOperation item;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: item.danger ? AirmiusColors.red.withValues(alpha: .45) : AirmiusColors.border, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .45))), child: Icon(item.icon, color: item.color)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)), const SizedBox(height: 5), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])),
      StatusPill(item.tab, color: item.color),
    ]),
    const SizedBox(height: 12),
    Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.bg, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Text('${item.method} ${item.endpoint}', style: const TextStyle(color: AirmiusColors.green, fontSize: 12, fontWeight: FontWeight.w900))),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, onPressed: () => openUiAction(context, title: item.title, body: '${item.body}\n\nEndpoint: ${item.method} ${item.endpoint}', status: item.tab, icon: item.icon)),
      AirmiusButton(label: 'Datenkontext', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Kontext', body: 'Sportart, Disziplin, Leistungswert, Ziel, Coach-Freigabe, Datenschutz und KI-Readiness anzeigen.', status: 'Kontext', icon: Icons.manage_search_outlined)),
    ]),
  ]));
}

class _SportsOperation {
  const _SportsOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
  final String tab;
  final String title;
  final String body;
  final String method;
  final String endpoint;
  final IconData icon;
  final String action;
  final Color color;
  final bool danger;
}

const _tabs = ['Profil', 'Ziele', 'Leistung', 'Admin', 'KI', 'Alle'];

final _operations = <_SportsOperation>[
  _SportsOperation(tab: 'Profil', title: 'Sportarten laden', body: 'Verfuegbare Sportarten und Disziplinen für Auswahl laden.', method: 'GET', endpoint: ApiContract.sports, icon: Icons.sports_outlined, action: 'Laden', color: AirmiusColors.blue),
  _SportsOperation(tab: 'Profil', title: 'Profil-Sportarten speichern', body: 'Hauptsportarten, Level, Erfahrung und Wochenumfang speichern.', method: 'POST', endpoint: ApiContract.profileSports, icon: Icons.save_outlined, action: 'Speichern', color: AirmiusColors.green),
  _SportsOperation(tab: 'Profil', title: 'Profil-Sportart entfernen', body: 'Sportart aus Profil entfernen und Trainings-/Coach-Kontext prüfen.', method: 'DELETE', endpoint: ApiContract.profileSport(1), icon: Icons.delete_outline, action: 'Entfernen', color: AirmiusColors.red, danger: true),
  _SportsOperation(tab: 'Ziele', title: 'Ziel setzen', body: 'Saison-, Gesundheits-, Wettkampf- oder Teamziel speichern.', method: 'POST', endpoint: ApiContract.sportGoals, icon: Icons.flag_outlined, action: 'Ziel', color: AirmiusColors.amber),
  _SportsOperation(tab: 'Ziele', title: 'Ziel aktualisieren', body: 'Zielwert, Zeitraum, Sichtbarkeit und Coach-Freigabe bearbeiten.', method: 'PATCH', endpoint: ApiContract.sportGoal(1), icon: Icons.edit_outlined, action: 'Aktualisieren', color: AirmiusColors.amber),
  _SportsOperation(tab: 'Leistung', title: 'Leistungsdaten speichern', body: 'Pace, Puls, Kraftwerte, Koordination oder individuelle Metriken speichern.', method: 'POST', endpoint: ApiContract.sportPerformance, icon: Icons.monitor_heart_outlined, action: 'Daten speichern', color: AirmiusColors.green),
  _SportsOperation(tab: 'Leistung', title: 'Coach-Freigabe setzen', body: 'Trainer darf ausgewählte Sportdaten sehen und auswerten.', method: 'PUT', endpoint: ApiContract.sportCoachVisibility, icon: Icons.visibility_outlined, action: 'Freigeben', color: AirmiusColors.blue),
  _SportsOperation(tab: 'Admin', title: 'Admin Sportart erstellen', body: 'Sportart, Disziplinen, Felder und KI-Relevanz administrieren.', method: 'POST', endpoint: ApiContract.adminSports, icon: Icons.add_circle_outline, action: 'Erstellen', color: AirmiusColors.blue),
  _SportsOperation(tab: 'Admin', title: 'Admin Sportart bearbeiten', body: 'Name, Icon, aktive Disziplinen und Leistungsfelder bearbeiten.', method: 'PUT', endpoint: ApiContract.adminSport(1), icon: Icons.edit_note_outlined, action: 'Bearbeiten', color: AirmiusColors.amber),
  _SportsOperation(tab: 'Admin', title: 'Admin Sportart löschen', body: 'Sportart entfernen, wenn keine aktiven Profile abhaengig sind.', method: 'DELETE', endpoint: ApiContract.adminSport(1), icon: Icons.delete_forever_outlined, action: 'Löschen', color: AirmiusColors.red, danger: true),
  _SportsOperation(tab: 'KI', title: 'KI-Readiness prüfen', body: 'Fehlende Daten für Trainingsplan, Risk-Check und Coach Weekly berechnen.', method: 'GET', endpoint: ApiContract.sportAiReadiness, icon: Icons.auto_awesome_outlined, action: 'Prüfen', color: AirmiusColors.green),
];
