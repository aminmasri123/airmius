import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'shared_file_access_screen.dart';
import 'ui_action_result_screen.dart';

class FilePreviewScreen extends StatefulWidget {
  const FilePreviewScreen({super.key, required this.title, required this.body, required this.status, required this.icon});

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  State<FilePreviewScreen> createState() => _FilePreviewScreenState();
}

class _FilePreviewScreenState extends State<FilePreviewScreen> {
  bool _requiredForApplication = true;
  bool _visibleOnClubProfile = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(height: 220, decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)), child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(widget.icon, color: AirmiusColors.blue, size: 72), const SizedBox(height: 12), const Text('Dokument-Vorschau', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))])),
            const SizedBox(height: 12),
            const Text('Datei-Preview, Download, Teilen, Rechte und Vereinsdokument-Verknuepfung als native Mobile-UI.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: 'PDF', label: 'Typ')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Links')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Rechte'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Verknuepfung'),
            SwitchListTile(value: _requiredForApplication, onChanged: (value) => setState(() => _requiredForApplication = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Pflichtdokument im Mitgliedsantrag', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Wird im Antrag angezeigt und muss akzeptiert werden.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _visibleOnClubProfile, onChanged: (value) => setState(() => _visibleOnClubProfile = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Auf Clubprofil sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Sichtbar für Mitglieder oder Besucher je nach Regel.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Freigabe & Rechte'),
            SizedBox(height: 10),
            _FileActionLine(icon: Icons.link_outlined, title: 'Share-Link', body: 'Ablaufdatum, Zugriff und Empfaenger verwalten.', status: 'Aktiv'),
            _FileActionLine(icon: Icons.download_outlined, title: 'Download', body: 'Datei herunterladen oder später offline verfuegbar machen.', status: 'PDF'),
            _FileActionLine(icon: Icons.history_outlined, title: 'Versionen', body: 'Dokumentversionen und Audit Trail vorbereiten.', status: 'v1'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Download', icon: Icons.download_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Download', body: '${widget.title} herunterladen oder offline verfuegbar machen.', status: 'PDF', icon: Icons.download_outlined)))),
            AirmiusButton(label: 'Teilen', icon: Icons.share_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Datei teilen', body: '${widget.title} per Share-Link, Ablaufdatum und Zugriffsregel freigeben.', status: 'Share', icon: Icons.share_outlined)))),
            AirmiusButton(label: 'Share-Link testen', icon: Icons.link_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SharedFileAccessScreen(token: 'share-${widget.title.toLowerCase().replaceAll(' ', '-')}')))),
          ]),
        ]),
      ),
    );
  }
}

class _FileActionLine extends StatelessWidget {
  const _FileActionLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}
