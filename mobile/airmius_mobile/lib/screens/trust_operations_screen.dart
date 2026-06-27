import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class TrustOperationsScreen extends StatefulWidget {
  const TrustOperationsScreen({super.key, this.initialTab = 'Verifizierung'});

  final String initialTab;

  @override
  State<TrustOperationsScreen> createState() => _TrustOperationsScreenState();
}

class _TrustOperationsScreenState extends State<TrustOperationsScreen> {
  String _tab = 'Verifizierung';
  bool _notifyAffected = true;
  bool _auditRequired = true;
  bool _escalate = false;

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
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Trust Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Trust Ops',
        subtitle: 'Club-Verifizierung, Moderation, Reports, Inaktivitaet und Operating Contracts',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Eyebrow('Trust & Admin'),
            const SizedBox(height: 8),
            const Text('Verifizierung und Moderation sind Entscheidungen mit Wirkung nach aussen. Die mobile UI zeigt deshalb Status, Audit, Benachrichtigung, Eskalation und Begruendung immer sichtbar.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            SwitchListTile(value: _notifyAffected, onChanged: (value) => setState(() => _notifyAffected = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Betroffene informieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Verein, User oder Reporter bekommt Statusupdate.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _auditRequired, onChanged: (value) => setState(() => _auditRequired = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Audit verpflichtend', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Entscheidung nur mit Grund und Bearbeiter speichern.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _escalate, onChanged: (value) => setState(() => _escalate = value), activeColor: AirmiusColors.red, contentPadding: EdgeInsets.zero, title: const Text('Eskalieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Safety, Recht, Zahlung oder Minderjaehrige priorisieren.', style: TextStyle(color: AirmiusColors.muted))),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.amber.withValues(alpha: .22), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.amber : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          Row(children: const [Expanded(child: MetricCard(value: '1', label: 'Vereine')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Reports')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Audits'))]),
          const SizedBox(height: 16),
          for (final item in items) ...[_TrustOperationCard(item: item), const SizedBox(height: 12)],
        ]),
      ),
    );
  }
}

class _TrustOperationCard extends StatelessWidget {
  const _TrustOperationCard({required this.item});
  final _TrustOperation item;

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
      AirmiusButton(label: 'Audit', icon: Icons.history_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Audit', body: 'Bearbeiter, Grund, alter Status, neuer Status, Benachrichtigung, Eskalation und Zeitstempel anzeigen.', status: 'Audit', icon: Icons.history_outlined)),
    ]),
  ]));
}

class _TrustOperation {
  const _TrustOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
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

const _tabs = ['Verifizierung', 'Moderation', 'Reports', 'Inaktivitaet', 'Contracts', 'Alle'];

final _operations = <_TrustOperation>[
  _TrustOperation(tab: 'Verifizierung', title: 'Club-Verifizierungen laden', body: 'Offene Vereinsprüfungen mit Dokumenten, Admins und Profilstatus laden.', method: 'GET', endpoint: ApiContract.adminClubVerifications, icon: Icons.verified_user_outlined, action: 'Laden', color: AirmiusColors.blue),
  _TrustOperation(tab: 'Verifizierung', title: 'Verein genehmigen', body: 'Verein verifizieren, Badge setzen und Admins informieren.', method: 'PUT', endpoint: ApiContract.adminClubVerificationApprove(1), icon: Icons.check_circle_outline, action: 'Genehmigen', color: AirmiusColors.green),
  _TrustOperation(tab: 'Verifizierung', title: 'Verein ablehnen', body: 'Verifizierung ablehnen, Begruendung speichern und Nachreichung ermöglichen.', method: 'PUT', endpoint: ApiContract.adminClubVerificationReject(1), icon: Icons.cancel_outlined, action: 'Ablehnen', color: AirmiusColors.red, danger: true),
  _TrustOperation(tab: 'Moderation', title: 'Moderation laden', body: 'Flags, Reports, Content-Faelle und Eskalationen laden.', method: 'GET', endpoint: ApiContract.adminModeration, icon: Icons.gpp_maybe_outlined, action: 'Moderation', color: AirmiusColors.blue),
  _TrustOperation(tab: 'Moderation', title: 'Flag aktualisieren', body: 'Moderationsflag bewerten, Status setzen und Autor informieren.', method: 'PUT', endpoint: ApiContract.adminModerationFlag(1), icon: Icons.flag_outlined, action: 'Flag', color: AirmiusColors.amber),
  _TrustOperation(tab: 'Reports', title: 'Report aktualisieren', body: 'Nutzerreport prüfen, Entscheidung speichern und Fall schließen.', method: 'PUT', endpoint: ApiContract.adminModerationReport(1), icon: Icons.report_outlined, action: 'Report', color: AirmiusColors.amber),
  _TrustOperation(tab: 'Reports', title: 'Support Report erstellen', body: 'Manuellen Safety- oder Datenschutzreport aus Supportfall anlegen.', method: 'POST', endpoint: ApiContract.supportReports, icon: Icons.support_agent_outlined, action: 'Erstellen', color: AirmiusColors.red, danger: true),
  _TrustOperation(tab: 'Inaktivitaet', title: 'Inaktive Nutzer laden', body: 'Redirect-/Adminbereich für Inaktivitaetsnotizen und Statusprüfung abbilden.', method: 'GET', endpoint: ApiContract.adminInactiveUsers, icon: Icons.person_off_outlined, action: 'Laden', color: AirmiusColors.blue),
  _TrustOperation(tab: 'Inaktivitaet', title: 'Inaktivitaetsnotiz speichern', body: 'Hinweis, Frist, Kontaktversuch und naechste Aktion speichern.', method: 'PUT', endpoint: ApiContract.adminInactiveUserNote(1), icon: Icons.edit_note_outlined, action: 'Notiz', color: AirmiusColors.amber),
  _TrustOperation(tab: 'Contracts', title: 'Operating Contracts laden', body: 'Betriebsverträge, Status, Verein, Laufzeit und Kosten laden.', method: 'GET', endpoint: ApiContract.adminOperatingContracts, icon: Icons.assignment_outlined, action: 'Contracts', color: AirmiusColors.blue),
  _TrustOperation(tab: 'Contracts', title: 'Operating Contract speichern', body: 'Vertrag, Leistungen, Status und Laufzeit speichern.', method: 'POST', endpoint: ApiContract.adminOperatingContracts, icon: Icons.save_outlined, action: 'Speichern', color: AirmiusColors.green),
  _TrustOperation(tab: 'Contracts', title: 'Operating Contract löschen', body: 'Vertrag entfernen oder archivieren und Auditgrund speichern.', method: 'DELETE', endpoint: ApiContract.adminOperatingContract(1), icon: Icons.delete_outline, action: 'Löschen', color: AirmiusColors.red, danger: true),
];
