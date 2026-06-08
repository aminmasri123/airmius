import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class GuardianConsentDetailScreen extends StatefulWidget {
  const GuardianConsentDetailScreen({super.key, required this.title, required this.body, required this.status, required this.icon});

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  State<GuardianConsentDetailScreen> createState() => _GuardianConsentDetailScreenState();
}

class _GuardianConsentDetailScreenState extends State<GuardianConsentDetailScreen> {
  bool _events = true;
  bool _media = true;
  bool _rides = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.status, color: widget.status == 'Offen' ? AirmiusColors.amber : AirmiusColors.blue),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(widget.icon, color: AirmiusColors.blue, size: 40),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Eyebrow('Guardian Consent'),
              const SizedBox(height: 6),
              const Text('Zustimmung granular verwalten.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
              const SizedBox(height: 6),
              Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            ])),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '2', label: 'Kinder')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'Code', label: 'Login'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Freigaben'),
            SwitchListTile(value: _events, onChanged: (value) => setState(() => _events = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Events & Training', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Teilnahme, Absagen und Eventchat erlauben.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _media, onChanged: (value) => setState(() => _media = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Medienfreigabe', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Fotos/Videos gemaess Richtlinien erlauben.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _rides, onChanged: (value) => setState(() => _rides = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Fahrgemeinschaften', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Mitfahrten nur mit expliziter Zustimmung.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Code & Historie'),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Consent Token oder Elterncode', hint: 'Code aus E-Mail oder Elternlogin', icon: Icons.password_outlined),
            SizedBox(height: 10),
            _GuardianActionLine(icon: Icons.password_outlined, title: 'Elterncode', body: 'Code-Verifizierung fuer Elternlogin vorbereiten.', status: 'Code'),
            _GuardianActionLine(icon: Icons.mark_email_read_outlined, title: 'E-Mail Einladung', body: 'Consent-Link senden, erneut senden oder widerrufen.', status: 'Mail'),
            _GuardianActionLine(icon: Icons.history_outlined, title: 'Consent Historie', body: 'Zustimmung, Widerruf, IP und Zeitpunkt spaeter per API.', status: 'Audit'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Zustimmen', icon: Icons.check_circle_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Guardian Consent bestaetigen', body: 'Granulare Freigaben bestaetigen, Code pruefen und Historie schreiben.', status: 'Zustimmung', icon: Icons.check_circle_outline)))),
            AirmiusButton(label: 'Widerrufen', icon: Icons.block_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Guardian Consent widerrufen', body: 'Freigaben entziehen, Schutzregeln aktualisieren und Audit vorbereiten.', status: 'Widerruf', icon: Icons.block_outlined)))),
          ]),
        ]),
      ),
    );
  }
}

class _GuardianActionLine extends StatelessWidget {
  const _GuardianActionLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}
