import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'file_operations_screen.dart';
import 'membership_operations_screen.dart';

class ClubPolicyDocumentsScreen extends StatefulWidget {
  const ClubPolicyDocumentsScreen({super.key, this.initialTab = 'Dokumente'});

  final String initialTab;

  @override
  State<ClubPolicyDocumentsScreen> createState() => _ClubPolicyDocumentsScreenState();
}

class _ClubPolicyDocumentsScreenState extends State<ClubPolicyDocumentsScreen> {
  late String _tab = widget.initialTab;
  bool _privacyVisible = true;
  bool _rulesVisible = true;
  bool _feesVisible = true;
  bool _requiresUpload = true;
  bool _memberCanDownload = true;
  bool _publicCanSeeRules = false;

  @override
  Widget build(BuildContext context) {
    final docs = _documents.where((doc) => doc.area == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Vereinsdokumente', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Vereinsdokumente',
        subtitle: 'Datenschutz, Regeln, Beitragsordnung, Uploads, Sichtbarkeit und Mitgliedsantrag',
        trailing: const StatusPill('Club'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 14),
                  const Text('Vereine entscheiden, was gezeigt wird und welche Dokumente Pflicht sind.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  const SizedBox(height: 8),
                  const Text('Dokumente koennen als Link oder Upload gepflegt werden. Uploads werden spaeter in den Dateimanager uebergeben und mit Zweck, Sichtbarkeit, Version und Mitgliedsantrag verknuepft.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Row(children: const [Expanded(child: MetricCard(value: '6', label: 'Dokumente')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Sichtbar')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'Upload', label: 'Dateien'))]),
                  const SizedBox(height: 14),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    for (final tab in _tabs)
                      ChoiceChip(
                        label: Text(tab),
                        selected: _tab == tab,
                        onSelected: (_) => setState(() => _tab = tab),
                        selectedColor: AirmiusColors.green.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.cardSoft,
                        side: BorderSide(color: _tab == tab ? AirmiusColors.green : AirmiusColors.border),
                        labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      ),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _VisibilityPanel(
              privacyVisible: _privacyVisible,
              rulesVisible: _rulesVisible,
              feesVisible: _feesVisible,
              requiresUpload: _requiresUpload,
              memberCanDownload: _memberCanDownload,
              publicCanSeeRules: _publicCanSeeRules,
              onPrivacy: (value) => setState(() => _privacyVisible = value),
              onRules: (value) => setState(() => _rulesVisible = value),
              onFees: (value) => setState(() => _feesVisible = value),
              onUpload: (value) => setState(() => _requiresUpload = value),
              onDownload: (value) => setState(() => _memberCanDownload = value),
              onPublicRules: (value) => setState(() => _publicCanSeeRules = value),
            ),
            const SizedBox(height: 16),
            for (final doc in docs) ...[
              _ClubDocumentCard(document: doc),
              const SizedBox(height: 12),
            ],
            _PolicyActionsPanel(tab: _tab),
          ],
        ),
      ),
    );
  }
}

class _VisibilityPanel extends StatelessWidget {
  const _VisibilityPanel({required this.privacyVisible, required this.rulesVisible, required this.feesVisible, required this.requiresUpload, required this.memberCanDownload, required this.publicCanSeeRules, required this.onPrivacy, required this.onRules, required this.onFees, required this.onUpload, required this.onDownload, required this.onPublicRules});

  final bool privacyVisible;
  final bool rulesVisible;
  final bool feesVisible;
  final bool requiresUpload;
  final bool memberCanDownload;
  final bool publicCanSeeRules;
  final ValueChanged<bool> onPrivacy;
  final ValueChanged<bool> onRules;
  final ValueChanged<bool> onFees;
  final ValueChanged<bool> onUpload;
  final ValueChanged<bool> onDownload;
  final ValueChanged<bool> onPublicRules;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.green.withValues(alpha: .44),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Eyebrow('Sichtbarkeit & Pflicht'),
            const SizedBox(height: 8),
            const Text('Diese Schalter bilden ab, was der Verein spaeter selbst einstellen kann: sichtbar im Profil, sichtbar im Antrag, Download erlaubt oder als Upload verpflichtend.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 10),
            _PolicySwitch(icon: Icons.privacy_tip_outlined, title: 'Datenschutz im Antrag anzeigen', body: 'Nutzer sehen Datenschutzdokumente vor dem Absenden der Mitgliedsanfrage.', value: privacyVisible, onChanged: onPrivacy, color: AirmiusColors.green),
            _PolicySwitch(icon: Icons.rule_folder_outlined, title: 'Vereinsregeln sichtbar', body: 'Regeln koennen im Vereinsprofil, Antrag oder nur intern sichtbar sein.', value: rulesVisible, onChanged: onRules, color: AirmiusColors.blue),
            _PolicySwitch(icon: Icons.receipt_long_outlined, title: 'Beitragsordnung anzeigen', body: 'Zahlrhythmus, Zahlmethode, Aufnahmegebuehr und Beitrag werden transparent gezeigt.', value: feesVisible, onChanged: onFees, color: AirmiusColors.amber),
            _PolicySwitch(icon: Icons.upload_file_outlined, title: 'Upload fuer Antrag verpflichtend', body: 'Zum Beispiel Passfoto, Ausweis, Bescheinigung, SEPA-Mandat oder unterschriebene Ordnung.', value: requiresUpload, onChanged: onUpload, color: AirmiusColors.green),
            _PolicySwitch(icon: Icons.download_outlined, title: 'Mitglieder duerfen herunterladen', body: 'Dokumente koennen spaeter fuer Mitglieder downloadbar sein oder nur als Lesedokument angezeigt werden.', value: memberCanDownload, onChanged: onDownload, color: AirmiusColors.blue),
            _PolicySwitch(icon: Icons.public_outlined, title: 'Regeln oeffentlich zeigen', body: 'Public-Sichtbarkeit fuer Vereinsprofil und Gastseite, getrennt von internem Mitgliederbereich.', value: publicCanSeeRules, onChanged: onPublicRules, color: AirmiusColors.amber),
          ],
        ),
      );
}

class _ClubDocumentCard extends StatelessWidget {
  const _ClubDocumentCard({required this.document});

  final _ClubDocument document;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: document.color.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(width: 50, height: 50, decoration: BoxDecoration(color: document.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: document.color.withValues(alpha: .45))), child: Icon(document.icon, color: document.color)),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(document.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
              const SizedBox(height: 5),
              Text(document.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(document.area, color: document.color), StatusPill(document.source), StatusPill(document.visibility, color: document.color)]),
            ])),
          ]),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Upload', icon: Icons.upload_file_outlined, onPressed: () => openUiAction(context, title: '${document.title} hochladen', body: 'Datei auswaehlen, Zweck ${document.area}, Sichtbarkeit ${document.visibility}, Version und Dateimanager-Verknuepfung speichern.', status: 'Upload', icon: Icons.upload_file_outlined)),
            AirmiusButton(label: 'Dateimanager', icon: Icons.folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FileOperationsScreen()))),
            AirmiusButton(label: 'Antrag', icon: Icons.assignment_ind_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipOperationsScreen()))),
          ]),
        ]),
      );
}

class _PolicyActionsPanel extends StatelessWidget {
  const _PolicyActionsPanel({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.blue.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Policy Workflow'),
          const SizedBox(height: 8),
          Text('Aktueller Bereich: $tab. Spaeter speichert Laravel pro Verein Dokumentzweck, Pflichtstatus, Sichtbarkeit, Version, Link/Upload und ob Nutzer vor Absenden zustimmen muessen.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Regeln speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Dokumentregeln speichern', body: 'Sichtbarkeit, Pflichtfelder, Downloadrechte, Beitragsregeln und Dokumentversionen fuer den Verein speichern.', status: 'Club Policy', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Version veroeffentlichen', icon: Icons.publish_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Version veroeffentlichen', body: 'Neue Dokumentversion aktivieren, Mitglieder informieren und Antrag-Consent aktualisieren.', status: 'Version', icon: Icons.publish_outlined)),
          ]),
        ]),
      );
}

class _PolicySwitch extends StatelessWidget {
  const _PolicySwitch({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, required this.color});

  final IconData icon;
  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final Color color;

  @override
  Widget build(BuildContext context) => SwitchListTile(
        value: value,
        onChanged: onChanged,
        activeColor: color,
        contentPadding: EdgeInsets.zero,
        secondary: Icon(icon, color: color),
        title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        subtitle: Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
      );
}

class _ClubDocument {
  const _ClubDocument({required this.area, required this.title, required this.body, required this.source, required this.visibility, required this.icon, required this.color});

  final String area;
  final String title;
  final String body;
  final String source;
  final String visibility;
  final IconData icon;
  final Color color;
}

const _tabs = ['Dokumente', 'Regeln', 'Beitrag', 'Consent', 'Upload'];

const _documents = <_ClubDocument>[
  _ClubDocument(area: 'Dokumente', title: 'Datenschutzerklaerung Verein', body: 'Vereinsbezogene Datenschutzhinweise fuer Mitgliedsantrag, Profil, Kommunikation, Fotos und Zahlungen.', source: 'Upload/Link', visibility: 'Antrag', icon: Icons.privacy_tip_outlined, color: AirmiusColors.green),
  _ClubDocument(area: 'Dokumente', title: 'Satzung / Vereinsordnung', body: 'Grundregeln, Mitgliedspflichten, Rechte, Kuedigung, interne Kommunikation und Verhaltensregeln.', source: 'Upload', visibility: 'Mitglieder', icon: Icons.rule_folder_outlined, color: AirmiusColors.blue),
  _ClubDocument(area: 'Regeln', title: 'Trainings- und Hallenordnung', body: 'Regeln fuer Training, Anwesenheit, Ausruestung, Sicherheit, Medien und Minderjaehrige.', source: 'Dateimanager', visibility: 'Team', icon: Icons.sports_outlined, color: AirmiusColors.green),
  _ClubDocument(area: 'Regeln', title: 'Medien- und Fotoregeln', body: 'Foto-/Video-Einwilligung, Guardian Consent, Widerruf, Sichtbarkeit und Moderation.', source: 'Upload/Link', visibility: 'Antrag', icon: Icons.photo_camera_outlined, color: AirmiusColors.amber),
  _ClubDocument(area: 'Beitrag', title: 'Beitragsordnung', body: 'Monatlich, jaehrlich, 4 Monate, 6 Monate, Barzahlung, Ueberweisung, SEPA, Aufnahmegebuehr und Familienrabatt.', source: 'Upload', visibility: 'Oeffentlich', icon: Icons.receipt_long_outlined, color: AirmiusColors.amber),
  _ClubDocument(area: 'Beitrag', title: 'SEPA-Lastschriftmandat', body: 'Optionales oder verpflichtendes Formular fuer Zahlungsdaten, Mandatsreferenz und Einzugserlaubnis.', source: 'Upload', visibility: 'Antrag', icon: Icons.account_balance_outlined, color: AirmiusColors.blue),
  _ClubDocument(area: 'Consent', title: 'Jugendschutz / Guardian Consent', body: 'Elternfreigaben fuer Minderjaehrige, Fahrgemeinschaft, Fotos, Chat, Events und Maturity-Gates.', source: 'Dateimanager', visibility: 'Guardian', icon: Icons.family_restroom_outlined, color: AirmiusColors.green),
  _ClubDocument(area: 'Consent', title: 'Widerruf & Rueckzug', body: 'Hinweise zum Rueckzug einer Mitgliedsanfrage, Widerruf von Einwilligungen und Kontakt zum Verein.', source: 'Link', visibility: 'Antrag', icon: Icons.undo_outlined, color: AirmiusColors.red),
  _ClubDocument(area: 'Upload', title: 'Passfoto / Profilbild', body: 'Optionaler Upload fuer Mitgliedsausweis, Teamprofil oder Vereinsverwaltung.', source: 'Upload', visibility: 'Intern', icon: Icons.badge_outlined, color: AirmiusColors.blue),
  _ClubDocument(area: 'Upload', title: 'Nachweis / Bescheinigung', body: 'Schueler-, Studenten-, Gesundheits-, Lizenz- oder Trainerbescheinigung als Pflicht- oder Optionalfeld.', source: 'Upload', visibility: 'Admin', icon: Icons.verified_outlined, color: AirmiusColors.green),
];
