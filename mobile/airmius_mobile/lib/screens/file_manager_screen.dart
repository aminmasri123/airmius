import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import 'file_operations_screen.dart';
import 'file_preview_screen.dart';
import 'shared_file_access_screen.dart';

class FileManagerScreen extends StatefulWidget {
  const FileManagerScreen({super.key});

  @override
  State<FileManagerScreen> createState() => _FileManagerScreenState();
}

class _FileManagerScreenState extends State<FileManagerScreen> {
  String _scope = 'Meine Dateien';
  String _folder = 'Hauptebene';
  int? _folderId;
  bool _showFilters = false;
  bool _showActions = false;
  bool _loading = true;
  bool _runningAction = false;
  bool _loadedOnce = false;
  String _fileName = 'Datei waehlen';
  String? _error;
  String? _success;
  PlatformFile? _pickedFile;
  AirmiusFileWorkspace? _workspace;
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

  List<_FileFolder> get _activeFolders => _workspace?.folders.map(_FileFolder.fromApi).toList() ?? _folders;

  List<_ManagedFile> get _activeFiles => _workspace?.files.map(_ManagedFile.fromApi).toList() ?? _files;

  AirmiusStorageUsage get _storage => _workspace?.storage ?? const AirmiusStorageUsage(limitGb: 1, usedBytes: 0, remainingBytes: 1024 * 1024 * 1024, usedPercent: 0, isFull: false);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loadedOnce) {
      _loadedOnce = true;
      _loadWorkspace();
    }
  }

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
          RefreshIndicator(
            onRefresh: _loadWorkspace,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(14, 12, 14, 86),
              children: [
                _ScopePanel(value: _scope, values: _scopes, onChanged: (value) => _changeScope(value)),
                if (_loading || _runningAction || _error != null || _success != null) ...[
                  const SizedBox(height: 12),
                  _StateBanner(
                    loading: _loading || _runningAction,
                    error: _error,
                    success: _success,
                    onRetry: _loadWorkspace,
                  ),
                ],
                const SizedBox(height: 12),
                _FileBrowserCard(
                  folder: _folder,
                  totalFolders: _workspace?.foldersPagination.total ?? _activeFolders.length,
                  totalFiles: _workspace?.filesPagination.total ?? _activeFiles.length,
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
                  onSearchChanged: _searchWorkspace,
                  onBack: _folderId == null ? null : _goHome,
                  folders: _activeFolders,
                  files: _activeFiles,
                  onOpenFolder: _openFolder,
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
                  onRenameFolder: _renameFolder,
                  onDeleteFolder: _deleteFolder,
                  onRenameFile: _renameFile,
                  onDeleteFile: _deleteFile,
                ),
              ],
            ),
          ),
          Positioned(
            left: 12,
            right: 12,
            bottom: 12,
            child: _StorageFooter(storage: _storage),
          ),
        ],
      ),
    );
  }

  Future<void> _loadWorkspace({String? search}) async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final workspace = await AirmiusServicesScope.of(context).repositories.files.workspace(
            scope: _apiScope,
            folderId: _folderId,
            search: search,
          );
      if (!mounted) return;
      setState(() {
        _workspace = workspace;
        _folder = workspace.currentFolder?.name ?? 'Hauptebene';
        _loading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = _messageFor(error);
      });
    }
  }

  String get _apiScope => 'user';

  void _changeScope(String value) {
    setState(() {
      _scope = value;
      _folder = 'Hauptebene';
      _folderId = null;
      _workspace = null;
    });
    _loadWorkspace();
  }

  void _goHome() {
    setState(() {
      _folder = 'Hauptebene';
      _folderId = null;
    });
    _loadWorkspace();
  }

  void _openFolder(_FileFolder folder) {
    if (folder.id == null) {
      setState(() => _folder = folder.name);
      return;
    }
    setState(() {
      _folder = folder.name;
      _folderId = folder.id;
    });
    _loadWorkspace();
  }

  void _searchWorkspace(String value) {
    _loadWorkspace(search: value);
  }

  Future<void> _openUploadIntent() async {
    final result = await FilePicker.platform.pickFiles(withData: true);
    final file = result?.files.single;
    if (file == null) return;
    setState(() {
      _pickedFile = file;
      _fileName = file.name;
      _error = null;
    });
  }

  void _submitUpload() {
    final file = _pickedFile;
    if (file == null) {
      setState(() => _error = 'Bitte zuerst eine Datei waehlen.');
      return;
    }

    _runAction(() async {
      await _uploadFile(file);
      _pickedFile = null;
      _fileName = 'Datei waehlen';
      _showActions = false;
      _success = 'Datei hochgeladen.';
      await _loadWorkspace();
    });
  }

  Future<void> _uploadFile(PlatformFile file) async {
    final services = AirmiusServicesScope.of(context);
    final base = Uri.parse(services.environment.apiBaseUrl);
    final path = '${base.path.endsWith('/') ? base.path : '${base.path}/'}api/v1/uploads';
    final request = http.MultipartRequest('POST', base.replace(path: path, query: null, fragment: null));
    request.headers.addAll({
      'Accept': 'application/json',
      'X-Airmius-Locale': services.environment.locale,
      if (services.authState.session?.token.isNotEmpty == true) 'Authorization': 'Bearer ${services.authState.session!.token}',
    });
    request.fields['scope'] = _apiScope;
    if (_folderId != null) request.fields['folder_id'] = '$_folderId';

    if (file.bytes != null && file.bytes!.isNotEmpty) {
      request.files.add(http.MultipartFile.fromBytes('file', file.bytes!, filename: file.name, contentType: _contentTypeFor(file)));
    } else if (file.path != null && file.path!.trim().isNotEmpty) {
      request.files.add(await http.MultipartFile.fromPath('file', file.path!, filename: file.name, contentType: _contentTypeFor(file)));
    } else {
      throw const AirmiusApiException(statusCode: 0, body: 'Die ausgewaehlte Datei konnte nicht gelesen werden.', path: '/api/v1/uploads');
    }

    final response = await http.Response.fromStream(await request.send());
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(statusCode: response.statusCode, body: response.body, path: '/api/v1/uploads');
    }
  }

  MediaType _contentTypeFor(PlatformFile file) {
    final extension = (file.extension ?? file.name.split('.').last).toLowerCase();
    return switch (extension) {
      'jpg' || 'jpeg' => MediaType('image', 'jpeg'),
      'png' => MediaType('image', 'png'),
      'webp' => MediaType('image', 'webp'),
      'gif' => MediaType('image', 'gif'),
      'mp4' => MediaType('video', 'mp4'),
      'mov' => MediaType('video', 'quicktime'),
      'webm' => MediaType('video', 'webm'),
      'pdf' => MediaType('application', 'pdf'),
      'doc' => MediaType('application', 'msword'),
      'docx' => MediaType('application', 'vnd.openxmlformats-officedocument.wordprocessingml.document'),
      _ => MediaType('application', 'octet-stream'),
    };
  }

  void _createFolder() {
    final name = _folderNameController.text.trim();
    if (name.isEmpty) {
      setState(() => _error = 'Bitte Ordnername eingeben.');
      return;
    }
    _runAction(() async {
      await AirmiusServicesScope.of(context).repositories.files.createFolder(scope: _apiScope, name: name, parentId: _folderId);
      _folderNameController.clear();
      _showActions = false;
      _success = 'Ordner erstellt.';
      await _loadWorkspace();
    });
  }

  Future<void> _renameFolder(_FileFolder folder) async {
    if (folder.id == null) return;
    final name = await _askName(title: 'Ordner umbenennen', initial: folder.name);
    if (name == null) return;
    _runAction(() async {
      await AirmiusServicesScope.of(context).repositories.files.renameFolder(folder.id!, name);
      _success = 'Ordner umbenannt.';
      await _loadWorkspace();
    });
  }

  Future<void> _deleteFolder(_FileFolder folder) async {
    if (folder.id == null) return;
    _runAction(() async {
      await AirmiusServicesScope.of(context).repositories.files.deleteFolder(folder.id!);
      _success = 'Ordner geloescht.';
      await _loadWorkspace();
    });
  }

  Future<void> _renameFile(_ManagedFile file) async {
    if (file.id == null) return;
    final name = await _askName(title: 'Datei umbenennen', initial: file.title);
    if (name == null) return;
    _runAction(() async {
      await AirmiusServicesScope.of(context).repositories.files.renameFile(file.id!, name);
      _success = 'Datei umbenannt.';
      await _loadWorkspace();
    });
  }

  Future<void> _deleteFile(_ManagedFile file) async {
    if (file.id == null) return;
    _runAction(() async {
      await AirmiusServicesScope.of(context).repositories.files.deleteFile(file.id!);
      _success = 'Datei geloescht.';
      await _loadWorkspace();
    });
  }

  Future<void> _runAction(Future<void> Function() action) async {
    setState(() {
      _runningAction = true;
      _error = null;
      _success = null;
    });
    try {
      await action();
    } catch (error) {
      if (!mounted) return;
      setState(() => _error = _messageFor(error));
    } finally {
      if (mounted) setState(() => _runningAction = false);
    }
  }

  Future<String?> _askName({required String title, required String initial}) async {
    final controller = TextEditingController(text: initial);
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AirmiusColors.card,
        title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        content: _TextField(controller: controller, hintText: 'Name'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Abbrechen')),
          TextButton(onPressed: () => Navigator.pop(context, controller.text.trim()), child: const Text('Speichern')),
        ],
      ),
    );
    controller.dispose();
    return result == null || result.isEmpty ? null : result;
  }

  String _messageFor(Object error) {
    if (error is AirmiusApiException) return error.userMessage;
    return 'Aktion konnte nicht abgeschlossen werden.';
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
    required this.onRenameFolder,
    required this.onDeleteFolder,
    required this.onRenameFile,
    required this.onDeleteFile,
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
  final ValueChanged<_FileFolder> onRenameFolder;
  final ValueChanged<_FileFolder> onDeleteFolder;
  final ValueChanged<_ManagedFile> onRenameFile;
  final ValueChanged<_ManagedFile> onDeleteFile;

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
                  _FolderRow(folder: item, onOpen: () => onOpenFolder(item), onShare: onShare, onRename: () => onRenameFolder(item), onDelete: () => onDeleteFolder(item)),
                  const SizedBox(height: 8),
                ],
                for (final file in files) ...[
                  _FileRow(file: file, onOpen: () => onOpenFile(file), onShare: onShare, onRename: () => onRenameFile(file), onDelete: () => onDeleteFile(file)),
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

class _StateBanner extends StatelessWidget {
  const _StateBanner({required this.loading, required this.error, required this.success, required this.onRetry});

  final bool loading;
  final String? error;
  final String? success;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final isError = error != null;
    final text = loading ? 'Backend wird geladen...' : (error ?? success ?? '');
    final color = isError ? AirmiusColors.red : AirmiusColors.green;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: color.withValues(alpha: 0.45)),
      ),
      child: Row(
        children: [
          if (loading)
            const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
          else
            Icon(isError ? Icons.error_outline : Icons.check_circle_outline, color: color, size: 20),
          const SizedBox(width: 10),
          Expanded(child: Text(text, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800))),
          if (isError) IconButton(onPressed: onRetry, icon: const Icon(Icons.refresh, color: AirmiusColors.text)),
        ],
      ),
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
  const _StorageFooter({required this.storage});

  final AirmiusStorageUsage storage;

  @override
  Widget build(BuildContext context) {
    final used = _formatBytes(storage.usedBytes);
    final remaining = _formatBytes(storage.remainingBytes);
    final progress = storage.limitGb <= 0 ? 0.0 : (storage.usedPercent / 100).clamp(0.0, 1.0).toDouble();

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
          Row(
            children: [
              Expanded(child: Text('Speicher: $used von ${storage.limitGb} GB', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 12, fontWeight: FontWeight.w900))),
              const SizedBox(width: 8),
              _FooterBadge('$remaining frei'),
            ],
          ),
          const SizedBox(height: 7),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              minHeight: 5,
              value: progress,
              backgroundColor: AirmiusColors.input,
              valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.blue),
            ),
          ),
        ],
      ),
    );
  }

  static String _formatBytes(int bytes) {
    if (bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var value = bytes.toDouble();
    var unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
      value /= 1024;
      unit += 1;
    }
    return '${value.toStringAsFixed(unit == 0 ? 0 : 2)} ${units[unit]}';
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
  const _FileFolder(this.name, this.filesCount, {this.id});

  factory _FileFolder.fromApi(AirmiusFolder folder) => _FileFolder(folder.name, folder.filesCount, id: folder.id);

  final String name;
  final int filesCount;
  final int? id;
}

class _ManagedFile {
  const _ManagedFile(this.icon, this.title, this.meta, this.previewBody, this.status, {this.id, this.url});

  factory _ManagedFile.fromApi(AirmiusManagedFile file) {
    final type = file.type.isEmpty ? 'Datei' : file.type;
    return _ManagedFile(
      _iconFor(type),
      file.name,
      '$type - ${_formatSize(file.size)}',
      file.url.isEmpty ? 'Backend-Datei ohne direkte Vorschau-URL.' : file.url,
      'Backend',
      id: file.id,
      url: file.url,
    );
  }

  final IconData icon;
  final String title;
  final String meta;
  final String previewBody;
  final String status;
  final int? id;
  final String? url;

  static IconData _iconFor(String type) {
    final normalized = type.toLowerCase();
    if (normalized.contains('pdf')) return Icons.picture_as_pdf_outlined;
    if (normalized.contains('image')) return Icons.image_outlined;
    if (normalized.contains('video')) return Icons.video_file_outlined;
    return Icons.description_outlined;
  }

  static String _formatSize(int bytes) {
    if (bytes <= 0) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB'];
    var value = bytes.toDouble();
    var unit = 0;
    while (value >= 1024 && unit < units.length - 1) {
      value /= 1024;
      unit += 1;
    }
    return '${value.toStringAsFixed(unit == 0 ? 0 : 2)} ${units[unit]}';
  }
}
