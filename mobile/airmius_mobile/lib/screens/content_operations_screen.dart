import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class ContentOperationsScreen extends StatefulWidget {
  const ContentOperationsScreen({super.key});

  @override
  State<ContentOperationsScreen> createState() => _ContentOperationsScreenState();
}

class _ContentOperationsScreenState extends State<ContentOperationsScreen> {
  String _filter = 'Blog';
  bool _publicPreview = true;
  bool _qualityGate = true;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _filter == 'Alle' || item.area == _filter).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Content Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Content Operations',
        subtitle: 'Blog-Kategorien, Medien, Vorschau, Learning-Quality, Sponsoren und Public-Freigaben',
        trailing: StatusPill(_filter),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Redaktion & Public Content'),
                  const SizedBox(height: 8),
                  const Text('Mobile Admin-UI fuer Content-Funktionen aus der Web-App: Kategorien, Bilduploads, Vorschau, Medienvisuals, Kursqualitaet und Sponsorenverwaltung.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final filter in const ['Blog', 'Medien', 'Learning', 'Sponsoren', 'Alle'])
                        ChoiceChip(
                          selected: _filter == filter,
                          label: Text(filter),
                          onSelected: (_) => setState(() => _filter = filter),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '8', label: 'Artikel')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Medien')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Sponsoren'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Freigaberegeln'),
                  SwitchListTile(value: _publicPreview, onChanged: (value) => setState(() => _publicPreview = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Public Preview aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Artikel, Kurs und Sponsor werden vor Veroeffentlichung als Vorschau angezeigt.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _qualityGate, onChanged: (value) => setState(() => _qualityGate = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Qualitaetsfreigabe erforderlich', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Learning, Medien und Public-Inhalte brauchen Review/Audit.', style: TextStyle(color: AirmiusColors.muted))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _ContentOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _ContentOperationCard extends StatelessWidget {
  const _ContentOperationCard({required this.item});

  final _ContentOperation item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: item.danger ? AirmiusColors.red.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(item.icon, color: item.danger ? AirmiusColors.red : AirmiusColors.blue, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    const SizedBox(height: 9),
                    Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.area), StatusPill(item.status, color: item.danger ? AirmiusColors.red : AirmiusColors.blue)]),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, secondary: !item.danger, onPressed: () => _run(context, item)),
              AirmiusButton(label: 'Preview', icon: Icons.visibility_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Preview', body: 'Mobile Vorschau, Public-Sichtbarkeit, Sprache und Freigabestatus fuer ${item.title} anzeigen.', status: 'Preview', icon: Icons.visibility_outlined)),
            ],
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _ContentOperation item) {
    final action = () => openUiAction(context, title: item.action, body: '${item.action}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.action}?', 'Diese Content-Aktion kann Public-Inhalte entfernen oder Sichtbarkeit aendern.', item.action, action);
      return;
    }
    action();
  }
}

class _ContentOperation {
  const _ContentOperation({required this.area, required this.title, required this.body, required this.status, required this.icon, required this.action, this.danger = false});

  final String area;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String action;
  final bool danger;
}

const _items = [
  _ContentOperation(area: 'Blog', title: 'Blog-Kategorie erstellen', body: 'Kategorie, Slug, Beschreibung, Sprache und Public-Sichtbarkeit anlegen.', status: 'Category', icon: Icons.category_outlined, action: 'Kategorie speichern'),
  _ContentOperation(area: 'Blog', title: 'Blog-Kategorie bearbeiten', body: 'Name, Slug, Reihenfolge und Sichtbarkeit aktualisieren.', status: 'Category', icon: Icons.edit_note_outlined, action: 'Kategorie aktualisieren'),
  _ContentOperation(area: 'Blog', title: 'Blog-Kategorie loeschen', body: 'Kategorie entfernen oder Inhalte in eine andere Kategorie verschieben.', status: 'Delete', icon: Icons.delete_outline, action: 'Kategorie loeschen', danger: true),
  _ContentOperation(area: 'Blog', title: 'Content-Bild hochladen', body: 'Inline-Bild fuer Artikel hochladen, Alt-Text und Rechtehinweis speichern.', status: 'Image', icon: Icons.cloud_upload_outlined, action: 'Bild hochladen'),
  _ContentOperation(area: 'Blog', title: 'Artikelvorschau', body: 'Public Preview fuer Artikel vor Veroeffentlichung anzeigen.', status: 'Preview', icon: Icons.visibility_outlined, action: 'Vorschau oeffnen'),
  _ContentOperation(area: 'Medien', title: 'Media Visuals aktualisieren', body: 'Bildrechte, Upload-Regeln, Public-Visuals und Richtlinienbanner speichern.', status: 'Visuals', icon: Icons.image_outlined, action: 'Visuals speichern'),
  _ContentOperation(area: 'Medien', title: 'Medienrichtlinie pruefen', body: 'Guardian Consent, Datenschutz, Sichtbarkeit und Altersfreigabe fuer Medien kontrollieren.', status: 'Policy', icon: Icons.policy_outlined, action: 'Richtlinie speichern'),
  _ContentOperation(area: 'Learning', title: 'Learning Quality freigeben', body: 'Kursqualitaet, Lektionen, Aufgaben, Quiz und Zertifikat pruefen.', status: 'Quality', icon: Icons.school_outlined, action: 'Qualitaet speichern'),
  _ContentOperation(area: 'Learning', title: 'Kurszertifikat pruefen', body: 'Zertifikatslogik, Gueltigkeit, Code und Public-Verifizierung kontrollieren.', status: 'Certificate', icon: Icons.verified_outlined, action: 'Zertifikat pruefen'),
  _ContentOperation(area: 'Sponsoren', title: 'Sponsor erstellen', body: 'Sponsorprofil, Logo, Paket, Kontakt und Public-Sichtbarkeit anlegen.', status: 'Sponsor', icon: Icons.handshake_outlined, action: 'Sponsor speichern'),
  _ContentOperation(area: 'Sponsoren', title: 'Sponsor bearbeiten', body: 'Paket, Kampagne, Reporting, Kontaktstatus und Sichtbarkeit aktualisieren.', status: 'Sponsor', icon: Icons.campaign_outlined, action: 'Sponsor aktualisieren'),
  _ContentOperation(area: 'Sponsoren', title: 'Sponsor loeschen', body: 'Sponsorprofil entfernen oder archivieren und Public-Sichtbarkeit stoppen.', status: 'Delete', icon: Icons.delete_forever_outlined, action: 'Sponsor loeschen', danger: true),
];
