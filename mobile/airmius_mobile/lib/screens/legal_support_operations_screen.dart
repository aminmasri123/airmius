import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LegalSupportOperationsScreen extends StatefulWidget {
  const LegalSupportOperationsScreen({super.key, this.initialTab = 'Legal'});

  final String initialTab;

  @override
  State<LegalSupportOperationsScreen> createState() => _LegalSupportOperationsScreenState();
}

class _LegalSupportOperationsScreenState extends State<LegalSupportOperationsScreen> {
  String _tab = 'Legal';
  bool _privacyAccepted = true;
  bool _copyToUser = true;
  bool _urgent = false;

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
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Legal & Support Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Legal & Support Ops',
        subtitle: 'Rechtstexte, Datenschutz, Kontakt, Meldungen und Moderation',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Eyebrow('Vertrauen & Sicherheit'),
            const SizedBox(height: 8),
            const Text('Mobile Nutzer brauchen klare Rechtstexte, einfache Meldewege und nachvollziehbare Moderation. Diese UI bildet die Public-Legal-Routen und Supportprozesse nativ ab.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            SwitchListTile(value: _privacyAccepted, onChanged: (value) => setState(() => _privacyAccepted = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Datenschutz akzeptiert', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kontakt- und Meldeformulare brauchen Einwilligung.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _copyToUser, onChanged: (value) => setState(() => _copyToUser = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Kopie an User', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Eingangsbestaetigung fuer Anfrage oder Meldung senden.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _urgent, onChanged: (value) => setState(() => _urgent = value), activeColor: AirmiusColors.red, contentPadding: EdgeInsets.zero, title: const Text('Dringend markieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Safety-, Minderjaehrigen- oder Datenschutzfall priorisieren.', style: TextStyle(color: AirmiusColors.muted))),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.amber.withValues(alpha: .22), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.amber : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          for (final item in items) ...[_LegalOperationCard(item: item), const SizedBox(height: 12)],
        ]),
      ),
    );
  }
}

class _LegalOperationCard extends StatelessWidget {
  const _LegalOperationCard({required this.item});
  final _LegalOperation item;

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
      AirmiusButton(label: 'Nachweis', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Nachweis', body: 'Version, Einwilligung, IP-/Zeitstempel, User, Moderationsstatus und Audit anzeigen.', status: 'Audit', icon: Icons.fact_check_outlined)),
    ]),
  ]));
}

class _LegalOperation {
  const _LegalOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
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

const _tabs = ['Legal', 'Kontakt', 'Meldung', 'Moderation', 'Alle'];

final _operations = <_LegalOperation>[
  _LegalOperation(tab: 'Legal', title: 'Impressum laden', body: 'Impressum mobil anzeigen, Version und Ansprechpartner erfassen.', method: 'GET', endpoint: ApiContract.legalImprint, icon: Icons.account_balance_outlined, action: 'Oeffnen', color: AirmiusColors.blue),
  _LegalOperation(tab: 'Legal', title: 'Datenschutz laden', body: 'Datenschutzversion fuer Nutzer, Vereine und Antraege anzeigen.', method: 'GET', endpoint: ApiContract.legalPrivacy, icon: Icons.privacy_tip_outlined, action: 'Oeffnen', color: AirmiusColors.green),
  _LegalOperation(tab: 'Legal', title: 'AGB laden', body: 'AGB fuer Registrierung, Commerce, Vereine und Plattform anzeigen.', method: 'GET', endpoint: ApiContract.legalTerms, icon: Icons.description_outlined, action: 'Oeffnen', color: AirmiusColors.blue),
  _LegalOperation(tab: 'Legal', title: 'Jugendschutz laden', body: 'Minderjaehrige, Guardian, Maturity-Gates und Reporting erklaeren.', method: 'GET', endpoint: ApiContract.legalMinors, icon: Icons.family_restroom_outlined, action: 'Oeffnen', color: AirmiusColors.amber),
  _LegalOperation(tab: 'Legal', title: 'Widerruf laden', body: 'Widerruf, Ruecktritt, Anfrage-Rueckzug und Zahlungsfall anzeigen.', method: 'GET', endpoint: ApiContract.legalWithdrawal, icon: Icons.undo_outlined, action: 'Oeffnen', color: AirmiusColors.amber),
  _LegalOperation(tab: 'Kontakt', title: 'Kontakt einreichen', body: 'Public Kontakt-, Standort-, Verein- oder Sponsor-Anfrage speichern.', method: 'POST', endpoint: ApiContract.contactStore, icon: Icons.contact_mail_outlined, action: 'Senden', color: AirmiusColors.green),
  _LegalOperation(tab: 'Meldung', title: 'Meldeweg laden', body: 'Kontakt-und-Melden-Seite mit Kategorien und Notfallhinweisen anzeigen.', method: 'GET', endpoint: ApiContract.legalReporting, icon: Icons.report_outlined, action: 'Meldeweg', color: AirmiusColors.red),
  _LegalOperation(tab: 'Meldung', title: 'Meldung erstellen', body: 'User, Beitrag, Fahrt, Datei, Chat oder Verein als Fall melden.', method: 'POST', endpoint: ApiContract.supportReports, icon: Icons.warning_amber_outlined, action: 'Melden', color: AirmiusColors.red, danger: true),
  _LegalOperation(tab: 'Moderation', title: 'Meldung bearbeiten', body: 'Admin-Entscheidung, Status, Notiz und Benachrichtigung speichern.', method: 'PUT', endpoint: ApiContract.adminModerationReport(1), icon: Icons.admin_panel_settings_outlined, action: 'Bearbeiten', color: AirmiusColors.amber),
  _LegalOperation(tab: 'Moderation', title: 'Cookie-Hinweis laden', body: 'Cookie-/Tracking-Hinweis fuer Public und Ads-Kontext anzeigen.', method: 'GET', endpoint: ApiContract.legalCookies, icon: Icons.cookie_outlined, action: 'Cookies', color: AirmiusColors.blue),
];
