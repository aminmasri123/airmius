import 'package:flutter/material.dart';
import 'legal_support_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'legal_support_operations_screen.dart';

class PublicLocationSubmissionScreen extends StatefulWidget {
  const PublicLocationSubmissionScreen({super.key});

  @override
  State<PublicLocationSubmissionScreen> createState() => _PublicLocationSubmissionScreenState();
}

class _PublicLocationSubmissionScreenState extends State<PublicLocationSubmissionScreen> {
  String _type = 'Verein';
  bool _privacy = true;
  bool _publicVisible = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFFB88320), foregroundColor: Colors.white, icon: const Icon(Icons.gavel_outlined), label: const Text('Kontakt Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => LegalSupportOperationsScreen(initialTab: 'Kontakt')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Standort vorschlagen', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Standort vorschlagen',
        subtitle: 'Public Kontaktformular für Vereine, Sportorte, Anbieter und Hinweise',
        trailing: StatusPill(_type),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Public Standort'),
            const SizedBox(height: 8),
            const Text('Die Web-App erlaubt öffentliche Standort-/Kontaktanlage. Die Mobile-App bereitet daraus einen moderierten Einreichungsflow vor.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final type in const ['Verein', 'Sportort', 'Anbieter', 'Korrektur'])
                ChoiceChip(
                  selected: _type == type,
                  label: Text(type),
                  onSelected: (_) => setState(() => _type = type),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: _type == type ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: _type == type ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ]),
          ])),
          const SizedBox(height: 14),
          Row(children: const [
            Expanded(child: MetricCard(value: 'Geo', label: 'Adresse')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: 'Mod', label: 'Review')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: 'API', label: 'Kontakt')),
          ]),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Standortdaten'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Name', hint: 'Verein, Sportplatz oder Anbieter', icon: Icons.place_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Adresse', hint: 'Straße, PLZ, Stadt', icon: Icons.location_on_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Beschreibung', hint: 'Warum soll dieser Standort aufgenommen werden?', icon: Icons.notes_outlined, maxLines: 3),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Kontakt'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Kontaktname', hint: 'Optional', icon: Icons.person_outline),
            SizedBox(height: 10),
            AirmiusTextField(label: 'E-Mail', hint: 'kontakt@example.com', icon: Icons.mail_outline),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sichtbarkeit & Datenschutz'),
            SwitchListTile(value: _publicVisible, onChanged: (value) => setState(() => _publicVisible = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Öffentlich sichtbar vorschlagen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Nach Moderation kann der Standort in Sportkarte/Public-Bereich erscheinen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _privacy, onChanged: (value) => setState(() => _privacy = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Datenschutz akzeptiert', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kontakt darf für Rückfragen verarbeitet werden.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Standort einreichen', icon: Icons.send_outlined, onPressed: _privacy ? () => openUiAction(context, title: 'Standort einreichen', body: 'Standortvorschlag, Kontakt, Moderationsstatus und Public-Sichtbarkeit vorbereiten.', status: _type, icon: Icons.send_outlined) : null),
            AirmiusButton(label: 'Als Korrektur melden', icon: Icons.edit_location_alt_outlined, secondary: true, onPressed: _privacy ? () => openUiAction(context, title: 'Standortkorrektur melden', body: 'Korrekturhinweis, bestehender Standort und Moderationsreview vorbereiten.', status: 'Korrektur', icon: Icons.edit_location_alt_outlined) : null),
          ]),
        ]),
      ),
    );
  }
}

