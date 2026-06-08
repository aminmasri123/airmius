import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class PublicGrowthOperationsScreen extends StatefulWidget {
  const PublicGrowthOperationsScreen({super.key, this.initialTab = 'Leads'});

  final String initialTab;

  @override
  State<PublicGrowthOperationsScreen> createState() => _PublicGrowthOperationsScreenState();
}

class _PublicGrowthOperationsScreenState extends State<PublicGrowthOperationsScreen> {
  String _tab = 'Leads';
  bool _privacy = true;
  bool _notifyTeam = true;
  bool _qualified = false;

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
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Public Growth Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Public Growth Ops',
        subtitle: 'Preise, Jobs, Werbeagentur, Website-Anfragen, Public Leads und Kontaktformulare',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: .42), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Eyebrow('Public Funnel'),
            const SizedBox(height: 8),
            const Text('Besucher sollen nicht in der App verloren gehen: Preise ansehen, Jobinteresse senden, Website-Projekt anfragen oder als Verein/Partner Kontakt aufnehmen.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            SwitchListTile(value: _privacy, onChanged: (value) => setState(() => _privacy = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Datenschutz bestaetigt', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kontaktformular darf verarbeitet werden.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _notifyTeam, onChanged: (value) => setState(() => _notifyTeam = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Team informieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Admin, Commerce oder Support nach Lead informieren.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _qualified, onChanged: (value) => setState(() => _qualified = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Lead qualifiziert', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Status fuer Follow-up, Angebot oder Demo setzen.', style: TextStyle(color: AirmiusColors.muted))),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.blue.withValues(alpha: .22), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          Row(children: const [Expanded(child: MetricCard(value: '7', label: 'Leads')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Jobs')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Website'))]),
          const SizedBox(height: 16),
          for (final item in items) ...[_PublicGrowthOperationCard(item: item), const SizedBox(height: 12)],
        ]),
      ),
    );
  }
}

class _PublicGrowthOperationCard extends StatelessWidget {
  const _PublicGrowthOperationCard({required this.item});
  final _PublicGrowthOperation item;

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
      AirmiusButton(label: 'Lead-Kontext', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Kontext', body: 'Kontakt, Quelle, Thema, Datenschutz, Status, Zuweisung, Benachrichtigung und Follow-up anzeigen.', status: 'Kontext', icon: Icons.manage_search_outlined)),
    ]),
  ]));
}

class _PublicGrowthOperation {
  const _PublicGrowthOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
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

const _tabs = ['Leads', 'Preise', 'Jobs', 'Website', 'Admin', 'Alle'];

final _operations = <_PublicGrowthOperation>[
  _PublicGrowthOperation(tab: 'Leads', title: 'Public Lead speichern', body: 'Allgemeines Interesse, Verein, Sponsor, Kurs oder Demo als Lead erfassen.', method: 'POST', endpoint: ApiContract.publicLeads, icon: Icons.send_outlined, action: 'Lead senden', color: AirmiusColors.green),
  _PublicGrowthOperation(tab: 'Leads', title: 'Standort/Kontakt einreichen', body: 'Verein, Sportort, Anbieter oder Korrektur mit Kontakt anlegen.', method: 'POST', endpoint: ApiContract.contactStore, icon: Icons.place_outlined, action: 'Einreichen', color: AirmiusColors.green),
  _PublicGrowthOperation(tab: 'Preise', title: 'Preise laden', body: 'Public Preis- und Abo-Seite fuer Nutzer, Vereine und Zusatzpakete laden.', method: 'GET', endpoint: ApiContract.publicPricing, icon: Icons.sell_outlined, action: 'Preise', color: AirmiusColors.blue),
  _PublicGrowthOperation(tab: 'Preise', title: 'Planinteresse senden', body: 'Preisplan, Kontakt, Verein und Rueckrufwunsch als Lead speichern.', method: 'POST', endpoint: ApiContract.publicPricingInterest, icon: Icons.request_quote_outlined, action: 'Anfragen', color: AirmiusColors.green),
  _PublicGrowthOperation(tab: 'Jobs', title: 'Jobs laden', body: 'Oeffentliche Job- und Engagement-Moeglichkeiten laden.', method: 'GET', endpoint: ApiContract.publicJobs, icon: Icons.work_outline, action: 'Jobs', color: AirmiusColors.blue),
  _PublicGrowthOperation(tab: 'Jobs', title: 'Jobinteresse senden', body: 'Bewerberkontakt, Nachricht, Rolle und Datenschutzstatus speichern.', method: 'POST', endpoint: ApiContract.publicJobInterest(1), icon: Icons.badge_outlined, action: 'Interesse', color: AirmiusColors.green),
  _PublicGrowthOperation(tab: 'Website', title: 'Werbeagentur laden', body: 'Public Website-/Werbeagentur-Seite fuer Vereine anzeigen.', method: 'GET', endpoint: ApiContract.publicAgency, icon: Icons.campaign_outlined, action: 'Oeffnen', color: AirmiusColors.blue),
  _PublicGrowthOperation(tab: 'Website', title: 'Website-Anfrage senden', body: 'Projektumfang, Verein, Budget, Kontakt und Wunschdatum einreichen.', method: 'POST', endpoint: ApiContract.publicWebsiteRequest, icon: Icons.web_outlined, action: 'Anfrage', color: AirmiusColors.green),
  _PublicGrowthOperation(tab: 'Admin', title: 'Website-Anfrage bearbeiten', body: 'Lead klassifizieren, Status setzen, Notiz schreiben und Team informieren.', method: 'PATCH', endpoint: ApiContract.adminCommerceWebsiteRequest(1), icon: Icons.fact_check_outlined, action: 'Bearbeiten', color: AirmiusColors.amber),
  _PublicGrowthOperation(tab: 'Admin', title: 'Lead archivieren', body: 'Doppelten oder erledigten Public Lead mit Auditgrund archivieren.', method: 'DELETE', endpoint: ApiContract.publicLead(1), icon: Icons.archive_outlined, action: 'Archivieren', color: AirmiusColors.red, danger: true),
];
