import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SponsorAdsOperationsScreen extends StatefulWidget {
  const SponsorAdsOperationsScreen({super.key, this.initialTab = 'Sponsoren'});

  final String initialTab;

  @override
  State<SponsorAdsOperationsScreen> createState() => _SponsorAdsOperationsScreenState();
}

class _SponsorAdsOperationsScreenState extends State<SponsorAdsOperationsScreen> {
  String _tab = 'Sponsoren';
  bool _publicVisible = true;
  bool _trackClicks = true;
  bool _trackConversions = true;

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
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Sponsor & Ads Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Sponsor & Ads Ops',
        subtitle: 'Sponsorprofile, Pakete, Kampagnen, Public-Sichtbarkeit und Tracking',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .42),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Eyebrow('Sponsoring'),
                const SizedBox(height: 8),
                const Text('Dieser Bereich verbindet die Web-Funktionen für Sponsoren, Public-Sponsorenseite, Commerce-Kampagnen und Ads-Tracking als native Mobile-UI.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                const SizedBox(height: 12),
                SwitchListTile(value: _publicVisible, onChanged: (value) => setState(() => _publicVisible = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Public sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Sponsor darf auf Gastseite, Verein und Kampagne erscheinen.', style: TextStyle(color: AirmiusColors.muted))),
                SwitchListTile(value: _trackClicks, onChanged: (value) => setState(() => _trackClicks = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Klicktracking', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Clicks mit Kampagne, Placement und Deep Link speichern.', style: TextStyle(color: AirmiusColors.muted))),
                SwitchListTile(value: _trackConversions, onChanged: (value) => setState(() => _trackConversions = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Conversiontracking', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Lead, Checkout oder Sponsor-Kontakt als Conversion speichern.', style: TextStyle(color: AirmiusColors.muted))),
                const SizedBox(height: 10),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final tab in _tabs)
                    ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.green.withValues(alpha: .2), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.green : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900)),
                ]),
              ]),
            ),
            const SizedBox(height: 16),
            Row(children: const [Expanded(child: MetricCard(value: '4', label: 'Sponsoren')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Kampagnen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '18', label: 'Leads'))]),
            const SizedBox(height: 16),
            for (final item in items) ...[
              _SponsorOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _SponsorOperationCard extends StatelessWidget {
  const _SponsorOperationCard({required this.item});

  final _SponsorOperation item;

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
            AirmiusButton(label: 'Reporting', icon: Icons.insights_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Reporting', body: 'Impressions, Clicks, Conversions, Leads, Zeitraum, Verein und Sponsor-Kontext anzeigen.', status: 'Reporting', icon: Icons.insights_outlined)),
          ]),
        ]),
      );
}

class _SponsorOperation {
  const _SponsorOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
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

const _tabs = ['Sponsoren', 'Pakete', 'Kampagnen', 'Ads', 'Leads', 'Alle'];

final _operations = <_SponsorOperation>[
  _SponsorOperation(tab: 'Sponsoren', title: 'Sponsoren laden', body: 'Sponsorprofile, Status, Paket und Public-Sichtbarkeit laden.', method: 'GET', endpoint: ApiContract.adminSponsors, icon: Icons.handshake_outlined, action: 'Laden', color: AirmiusColors.green),
  _SponsorOperation(tab: 'Sponsoren', title: 'Sponsor erstellen', body: 'Firma, Logo, Kontakt, Paket und Sichtbarkeit anlegen.', method: 'POST', endpoint: ApiContract.adminSponsors, icon: Icons.add_business_outlined, action: 'Erstellen', color: AirmiusColors.blue),
  _SponsorOperation(tab: 'Sponsoren', title: 'Sponsor bearbeiten', body: 'Profil, Paket, Kontaktstatus und Public-Darstellung aktualisieren.', method: 'PUT', endpoint: ApiContract.adminSponsor(1), icon: Icons.edit_outlined, action: 'Bearbeiten', color: AirmiusColors.amber),
  _SponsorOperation(tab: 'Sponsoren', title: 'Sponsor löschen', body: 'Sponsor archivieren oder entfernen und Kampagnen vorher stoppen.', method: 'DELETE', endpoint: ApiContract.adminSponsor(1), icon: Icons.delete_outline, action: 'Löschen', color: AirmiusColors.red, danger: true),
  _SponsorOperation(tab: 'Pakete', title: 'Sponsor-Paket speichern', body: 'Leistung, Preis, Laufzeit, Verein und Reporting-Umfang definieren.', method: 'POST', endpoint: ApiContract.sponsorPackages, icon: Icons.inventory_2_outlined, action: 'Paket', color: AirmiusColors.green),
  _SponsorOperation(tab: 'Kampagnen', title: 'Kampagne erstellen', body: 'Banner, Laufzeit, Zielgruppe, Ziel-URL und Budget anlegen.', method: 'POST', endpoint: ApiContract.commerceCampaigns, icon: Icons.campaign_outlined, action: 'Kampagne', color: AirmiusColors.blue),
  _SponsorOperation(tab: 'Kampagnen', title: 'Kampagnenstatus setzen', body: 'Entwurf, aktiv, pausiert oder beendet als Status speichern.', method: 'PATCH', endpoint: ApiContract.commerceCampaignStatus(1), icon: Icons.published_with_changes_outlined, action: 'Status', color: AirmiusColors.amber),
  _SponsorOperation(tab: 'Ads', title: 'Aktive Anzeige laden', body: 'Mobile Ad für Placement, Verein oder Marketplace laden.', method: 'GET', endpoint: ApiContract.publicActiveAd, icon: Icons.ads_click, action: 'Ad laden', color: AirmiusColors.blue),
  _SponsorOperation(tab: 'Ads', title: 'Ad-Klick erfassen', body: 'Klicktracking mit Campaign, User, Placement und Ziel-Deep-Link speichern.', method: 'GET', endpoint: ApiContract.publicAdClick(1), icon: Icons.touch_app_outlined, action: 'Click', color: AirmiusColors.green),
  _SponsorOperation(tab: 'Ads', title: 'Ad-Conversion melden', body: 'Lead, Checkout, Anfrage oder Sponsor-Kontakt als Conversion melden.', method: 'POST', endpoint: ApiContract.publicAdConversion(1), icon: Icons.track_changes_outlined, action: 'Conversion', color: AirmiusColors.green),
  _SponsorOperation(tab: 'Leads', title: 'Sponsor-Lead beantworten', body: 'Kontaktanfrage bewerten, Antwort vorbereiten und Sponsor informieren.', method: 'POST', endpoint: ApiContract.sponsorLeadReply(1), icon: Icons.reply_outlined, action: 'Antwort', color: AirmiusColors.amber),
];
