import 'package:flutter/material.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'public_location_submission_screen.dart';
import 'ui_action_result_screen.dart';
import 'legal_support_operations_screen.dart';
import 'public_growth_operations_screen.dart';

class PublicInterestScreen extends StatefulWidget {
  const PublicInterestScreen({super.key, required this.topic, required this.kind, required this.icon});

  final String topic;
  final String kind;
  final IconData icon;

  @override
  State<PublicInterestScreen> createState() => _PublicInterestScreenState();
}

class _PublicInterestScreenState extends State<PublicInterestScreen> {
  String _contactType = 'E-Mail';
  bool _privacyAccepted = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          FloatingActionButton.extended(
            backgroundColor: const Color(0xFF1D5FA8),
            foregroundColor: Colors.white,
            icon: const Icon(Icons.campaign_outlined),
            label: const Text('Lead Ops', style: TextStyle(fontWeight: FontWeight.w900)),
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PublicGrowthOperationsScreen(initialTab: 'Leads'))),
          ),
          const SizedBox(height: 10),
          FloatingActionButton.extended(
            backgroundColor: const Color(0xFFB88320),
            foregroundColor: Colors.white,
            icon: const Icon(Icons.gavel_outlined),
            label: const Text('Support Ops', style: TextStyle(fontWeight: FontWeight.w900)),
            onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => LegalSupportOperationsScreen(initialTab: 'Kontakt'))),
          ),
        ],
      ),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.topic, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: '${widget.topic} Anfrage',
        subtitle: 'Public Lead, Kontakt, Interesse, Datenschutz und spätere Laravel-API-Anbindung',
        trailing: StatusPill(widget.kind),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(widget.icon, color: AirmiusColors.blue, size: 42),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Eyebrow(widget.kind),
              const SizedBox(height: 6),
              Text('Interesse an ${widget.topic}', style: const TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
              const SizedBox(height: 8),
              const Text('Dieser Flow bereitet öffentliche Kontaktformulare, Leads, Bewerbungen, Sponsor-Anfragen und Checkout-Interesse nativ vor.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            ])),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Kontaktdaten'),
            const SizedBox(height: 12),
            const AirmiusTextField(label: 'Name', hint: 'Dein Name oder Organisation'),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'E-Mail', hint: 'kontakt@example.com'),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(value: _contactType, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Kontaktart'), items: const ['E-Mail', 'Telefon', 'Rückruf', 'Demo-Termin'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _contactType = value ?? _contactType)),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Nachricht'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Worum geht es?', hint: 'Beschreibe kurz dein Interesse...', maxLines: 4),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: SwitchListTile(
            value: _privacyAccepted,
            onChanged: (value) => setState(() => _privacyAccepted = value),
            activeColor: AirmiusColors.blue,
            contentPadding: EdgeInsets.zero,
            title: const Text('Datenschutz akzeptieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            subtitle: const Text('Die Anfrage darf zur Bearbeitung gespeichert werden.', style: TextStyle(color: AirmiusColors.muted)),
          )),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: 0.45), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Standort oder Organisation'),
            const SizedBox(height: 8),
            const Text('Wenn die Anfrage einen neuen Verein, Sportort oder Anbieter betrifft, kann direkt ein Standortvorschlag vorbereitet werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 12),
            AirmiusButton(label: 'Standort vorschlagen', icon: Icons.add_location_alt_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PublicLocationSubmissionScreen()))),
          ])),
          const SizedBox(height: 14),
          AirmiusButton(label: 'Anfrage senden', icon: Icons.send_outlined, onPressed: _privacyAccepted ? () => openUiAction(context, title: '${widget.topic} Anfrage senden', body: 'Public Lead, Kontaktart $_contactType, Datenschutzprotokoll und spätere Laravel-Bearbeitung vorbereiten.', status: widget.kind, icon: Icons.send_outlined) : null),
        ]),
      ),
    );
  }
}


