import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import 'file_operations_screen.dart';
import 'file_preview_screen.dart';
import 'shared_file_access_screen.dart';
import 'ui_action_result_screen.dart';

class FileManagerScreen extends StatefulWidget {
  const FileManagerScreen({super.key});

  @override
  State<FileManagerScreen> createState() => _FileManagerScreenState();
}

class _FileManagerScreenState extends State<FileManagerScreen> {
  String _scope = 'Meine Dateien';
  String _folder = 'Hauptebene';
  bool _showFilters = false;
  bool _showActions = false;
  String _fileName = 'Datei waehlen';
  final _folderNameController = TextEditingController();

  static const _scopes = ['Meine Dateien', 'Team', 'Verein', 'Event'];
  static const _folders = [
    _FileFolder('Vereinsdokumente', 3),
    _FileFolder('Mitglieder', 2),
    _FileFolder('Rechnungen', 4),
    _FileFolder('Training', 1),
  ];
  static const _files = [
    _ManagedFile(Icons.picture_as_pdf_outlined, 'Datenschutz.pdf', 'Privat - PDF - 420 KB', 'Mit Mitgliedsantrag verknuepft', 'Pflicht'),
    _ManagedFile(Icons.description_outlined, 'Beitragsordnung.docx', 'Verein - DOCX - 86 KB', 'Mit Beitragsregel verknuepft', 'Pflicht'),
    _ManagedFile(Icons.picture_as_pdf_outlined, 'Vereinsregeln.pdf', 'Club - PDF - 1.2 MB', 'Sichtbar auf Clubprofil', 'Optional'),
  ];

  @override
  void dispose() {
    _folderNameController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Dateien', style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(
            tooltip: 'Share-Link oeffnen',
            icon: const Icon(Icons.link_outlined),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SharedFileAccessScreen())),
          ),
          IconButton(
            tooltip: 'Datei Ops',
            icon: const Icon(Icons.folder_shared_outlined),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const FileOperationsScreen(initialTab: 'Uploads'))),
          ),
        ],
      ),
      body: Stack(
        children: [
          ListView(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 86),
            children: [
              _ScopePanel(value: _scope, values: _scopes, onChanged: (value) => setState(() => _scope = value)),
              const SizedBox(height: 12),
              _FileBrowserCard(
                folder: _folder,
                totalFolders: _folders.length,
                totalFiles: _files.length,
                showFilters: _showFilters,
                showActions: _showActions,
                fileName: _fileName,
                folderNameController: _folderNameController,
                onToggleFilters: () => setState(() {
                  _showFilters = !_showFilters;
                  _showActions = false;
                }),
                onToggleActions: () => setState(() {
                  _showActions = !_showActions;
                  _showFilters = false;
                }),
                onPickFile: _openUploadIntent,
                onUpload: _submitUpload,
                onCreateFolder: _createFolder,
                onSearchChanged: (_) {},
                onBack: _folder == 'Hauptebene' ? null : () => setState(() => _folder = 'Hauptebene'),
                folders: _folders,
                files: _files,
                onOpenFolder: (folder) => setState(() => _folder = folder.name),
                onOpenFile: (file) => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => FilePreviewScreen(
                      title: file.title,
                      body: file.previewBody,
                      status: file.status,
                      icon: file.icon,
                    ),
                  ),
                ),
                onShare: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SharedFileAccessScreen())),
                onRename: () => _showPreparedAction('Umbenennen', 'Datei oder Ordner umbenennen, ohne bestehende Verknuepfungen zu verlieren.', Icons.edit_outlined),
                onDelete: () => _showPreparedAction('Loeschen', 'Loeschbestaetigung wie in Laravel vorbereiten.', Icons.delete_outline),
              ),
            ],
          ),
          const Positioned(
            left: 12,
            right: 12,
            bottom: 12,
            child: _StorageFooter(),
          ),
        ],
      ),
    );
  }

  void _openUploadIntent() {
    setState(() => _fileName = 'upload-pruefung.pdf');
  }

  void _submitUpload() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => const UiActionResultScreen(
          title: 'Datei hochladen',
          body: 'Datei auswaehlen, in den Dateimanager laden und optional mit Antraegen oder Regeln verknuepfen.',
          status: 'Upload',
          icon: Icons.upload_file_outlined,
        ),
      ),
    );
  }

  void _createFolder() {
    final name = _folderNameController.text.trim();
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => UiActionResultScreen(
          title: 'Ordner erstellen',
          body: name.isEmpty ? 'Neuen Ordner fuer Dateien vorbereiten.' : 'Ordner "$name" im Dateimanager vorbereiten.',
          status: 'Ordner',
          icon: Icons.create_new_folder_outlined,
        ),
      ),
    );
  }

  void _showPreparedAction(String title, String body, IconData icon) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => UiActionResultScreen(title: title, body: body, status: 'UI bereit', icon: icon),
      ),
    );
  }
}

class _ScopePanel extends StatelessWidget {
  const _ScopePanel({required this.value, required this.values, required this.onChanged});

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      padding: const EdgeInsets.all(10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Bereich', style: TextStyle(color: AirmiusColors.muted, fontSize: 13, fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          Container(
            height: 44,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              color: AirmiusColors.input,
              borderRadius: BorderRadius.circular(9),
              border: Border.all(color: AirmiusColors.border),
            ),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: value,
                isExpanded: true,
                dropdownColor: AirmiusColors.card,
                iconEnabledColor: AirmiusColors.muted,
                style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
                items: values.map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
                onChanged: (next) {
                  if (next != null) onChanged(next);
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FileBrowserCard extends StatelessWidget {
  const _FileBrowserCard({
    required this.folder,
    required this.totalFolders,
    required this.totalFiles,
    required this.showFilters,
    required this.showActions,
    required this.fileName,
    required this.folderNameController,
    required this.onToggleFilters,
    required this.onToggleActions,
    required this.onPickFile,
    required this.onUpload,
    required this.onCreateFolder,
    required this.onSearchChanged,
    required this.onBack,
    required this.folders,
    required this.files,
    required this.onOpenFolder,
    required this.onOpenFile,
    required this.onShare,
    required this.onRename,
    required this.onDelete,
  });

  final String folder;
  final int totalFolders;
  final int totalFiles;
  final bool showFilters;
  final bool showActions;
  final String fileName;
  final TextEditingController folderNameController;
  final VoidCallback onToggleFilters;
  final VoidCallback onToggleActions;
  final VoidCallback onPickFile;
  final VoidCallback onUpload;
  final VoidCallback onCreateFolder;
  final ValueChanged<String> onSearchChanged;
  final VoidCallback? onBack;
  final List<_FileFolder> folders;
  final List<_ManagedFile> files;
  final ValueChanged<_FileFolder> onOpenFolder;
  final ValueChanged<_ManagedFile> onOpenFile;
  final VoidCallback onShare;
  final VoidCallback onRename;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(folder == 'Hauptebene' ? 'Dateimanager' : folder, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
                          const SizedBox(height: 6),
                          Wrap(
                            spacing: 6,
                            runSpacing: 6,
                            children: [
                              _CountChip('$totalFolders Ordner'),
                              _CountChip('$totalFiles Dateien'),
                              if (folder != 'Hauptebene') _CountChip(folder),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    _HeaderIconButton(
                      icon: Icons.tune_outlined,
                      active: showFilters,
                      onTap: onToggleFilters,
                      semanticLabel: 'Suchen und sortieren',
                    ),
                    const SizedBox(width: 8),
                    _HeaderIconButton(
                      icon: showActions ? Icons.close : Icons.add,
                      primary: true,
                      onTap: onToggleActions,
                      semanticLabel: 'Datei oder Ordner hinzufuegen',
                    ),
                  ],
                ),
                if (showActions) ...[
                  const SizedBox(height: 12),
                  _ActionsPanel(
                    fileName: fileName,
                    folderNameController: folderNameController,
                    onPickFile: onPickFile,
                    onUpload: onUpload,
                    onCreateFolder: onCreateFolder,
                  ),
                ],
                if (showFilters) ...[
                  const SizedBox(height: 12),
                  _FilterPanel(onSearchChanged: onSearchChanged),
                ],
                if (onBack != null) ...[
                  const SizedBox(height: 10),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: OutlinedButton.icon(
                      onPressed: onBack,
                      icon: const Icon(Icons.arrow_back),
                      label: const Text('Zurueck'),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AirmiusColors.text,
                        side: const BorderSide(color: AirmiusColors.border),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                const Text('Aktuelle Ansicht', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          const Divider(color: AirmiusColors.border, height: 1),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                for (final item in folders) ...[
                  _FolderRow(folder: item, onOpen: () => onOpenFolder(item), onShare: onShare, onRename: onRename, onDelete: onDelete),
                  const SizedBox(height: 8),
                ],
                for (final file in files) ...[
                  _FileRow(file: file, onOpen: () => onOpenFile(file), onShare: onShare, onRename: onRename, onDelete: onDelete),
                  const SizedBox(height: 8),
                ],
                const SizedBox(height: 2),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text('Dateien 1 - $totalFiles von $totalFiles', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
                ),
                const SizedBox(height: 4),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text('Ordner 1 - $totalFolders von $totalFolders', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionsPanel extends StatelessWidget {
  const _ActionsPanel({
    required this.fileName,
    required this.folderNameController,
    required this.onPickFile,
    required this.onUpload,
    required this.onCreateFolder,
  });

  final String fileName;
  final TextEditingController folderNameController;
  final VoidCallback onPickFile;
  final VoidCallback onUpload;
  final VoidCallback onCreateFolder;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: AirmiusColors.input.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Column(
        children: [
          _Panel(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Row(
                  children: [
                    Text('Datei hochladen', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w900, letterSpacing: .2)),
                    Spacer(),
                    Text('Hauptebene', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
                  ],
                ),
                const SizedBox(height: 10),
                _InputLikeButton(label: fileName, icon: Icons.attach_file, onTap: onPickFile),
                const SizedBox(height: 8),
                _PrimaryBlockButton(label: 'Hochladen', enabled: fileName != 'Datei waehlen', onTap: onUpload),
              ],
            ),
          ),
          const SizedBox(height: 8),
          _Panel(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Text('Ordner erstellen', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w900, letterSpacing: .2)),
                const SizedBox(height: 10),
                _TextField(controller: folderNameController, hintText: 'Ordnername'),
                const SizedBox(height: 8),
                _PrimaryBlockButton(label: 'Erstellen', enabled: true, onTap: onCreateFolder),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterPanel extends StatelessWidget {
  const _FilterPanel({required this.onSearchChanged});

  final ValueChanged<String> onSearchChanged;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _TextField(hintText: 'Suchen...', prefixIcon: Icons.search, onChanged: onSearchChanged),
        const SizedBox(height: 8),
        Row(
          children: const [
            Expanded(child: _SelectLike(label: 'Pro Seite: 24')),
            SizedBox(width: 8),
            Expanded(child: _SelectLike(label: 'Name (A-Z)')),
          ],
        ),
      ],
    );
  }
}

class _FolderRow extends StatelessWidget {
  const _FolderRow({required this.folder, required this.onOpen, required this.onShare, required this.onRename, required this.onDelete});

  final _FileFolder folder;
  final VoidCallback onOpen;
  final VoidCallback onShare;
  final VoidCallback onRename;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return _RowShell(
      child: Row(
        children: [
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(9),
              onTap: onOpen,
              child: Row(
                children: [
                  const Icon(Icons.folder, color: AirmiusColors.amber, size: 34),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(folder.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                        const SizedBox(height: 2),
                        Text('${folder.filesCount} Dateien', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          _SmallIcon(icon: Icons.share_outlined, onTap: onShare, semanticLabel: 'Ordner freigeben'),
          _SmallIcon(icon: Icons.edit_outlined, onTap: onRename, semanticLabel: 'Ordner umbenennen'),
          _SmallIcon(icon: Icons.delete_outline, onTap: onDelete, semanticLabel: 'Ordner loeschen'),
        ],
      ),
    );
  }
}

class _FileRow extends StatelessWidget {
  const _FileRow({required this.file, required this.onOpen, required this.onShare, required this.onRename, required this.onDelete});

  final _ManagedFile file;
  final VoidCallback onOpen;
  final VoidCallback onShare;
  final VoidCallback onRename;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return _RowShell(
      child: Column(
        children: [
          InkWell(
            borderRadius: BorderRadius.circular(9),
            onTap: onOpen,
            child: Row(
              children: [
                Icon(file.icon, color: AirmiusColors.muted, size: 32),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(file.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 2),
                      Text(file.meta, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              _SmallIcon(icon: Icons.share_outlined, onTap: onShare, semanticLabel: 'Datei freigeben'),
              _SmallIcon(icon: Icons.edit_outlined, onTap: onRename, semanticLabel: 'Datei umbenennen'),
              _SmallIcon(icon: Icons.download_outlined, onTap: onOpen, semanticLabel: 'Datei herunterladen'),
              _SmallIcon(icon: Icons.delete_outline, onTap: onDelete, semanticLabel: 'Datei loeschen'),
            ],
          ),
        ],
      ),
    );
  }
}

class _StorageFooter extends StatelessWidget {
  const _StorageFooter();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(9),
      decoration: BoxDecoration(
        color: AirmiusColors.card.withValues(alpha: 0.96),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: AirmiusColors.border),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.25), blurRadius: 22, offset: const Offset(0, -8)),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Row(
            children: [
              Expanded(child: Text('Speicher: 0 B von 1 GB', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: AirmiusColors.text, fontSize: 12, fontWeight: FontWeight.w900))),
              SizedBox(width: 8),
              _FooterBadge('1.00 GB frei'),
            ],
          ),
          const SizedBox(height: 7),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              minHeight: 5,
              value: 0.0,
              backgroundColor: AirmiusColors.input,
              valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.blue),
            ),
          ),
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.child, this.padding = const EdgeInsets.all(12)});

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: AirmiusColors.card,
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: child,
    );
  }
}

class _RowShell extends StatelessWidget {
  const _RowShell({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(9),
      decoration: BoxDecoration(
        color: AirmiusColors.input.withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: child,
    );
  }
}

class _HeaderIconButton extends StatelessWidget {
  const _HeaderIconButton({required this.icon, required this.onTap, required this.semanticLabel, this.primary = false, this.active = false});

  final IconData icon;
  final VoidCallback onTap;
  final String semanticLabel;
  final bool primary;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: semanticLabel,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(9),
        child: Container(
          height: 40,
          width: 40,
          decoration: BoxDecoration(
            color: primary ? AirmiusColors.text : (active ? AirmiusColors.input : Colors.transparent),
            borderRadius: BorderRadius.circular(9),
            border: primary ? null : Border.all(color: AirmiusColors.border),
          ),
          child: Icon(icon, color: primary ? AirmiusColors.bg : AirmiusColors.text, size: 22),
        ),
      ),
    );
  }
}

class _SmallIcon extends StatelessWidget {
  const _SmallIcon({required this.icon, required this.onTap, required this.semanticLabel});

  final IconData icon;
  final VoidCallback onTap;
  final String semanticLabel;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: semanticLabel,
      constraints: const BoxConstraints(minHeight: 40, minWidth: 40),
      padding: EdgeInsets.zero,
      visualDensity: VisualDensity.compact,
      icon: Icon(icon, color: AirmiusColors.muted, size: 21),
      onPressed: onTap,
    );
  }
}

class _CountChip extends StatelessWidget {
  const _CountChip(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(99),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w800)),
    );
  }
}

class _FooterBadge extends StatelessWidget {
  const _FooterBadge(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(99),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w900)),
    );
  }
}

class _InputLikeButton extends StatelessWidget {
  const _InputLikeButton({required this.label, required this.icon, required this.onTap});

  final String label;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(9),
      child: Container(
        height: 44,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: AirmiusColors.input,
          borderRadius: BorderRadius.circular(9),
          border: Border.all(color: AirmiusColors.border),
        ),
        child: Row(
          children: [
            Expanded(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 14, fontWeight: FontWeight.w800))),
            Icon(icon, color: AirmiusColors.muted, size: 20),
          ],
        ),
      ),
    );
  }
}

class _PrimaryBlockButton extends StatelessWidget {
  const _PrimaryBlockButton({required this.label, required this.enabled, required this.onTap});

  final String label;
  final bool enabled;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: ElevatedButton(
        onPressed: enabled ? onTap : null,
        style: ElevatedButton.styleFrom(
          elevation: 0,
          backgroundColor: AirmiusColors.blueDeep,
          disabledBackgroundColor: Colors.white.withValues(alpha: 0.55),
          foregroundColor: Colors.white,
          disabledForegroundColor: AirmiusColors.bg.withValues(alpha: 0.80),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
        ),
        child: Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
    );
  }
}

class _TextField extends StatelessWidget {
  const _TextField({this.controller, this.hintText = '', this.prefixIcon, this.onChanged});

  final TextEditingController? controller;
  final String hintText;
  final IconData? prefixIcon;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 44,
      child: TextField(
        controller: controller,
        onChanged: onChanged,
        style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
        decoration: InputDecoration(
          hintText: hintText,
          hintStyle: const TextStyle(color: AirmiusColors.mutedSoft),
          prefixIcon: prefixIcon == null ? null : Icon(prefixIcon, color: AirmiusColors.muted, size: 20),
          filled: true,
          fillColor: AirmiusColors.input,
          contentPadding: const EdgeInsets.symmetric(horizontal: 12),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(9), borderSide: const BorderSide(color: AirmiusColors.border)),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(9), borderSide: const BorderSide(color: AirmiusColors.blue)),
        ),
      ),
    );
  }
}

class _SelectLike extends StatelessWidget {
  const _SelectLike({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 44,
      padding: const EdgeInsets.symmetric(horizontal: 10),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        children: [
          Expanded(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 13, fontWeight: FontWeight.w800))),
          const Icon(Icons.keyboard_arrow_down, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _FileFolder {
  const _FileFolder(this.name, this.filesCount);

  final String name;
  final int filesCount;
}

class _ManagedFile {
  const _ManagedFile(this.icon, this.title, this.meta, this.previewBody, this.status);

  final IconData icon;
  final String title;
  final String meta;
  final String previewBody;
  final String status;
}
