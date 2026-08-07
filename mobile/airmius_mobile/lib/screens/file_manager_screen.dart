import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_upload_retry_policy.dart';
import 'file_operations_screen.dart';
import 'file_preview_screen.dart';

class FileManagerScreen extends StatefulWidget {
  const FileManagerScreen({
    super.key,
    this.initialScope = 'mine',
    this.initialTeamId,
    this.initialSearch = '',
  });

  final String initialScope;
  final int? initialTeamId;
  final String initialSearch;

  @override
  State<FileManagerScreen> createState() => _FileManagerScreenState();
}

class _FileManagerScreenState extends State<FileManagerScreen> {
  String _scope = 'mine';
  int? _fixedTeamId;
  String _folder = 'Hauptebene';
  int? _folderId;
  bool _showFilters = false;
  bool _showActions = false;
  bool _loading = true;
  bool _runningAction = false;
  bool _loadedOnce = false;
  String? _error;
  String? _success;
  AirmiusFileWorkspace? _workspace;
  final _folderNameController = TextEditingController();

  AirmiusUser? get _user => AirmiusServicesScope.of(context).authState.user;

  List<String> get _scopes {
    final user = _user;
    return [
      'mine',
      if (_fixedTeamId != null || user?.teams.isNotEmpty == true) 'team',
      if (user?.clubs.isNotEmpty == true) 'club',
    ];
  }

  int? get _selectedTeamId {
    if (_scope != 'team') return null;
    if (_fixedTeamId != null) return _fixedTeamId;
    final teams = _user?.teams ?? const <AirmiusNamedItem>[];
    return teams.isEmpty ? null : teams.first.id;
  }

  int? get _selectedClubId {
    if (_scope != 'club') return null;
    final clubs = _user?.clubs ?? const <AirmiusNamedItem>[];
    return clubs.isEmpty ? null : clubs.first.id;
  }

  int? get _selectedEventId => null;

  List<_FileFolder> get _activeFolders =>
      _workspace?.folders.map(_FileFolder.fromApi).toList() ?? const [];

  List<_ManagedFile> get _activeFiles =>
      _workspace?.files
          .map((file) => _ManagedFile.fromApi(file, AirmiusScope.of(context).t))
          .toList() ??
      const [];

  AirmiusStorageUsage? get _storage => _workspace?.storage;

  @override
  void initState() {
    super.initState();
    _scope = widget.initialScope;
    _fixedTeamId = widget.initialTeamId;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loadedOnce) {
      _loadedOnce = true;
      _loadWorkspace(search: widget.initialSearch);
    }
  }

  @override
  void dispose() {
    _folderNameController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(t('files'), style: TextStyle(fontWeight: FontWeight.w900)),
        actions: [
          IconButton(
            tooltip: t('files.shareLink'),
            icon: const Icon(Icons.link_outlined),
            onPressed: _openSharePicker,
          ),
          IconButton(
            tooltip: t('files.operations'),
            icon: const Icon(Icons.folder_shared_outlined),
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => FileOperationsScreen(
                  initialTab: 'Uploads',
                  onOpenUploader: () {
                    Navigator.pop(context);
                    _openUploadIntent();
                  },
                ),
              ),
            ),
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
                _ScopePanel(
                  value: _scope,
                  values: _scopes,
                  onChanged: (value) => _changeScope(value),
                ),
                if (_loading ||
                    _runningAction ||
                    _error != null ||
                    _success != null) ...[
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
                  totalFolders:
                      _workspace?.foldersPagination.total ??
                      _activeFolders.length,
                  totalFiles:
                      _workspace?.filesPagination.total ?? _activeFiles.length,
                  showFilters: _showFilters,
                  showActions: _showActions,
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
                        fileId: file.id,
                        fileMeta: file.meta,
                        body: file.previewBody,
                        status: file.status,
                        icon: file.icon,
                        fileUrl: file.url,
                      ),
                    ),
                  ),
                  onShareFile: _shareFile,
                  onShareFolder: _shareFolder,
                  onRenameFolder: _renameFolder,
                  onDeleteFolder: _deleteFolder,
                  onRenameFile: _renameFile,
                  onDeleteFile: _deleteFile,
                ),
              ],
            ),
          ),
          if (_storage != null)
            Positioned(
              left: 12,
              right: 12,
              bottom: 12,
              child: _StorageFooter(storage: _storage!),
            ),
        ],
      ),
    );
  }

  Future<void> _loadWorkspace({String? search}) async {
    final homeLabel = AirmiusScope.of(context).t('files.home');
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final workspace = await AirmiusServicesScope.of(context)
          .repositories
          .files
          .workspace(
            scope: _apiScope,
            folderId: _folderId,
            clubId: _selectedClubId,
            teamId: _selectedTeamId,
            eventId: _selectedEventId,
            search: search,
          );
      if (!mounted) return;
      setState(() {
        _workspace = workspace;
        _folder = workspace.currentFolder?.name ?? homeLabel;
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

  String get _apiScope => switch (_scope) {
    'team' => 'team',
    'club' => 'club',
    'event' => 'event',
    _ => 'user',
  };

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
    final t = AirmiusScope.of(context).t;
    final user = _user;
    final destination = await showModalBottomSheet<_UploadDestination>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      backgroundColor: airmiusSurfaceColor(context),
      builder: (_) => _UploadDestinationSheet(
        clubs: user?.clubs ?? const <AirmiusNamedItem>[],
        teams: user?.teams ?? const <AirmiusNamedItem>[],
      ),
    );
    if (destination == null || !mounted) return;

    final result = await FilePicker.platform.pickFiles(withData: true);
    final file = result?.files.single;
    if (file == null) return;
    setState(() => _error = null);

    await _runAction(() async {
      await _uploadFile(file, destination: destination);
      if (!mounted) return;
      setState(() => _success = t('files.uploaded'));
      if (_destinationMatchesCurrent(destination)) {
        await _loadWorkspace();
      }
    });
  }

  Future<void> _uploadFile(
    PlatformFile file, {
    _UploadDestination? destination,
  }) async {
    final readFailed = AirmiusScope.of(context).t('files.readFailed');
    final services = AirmiusServicesScope.of(context);
    final base = Uri.parse(services.environment.apiBaseUrl);
    final path =
        '${base.path.endsWith('/') ? base.path : '${base.path}/'}api/v1/uploads';
    final uri = base.replace(path: path, query: null, fragment: null);
    const retryPolicy = AirmiusUploadRetryPolicy();

    await retryPolicy.run((_) async {
      final request = http.MultipartRequest('POST', uri);
      request.headers.addAll({
        'Accept': 'application/json',
        'X-Airmius-Locale': services.environment.locale,
        if (services.authState.session?.token.isNotEmpty == true)
          'Authorization': 'Bearer ${services.authState.session!.token}',
      });
      final targetScope = destination?.apiScope ?? _apiScope;
      final targetClubId = destination != null
          ? destination.clubId
          : _selectedClubId;
      final targetTeamId = destination != null
          ? destination.teamId
          : _selectedTeamId;
      request.fields['scope'] = targetScope;
      if (targetClubId != null) {
        request.fields['club_id'] = '$targetClubId';
      }
      if (targetTeamId != null) {
        request.fields['team_id'] = '$targetTeamId';
      }
      if (_selectedEventId != null) {
        request.fields['event_id'] = '$_selectedEventId';
      }
      if (_folderId != null &&
          (destination == null || _destinationMatchesCurrent(destination))) {
        request.fields['folder_id'] = '$_folderId';
      }

      if (file.bytes != null && file.bytes!.isNotEmpty) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'file',
            file.bytes!,
            filename: file.name,
            contentType: _contentTypeFor(file),
          ),
        );
      } else if (file.path != null && file.path!.trim().isNotEmpty) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'file',
            file.path!,
            filename: file.name,
            contentType: _contentTypeFor(file),
          ),
        );
      } else {
        throw AirmiusApiException(
          statusCode: 0,
          body: readFailed,
          path: '/api/v1/uploads',
        );
      }

      final response = await http.Response.fromStream(await request.send());
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: '/api/v1/uploads',
        );
      }
    });
  }

  bool _destinationMatchesCurrent(_UploadDestination destination) {
    final currentScope = _apiScope;
    if (destination.apiScope != currentScope) return false;
    if (destination.clubId != _selectedClubId) return false;
    return destination.teamId == _selectedTeamId;
  }

  MediaType _contentTypeFor(PlatformFile file) {
    final extension = (file.extension ?? file.name.split('.').last)
        .toLowerCase();
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
      'docx' => MediaType(
        'application',
        'vnd.openxmlformats-officedocument.wordprocessingml.document',
      ),
      _ => MediaType('application', 'octet-stream'),
    };
  }

  void _createFolder() {
    final t = AirmiusScope.of(context).t;
    final name = _folderNameController.text.trim();
    if (name.isEmpty) {
      setState(() => _error = t('files.folderNameRequired'));
      return;
    }
    _runAction(() async {
      await AirmiusServicesScope.of(context).repositories.files.createFolder(
        scope: _apiScope,
        name: name,
        parentId: _folderId,
        clubId: _selectedClubId,
        teamId: _selectedTeamId,
        eventId: _selectedEventId,
      );
      _folderNameController.clear();
      _showActions = false;
      _success = t('files.folderCreated');
      await _loadWorkspace();
    });
  }

  Future<void> _renameFolder(_FileFolder folder) async {
    if (folder.id == null) return;
    final t = AirmiusScope.of(context).t;
    final name = await _askName(
      title: t('files.renameFolder'),
      initial: folder.name,
    );
    if (name == null) return;
    _runAction(() async {
      await AirmiusServicesScope.of(
        context,
      ).repositories.files.renameFolder(folder.id!, name);
      _success = t('files.folderRenamed');
      await _loadWorkspace();
    });
  }

  Future<void> _deleteFolder(_FileFolder folder) async {
    if (folder.id == null) return;
    final t = AirmiusScope.of(context).t;
    _runAction(() async {
      await AirmiusServicesScope.of(
        context,
      ).repositories.files.deleteFolder(folder.id!);
      _success = t('files.folderDeleted');
      await _loadWorkspace();
    });
  }

  Future<void> _renameFile(_ManagedFile file) async {
    if (file.id == null) return;
    final t = AirmiusScope.of(context).t;
    final name = await _askName(
      title: t('files.renameFile'),
      initial: file.title,
    );
    if (name == null) return;
    _runAction(() async {
      await AirmiusServicesScope.of(
        context,
      ).repositories.files.renameFile(file.id!, name);
      _success = t('files.fileRenamed');
      await _loadWorkspace();
    });
  }

  Future<void> _deleteFile(_ManagedFile file) async {
    if (file.id == null) return;
    final t = AirmiusScope.of(context).t;
    _runAction(() async {
      await AirmiusServicesScope.of(
        context,
      ).repositories.files.deleteFile(file.id!);
      _success = t('files.fileDeleted');
      await _loadWorkspace();
    });
  }

  Future<void> _shareFile(_ManagedFile file) async {
    final t = AirmiusScope.of(context).t;
    if (file.id == null) {
      _showMessage(t('files.shareUnavailable'));
      return;
    }

    await _runAction(() async {
      final data = await AirmiusServicesScope.of(
        context,
      ).repositories.files.createFileShare(file.id!);
      final link = '${data['url'] ?? ''}'.trim();
      if (link.isEmpty) {
        _showMessage(t('files.shareUnavailable'));
        return;
      }
      await Clipboard.setData(ClipboardData(text: link));
      _success = t('files.shareCreated');
    });
  }

  Future<void> _openSharePicker() async {
    final files = _activeFiles;
    final t = AirmiusScope.of(context).t;
    if (files.isEmpty) {
      _showMessage(t('files.selectFileToShare'));
      return;
    }

    final selected = await showModalBottomSheet<_ManagedFile>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) {
        return SafeArea(
          child: ListView.separated(
            shrinkWrap: true,
            padding: const EdgeInsets.fromLTRB(14, 4, 14, 14),
            itemCount: files.length,
            separatorBuilder: (_, _) => const SizedBox(height: 6),
            itemBuilder: (_, index) {
              final file = files[index];
              return ListTile(
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(14),
                  side: BorderSide(color: airmiusBorderColor(context)),
                ),
                leading: Icon(file.icon, color: airmiusAccentColor(context)),
                title: Text(file.title),
                subtitle: Text(file.meta),
                trailing: const Icon(Icons.link_outlined),
                onTap: () => Navigator.pop(sheetContext, file),
              );
            },
          ),
        );
      },
    );
    if (selected != null && mounted) await _shareFile(selected);
  }

  Future<void> _shareFolder(_FileFolder folder) async {
    final t = AirmiusScope.of(context).t;
    if (folder.id == null) {
      _showMessage(t('files.shareUnavailable'));
      return;
    }

    try {
      final services = AirmiusServicesScope.of(context);
      final response = await services
          .clientForSession(services.authState.session)
          .friends();
      final payload = response['data'];
      final rawFriends = payload is Map ? payload['friends'] : null;
      final friends = rawFriends is List
          ? rawFriends
                .whereType<Map>()
                .map((friend) {
                  final id = int.tryParse('${friend['id'] ?? ''}');
                  final name = '${friend['name'] ?? ''}'.trim();
                  return id == null || name.isEmpty
                      ? null
                      : _ShareFriend(id: id, name: name);
                })
                .whereType<_ShareFriend>()
                .toList()
          : const <_ShareFriend>[];

      if (!mounted) return;
      if (friends.isEmpty) {
        _showMessage(t('files.noFriendsToShare'));
        return;
      }

      final selected = await _chooseShareFriend(friends);
      if (selected == null || !mounted) return;

      await _runAction(() async {
        await AirmiusServicesScope.of(
          context,
        ).repositories.files.shareFolder(folder.id!, selected.id);
        _success = t('files.folderShareCreated');
      });
    } catch (error) {
      if (mounted) setState(() => _error = _messageFor(error));
    }
  }

  Future<_ShareFriend?> _chooseShareFriend(List<_ShareFriend> friends) {
    final t = AirmiusScope.of(context).t;
    return showDialog<_ShareFriend>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(dialogContext),
        title: Text(
          t('files.shareFolderTitle'),
          style: TextStyle(
            color: airmiusTextColor(dialogContext),
            fontWeight: FontWeight.w900,
          ),
        ),
        content: SizedBox(
          width: double.maxFinite,
          child: ListView.separated(
            shrinkWrap: true,
            itemCount: friends.length,
            separatorBuilder: (_, _) =>
                Divider(color: airmiusBorderColor(dialogContext), height: 1),
            itemBuilder: (_, index) {
              final friend = friends[index];
              return ListTile(
                minVerticalPadding: 10,
                leading: CircleAvatar(
                  backgroundColor: airmiusAccentColor(dialogContext),
                  foregroundColor: Theme.of(
                    dialogContext,
                  ).colorScheme.onPrimary,
                  child: Text(friend.name.characters.first.toUpperCase()),
                ),
                title: Text(
                  friend.name,
                  style: TextStyle(
                    color: airmiusTextColor(dialogContext),
                    fontWeight: FontWeight.w800,
                  ),
                ),
                onTap: () => Navigator.pop(dialogContext, friend),
              );
            },
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(t('files.cancel')),
          ),
        ],
      ),
    );
  }

  void _showMessage(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
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

  Future<String?> _askName({
    required String title,
    required String initial,
  }) async {
    final t = AirmiusScope.of(context).t;
    final controller = TextEditingController(text: initial);
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        title: Text(
          title,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
        content: _TextField(controller: controller, hintText: t('files.name')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(t('files.cancel')),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: Text(t('files.save')),
          ),
        ],
      ),
    );
    controller.dispose();
    return result == null || result.isEmpty ? null : result;
  }

  String _messageFor(Object error) {
    if (error is AirmiusApiException) return error.userMessage;
    return AirmiusScope.of(context).t('files.actionFailed');
  }
}

class _ScopePanel extends StatelessWidget {
  const _ScopePanel({
    required this.value,
    required this.values,
    required this.onChanged,
  });

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Panel(
      padding: const EdgeInsets.all(10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            t('files.scope'),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 13,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 8),
          Container(
            height: 44,
            padding: const EdgeInsets.symmetric(horizontal: 12),
            decoration: BoxDecoration(
              color: airmiusInputColor(context),
              borderRadius: BorderRadius.circular(9),
              border: Border.all(color: airmiusBorderColor(context)),
            ),
            child: DropdownButtonHideUnderline(
              child: DropdownButton<String>(
                value: value,
                isExpanded: true,
                dropdownColor: airmiusSurfaceColor(context),
                iconEnabledColor: airmiusMutedColor(context),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w800,
                ),
                items: values
                    .map(
                      (item) => DropdownMenuItem(
                        value: item,
                        child: Text(t('files.scope.$item')),
                      ),
                    )
                    .toList(),
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
    required this.folderNameController,
    required this.onToggleFilters,
    required this.onToggleActions,
    required this.onPickFile,
    required this.onCreateFolder,
    required this.onSearchChanged,
    required this.onBack,
    required this.folders,
    required this.files,
    required this.onOpenFolder,
    required this.onOpenFile,
    required this.onShareFile,
    required this.onShareFolder,
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
  final TextEditingController folderNameController;
  final VoidCallback onToggleFilters;
  final VoidCallback onToggleActions;
  final VoidCallback onPickFile;
  final VoidCallback onCreateFolder;
  final ValueChanged<String> onSearchChanged;
  final VoidCallback? onBack;
  final List<_FileFolder> folders;
  final List<_ManagedFile> files;
  final ValueChanged<_FileFolder> onOpenFolder;
  final ValueChanged<_ManagedFile> onOpenFile;
  final ValueChanged<_ManagedFile> onShareFile;
  final ValueChanged<_FileFolder> onShareFolder;
  final ValueChanged<_FileFolder> onRenameFolder;
  final ValueChanged<_FileFolder> onDeleteFolder;
  final ValueChanged<_ManagedFile> onRenameFile;
  final ValueChanged<_ManagedFile> onDeleteFile;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
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
                          Text(
                            onBack == null ? t('files.manager') : folder,
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Wrap(
                            spacing: 6,
                            runSpacing: 6,
                            children: [
                              _CountChip(
                                t(
                                  'files.countFolders',
                                ).replaceAll('{count}', '$totalFolders'),
                              ),
                              _CountChip(
                                t(
                                  'files.countFiles',
                                ).replaceAll('{count}', '$totalFiles'),
                              ),
                              if (onBack != null) _CountChip(folder),
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
                      semanticLabel: t('files.searchSort'),
                    ),
                    const SizedBox(width: 8),
                    _HeaderIconButton(
                      icon: showActions
                          ? Icons.close
                          : Icons.create_new_folder_outlined,
                      active: showActions,
                      onTap: onToggleActions,
                      semanticLabel: t('files.createFolder'),
                    ),
                    const SizedBox(width: 8),
                    _HeaderIconButton(
                      icon: Icons.add,
                      primary: true,
                      onTap: onPickFile,
                      semanticLabel: t('files.add'),
                    ),
                  ],
                ),
                  if (showActions) ...[
                    const SizedBox(height: 12),
                  _ActionsPanel(
                    folderNameController: folderNameController,
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
                      label: Text(t('files.back')),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: airmiusTextColor(context),
                        side: BorderSide(color: airmiusBorderColor(context)),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(9),
                        ),
                      ),
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Text(
                  t('files.currentView'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Divider(color: airmiusBorderColor(context), height: 1),
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              children: [
                for (final item in folders) ...[
                  _FolderRow(
                    folder: item,
                    onOpen: () => onOpenFolder(item),
                    onShare: () => onShareFolder(item),
                    onRename: () => onRenameFolder(item),
                    onDelete: () => onDeleteFolder(item),
                  ),
                  const SizedBox(height: 8),
                ],
                for (final file in files) ...[
                  _FileRow(
                    file: file,
                    onOpen: () => onOpenFile(file),
                    onShare: () => onShareFile(file),
                    onRename: () => onRenameFile(file),
                    onDelete: () => onDeleteFile(file),
                  ),
                  const SizedBox(height: 8),
                ],
                const SizedBox(height: 2),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    t('files.fileRange').replaceAll('{count}', '$totalFiles'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                    ),
                  ),
                ),
                const SizedBox(height: 4),
                Align(
                  alignment: Alignment.centerLeft,
                  child: Text(
                    t(
                      'files.folderRange',
                    ).replaceAll('{count}', '$totalFolders'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                    ),
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

class _ActionsPanel extends StatelessWidget {
  const _ActionsPanel({
    required this.folderNameController,
    required this.onCreateFolder,
  });

  final TextEditingController folderNameController;
  final VoidCallback onCreateFolder;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(8),
      decoration: BoxDecoration(
        color: airmiusInputColor(context).withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        children: [
          _Panel(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  t('files.createFolder'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                    letterSpacing: .2,
                  ),
                ),
                const SizedBox(height: 10),
                _TextField(
                  controller: folderNameController,
                  hintText: t('files.name'),
                ),
                const SizedBox(height: 8),
                _PrimaryBlockButton(
                  label: t('files.create'),
                  enabled: true,
                  onTap: onCreateFolder,
                ),
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
    final t = AirmiusScope.of(context).t;
    return Column(
      children: [
        _TextField(
          hintText: t('files.searchHint'),
          prefixIcon: Icons.search,
          onChanged: onSearchChanged,
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(child: _SelectLike(label: t('files.pageSize'))),
            SizedBox(width: 8),
            Expanded(child: _SelectLike(label: t('files.sortName'))),
          ],
        ),
      ],
    );
  }
}

class _StateBanner extends StatelessWidget {
  const _StateBanner({
    required this.loading,
    required this.error,
    required this.success,
    required this.onRetry,
  });

  final bool loading;
  final String? error;
  final String? success;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final isError = error != null;
    final text = loading ? t('files.loading') : (error ?? success ?? '');
    final color = isError
        ? Theme.of(context).colorScheme.error
        : Theme.of(context).colorScheme.secondary;

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
            const SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          else
            Icon(
              isError ? Icons.error_outline : Icons.check_circle_outline,
              color: color,
              size: 20,
            ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          if (isError)
            IconButton(
              tooltip: t('common.retry'),
              onPressed: onRetry,
              icon: Icon(Icons.refresh, color: airmiusTextColor(context)),
            ),
        ],
      ),
    );
  }
}

class _FolderRow extends StatelessWidget {
  const _FolderRow({
    required this.folder,
    required this.onOpen,
    required this.onShare,
    required this.onRename,
    required this.onDelete,
  });

  final _FileFolder folder;
  final VoidCallback onOpen;
  final VoidCallback onShare;
  final VoidCallback onRename;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _RowShell(
      child: Row(
        children: [
          Expanded(
            child: InkWell(
              borderRadius: BorderRadius.circular(9),
              onTap: onOpen,
              child: Row(
                children: [
                  Icon(
                    Icons.folder,
                    color: Theme.of(context).colorScheme.tertiary,
                    size: 34,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          folder.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          t(
                            'files.countFiles',
                          ).replaceAll('{count}', '${folder.filesCount}'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
          _SmallIcon(
            icon: Icons.share_outlined,
            onTap: onShare,
            semanticLabel: t('files.folderShare'),
          ),
          _SmallIcon(
            icon: Icons.edit_outlined,
            onTap: onRename,
            semanticLabel: t('files.folderRename'),
          ),
          _SmallIcon(
            icon: Icons.delete_outline,
            onTap: onDelete,
            semanticLabel: t('files.folderDelete'),
          ),
        ],
      ),
    );
  }
}

class _FileRow extends StatelessWidget {
  const _FileRow({
    required this.file,
    required this.onOpen,
    required this.onShare,
    required this.onRename,
    required this.onDelete,
  });

  final _ManagedFile file;
  final VoidCallback onOpen;
  final VoidCallback onShare;
  final VoidCallback onRename;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _RowShell(
      child: Column(
        children: [
          InkWell(
            borderRadius: BorderRadius.circular(9),
            onTap: onOpen,
            child: Row(
              children: [
                Icon(file.icon, color: airmiusMutedColor(context), size: 32),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        file.title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        file.meta,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
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
              _SmallIcon(
                icon: Icons.share_outlined,
                onTap: onShare,
                semanticLabel: t('files.fileShare'),
              ),
              _SmallIcon(
                icon: Icons.edit_outlined,
                onTap: onRename,
                semanticLabel: t('files.fileRename'),
              ),
              _SmallIcon(
                icon: Icons.download_outlined,
                onTap: onOpen,
                semanticLabel: t('files.fileDownload'),
              ),
              _SmallIcon(
                icon: Icons.delete_outline,
                onTap: onDelete,
                semanticLabel: t('files.fileDelete'),
              ),
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
    final t = AirmiusScope.of(context).t;
    final used = _formatBytes(storage.usedBytes);
    final remaining = _formatBytes(storage.remainingBytes);
    final progress = storage.limitGb <= 0
        ? 0.0
        : (storage.usedPercent / 100).clamp(0.0, 1.0).toDouble();

    return Container(
      padding: const EdgeInsets.all(9),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context).withValues(alpha: 0.96),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: airmiusBorderColor(context)),
        boxShadow: [
          BoxShadow(
            color: Theme.of(context).shadowColor.withValues(alpha: 0.25),
            blurRadius: 22,
            offset: const Offset(0, -8),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  t('files.storage')
                      .replaceAll('{used}', used)
                      .replaceAll('{limit}', '${storage.limitGb}'),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              _FooterBadge(
                t('files.free').replaceAll('{remaining}', remaining),
              ),
            ],
          ),
          const SizedBox(height: 7),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              minHeight: 5,
              value: progress,
              backgroundColor: airmiusInputColor(context),
              valueColor: AlwaysStoppedAnimation<Color>(
                airmiusAccentColor(context),
              ),
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
        color: airmiusSurfaceColor(context),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: airmiusBorderColor(context)),
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
        color: airmiusInputColor(context).withValues(alpha: 0.35),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: child,
    );
  }
}

class _HeaderIconButton extends StatelessWidget {
  const _HeaderIconButton({
    required this.icon,
    required this.onTap,
    required this.semanticLabel,
    this.primary = false,
    this.active = false,
  });

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
            color: primary
                ? airmiusTextColor(context)
                : (active ? airmiusInputColor(context) : Colors.transparent),
            borderRadius: BorderRadius.circular(9),
            border: primary
                ? null
                : Border.all(color: airmiusBorderColor(context)),
          ),
          child: Icon(
            icon,
            color: primary
                ? Theme.of(context).scaffoldBackgroundColor
                : airmiusTextColor(context),
            size: 22,
          ),
        ),
      ),
    );
  }
}

class _SmallIcon extends StatelessWidget {
  const _SmallIcon({
    required this.icon,
    required this.onTap,
    required this.semanticLabel,
  });

  final IconData icon;
  final VoidCallback onTap;
  final String semanticLabel;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: semanticLabel,
      constraints: const BoxConstraints(minHeight: 48, minWidth: 48),
      padding: EdgeInsets.zero,
      visualDensity: VisualDensity.compact,
      icon: Icon(icon, color: airmiusMutedColor(context), size: 21),
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
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: airmiusMutedColor(context),
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
      ),
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
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: airmiusMutedColor(context),
          fontSize: 11,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _PrimaryBlockButton extends StatelessWidget {
  const _PrimaryBlockButton({
    required this.label,
    required this.enabled,
    required this.onTap,
  });

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
          backgroundColor: airmiusAccentColor(context),
          disabledBackgroundColor: airmiusSurfaceSoftColor(context),
          foregroundColor: airmiusOnColor(airmiusAccentColor(context)),
          disabledForegroundColor: airmiusMutedColor(context),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
        ),
        child: Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
    );
  }
}

class _TextField extends StatelessWidget {
  const _TextField({
    this.controller,
    this.hintText = '',
    this.prefixIcon,
    this.onChanged,
  });

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
        style: TextStyle(
          color: airmiusTextColor(context),
          fontWeight: FontWeight.w800,
        ),
        decoration: InputDecoration(
          hintText: hintText,
          hintStyle: TextStyle(color: airmiusMutedColor(context)),
          prefixIcon: prefixIcon == null
              ? null
              : Icon(prefixIcon, color: airmiusMutedColor(context), size: 20),
          filled: true,
          fillColor: airmiusInputColor(context),
          contentPadding: const EdgeInsets.symmetric(horizontal: 12),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(9),
            borderSide: BorderSide(color: airmiusBorderColor(context)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(9),
            borderSide: BorderSide(color: airmiusAccentColor(context)),
          ),
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
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(9),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 13,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          Icon(Icons.keyboard_arrow_down, color: airmiusMutedColor(context)),
        ],
      ),
    );
  }
}

class _UploadDestination {
  const _UploadDestination({
    required this.scope,
    this.clubId,
    this.teamId,
  });

  final String scope;
  final int? clubId;
  final int? teamId;

  String get apiScope => switch (scope) {
    'club' => 'club',
    'team' => 'team',
    _ => 'user',
  };
}

class _UploadDestinationSheet extends StatefulWidget {
  const _UploadDestinationSheet({required this.clubs, required this.teams});

  final List<AirmiusNamedItem> clubs;
  final List<AirmiusNamedItem> teams;

  @override
  State<_UploadDestinationSheet> createState() =>
      _UploadDestinationSheetState();
}

class _UploadDestinationSheetState extends State<_UploadDestinationSheet> {
  String _scope = 'mine';
  int? _clubId;
  int? _teamId;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final targets = <({String value, IconData icon, String label})>[
      (value: 'mine', icon: Icons.person_outline, label: t('files.scope.mine')),
      if (widget.clubs.isNotEmpty)
        (value: 'club', icon: Icons.business_outlined, label: t('files.scope.club')),
      if (widget.teams.isNotEmpty)
        (value: 'team', icon: Icons.groups_outlined, label: t('files.scope.team')),
    ];
    final canContinue =
        _scope == 'mine' ||
        (_scope == 'club' && _clubId != null) ||
        (_scope == 'team' && _teamId != null);

    return SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(18, 4, 18, 22),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    airmiusAccentColor(context).withValues(alpha: 0.22),
                    airmiusSurfaceSoftColor(context),
                  ],
                ),
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: airmiusBorderColor(context)),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.cloud_upload_outlined,
                    color: airmiusAccentColor(context),
                    size: 32,
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          t('files.uploadTitle'),
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontSize: 21,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          t('files.scope'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            Text(
              t('files.scope'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 15,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 10),
            for (final target in targets) ...[
              InkWell(
                borderRadius: BorderRadius.circular(18),
                onTap: () => setState(() {
                  _scope = target.value;
                  if (_scope != 'club') _clubId = null;
                  if (_scope != 'team') _teamId = null;
                }),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: _scope == target.value
                        ? airmiusAccentColor(context).withValues(alpha: 0.14)
                        : airmiusSurfaceSoftColor(context),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: _scope == target.value
                          ? airmiusAccentColor(context)
                          : airmiusBorderColor(context),
                      width: _scope == target.value ? 1.5 : 1,
                    ),
                  ),
                  child: Row(
                    children: [
                      Icon(
                        target.icon,
                        color: _scope == target.value
                            ? airmiusAccentColor(context)
                            : airmiusMutedColor(context),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          target.label,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      Icon(
                        _scope == target.value
                            ? Icons.radio_button_checked
                            : Icons.radio_button_unchecked,
                        color: _scope == target.value
                            ? airmiusAccentColor(context)
                            : airmiusMutedColor(context),
                      ),
                    ],
                  ),
                ),
              ),
            ],
            if (_scope == 'club') ...[
              const SizedBox(height: 2),
              _DestinationDropdown(
                label: t('files.scope.club'),
                value: _clubId,
                items: widget.clubs,
                onChanged: (value) => setState(() => _clubId = value),
              ),
            ],
            if (_scope == 'team') ...[
              const SizedBox(height: 2),
              _DestinationDropdown(
                label: t('files.scope.team'),
                value: _teamId,
                items: widget.teams,
                onChanged: (value) => setState(() => _teamId = value),
              ),
            ],
            const SizedBox(height: 18),
            SizedBox(
              height: 52,
              child: ElevatedButton.icon(
                onPressed: canContinue
                    ? () => Navigator.pop(
                        context,
                        _UploadDestination(
                          scope: _scope,
                          clubId: _clubId,
                          teamId: _teamId,
                        ),
                      )
                    : null,
                icon: const Icon(Icons.attach_file_outlined),
                label: Text(
                  t('files.choose'),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: airmiusAccentColor(context),
                  foregroundColor: airmiusOnColor(airmiusAccentColor(context)),
                  disabledBackgroundColor: airmiusSurfaceSoftColor(context),
                  disabledForegroundColor: airmiusMutedColor(context),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DestinationDropdown extends StatelessWidget {
  const _DestinationDropdown({
    required this.label,
    required this.value,
    required this.items,
    required this.onChanged,
  });

  final String label;
  final int? value;
  final List<AirmiusNamedItem> items;
  final ValueChanged<int?> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<int>(
      initialValue: value,
      decoration: InputDecoration(
        labelText: label,
        filled: true,
        fillColor: airmiusInputColor(context),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: airmiusBorderColor(context)),
        ),
      ),
      dropdownColor: airmiusSurfaceColor(context),
      items: items
          .map(
            (item) => DropdownMenuItem<int>(
              value: item.id,
              child: Text(item.name),
            ),
          )
          .toList(),
      onChanged: onChanged,
    );
  }
}

class _FileFolder {
  const _FileFolder(this.name, this.filesCount, {this.id});

  factory _FileFolder.fromApi(AirmiusFolder folder) =>
      _FileFolder(folder.name, folder.filesCount, id: folder.id);

  final String name;
  final int filesCount;
  final int? id;
}

class _ShareFriend {
  const _ShareFriend({required this.id, required this.name});

  final int id;
  final String name;
}

class _ManagedFile {
  const _ManagedFile(
    this.icon,
    this.title,
    this.meta,
    this.previewBody,
    this.status, {
    this.id,
    this.url,
  });

  factory _ManagedFile.fromApi(
    AirmiusManagedFile file,
    String Function(String) t,
  ) {
    final type = file.type.isEmpty ? t('files') : file.type;
    return _ManagedFile(
      _iconFor(type),
      file.name,
      '$type - ${_formatSize(file.size)}',
      file.url.isEmpty ? t('files.backendPreview') : file.url,
      t('files.backend'),
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
