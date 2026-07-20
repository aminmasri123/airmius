import 'package:flutter/material.dart';
import 'legal_support_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LegalDocumentScreen extends StatefulWidget {
  const LegalDocumentScreen({super.key, this.initialDocument = 'Datenschutz'});

  final String initialDocument;

  @override
  State<LegalDocumentScreen> createState() => _LegalDocumentScreenState();
}

class _LegalDocumentScreenState extends State<LegalDocumentScreen> {
  late String _document = widget.initialDocument;
  bool _accepted = false;
  bool _showVersion = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFFB88320), foregroundColor: Colors.white, icon: const Icon(Icons.gavel_outlined), label: const Text('Legal Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => LegalSupportOperationsScreen(initialTab: 'Legal')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Rechtliches', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: _document,
        subtitle: 'Impressum, Datenschutz, AGB, Community, Jugendschutz, Cookies, Widerruf und Kontakt/Melden',
        trailing: const StatusPill('Legal'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Legal Center'),
            const SizedBox(height: 8),
            const Text('Die Public-Web-Routen für Rechtstexte werden als native App-Ansichten mit Version, Download, Kontakt und Meldung vorbereitet.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final document in const ['Impressum', 'Datenschutz', 'AGB', 'Community', 'Jugendschutz', 'Cookies', 'Widerruf', 'Kontakt & Melden'])
                ChoiceChip(
                  selected: _document == document,
                  label: Text(document),
                  onSelected: (_) => setState(() => _document = document),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: _document == document ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: _document == document ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ]),
          ])),
          const SizedBox(height: 14),
          Row(children: const [
            Expanded(child: MetricCard(value: 'v1', label: 'Version')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: 'DE', label: 'Sprache')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: 'PDF', label: 'Export')),
          ]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow(_document),
            const SizedBox(height: 10),
            Text(_bodyFor(_document), style: const TextStyle(color: AirmiusColors.muted, height: 1.42)),
            const SizedBox(height: 12),
            const _LegalLine(icon: Icons.description_outlined, title: 'Abschnittsstruktur', body: 'Überschriften, Stand, Verantwortliche und Kontakt werden später aus Laravel geladen.', status: 'Content'),
            const _LegalLine(icon: Icons.history_outlined, title: 'Version & Audit', body: 'Änderungsdatum, Version und Akzeptanzprotokoll sind vorbereitet.', status: 'Audit'),
            const _LegalLine(icon: Icons.language_outlined, title: 'Mehrsprachig', body: 'DE, EN, FR und AR können pro Rechtstext lokalisiert werden.', status: 'i18n'),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Akzeptanz & Version'),
            SwitchListTile(value: _showVersion, onChanged: (value) => setState(() => _showVersion = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Versionshinweis anzeigen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Zeigt Stand und Änderungsdatum in der App.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _accepted, onChanged: (value) => setState(() => _accepted = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Zur Kenntnis genommen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Akzeptanz wird später serverseitig protokolliert.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'PDF herunterladen', icon: Icons.picture_as_pdf_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Legal PDF herunterladen', body: '$_document als PDF exportieren, Sprache und Version berücksichtigen.', status: 'PDF', icon: Icons.picture_as_pdf_outlined)),
            AirmiusButton(label: 'Kontakt & Melden', icon: Icons.report_outlined, onPressed: () => openUiAction(context, title: 'Kontakt & Melden', body: 'Rechtliche Anfrage, Meldung, Datenschutzkontakt oder Supportfall vorbereiten.', status: 'Legal', icon: Icons.report_outlined)),
            AirmiusButton(label: 'Akzeptanz speichern', icon: Icons.check_circle_outline, onPressed: _accepted ? () => openUiAction(context, title: 'Akzeptanz speichern', body: 'Akzeptanz für $_document mit Version, Sprache und Zeitstempel speichern.', status: 'Akzeptiert', icon: Icons.check_circle_outline) : null),
          ]),
        ]),
      ),
    );
  }

  String _bodyFor(String document) {
    return switch (document) {
      'Impressum' => 'Anbieterkennzeichnung, Verantwortliche, Kontakt, Registerdaten und rechtliche Pflichtangaben.',
      'Datenschutz' => 'Datenverarbeitung, Rechte der Nutzer, Uploads, Mitgliederverwaltung, Kommunikation und API-Daten.',
      'AGB' => 'Nutzungsbedingungen, Plattformregeln, Commerce, Abos, Zahlungen und Verantwortlichkeiten.',
      'Community' => 'Verhaltensregeln, Meldungen, Moderation, Sperren, Schutz von Minderjaehrigen und Fairness.',
      'Jugendschutz' => 'Guardian Consent, Altersfreigaben, Medien, Fahrgemeinschaften und sichere Kommunikation.',
      'Cookies' => 'Cookie- und Tracking-Hinweise für Web, App, Analytics und Marketplace-Interessen.',
      'Widerruf' => 'Widerrufsrecht, Rückgaben, digitale Inhalte, Abos, Marketplace-Bestellungen und Fristen.',
      _ => 'Kontaktwege, Meldungen, Datenschutzanfragen, Missbrauchsmeldungen und Supportprozesse.',
    };
  }
}

class _LegalLine extends StatelessWidget {
  const _LegalLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}

