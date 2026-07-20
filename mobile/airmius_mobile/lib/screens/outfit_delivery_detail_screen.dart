import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class OutfitDeliveryDetailScreen extends StatefulWidget {
  const OutfitDeliveryDetailScreen({super.key, required this.title, required this.body, required this.status, required this.icon});

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  State<OutfitDeliveryDetailScreen> createState() => _OutfitDeliveryDetailScreenState();
}

class _OutfitDeliveryDetailScreenState extends State<OutfitDeliveryDetailScreen> {
  String _issue = 'Groesse';
  bool _pauseNext = false;

  @override
  Widget build(BuildContext context) {
    final support = widget.status == 'Support';
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.status, color: support ? AirmiusColors.amber : AirmiusColors.green),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(widget.icon, color: AirmiusColors.blue, size: 42),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Eyebrow('Outfit-Abo Detail'),
              const SizedBox(height: 6),
              Text(widget.status, style: const TextStyle(color: AirmiusColors.text, fontSize: 26, fontWeight: FontWeight.w900)),
              const SizedBox(height: 6),
              Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            ])),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: 'M', label: 'Groesse')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'Sport', label: 'Stil')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Lieferungen'))]),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Lieferstatus'),
            SizedBox(height: 10),
            _OutfitLine(icon: Icons.inventory_2_outlined, title: 'Paket vorbereitet', body: 'Artikel, Groesse, Stil und Vereinsfarben zusammengestellt.', status: 'Done'),
            _OutfitLine(icon: Icons.local_shipping_outlined, title: 'Versand', body: 'Tracking, Adresse und Lieferfenster als UI vorbereitet.', status: 'Aktiv'),
            _OutfitLine(icon: Icons.assignment_return_outlined, title: 'Rückgabe', body: 'Problem, Rückgabe oder Austausch als Supportfall melden.', status: 'Optional'),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Supportfall'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(initialValue: _issue, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Problemtyp'), items: const ['Groesse', 'Qualitaet', 'Versand', 'Rückgabe', 'Sonstiges'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _issue = value ?? _issue)),
            const SizedBox(height: 12),
            const AirmiusTextField(label: 'Beschreibung', hint: 'Was ist passiert?', maxLines: 3),
            SwitchListTile(value: _pauseNext, onChanged: (value) => setState(() => _pauseNext = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Naechste Lieferung pausieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Abo bleibt aktiv, naechste Box wird ausgesetzt.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: support ? 'Support senden' : 'Tracking ansehen', icon: support ? Icons.support_agent_outlined : Icons.local_shipping_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: support ? 'Support senden' : 'Tracking ansehen', body: support ? 'Supportfall, Nachricht und betroffene Lieferung vorbereiten.' : 'Trackingstatus, Paketdienst und Lieferhistorie anzeigen.', status: support ? 'Support' : 'Tracking', icon: support ? Icons.support_agent_outlined : Icons.local_shipping_outlined)))),
            AirmiusButton(label: 'Adresse bearbeiten', icon: Icons.edit_location_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Adresse bearbeiten', body: 'Lieferadresse, Kontakt und naechste Lieferung aktualisieren.', status: 'Adresse', icon: Icons.edit_location_alt_outlined)))),
            AirmiusButton(label: 'Abo kündigen', icon: Icons.cancel_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Outfit-Abo kündigen', body: 'Kündigung aus der Lieferdetailansicht vorbereiten.', status: 'Kündigung', icon: Icons.cancel_outlined)))),
          ]),
        ]),
      ),
    );
  }
}

class _OutfitLine extends StatelessWidget {
  const _OutfitLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}
