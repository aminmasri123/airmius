import 'package:flutter/material.dart';
import 'sponsor_ads_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AdCampaignScreen extends StatefulWidget {
  const AdCampaignScreen({
    super.key,
    this.title = 'Marketplace Kampagne',
    this.status = 'Aktiv',
  });

  final String title;
  final String status;

  @override
  State<AdCampaignScreen> createState() => _AdCampaignScreenState();
}

class _AdCampaignScreenState extends State<AdCampaignScreen> {
  String _placement = 'Marketplace Karte';
  bool _trackClicks = true;
  bool _trackConversions = true;
  bool _clubVisible = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF16855E), foregroundColor: Colors.white, icon: const Icon(Icons.handshake_outlined), label: const Text('Ads Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SponsorAdsOperationsScreen(initialTab: 'Ads')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Active Ad, Klicktracking, Conversion, Placement und Sponsor-/Commerce-Kontext',
        trailing: StatusPill(widget.status, color: widget.status == 'Aktiv' ? AirmiusColors.green : AirmiusColors.amber),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Ads'),
            const SizedBox(height: 8),
            const Text('Die Web-App hat eigene Ads-Routen für aktive Anzeige, Klick und Conversion. Diese App-UI macht Kampagnen mobil sichtbar und testbar.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 14),
            Container(
              height: 170,
              decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)),
              child: const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.campaign_outlined, color: AirmiusColors.blue, size: 58),
                SizedBox(height: 10),
                Text('Airmius Kampagnenkarte', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                SizedBox(height: 4),
                Text('Sponsor, Marketplace oder Vereinsservice', style: TextStyle(color: AirmiusColors.muted)),
              ])),
            ),
          ])),
          const SizedBox(height: 14),
          Row(children: const [
            Expanded(child: MetricCard(value: '1.2k', label: 'Views')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: '84', label: 'Klicks')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: '9', label: 'Conv.')),
          ]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Placement'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _placement,
              dropdownColor: AirmiusColors.cardSoft,
              decoration: const InputDecoration(labelText: 'Anzeigenflaeche'),
              items: const ['Marketplace Karte', 'Hero Banner', 'Sale Kachel', 'Sponsor Slot', 'Vereinsprofil'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _placement = value ?? _placement),
            ),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'Ziel-URL / Deep Link', hint: 'Produkt, Sponsor, Verein oder Public Lead', icon: Icons.link_outlined),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Tracking'),
            SwitchListTile(value: _trackClicks, onChanged: (value) => setState(() => _trackClicks = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Klicktracking aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Entspricht später der Ads-Click-Route.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _trackConversions, onChanged: (value) => setState(() => _trackConversions = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Conversiontracking aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Checkout, Lead oder Sponsor-Anfrage als Conversion vorbereiten.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _clubVisible, onChanged: (value) => setState(() => _clubVisible = value), activeThumbColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Im Vereinskontext sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Sichtbarkeit für Verein, Public-Bereich oder Marketplace steuern.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Kampagnenstatus'),
            SizedBox(height: 10),
            _AdLine(icon: Icons.visibility_outlined, title: 'Active Ad', body: 'Aktive Anzeige laden, Preview anzeigen und Placement auswerten.', status: 'Active'),
            _AdLine(icon: Icons.ads_click, title: 'Click Event', body: 'Klick speichern, Ziel öffnen und Kampagnenmetrik aktualisieren.', status: 'Click'),
            _AdLine(icon: Icons.track_changes_outlined, title: 'Conversion Event', body: 'Lead, Checkout oder Anfrage als Conversion protokollieren.', status: 'Conversion'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Active Ad laden', icon: Icons.visibility_outlined, onPressed: () => openUiAction(context, title: 'Active Ad laden', body: 'Aktive Anzeige für Placement $_placement laden und Preview aktualisieren.', status: 'Active', icon: Icons.visibility_outlined)),
            AirmiusButton(label: 'Klick erfassen', icon: Icons.ads_click, secondary: true, onPressed: _trackClicks ? () => openUiAction(context, title: 'Ad-Klick erfassen', body: 'Klickevent, Ziel-Deep-Link und Kampagnenmetrik vorbereiten.', status: 'Click', icon: Icons.ads_click) : null),
            AirmiusButton(label: 'Conversion melden', icon: Icons.track_changes_outlined, secondary: true, onPressed: _trackConversions ? () => openUiAction(context, title: 'Ad-Conversion melden', body: 'Conversion für Lead, Checkout oder Sponsor-Anfrage vorbereiten.', status: 'Conversion', icon: Icons.track_changes_outlined) : null),
          ]),
        ]),
      ),
    );
  }
}

class _AdLine extends StatelessWidget {
  const _AdLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}

