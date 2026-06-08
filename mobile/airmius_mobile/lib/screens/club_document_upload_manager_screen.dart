import 'package:flutter/material.dart';

import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_contribution_rules_screen.dart';
import 'club_policy_documents_screen.dart';
import 'file_operations_screen.dart';

class ClubDocumentUploadManagerScreen extends StatefulWidget {
  const ClubDocumentUploadManagerScreen({super.key});

  @override
  State<ClubDocumentUploadManagerScreen> createState() => _ClubDocumentUploadManagerScreenState();
}

class _ClubDocumentUploadManagerScreenState extends State<ClubDocumentUploadManagerScreen> {
  String _folder = 'Alle';
  bool _moveToFileManager = true;
  bool _requireVersion = true;
  bool _linkToRules = true;
  bool _memberVisible = true;
  bool _uploading = false;
  String? _lastUploadIntent;

  static const _folders = ['Alle', 'Datenschutz', 'Regeln', 'Beitraege', 'SEPA', 'Formulare'];

  final List<_DocumentItem> _documents = const [
    _DocumentItem(folder: 'Datenschutz', title: 'Datenschutzerklaerung', type: 'PDF', status: 'Pflicht', linked: 'Mitgliedsantrag', icon: Icons.privacy_tip_outlined, color: AirmiusColors.blue),
    _DocumentItem(folder: 'Regeln', title: 'Vereinsordnung', type: 'PDF', status: 'Sichtbar', linked: 'Clubseite', icon: Icons.gavel_outlined, color: AirmiusColors.green),
    _DocumentItem(folder: 'Beitraege', title: 'Beitragsordnung 2026', type: 'PDF', status: 'Verknuepft', linked: 'Beitragsregeln', icon: Icons.receipt_long_outlined, color: AirmiusColors.amber),
    _DocumentItem(folder: 'SEPA', title: 'SEPA-Lastschriftmandat', type: 'PDF', status: 'Privat', linked: 'Zahlungsdaten', icon: Icons.account_balance_outlined, color: AirmiusColors.blueDeep),
    _DocumentItem(folder: 'Formulare', title: 'Einwilligung Minderjaehrige', type: 'DOCX', status: 'Review', linked: 'Erziehungsberechtigte', icon: Icons.description_outlined, color: AirmiusColors.red),
  ];

  List<_DocumentItem> get _visibleDocuments => _documents.where((document) => _folder == 'Alle' || document.folder == _folder).toList();

  @override
  Widget build(BuildContext context) {
    final visibleDocuments = _visibleDocuments;

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(title: 'Vereinsdateien hochladen', subtitle: 'Datenschutz, Regeln, Beitragsdokumente, SEPA, Formulare und Datei-Manager-Verknuepfung.'),
                        const SizedBox(height: 16),
                        _UploadHero(
                          uploading: _uploading,
                          lastUploadIntent: _lastUploadIntent,
                          onUpload: () => _requestUploadIntent(fileName: 'vereinsdokument.pdf', mimeType: 'application/pdf'),
                        ),
                        const SizedBox(height: 16),
                        _FolderPicker(folders: _folders, value: _folder, onChanged: (value) => setState(() => _folder = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Upload-Regeln',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'In Dateimanager verschieben', subtitle: 'Uploads landen automatisch im Vereins-Dateimanager.', value: _moveToFileManager, onChanged: (value) => setState(() => _moveToFileManager = value)),
                              _SwitchRow(title: 'Versionierung erzwingen', subtitle: 'Neue Dateien ersetzen alte Regeln nicht heimlich.', value: _requireVersion, onChanged: (value) => setState(() => _requireVersion = value)),
                              _SwitchRow(title: 'Mit Beitragsregeln verknuepfen', subtitle: 'Beitragsordnung und SEPA koennen direkt am Beitrag haengen.', value: _linkToRules, onChanged: (value) => setState(() => _linkToRules = value)),
                              _SwitchRow(title: 'Fuer Mitglieder sichtbar', subtitle: 'Verein entscheidet, welche Dateien im Antrag oder Profil sichtbar sind.', value: _memberVisible, onChanged: (value) => setState(() => _memberVisible = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final document in visibleDocuments) ...[
                          _DocumentCard(document: document, onAction: _handleAction),
                          const SizedBox(height: 12),
                        ],
                        if (visibleDocuments.isEmpty) const EmptyPanel('Keine Dateien fuer diesen Ordner gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Direkte Verknuepfungen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Dateimanager', icon: Icons.folder_copy_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => FileOperationsScreen()))),
                              AirmiusButton(label: 'Vereinsdokumente', icon: Icons.policy_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPolicyDocumentsScreen()))),
                              AirmiusButton(label: 'Beitragsregeln', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubContributionRulesScreen()))),
                              AirmiusButton(label: 'Upload pruefen', icon: Icons.cloud_upload_outlined, secondary: true, onPressed: () => _requestUploadIntent(fileName: 'upload-pruefung.pdf', mimeType: 'application/pdf')),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _handleAction(String action, _DocumentItem document) {
    if (action == 'Ersetzen') {
      _requestUploadIntent(fileName: '${document.title}.${document.type.toLowerCase()}', mimeType: document.type == 'DOCX' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'application/pdf');
      return;
    }
    _toast('${document.title}: $action vorbereitet');
  }

  Future<void> _requestUploadIntent({required String fileName, required String mimeType}) async {
    setState(() {
      _uploading = true;
      _lastUploadIntent = null;
    });

    try {
      final services = AirmiusServicesScope.of(context);
      final intent = await services.repositories.files.createUploadIntent(scope: 'club_documents', fileName: fileName, mimeType: mimeType);
      final uploadId = intent['upload_id'] ?? intent['id'] ?? intent['asset_id'] ?? 'bereit';
      final target = intent['target_folder'] ?? intent['folder'] ?? 'Vereins-Dateimanager';
      setState(() => _lastUploadIntent = 'Upload-Intent $uploadId -> $target');
      _toast('Upload-Intent erstellt: $fileName');
    } catch (error) {
      setState(() => _lastUploadIntent = 'Upload fehlgeschlagen: $error');
      _toast('Upload-Intent konnte nicht erstellt werden.');
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _UploadHero extends StatelessWidget {
  const _UploadHero({required this.onUpload, required this.uploading, this.lastUploadIntent});

  final VoidCallback onUpload;
  final bool uploading;
  final String? lastUploadIntent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF132437), Color(0xFF0A111C)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('DATEIEN & REGELN'), SizedBox(height: 4), Text('Dokumente hochladen und verknuepfen', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: uploading ? 'Wird vorbereitet' : 'Upload', icon: Icons.cloud_upload_outlined, onPressed: onUpload),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Vereine koennen Dateien nicht nur als Link hinterlegen, sondern als Upload im Dateimanager speichern und systematisch mit Regeln verbinden.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '5', label: 'Ordner')), SizedBox(width: 10), Expanded(child: MetricCard(value: '12', label: 'Dateien')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Links'))]),
          if (lastUploadIntent != null) ...[
            const SizedBox(height: 12),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.blue.withValues(alpha: .45))),
              child: Text(lastUploadIntent!, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800)),
            ),
          ],
        ],
      ),
    );
  }
}

class _FolderPicker extends StatelessWidget {
  const _FolderPicker({required this.folders, required this.value, required this.onChanged});

  final List<String> folders;
  final String value;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final folder in folders) ...[
            ChoiceChip(
              label: Text(folder),
              selected: value == folder,
              onSelected: (_) => onChanged(folder),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == folder ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == folder ? AirmiusColors.blue : AirmiusColors.border),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
          Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
        ],
      ),
    );
  }
}

class _DocumentCard extends StatelessWidget {
  const _DocumentCard({required this.document, required this.onAction});

  final _DocumentItem document;
  final void Function(String action, _DocumentItem document) onAction;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: document.folder,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(width: 48, height: 48, decoration: BoxDecoration(color: document.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: document.color.withValues(alpha: .5))), child: Icon(document.icon, color: document.color)),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(document.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text('${document.type} - ${document.linked}', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))])),
              StatusPill(document.status, color: document.color),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Ersetzen', icon: Icons.swap_horiz_outlined, secondary: true, onPressed: () => onAction('Ersetzen', document)),
            AirmiusButton(label: 'Verknuepfen', icon: Icons.link_outlined, secondary: true, onPressed: () => onAction('Verknuepfen', document)),
            AirmiusButton(label: 'Sichtbarkeit', icon: Icons.visibility_outlined, secondary: true, onPressed: () => onAction('Sichtbarkeit', document)),
            AirmiusButton(label: 'Version', icon: Icons.history_outlined, secondary: true, onPressed: () => onAction('Version', document)),
          ]),
        ],
      ),
    );
  }
}

class _DocumentItem {
  const _DocumentItem({required this.folder, required this.title, required this.type, required this.status, required this.linked, required this.icon, required this.color});

  final String folder;
  final String title;
  final String type;
  final String status;
  final String linked;
  final IconData icon;
  final Color color;
}
