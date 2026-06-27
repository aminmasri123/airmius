import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class CarpoolDetailScreen extends StatefulWidget {
  const CarpoolDetailScreen({super.key, required this.title, required this.status});

  final String title;
  final String status;

  @override
  State<CarpoolDetailScreen> createState() => _CarpoolDetailScreenState();
}

class _CarpoolDetailScreenState extends State<CarpoolDetailScreen> {
  String _mode = 'Angebot';
  bool _guardianRequired = true;
  bool _sharePhone = false;
  bool _reportEnabled = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Fahrgemeinschaft', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Route, Plaetze, Treffpunkt, Sicherheit und Guardian-Freigabe',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Fahrt'),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final item in const ['Angebot', 'Gesuch'])
                ChoiceChip(
                  selected: _mode == item,
                  label: Text(item),
                  onSelected: (_) => setState(() => _mode = item),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: _mode == item ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: _mode == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ]),
            const SizedBox(height: 12),
            const AirmiusTextField(label: 'Route', hint: 'Start -> Treffpunkt -> Ziel', icon: Icons.route_outlined),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'Uhrzeit', hint: 'Heute 18:00', icon: Icons.schedule_outlined),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '2', label: 'Plaetze')), SizedBox(width: 10), Expanded(child: MetricCard(value: '14km', label: 'Route')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'OK', label: 'Safety'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Treffpunkt'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Treffpunkt', hint: 'Adresse oder bekannter Ort', icon: Icons.location_on_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Hinweis', hint: 'z. B. Eingang, Parkplatz, Kennzeichen', icon: Icons.notes_outlined, maxLines: 3),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sicherheit'),
            const SizedBox(height: 8),
            SwitchListTile(value: _guardianRequired, onChanged: (value) => setState(() => _guardianRequired = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Guardian-Freigabe verlangen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Pflicht fuer Minderjaehrige oder sensible Fahrten.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _sharePhone, onChanged: (value) => setState(() => _sharePhone = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Telefon erst nach Zusage teilen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kontakt bleibt bis zur bestaetigten Mitfahrt verborgen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _reportEnabled, onChanged: (value) => setState(() => _reportEnabled = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Melden & Blockieren erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Unsichere Fahrten koennen direkt moderiert werden.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: 0.55), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Teilnehmer'),
            SizedBox(height: 8),
            Text('ZBB Konto angefragt, Max Beispiel wartet auf Bestaetigung. Matching, Guardian Consent und Standortrechte kommen spaeter aus der API.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('1 bestaetigt'), StatusPill('1 offen'), StatusPill('Guardian OK')]),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Mitfahranfragen'),
            const SizedBox(height: 10),
            const _RideRequestLine(name: 'ZBB Konto', body: 'Moechte mitfahren - Guardian OK', status: 'Offen'),
            const _RideRequestLine(name: 'Max Beispiel', body: 'Bestaetigt - Telefon verborgen bis Zusage', status: 'Bestaetigt'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Mitfahrt anfragen', icon: Icons.person_add_alt_1_outlined, onPressed: () => openUiAction(context, title: 'Mitfahrt anfragen', body: 'Ride-Join-Request, Guardian-Prüfung, Kontaktfreigabe und Treffpunktzugriff vorbereiten.', status: 'Join', icon: Icons.person_add_alt_1_outlined)),
              AirmiusButton(label: 'Anfrage annehmen', icon: Icons.check_circle_outline, secondary: true, onPressed: () => openUiAction(context, title: 'Mitfahranfrage annehmen', body: 'Teilnehmer bestaetigen, Plaetze reduzieren und Kontaktfreigabe aktivieren.', status: 'Approve', icon: Icons.check_circle_outline)),
              AirmiusButton(label: 'Anfrage ablehnen', icon: Icons.close_outlined, danger: true, onPressed: () => openUiAction(context, title: 'Mitfahranfrage ablehnen', body: 'Anfrage ablehnen, Teilnehmer informieren und Auditstatus speichern.', status: 'Reject', icon: Icons.close_outlined)),
              AirmiusButton(label: 'Fahrt verlassen', icon: Icons.logout_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Fahrt verlassen', body: 'Teilnahme entfernen, Platz wieder freigeben und Teilnehmer informieren.', status: 'Leave', icon: Icons.logout_outlined)),
            ]),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Fahrt speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Fahrt speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Fahrt melden', icon: Icons.report_outlined, danger: true, onPressed: () => openUiAction(context, title: 'Fahrt melden', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.report_outlined)),
          ]),
        ]),
      ),
    );
  }
}

class _RideRequestLine extends StatelessWidget {
  const _RideRequestLine({required this.name, required this.body, required this.status});

  final String name;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AirmiusAvatar(name),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            const SizedBox(height: 3),
            Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
          ])),
          StatusPill(status, color: status == 'Offen' ? AirmiusColors.amber : AirmiusColors.green),
        ],
      ),
    );
  }
}
