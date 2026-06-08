import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'file_preview_screen.dart';
import 'shared_file_access_screen.dart';
import 'file_operations_screen.dart';
import 'ui_action_result_screen.dart';

class FileManagerScreen extends StatefulWidget {
  const FileManagerScreen({super.key});

  @override
  State<FileManagerScreen> createState() => _FileManagerScreenState();
}

class _FileManagerScreenState extends State<FileManagerScreen> {
  String _folder = 'Vereinsdokumente';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.folder_shared_outlined), label: const Text('Datei Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => FileOperationsScreen(initialTab: 'Uploads')))),
        
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Dateimanager', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Dateimanager',
        subtitle: 'Ordner, Uploads, Freigaben und Vereinsdokumente',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Upload'),
                  const SizedBox(height: 8),
                  const Text('Dateien koennen spaeter direkt aus der App in Laravel hochgeladen und mit Vereinsregeln verknuepft werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(label: 'Datei hochladen', icon: Icons.upload_file_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Datei hochladen', body: 'Datei auswaehlen, in den Vereins-Dateimanager laden und optional mit Antraegen oder Regeln verknuepfen.', status: 'Upload', icon: Icons.upload_file_outlined)))),
                      AirmiusButton(label: 'Ordner erstellen', icon: Icons.create_new_folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Ordner erstellen', body: 'Neuen Ordner fuer Vereinsdokumente, Mitglieder, Rechnungen oder Training vorbereiten.', status: 'Ordner', icon: Icons.create_new_folder_outlined)))),
                      AirmiusButton(label: 'Share-Link oeffnen', icon: Icons.link_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SharedFileAccessScreen()))),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Ordner'),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final folder in const ['Vereinsdokumente', 'Mitglieder', 'Rechnungen', 'Training'])
                        ChoiceChip(
                          selected: _folder == folder,
                          label: Text(folder),
                          onSelected: (_) => setState(() => _folder = folder),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _folder == folder ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _folder == folder ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(_folder),
                  const SizedBox(height: 10),
                  const _FileLine(icon: Icons.picture_as_pdf_outlined, title: 'Datenschutz.pdf', body: 'Mit Mitgliedsantrag verknuepft', status: 'Pflicht'),
                  const _FileLine(icon: Icons.description_outlined, title: 'Beitragsordnung.docx', body: 'Mit Beitragsregel verknuepft', status: 'Pflicht'),
                  const _FileLine(icon: Icons.picture_as_pdf_outlined, title: 'Vereinsregeln.pdf', body: 'Sichtbar auf Clubprofil', status: 'Optional'),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.blue.withValues(alpha: 0.50),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SharedFileAccessScreen())),
              child: const Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Icon(Icons.link_outlined, color: AirmiusColors.blue),
                SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Geteilter Dateilink', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  SizedBox(height: 4),
                  Text('Token-Link, Ablaufdatum, Datenschutzbestaetigung und Download pruefen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ])),
                Icon(Icons.chevron_right, color: AirmiusColors.muted),
              ]),
            ),
            const SizedBox(height: 14),
            const AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow('Preview'),
                  SizedBox(height: 10),
                  _PreviewBox(),
                  SizedBox(height: 12),
                  Text('Ausgewaehlte Datei kann spaeter hier betrachtet, heruntergeladen, geteilt oder als Pflichtdokument verknuepft werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FileLine extends StatelessWidget {
  const _FileLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: InkWell(
        onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FilePreviewScreen(title: title, body: body, status: status, icon: icon))),
        borderRadius: BorderRadius.circular(12),
        child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(body, style: const TextStyle(color: AirmiusColors.muted)),
              ],
            ),
          ),
          StatusPill(status),
          const SizedBox(width: 6),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted, size: 20),
        ],
      ),
      ),
    );
  }
}

class _PreviewBox extends StatelessWidget {
  const _PreviewBox();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 170,
      decoration: BoxDecoration(
        color: AirmiusColors.cardSoft,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: const Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.picture_as_pdf_outlined, color: AirmiusColors.blue, size: 48),
            SizedBox(height: 10),
            Text('Dokument-Vorschau', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          ],
        ),
      ),
    );
  }
}

