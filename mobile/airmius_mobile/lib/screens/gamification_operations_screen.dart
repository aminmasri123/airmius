import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class GamificationOperationsScreen extends StatefulWidget {
  const GamificationOperationsScreen({super.key, this.initialTab = 'Badges'});

  final String initialTab;

  @override
  State<GamificationOperationsScreen> createState() => _GamificationOperationsScreenState();
}

class _GamificationOperationsScreenState extends State<GamificationOperationsScreen> {
  String _tab = 'Badges';
  bool _leaderboardOptIn = true;
  bool _profileVisible = true;
  bool _notify = true;

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
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Gamification Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Gamification Ops',
        subtitle: 'Badges, XP, Streaks, Leaderboard, Datenschutz und Admin-Regeln',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              borderColor: AirmiusColors.amber.withValues(alpha: .44),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Eyebrow('Motivation & Regeln'),
                  const SizedBox(height: 8),
                  const Text('Gamification darf motivieren, aber nicht Druck erzeugen. Deshalb sind Sichtbarkeit, Opt-in, Guardian/Maturity und Audit Teil der nativen App-UI.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 12),
                  SwitchListTile(value: _leaderboardOptIn, onChanged: (value) => setState(() => _leaderboardOptIn = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Leaderboard Opt-in', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Ranking nur anzeigen, wenn User zugestimmt hat.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _profileVisible, onChanged: (value) => setState(() => _profileVisible = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Profil sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Badges nur im erlaubten Profilkontext anzeigen.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _notify, onChanged: (value) => setState(() => _notify = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Freischaltung melden', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Push/Inbox bei Badge, Level oder Streak.', style: TextStyle(color: AirmiusColors.muted))),
                  const SizedBox(height: 10),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    for (final tab in _tabs)
                      ChoiceChip(
                        label: Text(tab),
                        selected: _tab == tab,
                        onSelected: (_) => setState(() => _tab = tab),
                        selectedColor: AirmiusColors.amber.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _tab == tab ? AirmiusColors.amber : AirmiusColors.border),
                        labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      ),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            Row(children: const [Expanded(child: MetricCard(value: '9', label: 'Badges')), SizedBox(width: 10), Expanded(child: MetricCard(value: '420', label: 'XP')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Regeln'))]),
            const SizedBox(height: 16),
            for (final item in items) ...[
              _GamificationOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _GamificationOperationCard extends StatelessWidget {
  const _GamificationOperationCard({required this.item});

  final _GamificationOperation item;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: item.danger ? AirmiusColors.red.withValues(alpha: .45) : AirmiusColors.border,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
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
            AirmiusButton(label: 'Audit', icon: Icons.history_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Audit', body: 'Regelversion, Trigger, User, Datenschutzstatus, Guardian/Maturity-Gate und XP-Aenderung anzeigen.', status: 'Audit', icon: Icons.history_outlined)),
          ]),
        ]),
      );
}

class _GamificationOperation {
  const _GamificationOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
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

const _tabs = ['Badges', 'Regeln', 'XP', 'Streaks', 'Leaderboard', 'Alle'];

final _operations = <_GamificationOperation>[
  _GamificationOperation(tab: 'Badges', title: 'Badges laden', body: 'Eigene Badges, Fortschritt und sichtbare Auszeichnungen laden.', method: 'GET', endpoint: ApiContract.badges, icon: Icons.workspace_premium_outlined, action: 'Laden', color: AirmiusColors.amber),
  _GamificationOperation(tab: 'Badges', title: 'Badge Detail', body: 'Fortschritt, Freischaltung, Sichtbarkeit und Empfehlung eines Badges anzeigen.', method: 'GET', endpoint: ApiContract.badge(1), icon: Icons.military_tech_outlined, action: 'Oeffnen', color: AirmiusColors.amber),
  _GamificationOperation(tab: 'Badges', title: 'Admin Badge erstellen', body: 'Badge mit Name, Icon, Regel, Sichtbarkeit und Lokalisierung erstellen.', method: 'POST', endpoint: ApiContract.adminBadges, icon: Icons.add_circle_outline, action: 'Erstellen', color: AirmiusColors.blue),
  _GamificationOperation(tab: 'Badges', title: 'Admin Badge loeschen', body: 'Badge entfernen und bestehende User-Fortschritte vorher pruefen.', method: 'DELETE', endpoint: ApiContract.adminBadge(1), icon: Icons.delete_outline, action: 'Loeschen', color: AirmiusColors.red, danger: true),
  _GamificationOperation(tab: 'Regeln', title: 'Regeln laden', body: 'XP-, Badge-, Streak- und Leaderboard-Regeln laden.', method: 'GET', endpoint: ApiContract.gamificationRules, icon: Icons.rule_outlined, action: 'Regeln laden', color: AirmiusColors.blue),
  _GamificationOperation(tab: 'Regeln', title: 'Regeln speichern', body: 'Admin-Regeln aktualisieren, versionieren und Audit schreiben.', method: 'PUT', endpoint: ApiContract.gamificationRules, icon: Icons.save_outlined, action: 'Speichern', color: AirmiusColors.green),
  _GamificationOperation(tab: 'XP', title: 'XP Ledger anzeigen', body: 'XP-Historie mit Quelle, Trigger, Modul und Korrektur anzeigen.', method: 'GET', endpoint: ApiContract.gamificationXpLedger, icon: Icons.receipt_long_outlined, action: 'Ledger', color: AirmiusColors.green),
  _GamificationOperation(tab: 'XP', title: 'XP korrigieren', body: 'XP-Korrektur mit Admin-Grund und Auditlog vorbereiten.', method: 'POST', endpoint: ApiContract.gamificationXpAdjust, icon: Icons.tune_outlined, action: 'Korrigieren', color: AirmiusColors.amber),
  _GamificationOperation(tab: 'Streaks', title: 'Streaks laden', body: 'Training, Lernen, Community und Vereinsaktivitaet als Streaks anzeigen.', method: 'GET', endpoint: ApiContract.gamificationStreaks, icon: Icons.local_fire_department_outlined, action: 'Streaks', color: AirmiusColors.amber),
  _GamificationOperation(tab: 'Streaks', title: 'Streak retten', body: 'Kulanzaktion oder Freeze fuer unterbrochene Streak vorbereiten.', method: 'POST', endpoint: ApiContract.gamificationStreakRescue(1), icon: Icons.health_and_safety_outlined, action: 'Retten', color: AirmiusColors.green),
  _GamificationOperation(tab: 'Leaderboard', title: 'Leaderboard laden', body: 'Ranking nur mit Opt-in, Profilfreigabe und Altersfreigabe anzeigen.', method: 'GET', endpoint: ApiContract.gamificationLeaderboard, icon: Icons.leaderboard_outlined, action: 'Leaderboard', color: AirmiusColors.blue),
  _GamificationOperation(tab: 'Leaderboard', title: 'Leaderboard Opt-out', body: 'User aus Ranking entfernen und Sichtbarkeit sofort aktualisieren.', method: 'POST', endpoint: ApiContract.gamificationLeaderboardOptOut, icon: Icons.visibility_off_outlined, action: 'Opt-out', color: AirmiusColors.red, danger: true),
];
