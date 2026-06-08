import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class FileOperationsScreen extends StatefulWidget {
  const FileOperationsScreen({super.key, this.initialTab = 'Uploads'});

  final String initialTab;

  @override
  State<FileOperationsScreen> createState() => _FileOperationsScreenState();
}

class _FileOperationsScreenState extends State<FileOperationsScreen> {
  String _tab = 'Uploads';
  String _query = '';

  @override
  void initState() {
    super.initState();
    if (_tabs.contains(widget.initialTab)) _tab = widget.initialTab;
  }

  @override
  Widget build(BuildContext context) {
    final operations = _filtered(_tab == 'Alle' ? _operations : _operations.where((item) => item.tab == _tab).toList());
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Datei-Operationen', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Datei-Operationen',
        subtitle: 'Uploads, Vereinsdokumente, Regeln, Share-Links und API-Zuordnung',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              borderColor: AirmiusColors.blue.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Eyebrow('Dokumente & Dateimanager'),
                  const SizedBox(height: 8),
                  const Text('Vereine koennen Dateien hochladen, als Pflichtdokument markieren, mit Mitgliedsantraegen oder Beitragsregeln verknuepfen und sicher teilen.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  SearchBox(hint: 'Dateiaktion suchen', onChanged: (value) => setState(() => _query = value.trim().toLowerCase())),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in _tabs)
                        ChoiceChip(
                          label: Text(tab),
                          selected: _tab == tab,
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: AirmiusColors.blue.withValues(alpha: .24),
                          backgroundColor: AirmiusColors.panelSoft,
                          side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            for (final operation in operations) ...[
              _FileOperationCard(operation: operation),
              const SizedBox(height: 12),
            ],
            if (operations.isEmpty) const EmptyPanel('Keine Dateiaktion gefunden.'),
          ],
        ),
      ),
    );
  }

  List<_FileOperation> _filtered(List<_FileOperation> source) {
    if (_query.isEmpty) return source;
    return source.where((item) => '${item.title} ${item.body} ${item.endpoint} ${item.method}'.toLowerCase().contains(_query)).toList();
  }
}

class _FileOperationCard extends StatelessWidget {
  const _FileOperationCard({required this.operation});

  final _FileOperation operation;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: operation.danger ? AirmiusColors.red.withValues(alpha: .45) : AirmiusColors.border,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 48,
                  height: 48,
                  decoration: BoxDecoration(color: operation.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: operation.color.withValues(alpha: .5))),
                  child: Icon(operation.icon, color: operation.color),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(operation.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 17)),
                      const SizedBox(height: 5),
                      Text(operation.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    ],
                  ),
                ),
                StatusPill(operation.tab, color: operation.color),
              ],
            ),
            const SizedBox(height: 14),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AirmiusColors.bg, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
              child: Text('${operation.method} ${operation.endpoint}', style: const TextStyle(color: AirmiusColors.green, fontSize: 12, fontWeight: FontWeight.w900)),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                AirmiusButton(label: operation.action, icon: operation.icon, danger: operation.danger, onPressed: () => openUiAction(context, title: operation.title, body: '${operation.body}\n\nEndpoint: ${operation.method} ${operation.endpoint}', status: operation.tab, icon: operation.icon)),
                AirmiusButton(label: 'API Kontext', icon: Icons.api_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${operation.title} API', body: 'Payload, Rollenrechte, Auditlog, Dateimanager-Ziel und spaetere Laravel-Response fuer ${operation.title} anzeigen.', status: 'API', icon: Icons.api_outlined)),
              ],
            ),
          ],
        ),
      );
}

class _FileOperation {
  const _FileOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
  final String tab;
  final String title;
  final String body;
  final String method;
  final String endpoint;
  final IconData icon;
  final String action;
  final Color color;
  final bool danger;
}

const _tabs = ['Uploads', 'Verein', 'Antrag', 'Team', 'Share', 'Regeln', 'Alle'];

final _operations = <_FileOperation>[
  _FileOperation(tab: 'Uploads', title: 'Datei hochladen', body: 'Datei aus Galerie, Kamera oder Dateisystem in den zentralen Dateimanager laden.', method: 'POST', endpoint: ApiContract.uploads, icon: Icons.upload_file_outlined, action: 'Upload starten', color: AirmiusColors.blue),
  _FileOperation(tab: 'Uploads', title: 'Datei aktualisieren', body: 'Name, Beschreibung, Ordner, Sichtbarkeit und Metadaten einer Datei bearbeiten.', method: 'PATCH', endpoint: ApiContract.upload(1), icon: Icons.edit_outlined, action: 'Aktualisieren', color: AirmiusColors.blue),
  _FileOperation(tab: 'Uploads', title: 'Datei loeschen', body: 'Datei entfernen und bestehende Verknuepfungen vorher anzeigen.', method: 'DELETE', endpoint: ApiContract.upload(1), icon: Icons.delete_outline, action: 'Loeschen', color: AirmiusColors.red, danger: true),
  _FileOperation(tab: 'Verein', title: 'Vereinsdokument verknuepfen', body: 'Datenschutz, Beitragsordnung oder Vereinsregeln an das Vereinsprofil haengen.', method: 'POST', endpoint: ApiContract.clubDocuments(1), icon: Icons.folder_shared_outlined, action: 'Verknuepfen', color: AirmiusColors.amber),
  _FileOperation(tab: 'Verein', title: 'Vereinsdokument entfernen', body: 'Verknuepfung loesen, ohne die Datei aus dem Dateimanager zu loeschen.', method: 'DELETE', endpoint: ApiContract.clubDocument(1, 1), icon: Icons.link_off_outlined, action: 'Entfernen', color: AirmiusColors.red, danger: true),
  _FileOperation(tab: 'Antrag', title: 'Pflichtdokument an Antrag haengen', body: 'Dokumente fuer Mitgliedsantrag sichtbar machen und als Pflicht/Optional markieren.', method: 'POST', endpoint: ApiContract.clubMembershipDocuments(1), icon: Icons.assignment_outlined, action: 'Antrag verknuepfen', color: AirmiusColors.green),
  _FileOperation(tab: 'Antrag', title: 'Antragsdokument entfernen', body: 'Pflichtdokument aus Mitgliedsantrag oder Beitragsregel entfernen.', method: 'DELETE', endpoint: ApiContract.clubMembershipDocument(1, 1), icon: Icons.assignment_return_outlined, action: 'Antrag loesen', color: AirmiusColors.red, danger: true),
  _FileOperation(tab: 'Team', title: 'Teamdatei verknuepfen', body: 'Trainingsordnung, Spielplan oder interne Datei an ein Team haengen.', method: 'POST', endpoint: ApiContract.teamFiles(1), icon: Icons.groups_2_outlined, action: 'Team verknuepfen', color: AirmiusColors.blue),
  _FileOperation(tab: 'Team', title: 'Teamdatei entfernen', body: 'Dateizugriff fuer Team entfernen und Rechte aktualisieren.', method: 'DELETE', endpoint: ApiContract.teamFile(1, 1), icon: Icons.group_remove_outlined, action: 'Team loesen', color: AirmiusColors.red, danger: true),
  _FileOperation(tab: 'Share', title: 'Share-Link erstellen', body: 'Zeitlich begrenzten Link mit Token, Ablaufdatum und Datenschutzhinweis erzeugen.', method: 'POST', endpoint: ApiContract.uploadShare(1), icon: Icons.link_outlined, action: 'Link erstellen', color: AirmiusColors.green),
  _FileOperation(tab: 'Share', title: 'Geteilte Datei oeffnen', body: 'Oeffentliche Token-Route fuer Download oder Vorschau abbilden.', method: 'GET', endpoint: ApiContract.sharedFilePublic('{token}'), icon: Icons.visibility_outlined, action: 'Token oeffnen', color: AirmiusColors.green),
  _FileOperation(tab: 'Regeln', title: 'Dokument als Datenschutz setzen', body: 'Datei als aktuelle Datenschutzversion markieren und mit Antraegen verbinden.', method: 'PUT', endpoint: ApiContract.clubDocumentPurpose(1, 1, 'privacy'), icon: Icons.privacy_tip_outlined, action: 'Zweck setzen', color: AirmiusColors.amber),
  _FileOperation(tab: 'Regeln', title: 'Dokument als Beitragsordnung setzen', body: 'Datei als Beitragsregel markieren, damit Mitglieder sie vor Antrag sehen.', method: 'PUT', endpoint: ApiContract.clubDocumentPurpose(1, 1, 'fees'), icon: Icons.payments_outlined, action: 'Regel setzen', color: AirmiusColors.amber),
];
