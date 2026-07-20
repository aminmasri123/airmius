import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SponsorDetailScreen extends StatefulWidget {
  const SponsorDetailScreen({super.key, required this.title, required this.status});

  final String title;
  final String status;

  @override
  State<SponsorDetailScreen> createState() => _SponsorDetailScreenState();
}

class _SponsorDetailScreenState extends State<SponsorDetailScreen> {
  String _package = 'Gold';
  String _stage = 'Kontakt';
  bool _publicLogo = true;
  bool _campaignActive = true;
  bool _reporting = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Sponsor', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Profil, Paket, Kampagne, Kontaktpipeline und Sichtbarkeit',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Sponsorprofil'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Firma / Sponsor', hint: 'Airmius Partner GmbH', icon: Icons.business_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Kontaktperson', hint: 'Name, E-Mail, Telefon', icon: Icons.contact_mail_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Beschreibung', hint: 'Öffentliche Sponsorbeschreibung', icon: Icons.notes_outlined, maxLines: 3),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '2.4k', label: 'Views')), SizedBox(width: 10), Expanded(child: MetricCard(value: '18', label: 'Leads')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Wochen'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Paket & Pipeline'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _package,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Sponsor-Paket'),
              items: const ['Bronze', 'Silber', 'Gold', 'Individuell'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _package = value ?? _package),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _stage,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Anfrage-Status'),
              items: const ['Kontakt', 'Angebot', 'Vertrag', 'Aktiv', 'Abgelehnt'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _stage = value ?? _stage),
            ),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sichtbarkeit & Kampagne'),
            const SizedBox(height: 8),
            SwitchListTile(value: _publicLogo, onChanged: (value) => setState(() => _publicLogo = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Logo öffentlich zeigen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Gastseite, Vereinsprofil und Kampagnenbereich.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _campaignActive, onChanged: (value) => setState(() => _campaignActive = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Kampagne aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Banner und Sponsorhinweise im Vereinsbereich zeigen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _reporting, onChanged: (value) => setState(() => _reporting = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Reporting senden', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Monatliche Reichweiten- und Lead-Zahlen vorbereiten.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.green.withValues(alpha: 0.45), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Kampagnenreporting'),
            SizedBox(height: 8),
            Text('Banner wurde 2.400-mal gesehen, 18 Kontaktinteressen wurden erzeugt und 6 Nutzer haben das Sponsorprofil geöffnet.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('Public'), StatusPill('Verein ZBB'), StatusPill('Report aktiv')]),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Sponsor speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Sponsor speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Antwort senden', icon: Icons.reply_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Antwort senden', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.reply_outlined)),
          ]),
        ]),
      ),
    );
  }

  InputDecoration _fieldDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
      filled: true,
      fillColor: AirmiusColors.input,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.blue, width: 1.4)),
    );
  }
}
