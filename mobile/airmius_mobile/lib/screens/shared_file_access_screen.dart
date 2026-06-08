import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class SharedFileAccessScreen extends StatefulWidget {
  const SharedFileAccessScreen({super.key, this.token = 'share-zbb-2026'});

  final String token;

  @override
  State<SharedFileAccessScreen> createState() => _SharedFileAccessScreenState();
}

class _SharedFileAccessScreenState extends State<SharedFileAccessScreen> {
  bool _accepted = false;
  bool _requiresPassword = true;
  bool _expired = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Geteilte Datei', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Geteilte Datei',
        subtitle: 'Token-Link, Zugriff, Datenschutz und Download',
        trailing: StatusPill(_expired ? 'Abgelaufen' : 'Aktiv', color: _expired ? AirmiusColors.red : AirmiusColors.green),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Shared Link'),
            const SizedBox(height: 8),
            const Text('Oeffentliche oder halbprivate Dateilinks werden mobil als eigener sicherer Zugriff dargestellt.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 12),
            AirmiusTextField(label: 'Token', hint: widget.token, icon: Icons.link_outlined),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: 'PDF', label: 'Typ')), SizedBox(width: 10), Expanded(child: MetricCard(value: '24h', label: 'Ablauf')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Rechte'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Zugriff'),
            SwitchListTile(value: _requiresPassword, onChanged: (value) => setState(() => _requiresPassword = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Passwort erforderlich', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Optionaler Schutz fuer sensible Vereinsdokumente.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _accepted, onChanged: (value) => setState(() => _accepted = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Datenschutzhinweis akzeptiert', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Vor Download oder Preview bestaetigen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _expired, onChanged: (value) => setState(() => _expired = value), activeColor: AirmiusColors.red, contentPadding: EdgeInsets.zero, title: const Text('Link abgelaufen simulieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Zeigt den spaeteren Error-State fuer ungueltige Tokens.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Preview'),
            SizedBox(height: 12),
            _SharedPreviewBox(),
            SizedBox(height: 12),
            Text('Preview, Download, Ablaufdatum, IP-/Audit-Hinweis und Melden-Funktion sind als Mobile-UI vorbereitet.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Datei oeffnen', icon: Icons.visibility_outlined, onPressed: !_accepted || _expired ? null : () => openUiAction(context, title: 'Geteilte Datei oeffnen', body: 'Token pruefen, Zugriff protokollieren und Preview laden.', status: 'Share', icon: Icons.visibility_outlined)),
            AirmiusButton(label: 'Download', icon: Icons.download_outlined, secondary: true, onPressed: !_accepted || _expired ? null : () => openUiAction(context, title: 'Geteilte Datei herunterladen', body: 'Download ueber geteilten Token, Ablaufdatum und Audit vorbereiten.', status: 'Download', icon: Icons.download_outlined)),
            AirmiusButton(label: 'Link melden', icon: Icons.report_outlined, danger: true, onPressed: () => openUiAction(context, title: 'Geteilten Link melden', body: 'Missbrauchsmeldung, Datenschutz-Hinweis und Admin-Review vorbereiten.', status: 'Meldung', icon: Icons.report_outlined)),
          ]),
        ]),
      ),
    );
  }
}

class _SharedPreviewBox extends StatelessWidget {
  const _SharedPreviewBox();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 190,
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)),
      child: const Center(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.picture_as_pdf_outlined, color: AirmiusColors.blue, size: 58),
          SizedBox(height: 10),
          Text('Datenschutz.pdf', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          SizedBox(height: 4),
          Text('Geteilter Vereinslink', style: TextStyle(color: AirmiusColors.muted)),
        ]),
      ),
    );
  }
}
