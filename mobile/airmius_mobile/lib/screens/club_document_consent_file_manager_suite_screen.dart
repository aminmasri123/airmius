import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubDocumentConsentFileManagerSuiteScreen extends StatefulWidget {
  const ClubDocumentConsentFileManagerSuiteScreen({super.key});

  @override
  State<ClubDocumentConsentFileManagerSuiteScreen> createState() => _ClubDocumentConsentFileManagerSuiteScreenState();
}

class _ClubDocumentConsentFileManagerSuiteScreenState extends State<ClubDocumentConsentFileManagerSuiteScreen> {
  bool requireConsent = true;
  bool visibleInApplication = true;
  bool autoFileManager = true;
  bool versionHistory = true;

  @override
  Widget build(BuildContext context) {
    final documents = [
      const _ClubDocument(
        title: 'Datenschutzerklaerung',
        category: 'Pflicht',
        body: 'Muss vor dem Absenden des Mitgliedschaftsantrags gelesen und bestätigt werden.',
        version: 'v2.1',
        icon: Icons.privacy_tip_outlined,
        color: AirmiusColors.blue,
      ),
      const _ClubDocument(
        title: 'Vereinssatzung',
        category: 'Regelwerk',
        body: 'Kann als PDF hochgeladen, im Dateimanager abgelegt und im Antrag verknuepft werden.',
        version: 'v1.4',
        icon: Icons.rule_folder_outlined,
        color: AirmiusColors.green,
      ),
      const _ClubDocument(
        title: 'Beitragsordnung',
        category: 'Finanzen',
        body: 'Verbindet Beitragsgruppen, Zahlungsrhythmus, SEPA und Zahlungsbedingungen.',
        version: 'v3.0',
        icon: Icons.receipt_long_outlined,
        color: AirmiusColors.amber,
      ),
      const _ClubDocument(
        title: 'Gesundheitsnachweis',
        category: 'Upload',
        body: 'User können später eigene Nachweise hochladen; Verein sieht Status und Gültigkeit.',
        version: 'optional',
        icon: Icons.health_and_safety_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Dokumente & Consent',
      subtitle: 'Upload, Dateimanager und Zustimmung',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('DATEIMANAGER'),
                const SizedBox(height: 8),
                const Text(
                  'Vereine können Dokumente nicht nur verlinken, sondern mobil hochladen, kategorisieren, versionieren und mit Mitgliedschaftsantraegen oder Zustimmungspflichten verbinden.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'Dokumente'),
                    Metric(value: 'PDF', label: 'Upload'),
                    Metric(value: 'Consent', label: 'Pflicht'),
                    Metric(value: 'Version', label: 'Historie'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('REGELN'),
                const SizedBox(height: 8),
                _ConsentSwitch(
                  title: 'Zustimmung im Antrag verlangen',
                  body: 'User muss Dokumente aktiv bestätigen, bevor die Anfrage gesendet werden kann.',
                  value: requireConsent,
                  color: AirmiusColors.blue,
                  onChanged: (value) => setState(() => requireConsent = value),
                ),
                _ConsentSwitch(
                  title: 'Im Mitgliedsantrag anzeigen',
                  body: 'Dokumente erscheinen direkt im Formular und nicht erst nach Absenden.',
                  value: visibleInApplication,
                  color: AirmiusColors.green,
                  onChanged: (value) => setState(() => visibleInApplication = value),
                ),
                _ConsentSwitch(
                  title: 'Automatisch im Dateimanager speichern',
                  body: 'Upload wird später in Vereinsordner, Kategorie und Sichtbarkeit eingeordnet.',
                  value: autoFileManager,
                  color: AirmiusColors.amber,
                  onChanged: (value) => setState(() => autoFileManager = value),
                ),
                _ConsentSwitch(
                  title: 'Versionen behalten',
                  body: 'Änderungen an Satzung, Datenschutz oder Beitragsordnung bleiben historisch nachvollziehbar.',
                  value: versionHistory,
                  color: AirmiusColors.pink,
                  onChanged: (value) => setState(() => versionHistory = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final document in documents) ...[
            _DocumentCard(document: document),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('UPLOAD-FLOW'),
                const SizedBox(height: 8),
                const Text(
                  'Der spätere API-Flow: Datei auswählen, Kategorie bestimmen, Sichtbarkeit setzen, Consent-Pflicht aktivieren, Version speichern und Vereinsadmins informieren.',
                  style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Upload vorbereiten',
                  icon: Icons.upload_file_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Dokument hochladen',
                    body: 'Diese UI bereitet Upload, Dateimanager-Zuordnung, Consent-Pflicht, Versionierung und Admin-Benachrichtigung für Vereinsdokumente vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.upload_file_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ClubDocument {
  const _ClubDocument({
    required this.title,
    required this.category,
    required this.body,
    required this.version,
    required this.icon,
    required this.color,
  });

  final String title;
  final String category;
  final String body;
  final String version;
  final IconData icon;
  final Color color;
}

class _ConsentSwitch extends StatelessWidget {
  const _ConsentSwitch({
    required this.title,
    required this.body,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final String body;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      subtitle: Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
      value: value,
      activeColor: color,
      onChanged: onChanged,
    );
  }
}

class _DocumentCard extends StatelessWidget {
  const _DocumentCard({required this.document});

  final _ClubDocument document;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: document.icon, color: document.color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(document.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(document.version, color: document.color),
                  ],
                ),
                const SizedBox(height: 4),
                Text(document.category, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                Text(document.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
