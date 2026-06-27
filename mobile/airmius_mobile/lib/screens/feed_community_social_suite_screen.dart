import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_post_upload_service.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_story_upload_service.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_inline_video.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/content_report_dialog.dart';
import 'feed_post_detail_screen.dart';

class FeedCommunitySocialSuiteScreen extends StatefulWidget {
  const FeedCommunitySocialSuiteScreen({super.key});

  @override
  State<FeedCommunitySocialSuiteScreen> createState() => _FeedCommunitySocialSuiteScreenState();
}

class _FeedCommunitySocialSuiteScreenState extends State<FeedCommunitySocialSuiteScreen> {
  final TextEditingController _contentController = TextEditingController();
  final Map<int, AirmiusPost> _postOverrides = {};
  final List<AirmiusPost> _localPosts = [];
  final Set<int> _removedPostIds = {};
  late Future<AirmiusPage<AirmiusPost>> _feedFuture;
  late Future<List<AirmiusStory>> _storiesFuture;
  late Future<AirmiusPage<AirmiusClub>> _clubsFuture;
  late Future<AirmiusPage<AirmiusTeam>> _teamsFuture;
  late Future<AirmiusPage<AirmiusSport>> _sportsFuture;
  String _visibility = 'public';
  String _postType = 'normal';
  String _contentOrigin = 'self';
  int? _clubId;
  int? _teamId;
  int? _sportId;
  List<int> _sportSkillIds = const [];
  PlatformFile? _imageFile;
  List<PlatformFile> _attachments = const [];
  bool _advanced = false;
  bool _sending = false;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_loaded) return;
    _feedFuture = _loadFeed();
    _storiesFuture = _loadStories();
    _clubsFuture = _loadClubs();
    _teamsFuture = _loadTeams();
    _sportsFuture = _loadSports();
    _loaded = true;
  }

  @override
  void dispose() {
    _contentController.dispose();
    super.dispose();
  }

  Future<AirmiusPage<AirmiusPost>> _loadFeed() {
    return AirmiusServicesScope.of(context).repositories.feed.feed();
  }

  Future<List<AirmiusStory>> _loadStories() {
    return AirmiusServicesScope.of(context).repositories.feed.stories();
  }

  Future<AirmiusPage<AirmiusClub>> _loadClubs() {
    return AirmiusServicesScope.of(context).repositories.clubs.searchClubs();
  }

  Future<AirmiusPage<AirmiusTeam>> _loadTeams() {
    return AirmiusServicesScope.of(context).repositories.clubs.teams();
  }

  Future<AirmiusPage<AirmiusSport>> _loadSports() {
    return Future.value(const AirmiusPage<AirmiusSport>(items: [], currentPage: 1, lastPage: 1));
  }

  void _reload() {
    setState(() {
      _postOverrides.clear();
      _localPosts.clear();
      _removedPostIds.clear();
      _feedFuture = _loadFeed();
      _storiesFuture = _loadStories();
      _clubsFuture = _loadClubs();
      _teamsFuture = _loadTeams();
      _sportsFuture = _loadSports();
    });
  }

  void _reloadStories() {
    setState(() {
      _storiesFuture = _loadStories();
    });
  }

  void _removePostLocally(int postId) {
    setState(() {
      _postOverrides.remove(postId);
      _removedPostIds.add(postId);
    });
  }

  void _restorePostLocally(AirmiusPost post) {
    setState(() {
      _removedPostIds.remove(post.id);
      _postOverrides[post.id] = post;
    });
  }

  void _prependPostLocally(AirmiusPost post) {
    setState(() {
      _removedPostIds.remove(post.id);
      _postOverrides.remove(post.id);
      _localPosts.removeWhere((item) => item.id == post.id);
      _localPosts.insert(0, post);
    });
  }

  Future<void> _pickImage() async {
    final picked = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'gif'],
      withData: true,
    );
    final file = picked?.files.single;
    if (file == null) return;
    setState(() => _imageFile = file);
  }

  Future<void> _pickAttachments() async {
    final picked = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowMultiple: true,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'webm', 'ogg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'],
      withData: true,
    );
    if (picked == null) return;
    setState(() => _attachments = picked.files);
  }

  void _setVisibility(String value) {
    setState(() {
      _visibility = value;
      if (value != 'organization') _clubId = null;
      if (value != 'team') _teamId = null;
      if (value != 'public') _advanced = true;
    });
  }

  void _resetComposer() {
    _contentController.clear();
    _visibility = 'public';
    _postType = 'normal';
    _contentOrigin = 'self';
    _clubId = null;
    _teamId = null;
    _sportId = null;
    _sportSkillIds = const [];
    _imageFile = null;
    _attachments = const [];
    _advanced = false;
  }

  Future<bool> _publish() async {
    final content = _contentController.text.trim();
    final hasMedia = _imageFile != null || _attachments.isNotEmpty;
    if ((!hasMedia && content.isEmpty) || _sending) return false;
    if (_visibility == 'organization' && _clubId == null) {
      setState(() => _advanced = true);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Waehle einen Verein fuer einen Vereinsbeitrag.')));
      return false;
    }
    if (_visibility == 'team' && _teamId == null) {
      setState(() => _advanced = true);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Waehle ein Team fuer einen Teambeitrag.')));
      return false;
    }

    setState(() => _sending = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      final createdPost = await AirmiusPostUploadService(client).upload(
        content: content,
        visibility: _visibility,
        postType: _postType,
        contentOrigin: _contentOrigin,
        clubId: _clubId,
        teamId: _teamId,
        sportId: _sportId,
        sportSkillIds: _sportSkillIds,
        image: _imageFile,
        attachments: _attachments,
      );
      if (!mounted) return false;
      if (Navigator.canPop(context)) {
        Navigator.pop(context);
      }
      setState(() {
        _resetComposer();
        _sending = false;
      });
      _prependPostLocally(createdPost);
      return true;
    } catch (error) {
      if (!mounted) return false;
      setState(() => _sending = false);
      await _showUploadError(error);
      return false;
    }
  }

  Future<void> _showUploadError(Object error) async {
    final message = _uploadErrorMessage(error);
    if (!mounted) return;
    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: AirmiusColors.card,
        surfaceTintColor: Colors.transparent,
        title: const Text('Upload abgelehnt', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        content: SingleChildScrollView(
          child: SelectableText(
            message,
            style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: const Text('Schliessen'),
          ),
        ],
      ),
    );
  }

  String _uploadErrorMessage(Object error) {
    if (error is AirmiusApiException) {
      try {
        final decoded = jsonDecode(error.body);
        if (decoded is Map<String, dynamic>) {
          final message = decoded['message'];
          final errors = decoded['errors'];
          final parts = <String>[];
          if (message is String && message.trim().isNotEmpty) {
            parts.add(_explainValidationMessage(message.trim()));
          }
          if (errors is Map<String, dynamic>) {
            final validationMessages = <String>[];
            errors.forEach((field, value) {
              if (value is List && value.isNotEmpty) {
                validationMessages.add('$field: ${_explainValidationMessage(value.first.toString())}');
              }
              if (value is String && value.trim().isNotEmpty) {
                validationMessages.add('$field: ${_explainValidationMessage(value)}');
              }
            });
            if (validationMessages.any((item) => item.startsWith('image:') && item.contains('validation.string'))) {
              validationMessages.add('Erklaerung: Die Production-API erwartet beim Feld image offenbar einen Text/Pfad, bekommt von Flutter aber eine echte Datei. Das ist ein Backend/API-Stand-Problem: /api/v1/feed muss multipart image uploads akzeptieren oder ein separates Upload-then-post-Verfahren bereitstellen.');
            }
            if (validationMessages.isNotEmpty) {
              parts.add(validationMessages.join(' | '));
            }
          }
          final debug = decoded['flutter_upload_debug'];
          if (debug is Map<String, dynamic>) {
            final image = debug['image'];
            if (image is Map<String, dynamic>) {
              parts.add('Bild: ${image['name']} (${image['bytes_length'] ?? image['size']} Bytes, ${image['content_type']})');
            }
            final sentFiles = debug['sent_files'];
            if (sentFiles is List && sentFiles.isNotEmpty) {
              parts.add('Gesendet: ${sentFiles.map((file) {
                if (file is Map<String, dynamic>) {
                  return '${file['field']}=${file['filename']} ${file['content_type']} ${file['length']}B';
                }
                return file.toString();
              }).join(', ')}');
            }
          }
          if (parts.isNotEmpty) {
            return 'Upload abgelehnt (${error.statusCode}): ${parts.join(' - ')}';
          }
        }
      } catch (_) {
        if (error.body.trim().isNotEmpty) return error.body;
      }
      if (error.body.trim().isNotEmpty) return 'Upload abgelehnt (${error.statusCode}): ${error.body}';
    }
    return AirmiusScope.of(context).t('feed.error');
  }

  String _explainValidationMessage(String message) {
    return switch (message) {
      'validation.string' => 'validation.string (Server erwartet Text/String, bekam aber einen anderen Wert)',
      'validation.image' => 'validation.image (Server erkennt die Datei nicht als Bild)',
      'validation.file' => 'validation.file (Server erkennt keinen gueltigen Datei-Upload)',
      'validation.mimes' => 'validation.mimes (Dateityp ist nicht erlaubt)',
      'validation.max.file' => 'validation.max.file (Datei ist zu gross)',
      'validation.max' => 'validation.max (Wert oder Datei ist zu gross)',
      _ => message,
    };
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final isCompactFeedLayout = MediaQuery.sizeOf(context).width < 960;
    return PageFrame(
      title: scope.t('feed.title'),
      subtitle: scope.t('feed.subtitle'),
      showHeader: !isCompactFeedLayout,
      child: RefreshIndicator(
        color: AirmiusColors.blue,
        backgroundColor: AirmiusColors.card,
        onRefresh: () async {
          _reload();
          await _feedFuture;
        },
        child: FutureBuilder<AirmiusPage<AirmiusPost>>(
          future: _feedFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return _FeedListScaffold(composer: _ComposerTrigger(onTap: _openComposer), child: const _LoadingFeed());
            }
            if (snapshot.hasError) {
              return _FeedListScaffold(composer: _ComposerTrigger(onTap: _openComposer), child: _ErrorFeed(onRetry: _reload));
            }

            final loadedPosts = snapshot.data?.items ?? const <AirmiusPost>[];
            final localPostIds = _localPosts.map((post) => post.id).toSet();
            final posts = [
              ..._localPosts,
              ...loadedPosts.where((post) => !localPostIds.contains(post.id)),
            ]
                .where((post) => !_removedPostIds.contains(post.id))
                .map((post) => _postOverrides[post.id] ?? post)
                .toList();
            return _FeedListScaffold(
              composer: _ComposerTrigger(onTap: _openComposer),
              child: posts.isEmpty
                  ? EmptyPanel(scope.t('feed.empty'))
                  : Column(
                      children: [
                        _StoriesRail(storiesFuture: _storiesFuture, onChanged: _reloadStories),
                        const SizedBox(height: 14),
                        for (final post in posts) ...[
                          _PostCard(
                            post: post,
                            onChanged: _reload,
                            onDeleted: _removePostLocally,
                            onDeleteFailed: _restorePostLocally,
                            onPostChanged: (nextPost) => setState(() => _postOverrides[nextPost.id] = nextPost),
                          ),
                          const SizedBox(height: 12),
                        ],
                      ],
                    ),
            );
          },
        ),
      ),
    );
  }

  Future<void> _openComposer() async {
    await showDialog<void>(
      context: context,
      useSafeArea: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (dialogContext, setDialogState) {
            void refreshDialog() => setDialogState(() {});
            final user = AirmiusServicesScope.of(context).authState.user;
            final composer = _Composer(
              contentController: _contentController,
              userName: user?.name ?? 'Airmius',
              userAvatarUrl: user?.avatarUrl,
              visibility: _visibility,
              postType: _postType,
              contentOrigin: _contentOrigin,
              clubId: _clubId,
              teamId: _teamId,
              sportId: _sportId,
              sportSkillIds: _sportSkillIds,
              imageFile: _imageFile,
              attachmentCount: _attachments.length,
              advanced: _advanced,
              clubsFuture: _clubsFuture,
              teamsFuture: _teamsFuture,
              sportsFuture: _sportsFuture,
              sending: _sending,
              onVisibilityChanged: (value) {
                _setVisibility(value);
                refreshDialog();
              },
              onPostTypeChanged: (value) {
                setState(() => _postType = value);
                refreshDialog();
              },
              onContentOriginChanged: (value) {
                setState(() => _contentOrigin = value);
                refreshDialog();
              },
              onClubChanged: (value) {
                setState(() => _clubId = value);
                refreshDialog();
              },
              onTeamChanged: (value) {
                setState(() => _teamId = value);
                refreshDialog();
              },
              onSportChanged: (value) {
                setState(() {
                  _sportId = value;
                  _sportSkillIds = const [];
                });
                refreshDialog();
              },
              onSportSkillToggled: (skillId) {
                setState(() {
                  _sportSkillIds = _sportSkillIds.contains(skillId)
                      ? _sportSkillIds.where((id) => id != skillId).toList()
                      : [..._sportSkillIds, skillId];
                });
                refreshDialog();
              },
              onAdvancedChanged: (value) {
                setState(() => _advanced = value);
                refreshDialog();
              },
              onPickImage: () async {
                await _pickImage();
                refreshDialog();
              },
              onClearImage: () {
                setState(() => _imageFile = null);
                refreshDialog();
              },
              onPickAttachments: () async {
                await _pickAttachments();
                refreshDialog();
              },
              onClearAttachments: () {
                setState(() => _attachments = const []);
                refreshDialog();
              },
              onPublish: () async {
                final success = await _publish();
                if (!success) refreshDialog();
              },
            );

            return Dialog.fullscreen(
              backgroundColor: Colors.black.withValues(alpha: 0.62),
              child: SafeArea(
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    return Align(
                      alignment: constraints.maxWidth < 700 ? Alignment.bottomCenter : Alignment.center,
                      child: ConstrainedBox(
                        constraints: BoxConstraints(
                          maxWidth: 672,
                          maxHeight: constraints.maxHeight - 24,
                        ),
                        child: Container(
                          margin: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AirmiusColors.card,
                            borderRadius: BorderRadius.circular(24),
                            border: Border.all(color: AirmiusColors.border),
                            boxShadow: [
                              BoxShadow(
                                color: Colors.black.withValues(alpha: 0.34),
                                blurRadius: 30,
                                offset: const Offset(0, 18),
                              ),
                            ],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Padding(
                                padding: const EdgeInsets.fromLTRB(16, 14, 10, 8),
                                child: Row(
                                  children: [
                                    Expanded(child: Text('Beitrag erstellen', style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900))),
                                    IconButton(
                                      onPressed: _sending ? null : () => Navigator.pop(dialogContext),
                                      icon: const Icon(Icons.close, color: AirmiusColors.muted),
                                    ),
                                  ],
                                ),
                              ),
                              Expanded(
                                child: SingleChildScrollView(
                                  padding: const EdgeInsets.fromLTRB(16, 6, 16, 18),
                                  child: composer,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            );
          },
        );
      },
    );
  }
}

class _FeedListScaffold extends StatelessWidget {
  const _FeedListScaffold({required this.composer, required this.child});

  final Widget composer;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          composer,
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

class _ComposerTrigger extends StatelessWidget {
  const _ComposerTrigger({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final user = AirmiusServicesScope.of(context).authState.user;
    return AirmiusPanel(
      onTap: onTap,
      child: Row(
        children: [
          AirmiusAvatar(user?.name ?? 'Airmius', imageUrl: user?.avatarUrl),
          const SizedBox(width: 12),
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
              decoration: BoxDecoration(
                color: AirmiusColors.cardSoft,
                borderRadius: BorderRadius.circular(999),
                border: Border.all(color: AirmiusColors.border),
              ),
              child: const Text(
                'Was gibt es Neues?',
                style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StoriesRail extends StatelessWidget {
  const _StoriesRail({required this.storiesFuture, required this.onChanged});

  final Future<List<AirmiusStory>> storiesFuture;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return FutureBuilder<List<AirmiusStory>>(
      future: storiesFuture,
      builder: (context, snapshot) {
        final stories = snapshot.data ?? const <AirmiusStory>[];
        if (snapshot.connectionState == ConnectionState.waiting) {
          return AirmiusPanel(child: Text(scope.t('feed.stories'), style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)));
        }
        if (stories.isEmpty) {
          return AirmiusPanel(
            child: Row(
              children: [
                Expanded(child: SectionLabel(scope.t('feed.stories'))),
                TextButton.icon(
                  onPressed: () => _createStory(context),
                  icon: const Icon(Icons.add_circle_outline, size: 18),
                  label: Text(scope.t('feed.storyCreate'), style: const TextStyle(fontWeight: FontWeight.w900)),
                ),
              ],
            ),
          );
        }

        final storyGroups = _storyGroups(stories);
        return AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: SectionLabel(scope.t('feed.stories'))),
                  TextButton.icon(
                    onPressed: () => _createStory(context),
                    icon: const Icon(Icons.add_circle_outline, size: 18),
                    label: Text(scope.t('feed.storyCreate'), style: const TextStyle(fontWeight: FontWeight.w900)),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 112,
                child: ListView.separated(
                  scrollDirection: Axis.horizontal,
                  itemCount: storyGroups.length,
                  separatorBuilder: (context, index) => const SizedBox(width: 10),
                  itemBuilder: (context, index) => _StoryChip(group: storyGroups[index], onChanged: onChanged),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _createStory(BuildContext context) async {
    final scope = AirmiusScope.of(context);
    final services = AirmiusServicesScope.of(context);
    try {
      final client = services.clientForSession(services.authState.session);
      final story = await AirmiusStoryUploadService(client).pickAndUpload(visibility: 'public');
      if (story == null) {
        if (!context.mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(scope.t('feed.storyNoFile'))));
        return;
      }
      onChanged();
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(scope.t('feed.storyCreated'))));
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('${scope.t('feed.storyUploadError')} $error')));
    }
  }

  List<_StoryGroup> _storyGroups(List<AirmiusStory> stories) {
    final grouped = <String, List<AirmiusStory>>{};
    for (final story in stories) {
      final key = story.actorId > 0 ? '${story.actorType}:${story.actorId}' : 'name:${story.actorName}:${story.actorAvatarUrl ?? ''}';
      grouped.putIfAbsent(key, () => <AirmiusStory>[]).add(story);
    }
    return grouped.values.map(_StoryGroup.new).toList();
  }
}

class _StoryGroup {
  const _StoryGroup(this.stories);

  final List<AirmiusStory> stories;

  AirmiusStory get first => stories.first;
  String get actorName => first.actorName;
  String? get actorAvatarUrl => first.actorAvatarUrl;
  bool get viewedByMe => stories.every((story) => story.viewedByMe);
  int get count => stories.length;
}

class _StoryChip extends StatelessWidget {
  const _StoryChip({required this.group, required this.onChanged});

  final _StoryGroup group;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final story = group.first;
    return InkWell(
      borderRadius: BorderRadius.circular(18),
      onTap: () => _openStory(context),
      child: SizedBox(
        width: 88,
        child: Column(
          children: [
            Container(
              width: 68,
              height: 68,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(22),
                border: Border.all(color: group.viewedByMe ? AirmiusColors.border : AirmiusColors.blue, width: 2),
                color: AirmiusColors.cardSoft,
              ),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(20),
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    Center(child: UserBubble(label: group.actorName, imageUrl: group.actorAvatarUrl)),
                    if (group.count > 1)
                      Positioned(
                        right: 4,
                        bottom: 4,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                          decoration: BoxDecoration(color: AirmiusColors.blue, borderRadius: BorderRadius.circular(999), border: Border.all(color: AirmiusColors.card, width: 2)),
                          child: Text('${group.count}', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900)),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 7),
            Text(group.actorName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 12, fontWeight: FontWeight.w900)),
          ],
        ),
      ),
    );
  }

  Future<void> _openStory(BuildContext context) async {
    final scope = AirmiusScope.of(context);
    final rootContext = context;
    var currentIndex = group.stories.indexWhere((story) => !story.viewedByMe);
    if (currentIndex < 0) currentIndex = 0;
    final stories = List<AirmiusStory>.from(group.stories);
    final pageController = PageController(initialPage: currentIndex);
    var storyMuted = true;
    try {
      var story = stories[currentIndex];
      await AirmiusServicesScope.of(context).repositories.feed.markStoryViewed(story.id);
      if (!context.mounted) return;
      var storyChanged = false;
      await showModalBottomSheet<void>(
        context: context,
        isScrollControlled: true,
        backgroundColor: AirmiusColors.card,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
        builder: (sheetContext) {
          final mediaHeight = MediaQuery.sizeOf(sheetContext).height * .58;
          return StatefulBuilder(builder: (context, setSheetState) {
            story = stories[currentIndex];
            return SafeArea(
              child: SingleChildScrollView(
                padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(sheetContext).bottom),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(children: [
                        AirmiusAvatar(story.actorName, imageUrl: story.actorAvatarUrl),
                        const SizedBox(width: 12),
                        Expanded(child: Text(story.actorName, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 18))),
                        if (stories.length > 1) StatusPill('${currentIndex + 1}/${stories.length}'),
                      ]),
                      if (story.caption != null) ...[
                        const SizedBox(height: 12),
                        Text(story.caption!, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
                      ],
                      const SizedBox(height: 14),
                      SizedBox(
                        height: mediaHeight,
                        child: Stack(
                          children: [
                            PageView.builder(
                              controller: pageController,
                              itemCount: stories.length,
                              onPageChanged: (index) async {
                                setSheetState(() => currentIndex = index);
                                await AirmiusServicesScope.of(context).repositories.feed.markStoryViewed(stories[index].id);
                                storyChanged = true;
                              },
                              itemBuilder: (context, index) {
                                final pageStory = stories[index];
                                return pageStory.mediaUrl.isNotEmpty
                                    ? _StoryMediaPreview(
                                        story: pageStory,
                                        height: mediaHeight,
                                        muted: storyMuted,
                                        onMutedChanged: (muted) => setSheetState(() => storyMuted = muted),
                                        onEnded: index < stories.length - 1
                                            ? () {
                                                if (pageController.hasClients) {
                                                  pageController.nextPage(duration: const Duration(milliseconds: 260), curve: Curves.easeOutCubic);
                                                }
                                              }
                                            : null,
                                      )
                                    : const SizedBox.shrink();
                              },
                            ),
                            if (stories.length > 1 && currentIndex > 0)
                              Positioned(
                                left: 10,
                                top: 0,
                                bottom: 0,
                                child: Center(
                                  child: IconButton.filled(
                                    onPressed: () => pageController.previousPage(duration: const Duration(milliseconds: 220), curve: Curves.easeOutCubic),
                                    icon: const Icon(Icons.chevron_left),
                                    style: IconButton.styleFrom(backgroundColor: Colors.black.withValues(alpha: 0.48), foregroundColor: Colors.white),
                                  ),
                                ),
                              ),
                            if (stories.length > 1 && currentIndex < stories.length - 1)
                              Positioned(
                                right: 10,
                                top: 0,
                                bottom: 0,
                                child: Center(
                                  child: IconButton.filled(
                                    onPressed: () => pageController.nextPage(duration: const Duration(milliseconds: 220), curve: Curves.easeOutCubic),
                                    icon: const Icon(Icons.chevron_right),
                                    style: IconButton.styleFrom(backgroundColor: Colors.black.withValues(alpha: 0.48), foregroundColor: Colors.white),
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 14),
                      AirmiusButton(label: scope.t('feed.storyReact'), icon: Icons.favorite_outline, onPressed: () async {
                        await AirmiusServicesScope.of(context).repositories.feed.reactToStory(story.id, 'heart');
                        storyChanged = true;
                        if (context.mounted) Navigator.pop(context);
                      }),
                      const SizedBox(height: 10),
                      AirmiusButton(label: 'Story melden', icon: Icons.flag_outlined, secondary: true, onPressed: () async {
                        final report = await showContentReportDialog(context, title: 'Story melden');
                        if (report == null || !context.mounted) return;
                        await AirmiusServicesScope.of(context).repositories.feed.reportContent(type: 'story', id: story.id, reason: report.reason, details: report.details);
                        storyChanged = true;
                        if (context.mounted) Navigator.pop(context);
                      }),
                      if (story.canDelete) ...[
                        const SizedBox(height: 10),
                        AirmiusButton(label: scope.t('feed.storyDelete'), icon: Icons.delete_outline, danger: true, onPressed: () async {
                          final deletedStory = story;
                          final deletedIndex = currentIndex;
                          final closesViewer = stories.length <= 1;
                          storyChanged = true;
                          if (closesViewer) {
                            if (context.mounted) Navigator.pop(context);
                          } else {
                            setSheetState(() {
                              stories.removeAt(deletedIndex);
                              currentIndex = deletedIndex >= stories.length ? stories.length - 1 : deletedIndex;
                            });
                            WidgetsBinding.instance.addPostFrameCallback((_) {
                              if (pageController.hasClients) pageController.jumpToPage(currentIndex);
                            });
                          }

                          try {
                            await AirmiusServicesScope.of(context).repositories.feed.deleteStory(deletedStory.id);
                            if (!rootContext.mounted) return;
                            ScaffoldMessenger.of(rootContext).showSnackBar(const SnackBar(content: Text('Story geloescht.')));
                          } catch (_) {
                            if (!rootContext.mounted) return;
                            if (!closesViewer && stories.isNotEmpty) {
                              setSheetState(() {
                                final restoreIndex = deletedIndex > stories.length ? stories.length : deletedIndex;
                                stories.insert(restoreIndex, deletedStory);
                                currentIndex = restoreIndex;
                              });
                              WidgetsBinding.instance.addPostFrameCallback((_) {
                                if (pageController.hasClients) pageController.jumpToPage(currentIndex);
                              });
                            }
                            ScaffoldMessenger.of(rootContext).showSnackBar(SnackBar(content: Text(scope.t('feed.error'))));
                          }
                        }),
                      ],
                    ],
                  ),
                ),
              ),
            );
          });
        },
      );
      if (storyChanged) onChanged();
    } catch (_) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(scope.t('feed.error'))));
    } finally {
      pageController.dispose();
    }
  }
}

class _StoryMediaPreview extends StatelessWidget {
  const _StoryMediaPreview({
    required this.story,
    required this.height,
    required this.muted,
    required this.onMutedChanged,
    this.onEnded,
  });

  final AirmiusStory story;
  final double height;
  final bool muted;
  final ValueChanged<bool> onMutedChanged;
  final VoidCallback? onEnded;

  @override
  Widget build(BuildContext context) {
    final isVideo = story.mediaKind.toLowerCase().contains('video');
    if (isVideo) {
      return AirmiusInlineVideo(
        key: ValueKey(story.mediaUrl),
        url: story.mediaUrl,
        thumbnailUrl: story.thumbnailUrl,
        height: height,
        borderRadius: 18,
        title: story.caption ?? 'Story Video',
        autoPlay: true,
        muted: muted,
        onMutedChanged: onMutedChanged,
        onEnded: onEnded,
      );
    }
    final imageUrl = isVideo ? story.thumbnailUrl : story.mediaUrl;
    return ClipRRect(
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        height: height,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (imageUrl != null)
              AirmiusMediaImage(url: imageUrl, borderRadius: 0, height: height)
            else
              Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      AirmiusColors.blue.withValues(alpha: 0.34),
                      AirmiusColors.cardSoft,
                      AirmiusColors.pink.withValues(alpha: 0.22),
                    ],
                  ),
                ),
              ),
            if (isVideo) Container(color: Colors.black.withValues(alpha: 0.24)),
            if (isVideo)
              const Center(
                child: Icon(Icons.play_circle_fill, color: Colors.white, size: 64),
              ),
          ],
        ),
      ),
    );
  }
}

class _Composer extends StatelessWidget {
  const _Composer({
    required this.contentController,
    required this.userName,
    required this.userAvatarUrl,
    required this.visibility,
    required this.postType,
    required this.contentOrigin,
    required this.clubId,
    required this.teamId,
    required this.sportId,
    required this.sportSkillIds,
    required this.imageFile,
    required this.attachmentCount,
    required this.advanced,
    required this.clubsFuture,
    required this.teamsFuture,
    required this.sportsFuture,
    required this.sending,
    required this.onVisibilityChanged,
    required this.onPostTypeChanged,
    required this.onContentOriginChanged,
    required this.onClubChanged,
    required this.onTeamChanged,
    required this.onSportChanged,
    required this.onSportSkillToggled,
    required this.onAdvancedChanged,
    required this.onPickImage,
    required this.onClearImage,
    required this.onPickAttachments,
    required this.onClearAttachments,
    required this.onPublish,
  });

  final TextEditingController contentController;
  final String userName;
  final String? userAvatarUrl;
  final String visibility;
  final String postType;
  final String contentOrigin;
  final int? clubId;
  final int? teamId;
  final int? sportId;
  final List<int> sportSkillIds;
  final PlatformFile? imageFile;
  final int attachmentCount;
  final bool advanced;
  final Future<AirmiusPage<AirmiusClub>> clubsFuture;
  final Future<AirmiusPage<AirmiusTeam>> teamsFuture;
  final Future<AirmiusPage<AirmiusSport>> sportsFuture;
  final bool sending;
  final ValueChanged<String> onVisibilityChanged;
  final ValueChanged<String> onPostTypeChanged;
  final ValueChanged<String> onContentOriginChanged;
  final ValueChanged<int?> onClubChanged;
  final ValueChanged<int?> onTeamChanged;
  final ValueChanged<int?> onSportChanged;
  final ValueChanged<int> onSportSkillToggled;
  final ValueChanged<bool> onAdvancedChanged;
  final VoidCallback onPickImage;
  final VoidCallback onClearImage;
  final VoidCallback onPickAttachments;
  final VoidCallback onClearAttachments;
  final VoidCallback onPublish;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              AirmiusAvatar(userName, imageUrl: userAvatarUrl),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(userName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 14)),
                    const SizedBox(height: 4),
                    const Text('Neuer Beitrag', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          AirmiusTextField(
            label: scope.t('feed.placeholder'),
            icon: Icons.edit_outlined,
            maxLines: 5,
            controller: contentController,
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: sending ? null : () => onAdvancedChanged(!advanced),
            icon: const Icon(Icons.tune_outlined, size: 18),
            label: Text('Zielgruppe, Sport & Typ', style: const TextStyle(fontWeight: FontWeight.w900)),
            style: OutlinedButton.styleFrom(
              foregroundColor: AirmiusColors.text,
              side: const BorderSide(color: AirmiusColors.border),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
          ),
          if (advanced) ...[
            const SizedBox(height: 12),
            _ComposerAdvanced(
              visibility: visibility,
              postType: postType,
              contentOrigin: contentOrigin,
              clubId: clubId,
              teamId: teamId,
              sportId: sportId,
              sportSkillIds: sportSkillIds,
              clubsFuture: clubsFuture,
              teamsFuture: teamsFuture,
              sportsFuture: sportsFuture,
              sending: sending,
              onVisibilityChanged: onVisibilityChanged,
              onPostTypeChanged: onPostTypeChanged,
              onContentOriginChanged: onContentOriginChanged,
              onClubChanged: onClubChanged,
              onTeamChanged: onTeamChanged,
              onSportChanged: onSportChanged,
              onSportSkillToggled: onSportSkillToggled,
            ),
          ],
          if (imageFile != null) ...[
            const SizedBox(height: 12),
            _SelectedFileCard(
              icon: Icons.image_outlined,
              title: imageFile!.name,
              subtitle: 'Bild ausgewaehlt',
              onClear: sending ? null : onClearImage,
            ),
          ],
          if (attachmentCount > 0) ...[
            const SizedBox(height: 12),
            _SelectedFileCard(
              icon: Icons.video_file_outlined,
              title: '$attachmentCount Datei(en)',
              subtitle: 'Video, Bild oder Datei ausgewaehlt',
              onClear: sending ? null : onClearAttachments,
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(label: 'Bild', icon: Icons.image_outlined, onPressed: sending ? null : onPickImage, secondary: true),
              AirmiusButton(label: 'Video / Dateien', icon: Icons.video_library_outlined, onPressed: sending ? null : onPickAttachments, secondary: true),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                decoration: BoxDecoration(
                  color: AirmiusColors.cardSoft,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AirmiusColors.border),
                ),
                child: const Text('Bilder optimiert, Videos bis 50 MB', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w800)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: AirmiusButton(
                  label: 'Abbrechen',
                  icon: Icons.close_outlined,
                  onPressed: sending ? null : () => Navigator.pop(context),
                  secondary: true,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: AirmiusButton(
                  label: sending ? scope.t('status.loading') : 'Posten',
                  icon: Icons.send_outlined,
                  onPressed: sending ? null : onPublish,
                ),
              ),
            ],
          ),
        ],
    );
  }
}

class _ComposerAdvanced extends StatelessWidget {
  const _ComposerAdvanced({
    required this.visibility,
    required this.postType,
    required this.contentOrigin,
    required this.clubId,
    required this.teamId,
    required this.sportId,
    required this.sportSkillIds,
    required this.clubsFuture,
    required this.teamsFuture,
    required this.sportsFuture,
    required this.sending,
    required this.onVisibilityChanged,
    required this.onPostTypeChanged,
    required this.onContentOriginChanged,
    required this.onClubChanged,
    required this.onTeamChanged,
    required this.onSportChanged,
    required this.onSportSkillToggled,
  });

  final String visibility;
  final String postType;
  final String contentOrigin;
  final int? clubId;
  final int? teamId;
  final int? sportId;
  final List<int> sportSkillIds;
  final Future<AirmiusPage<AirmiusClub>> clubsFuture;
  final Future<AirmiusPage<AirmiusTeam>> teamsFuture;
  final Future<AirmiusPage<AirmiusSport>> sportsFuture;
  final bool sending;
  final ValueChanged<String> onVisibilityChanged;
  final ValueChanged<String> onPostTypeChanged;
  final ValueChanged<String> onContentOriginChanged;
  final ValueChanged<int?> onClubChanged;
  final ValueChanged<int?> onTeamChanged;
  final ValueChanged<int?> onSportChanged;
  final ValueChanged<int> onSportSkillToggled;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.cardSoft.withValues(alpha: 0.78),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DropdownButtonFormField<String>(
            initialValue: visibility,
            decoration: const InputDecoration(labelText: 'Zielgruppe'),
            dropdownColor: AirmiusColors.card,
            items: const [
              DropdownMenuItem(value: 'public', child: Text('Oeffentlich')),
              DropdownMenuItem(value: 'organization', child: Text('Verein')),
              DropdownMenuItem(value: 'team', child: Text('Team')),
            ],
            onChanged: sending ? null : (value) => onVisibilityChanged(value ?? 'public'),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: postType,
            decoration: const InputDecoration(labelText: 'Beitragstyp'),
            dropdownColor: AirmiusColors.card,
            items: const [
              DropdownMenuItem(value: 'normal', child: Text('Normal')),
              DropdownMenuItem(value: 'question', child: Text('Frage')),
              DropdownMenuItem(value: 'knowledge', child: Text('Wissen')),
              DropdownMenuItem(value: 'training_drill', child: Text('Trainingsuebung')),
              DropdownMenuItem(value: 'tactic', child: Text('Taktik')),
              DropdownMenuItem(value: 'analysis', child: Text('Analyse')),
              DropdownMenuItem(value: 'experience', child: Text('Erfahrung')),
              DropdownMenuItem(value: 'club_update', child: Text('Vereinsinfo')),
            ],
            onChanged: sending ? null : (value) => onPostTypeChanged(value ?? 'normal'),
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: contentOrigin,
            decoration: const InputDecoration(labelText: 'Quelle'),
            dropdownColor: AirmiusColors.card,
            items: const [
              DropdownMenuItem(value: 'self', child: Text('Von mir selbst erstellt')),
              DropdownMenuItem(value: 'ai', child: Text('Mit KI erstellt')),
            ],
            onChanged: sending ? null : (value) => onContentOriginChanged(value ?? 'self'),
          ),
          const SizedBox(height: 10),
          FutureBuilder<AirmiusPage<AirmiusClub>>(
            future: clubsFuture,
            builder: (context, snapshot) {
              final clubs = snapshot.data?.items ?? const <AirmiusClub>[];
              return DropdownButtonFormField<int?>(
                initialValue: clubId,
                decoration: InputDecoration(
                  labelText: 'Verein',
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: BorderSide(color: visibility == 'organization' && clubId == null ? AirmiusColors.red : AirmiusColors.border),
                  ),
                ),
                dropdownColor: AirmiusColors.card,
                items: [
                  const DropdownMenuItem<int?>(value: null, child: Text('Kein Verein')),
                  for (final club in clubs) DropdownMenuItem<int?>(value: club.id, child: Text(club.name)),
                ],
                onChanged: sending ? null : onClubChanged,
              );
            },
          ),
          const SizedBox(height: 10),
          FutureBuilder<AirmiusPage<AirmiusTeam>>(
            future: teamsFuture,
            builder: (context, snapshot) {
              final teams = snapshot.data?.items ?? const <AirmiusTeam>[];
              return DropdownButtonFormField<int?>(
                initialValue: teamId,
                decoration: InputDecoration(
                  labelText: 'Team',
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(14),
                    borderSide: BorderSide(color: visibility == 'team' && teamId == null ? AirmiusColors.red : AirmiusColors.border),
                  ),
                ),
                dropdownColor: AirmiusColors.card,
                items: [
                  const DropdownMenuItem<int?>(value: null, child: Text('Kein Team')),
                  for (final team in teams) DropdownMenuItem<int?>(value: team.id, child: Text(team.clubName == null ? team.name : '${team.name} - ${team.clubName}')),
                ],
                onChanged: sending ? null : onTeamChanged,
              );
            },
          ),
          const SizedBox(height: 10),
          FutureBuilder<AirmiusPage<AirmiusSport>>(
            future: sportsFuture,
            builder: (context, snapshot) {
              final sports = snapshot.data?.items ?? const <AirmiusSport>[];
              AirmiusSport? selectedSport;
              for (final sport in sports) {
                if (sport.id == sportId) {
                  selectedSport = sport;
                  break;
                }
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  DropdownButtonFormField<int?>(
                    initialValue: sportId,
                    decoration: const InputDecoration(labelText: 'Sportart zum Beitrag'),
                    dropdownColor: AirmiusColors.card,
                    items: [
                      const DropdownMenuItem<int?>(value: null, child: Text('Keine Sportart')),
                      for (final sport in sports) DropdownMenuItem<int?>(value: sport.id, child: Text(sport.name)),
                    ],
                    onChanged: sending ? null : onSportChanged,
                  ),
                  const SizedBox(height: 10),
                  _SportSkillPicker(
                    sport: selectedSport,
                    selectedIds: sportSkillIds,
                    sending: sending,
                    onToggle: onSportSkillToggled,
                  ),
                ],
              );
            },
          ),
          const SizedBox(height: 10),
          Text(
            _visibilityHint(visibility),
            style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700, height: 1.35),
          ),
        ],
      ),
    );
  }

  String _visibilityHint(String visibility) {
    return switch (visibility) {
      'organization' => 'Sichtbar fuer Mitglieder des ausgewaehlten Vereins.',
      'team' => 'Sichtbar fuer Mitglieder des ausgewaehlten Teams.',
      _ => 'Sichtbar fuer dein Netzwerk und passende oeffentliche Feed-Kontexte.',
    };
  }
}

class _SportSkillPicker extends StatelessWidget {
  const _SportSkillPicker({required this.sport, required this.selectedIds, required this.sending, required this.onToggle});

  final AirmiusSport? sport;
  final List<int> selectedIds;
  final bool sending;
  final ValueChanged<int> onToggle;

  @override
  Widget build(BuildContext context) {
    if (sport == null) {
      return const Text('Optional: Sportart waehlen, um passende Skills zu markieren.', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700));
    }
    if (sport!.skills.isEmpty) {
      return Text('Keine Skills fuer ${sport!.name}.', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700));
    }
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final skill in sport!.skills)
          FilterChip(
            selected: selectedIds.contains(skill.id),
            label: Text(skill.name),
            onSelected: sending ? null : (_) => onToggle(skill.id),
            selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
            backgroundColor: AirmiusColors.card,
            side: BorderSide(color: selectedIds.contains(skill.id) ? AirmiusColors.blue : AirmiusColors.border),
            labelStyle: TextStyle(color: selectedIds.contains(skill.id) ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900, fontSize: 12),
          ),
      ],
    );
  }
}

class _ChoiceChipButton extends StatelessWidget {
  const _ChoiceChipButton({required this.label, required this.icon, required this.active, required this.onTap});

  final String label;
  final IconData icon;
  final bool active;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final color = active ? AirmiusColors.blue : AirmiusColors.border;
    return InkWell(
      borderRadius: BorderRadius.circular(999),
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
        decoration: BoxDecoration(
          color: active ? AirmiusColors.blue.withValues(alpha: 0.16) : AirmiusColors.cardSoft,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: color.withValues(alpha: active ? 0.75 : 1)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 16, color: active ? AirmiusColors.blue : AirmiusColors.muted),
            const SizedBox(width: 7),
            Text(label, style: TextStyle(color: active ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}

class _SelectedFileCard extends StatelessWidget {
  const _SelectedFileCard({required this.icon, required this.title, required this.subtitle, required this.onClear});

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.blue.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.blue.withValues(alpha: 0.28)),
      ),
      child: Row(
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 2),
                Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          IconButton(onPressed: onClear, icon: const Icon(Icons.close, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _PostCard extends StatefulWidget {
  const _PostCard({required this.post, required this.onChanged, required this.onDeleted, required this.onDeleteFailed, required this.onPostChanged});

  final AirmiusPost post;
  final VoidCallback onChanged;
  final ValueChanged<int> onDeleted;
  final ValueChanged<AirmiusPost> onDeleteFailed;
  final ValueChanged<AirmiusPost> onPostChanged;

  @override
  State<_PostCard> createState() => _PostCardState();
}

class _PostCardState extends State<_PostCard> {
  final TextEditingController _commentController = TextEditingController();
  final List<AirmiusComment> _localComments = [];
  Future<AirmiusPage<AirmiusComment>>? _commentsFuture;
  bool _commentsOpen = false;
  bool _sendingComment = false;
  bool _savingEdit = false;
  int _commentsPerPage = 20;

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  void _toggleComments() {
    setState(() {
      _commentsOpen = !_commentsOpen;
      _commentsFuture ??= _loadComments();
    });
  }

  Future<AirmiusPage<AirmiusComment>> _loadComments() {
    return AirmiusServicesScope.of(context).repositories.feed.comments(widget.post.id, perPage: _commentsPerPage);
  }

  void _showAllCommentsInline() {
    setState(() {
      _commentsPerPage = 50;
      _commentsFuture = _loadComments();
    });
  }

  Future<void> _sendComment() async {
    final content = _commentController.text.trim();
    if (content.isEmpty || _sendingComment) return;

    final user = AirmiusServicesScope.of(context).authState.user;
    final previousPost = widget.post;
    final optimisticPost = previousPost.copyWith(commentsCount: previousPost.commentsCount + 1);
    final optimisticComment = AirmiusComment(
      id: -DateTime.now().microsecondsSinceEpoch,
      postId: widget.post.id,
      content: content,
      authorName: user?.name ?? 'Ich',
      authorAvatarUrl: user?.avatarUrl,
      likesCount: 0,
      mine: true,
      canDelete: true,
      createdAt: DateTime.now(),
    );

    _commentController.clear();
    widget.onPostChanged(optimisticPost);
    setState(() {
      _sendingComment = true;
      _localComments.insert(0, optimisticComment);
    });

    try {
      final savedComment = await AirmiusServicesScope.of(context).repositories.feed.createComment(widget.post.id, content);
      if (!mounted) return;
      setState(() {
        _sendingComment = false;
        final index = _localComments.indexWhere((comment) => comment.id == optimisticComment.id);
        if (index >= 0) {
          _localComments[index] = savedComment;
        } else {
          _localComments.insert(0, savedComment);
        }
      });
    } catch (_) {
      if (!mounted) return;
      widget.onPostChanged(previousPost);
      setState(() {
        _sendingComment = false;
        _localComments.removeWhere((comment) => comment.id == optimisticComment.id);
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('feed.commentsError'))));
    }
  }

  Future<void> _deletePost() async {
    final ok = await confirmDanger(context, 'Beitrag loeschen', 'Moechtest du diesen Beitrag wirklich loeschen?');
    if (!ok || !mounted) return;
    final deletedPost = widget.post;
    widget.onDeleted(deletedPost.id);
    try {
      await AirmiusServicesScope.of(context).repositories.feed.deletePost(deletedPost.id);
    } catch (_) {
      if (!mounted) return;
      widget.onDeleteFailed(deletedPost);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('feed.error'))));
    }
  }

  Future<void> _editPost() async {
    final contentController = TextEditingController(text: widget.post.content);
    var visibility = widget.post.visibility;
    var postType = widget.post.postType;
    var contentOrigin = widget.post.contentOrigin;
    var clubId = widget.post.clubId;
    var teamId = widget.post.teamId;
    var sportId = widget.post.sportId;
    var sportSkillIds = [...widget.post.sportSkillIds];
    PlatformFile? imageFile;
    List<PlatformFile> attachments = const [];
    final repositories = AirmiusServicesScope.of(context).repositories;
    final clubsFuture = repositories.clubs.searchClubs();
    final teamsFuture = repositories.clubs.teams();
    final sportsFuture = repositories.sports.sports();

    final updated = await showDialog<AirmiusPost>(
      context: context,
      useSafeArea: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (dialogContext, setDialogState) {
            Future<void> pickEditImage() async {
              final picked = await FilePicker.platform.pickFiles(
                type: FileType.custom,
                allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                withData: true,
              );
              final file = picked?.files.single;
              if (file == null) return;
              setDialogState(() => imageFile = file);
            }

            Future<void> pickEditAttachments() async {
              final picked = await FilePicker.platform.pickFiles(
                type: FileType.custom,
                allowMultiple: true,
                allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'webm', 'ogg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'],
                withData: true,
              );
              if (picked == null) return;
              setDialogState(() => attachments = picked.files);
            }

            Future<void> save() async {
              final content = contentController.text.trim();
              final hasNewMedia = imageFile != null || attachments.isNotEmpty;
              if (_savingEdit || (content.isEmpty && !hasNewMedia)) return;
              if (visibility == 'organization' && clubId == null) {
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Waehle einen Verein fuer einen Vereinsbeitrag.')));
                return;
              }
              if (visibility == 'team' && teamId == null) {
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Waehle ein Team fuer einen Teambeitrag.')));
                return;
              }

              setState(() => _savingEdit = true);
              setDialogState(() {});
              try {
                final services = AirmiusServicesScope.of(context);
                final client = services.clientForSession(services.authState.session);
                final nextPost = await AirmiusPostUploadService(client).update(
                  postId: widget.post.id,
                  content: content,
                  visibility: visibility,
                  postType: postType,
                  contentOrigin: contentOrigin,
                  clubId: clubId,
                  teamId: teamId,
                  sportId: sportId,
                  sportSkillIds: sportSkillIds,
                  image: imageFile,
                  attachments: attachments,
                );
                if (!mounted) return;
                setState(() => _savingEdit = false);
                if (dialogContext.mounted) Navigator.pop(dialogContext, nextPost);
              } catch (_) {
                if (!mounted) return;
                setState(() => _savingEdit = false);
                setDialogState(() {});
                ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('feed.error'))));
              }
            }

            return Dialog.fullscreen(
              backgroundColor: Colors.black.withValues(alpha: 0.62),
              child: SafeArea(
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    return Align(
                      alignment: constraints.maxWidth < 700 ? Alignment.bottomCenter : Alignment.center,
                      child: ConstrainedBox(
                        constraints: BoxConstraints(maxWidth: 672, maxHeight: constraints.maxHeight - 24),
                        child: Container(
                          margin: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AirmiusColors.card,
                            borderRadius: BorderRadius.circular(24),
                            border: Border.all(color: AirmiusColors.border),
                            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.34), blurRadius: 30, offset: const Offset(0, 18))],
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              Padding(
                                padding: const EdgeInsets.fromLTRB(16, 14, 10, 8),
                                child: Row(
                                  children: [
                                    Expanded(child: Text('Beitrag bearbeiten', style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900))),
                                    IconButton(onPressed: _savingEdit ? null : () => Navigator.pop(dialogContext), icon: const Icon(Icons.close, color: AirmiusColors.muted)),
                                  ],
                                ),
                              ),
                              Expanded(
                                child: SingleChildScrollView(
                                  padding: const EdgeInsets.fromLTRB(16, 6, 16, 18),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.stretch,
                                    children: [
                                      AirmiusTextField(label: 'Was gibt es Neues?', icon: Icons.edit_outlined, maxLines: 4, controller: contentController),
                                      const SizedBox(height: 12),
                                      _ComposerAdvanced(
                                        visibility: visibility,
                                        postType: postType,
                                        contentOrigin: contentOrigin,
                                        clubId: clubId,
                                        teamId: teamId,
                                        sportId: sportId,
                                        sportSkillIds: sportSkillIds,
                                        clubsFuture: clubsFuture,
                                        teamsFuture: teamsFuture,
                                        sportsFuture: sportsFuture,
                                        sending: _savingEdit,
                                        onVisibilityChanged: (value) => setDialogState(() {
                                          visibility = value;
                                          if (value != 'organization') clubId = null;
                                          if (value != 'team') teamId = null;
                                        }),
                                        onPostTypeChanged: (value) => setDialogState(() => postType = value),
                                        onContentOriginChanged: (value) => setDialogState(() => contentOrigin = value),
                                        onClubChanged: (value) => setDialogState(() => clubId = value),
                                        onTeamChanged: (value) => setDialogState(() => teamId = value),
                                        onSportChanged: (value) => setDialogState(() {
                                          sportId = value;
                                          sportSkillIds = [];
                                        }),
                                        onSportSkillToggled: (skillId) => setDialogState(() {
                                          sportSkillIds = sportSkillIds.contains(skillId) ? sportSkillIds.where((id) => id != skillId).toList() : [...sportSkillIds, skillId];
                                        }),
                                      ),
                                      const SizedBox(height: 12),
                                      if (imageFile != null) ...[
                                        _SelectedFileCard(
                                          icon: Icons.image_outlined,
                                          title: imageFile!.name,
                                          subtitle: 'Neues Bild ausgewaehlt',
                                          onClear: _savingEdit ? null : () => setDialogState(() => imageFile = null),
                                        ),
                                        const SizedBox(height: 10),
                                      ],
                                      if (attachments.isNotEmpty) ...[
                                        _SelectedFileCard(
                                          icon: Icons.video_file_outlined,
                                          title: '${attachments.length} Datei(en)',
                                          subtitle: 'Neue Video-, Bild- oder Datei-Anhaenge',
                                          onClear: _savingEdit ? null : () => setDialogState(() => attachments = const []),
                                        ),
                                        const SizedBox(height: 10),
                                      ],
                                      Wrap(
                                        spacing: 8,
                                        runSpacing: 8,
                                        children: [
                                          AirmiusButton(label: 'Bild', icon: Icons.image_outlined, onPressed: _savingEdit ? null : pickEditImage, secondary: true),
                                          AirmiusButton(label: 'Video / Dateien', icon: Icons.video_library_outlined, onPressed: _savingEdit ? null : pickEditAttachments, secondary: true),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                                            decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
                                            child: const Text('Neue Medien werden wie im Web an den Beitrag angehaengt.', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w800)),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 12),
                                      Row(
                                        children: [
                                          Expanded(child: AirmiusButton(label: 'Abbrechen', icon: Icons.close_outlined, onPressed: _savingEdit ? null : () => Navigator.pop(dialogContext), secondary: true)),
                                          const SizedBox(width: 10),
                                          Expanded(child: AirmiusButton(label: _savingEdit ? 'Speichere...' : 'Speichern', icon: Icons.save_outlined, onPressed: _savingEdit ? null : save)),
                                        ],
                                      ),
                                    ],
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),
            );
          },
        );
      },
    );

    contentController.dispose();
    if (!mounted) return;
    setState(() => _savingEdit = false);
    if (updated != null) {
      widget.onPostChanged(updated);
      widget.onChanged();
    }
  }

  Future<void> _reportPost() async {
    final report = await showContentReportDialog(context, title: 'Beitrag melden');
    if (report == null || !mounted) return;
    try {
      await AirmiusServicesScope.of(context).repositories.feed.reportContent(
            type: 'post',
            id: widget.post.id,
            reason: report.reason,
            details: report.details,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Danke. Die Meldung wurde an die Moderation gesendet.')));
      widget.onChanged();
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('feed.error'))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final post = widget.post;
    final meta = [
      post.clubName,
      post.teamName,
      _timeLabel(scope, post.createdAt),
    ].whereType<String>().where((value) => value.trim().isNotEmpty).join(' - ');

    return AirmiusPanel(
      onTap: () => _openPostDetail(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AirmiusAvatar(post.authorName, imageUrl: post.authorAvatarUrl),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(post.authorName, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                        StatusPill(_visibilityLabel(scope, post.visibility), color: post.visibility == 'team' ? AirmiusColors.green : post.visibility == 'organization' ? AirmiusColors.amber : AirmiusColors.blue),
                      ],
                    ),
                    if (meta.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(meta, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
                    ],
                  ],
                ),
              ),
              PopupMenuButton<String>(
                icon: const Icon(Icons.more_horiz, color: AirmiusColors.muted),
                color: AirmiusColors.card,
                onSelected: (value) {
                  if (value == 'edit') {
                    _editPost();
                  }
                  if (value == 'report') {
                    _reportPost();
                  }
                  if (value == 'delete') {
                    _deletePost();
                  }
                },
                itemBuilder: (context) {
                  final currentUserId = AirmiusServicesScope.of(context).authState.user?.id;
                  final isOwnPost = currentUserId != null && currentUserId > 0 && post.userId == currentUserId;
                  return [
                    if (post.canUpdate || isOwnPost) const PopupMenuItem(value: 'edit', child: Text('Beitrag bearbeiten')),
                    if (!isOwnPost) const PopupMenuItem(value: 'report', child: Text('Beitrag melden')),
                    if (post.canDelete || isOwnPost) const PopupMenuItem(value: 'delete', child: Text('Beitrag loeschen')),
                  ];
                },
              ),
            ],
          ),
          const SizedBox(height: 12),
          _PostMetaBadges(post: post),
          if (post.content.trim().isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(post.content, style: const TextStyle(color: AirmiusColors.text, height: 1.45, fontSize: 15, fontWeight: FontWeight.w700)),
          ],
          if (post.imageUrl != null || post.attachments.isNotEmpty) ...[
            const SizedBox(height: 12),
            _PostMediaGallery(post: post),
          ],
          const SizedBox(height: 14),
          _PostEngagementBar(post: post, onPostChanged: widget.onPostChanged, onOpenComments: _toggleComments),
          if (_commentsOpen) ...[
            const SizedBox(height: 10),
            _InlineComments(
              post: post,
              commentsFuture: _commentsFuture ??= _loadComments(),
              localComments: _localComments,
              controller: _commentController,
              sending: _sendingComment,
              showAll: _commentsPerPage > 20,
              onSend: _sendComment,
              onReload: () {
                setState(() => _commentsFuture = _loadComments());
                widget.onChanged();
              },
              onShowAll: _showAllCommentsInline,
            ),
          ],
        ],
      ),
    );
  }

  Future<void> _openPostDetail(BuildContext context) async {
    final changed = await Navigator.push<bool>(context, MaterialPageRoute(builder: (_) => FeedPostDetailScreen(post: widget.post)));
    if (changed == true) widget.onChanged();
  }

  String _visibilityLabel(AirmiusScope scope, String visibility) {
    return switch (visibility) {
      'team' => 'Team',
      'organization' => 'Verein',
      _ => scope.t('feed.public'),
    };
  }

  String _timeLabel(AirmiusScope scope, DateTime date) {
    if (date.millisecondsSinceEpoch == 0) return '';
    final diff = DateTime.now().difference(date);
    if (diff.inMinutes < 1) return scope.t('feed.now');
    if (diff.inHours < 1) return '${diff.inMinutes} min';
    if (diff.inDays < 1) return '${diff.inHours} h';
    if (diff.inDays < 7) return '${diff.inDays} d';
    return '${date.day}.${date.month}.${date.year}';
  }
}

class _PostMetaBadges extends StatelessWidget {
  const _PostMetaBadges({required this.post});

  final AirmiusPost post;

  @override
  Widget build(BuildContext context) {
    final badges = <_PostBadgeData>[
      _PostBadgeData(_postTypeLabel(post.postType), AirmiusColors.mutedSoft),
      _PostBadgeData(_contentOriginLabel(post.contentOrigin), post.contentOrigin == 'ai' ? AirmiusColors.blue : AirmiusColors.mutedSoft),
      if (post.moderationStatus != 'approved') const _PostBadgeData('In Prüfung', AirmiusColors.amber),
      if (post.sportName != null) _PostBadgeData(post.sportName!, AirmiusColors.green),
      for (final skill in post.sportSkills) _PostBadgeData(skill, AirmiusColors.mutedSoft),
    ];
    return Wrap(
      spacing: 7,
      runSpacing: 7,
      children: [
        for (final badge in badges)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: badge.color.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: badge.color.withValues(alpha: 0.36)),
            ),
            child: Text(badge.label, style: TextStyle(color: badge.color, fontSize: 11, fontWeight: FontWeight.w900)),
          ),
      ],
    );
  }

  static String _postTypeLabel(String type) {
    return switch (type) {
      'question' => 'Frage',
      'knowledge' => 'Wissen',
      'training_drill' => 'Trainingsuebung',
      'tactic' => 'Taktik',
      'analysis' => 'Analyse',
      'experience' => 'Erfahrung',
      'club_update' => 'Vereinsinfo',
      _ => 'Normal',
    };
  }

  static String _contentOriginLabel(String origin) {
    return switch (origin) {
      'ai' => 'Mit KI erstellt',
      _ => 'Von mir selbst erstellt',
    };
  }
}

class _PostBadgeData {
  const _PostBadgeData(this.label, this.color);

  final String label;
  final Color color;
}

class _InlineComments extends StatelessWidget {
  const _InlineComments({
    required this.post,
    required this.commentsFuture,
    required this.localComments,
    required this.controller,
    required this.sending,
    required this.showAll,
    required this.onSend,
    required this.onReload,
    required this.onShowAll,
  });

  final AirmiusPost post;
  final Future<AirmiusPage<AirmiusComment>> commentsFuture;
  final List<AirmiusComment> localComments;
  final TextEditingController controller;
  final bool sending;
  final bool showAll;
  final VoidCallback onSend;
  final VoidCallback onReload;
  final VoidCallback onShowAll;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.cardSoft,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          FutureBuilder<AirmiusPage<AirmiusComment>>(
            future: commentsFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting && localComments.isEmpty) {
                return Text(scope.t('status.loading'), style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800));
              }
              if (snapshot.hasError && localComments.isEmpty) {
                return Row(
                  children: [
                    Expanded(child: Text(scope.t('feed.commentsError'), style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))),
                    TextButton(onPressed: onReload, child: Text(scope.t('feed.retry'))),
                  ],
                );
              }

              final loadedComments = snapshot.data?.items ?? const <AirmiusComment>[];
              final localCommentIds = localComments.map((comment) => comment.id).toSet();
              final comments = [
                ...localComments,
                ...loadedComments.where((comment) => !localCommentIds.contains(comment.id)),
              ];
              if (comments.isEmpty) {
                return Text(scope.t('feed.noComments'), style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700));
              }

              final visibleComments = showAll ? comments : comments.take(4).toList();
              return Column(
                children: [
                  for (final comment in visibleComments) ...[
                    _InlineCommentBubble(comment: comment, onChanged: onReload),
                    const SizedBox(height: 10),
                  ],
                  if (post.commentsCount > visibleComments.length)
                    Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
                      decoration: BoxDecoration(
                        color: AirmiusColors.input,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: AirmiusColors.border),
                      ),
                      child: Row(
                        children: [
                          Expanded(child: Text('Es werden ${visibleComments.length} von ${post.commentsCount} Kommentaren angezeigt.', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700))),
                          TextButton.icon(
                            onPressed: onShowAll,
                            icon: const Icon(Icons.forum_outlined, size: 16),
                            label: const Text('Alle anzeigen'),
                          ),
                        ],
                      ),
                    ),
                ],
              );
            },
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: controller,
                  minLines: 1,
                  maxLines: 3,
                  style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w700),
                  decoration: InputDecoration(
                    hintText: scope.t('feed.commentPlaceholder'),
                    filled: true,
                    fillColor: AirmiusColors.input,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
                    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
                    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.blue)),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              IconButton.filled(
                onPressed: sending ? null : onSend,
                icon: sending
                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.send_outlined),
                style: IconButton.styleFrom(backgroundColor: AirmiusColors.blue, foregroundColor: Colors.white),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InlineCommentBubble extends StatelessWidget {
  const _InlineCommentBubble({required this.comment, required this.onChanged});

  final AirmiusComment comment;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        UserBubble(label: comment.authorName, imageUrl: comment.authorAvatarUrl, small: true),
        const SizedBox(width: 8),
        Expanded(
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 9),
            decoration: BoxDecoration(
              color: AirmiusColors.input,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AirmiusColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(comment.authorName, style: const TextStyle(color: AirmiusColors.text, fontSize: 12, fontWeight: FontWeight.w900))),
                    PopupMenuButton<String>(
                      padding: EdgeInsets.zero,
                      icon: const Icon(Icons.more_horiz, color: AirmiusColors.muted, size: 18),
                      color: AirmiusColors.card,
                      onSelected: (value) {
                        if (value == 'edit') _edit(context);
                        if (value == 'delete') _delete(context);
                        if (value == 'report') _report(context);
                      },
                      itemBuilder: (context) {
                        final scope = AirmiusScope.of(context);
                        return [
                          if (comment.mine) PopupMenuItem(value: 'edit', child: Text(scope.t('feed.commentEdit'))),
                          if (comment.canDelete) PopupMenuItem(value: 'delete', child: Text(scope.t('feed.commentDelete'))),
                          if (!comment.mine) const PopupMenuItem(value: 'report', child: Text('Kommentar melden')),
                        ];
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 3),
                Text(comment.content, style: const TextStyle(color: AirmiusColors.text, fontSize: 13, height: 1.35, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _edit(BuildContext context) async {
    final scope = AirmiusScope.of(context);
    final controller = TextEditingController(text: comment.content);
    final next = await showDialog<String>(
      context: context,
      useSafeArea: false,
      builder: (dialogContext) => Dialog.fullscreen(
        backgroundColor: Colors.transparent,
        child: Container(
          color: Colors.black.withValues(alpha: 0.62),
          child: SafeArea(
            child: Align(
              alignment: Alignment.bottomCenter,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 672),
                child: Container(
                  margin: const EdgeInsets.all(12),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: AirmiusColors.card,
                    borderRadius: BorderRadius.circular(26),
                    border: Border.all(color: AirmiusColors.border),
                    boxShadow: const [BoxShadow(color: Colors.black45, blurRadius: 28, offset: Offset(0, 18))],
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(child: Text(scope.t('feed.commentEdit'), style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900))),
                          IconButton(onPressed: () => Navigator.pop(dialogContext), icon: const Icon(Icons.close, color: AirmiusColors.muted)),
                        ],
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: controller,
                        maxLines: 5,
                        autofocus: true,
                        style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w700),
                        decoration: InputDecoration(
                          filled: true,
                          fillColor: AirmiusColors.input,
                          hintText: scope.t('feed.commentPlaceholder'),
                          hintStyle: const TextStyle(color: AirmiusColors.muted),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AirmiusColors.border)),
                          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AirmiusColors.border)),
                          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AirmiusColors.blue)),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(child: AirmiusButton(label: 'Abbrechen', icon: Icons.close_outlined, secondary: true, onPressed: () => Navigator.pop(dialogContext))),
                          const SizedBox(width: 10),
                          Expanded(child: AirmiusButton(label: scope.t('status.ready'), icon: Icons.check_outlined, onPressed: () => Navigator.pop(dialogContext, controller.text.trim()))),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
    controller.dispose();
    if (next == null || next.isEmpty || !context.mounted) return;
    try {
      await AirmiusServicesScope.of(context).repositories.feed.updateComment(comment.id, next);
      onChanged();
    } catch (_) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(scope.t('feed.error'))));
    }
  }

  Future<void> _delete(BuildContext context) async {
    final scope = AirmiusScope.of(context);
    final ok = await confirmDanger(context, scope.t('feed.commentDelete'), scope.t('feed.commentDelete'));
    if (!ok || !context.mounted) return;
    try {
      await AirmiusServicesScope.of(context).repositories.feed.deleteComment(comment.id);
      onChanged();
    } catch (_) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(scope.t('feed.error'))));
    }
  }

  Future<void> _report(BuildContext context) async {
    final report = await showContentReportDialog(context, title: 'Kommentar melden');
    if (report == null || !context.mounted) return;
    try {
      await AirmiusServicesScope.of(context).repositories.feed.reportContent(
            type: 'comment',
            id: comment.id,
            reason: report.reason,
            details: report.details,
          );
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Danke. Die Meldung wurde an die Moderation gesendet.')));
    } catch (_) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('feed.error'))));
    }
  }
}

class _PostMediaGallery extends StatelessWidget {
  const _PostMediaGallery({required this.post});

  final AirmiusPost post;

  @override
  Widget build(BuildContext context) {
    final media = <_PostMediaItem>[
      for (final attachment in post.attachments.where((attachment) => attachment.url != post.imageUrl && (attachment.isImage || attachment.isVideo)))
        attachment.isImage ? _PostMediaItem.image(attachment.url, attachment.name) : _PostMediaItem.video(attachment.url, attachment.name, attachment.thumbnailUrl),
      if (post.imageUrl != null && !post.attachments.any((attachment) => attachment.isImage && attachment.url == post.imageUrl)) _PostMediaItem.image(post.imageUrl!, 'Beitragsbild', urls: post.imageUrls),
    ];
    final files = post.attachments.where((attachment) => !attachment.isImage && !attachment.isVideo).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (media.isNotEmpty)
          LayoutBuilder(
            builder: (context, constraints) {
              if (media.length == 1) {
                return _MediaTile(item: media.first, large: true);
              }
              final width = (constraints.maxWidth - 8) / 2;
              return Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final item in media.take(4))
                    SizedBox(
                      width: width,
                      child: _MediaTile(item: item, hiddenCount: item == media.take(4).last && media.length > 4 ? media.length - 4 : 0),
                    ),
                ],
              );
            },
          ),
        if (files.isNotEmpty) ...[
          if (media.isNotEmpty) const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final attachment in files) _FileChip(attachment: attachment),
            ],
          ),
        ],
      ],
    );
  }
}

class _PostMediaItem {
  const _PostMediaItem._({required this.url, required this.name, required this.isVideo, this.thumbnailUrl, this.urls = const []});

  factory _PostMediaItem.image(String url, String name, {List<String> urls = const []}) => _PostMediaItem._(url: url, name: name, isVideo: false, urls: urls);
  factory _PostMediaItem.video(String url, String name, String? thumbnailUrl) => _PostMediaItem._(url: url, name: name, isVideo: true, thumbnailUrl: thumbnailUrl);

  final String url;
  final List<String> urls;
  final String name;
  final bool isVideo;
  final String? thumbnailUrl;
}

class _MediaTile extends StatelessWidget {
  const _MediaTile({required this.item, this.large = false, this.hiddenCount = 0});

  final _PostMediaItem item;
  final bool large;
  final int hiddenCount;

  @override
  Widget build(BuildContext context) {
    final height = large ? 260.0 : 150.0;
    return InkWell(
      onTap: item.isVideo ? null : () => _openMedia(context),
      borderRadius: BorderRadius.circular(16),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: SizedBox(
          height: height,
          child: Stack(
            fit: StackFit.expand,
            children: [
              if (!item.isVideo)
                AirmiusMediaImage(url: item.url, fallbackUrls: item.urls, borderRadius: 0, height: height)
              else
                AirmiusInlineVideo(url: item.url, thumbnailUrl: item.thumbnailUrl, height: height, borderRadius: 0, title: item.name),
              if (hiddenCount > 0)
                Container(color: Colors.black.withValues(alpha: 0.54)),
              if (item.isVideo)
                const SizedBox.shrink(),
              if (hiddenCount > 0)
                Center(
                  child: Text('+$hiddenCount', style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
                ),
              Positioned(
                left: 10,
                right: 10,
                bottom: 10,
                child: Text(item.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w900, shadows: [Shadow(color: Colors.black, blurRadius: 8)])),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _openMedia(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (_) => Dialog.fullscreen(
        backgroundColor: Colors.black,
        child: SafeArea(
          child: Stack(
            children: [
              Center(
                child: item.isVideo
                    ? Padding(
                        padding: const EdgeInsets.all(16),
                        child: AirmiusInlineVideo(url: item.url, thumbnailUrl: item.thumbnailUrl, title: item.name, borderRadius: 18),
                      )
                    : AirmiusMediaImage(url: item.url, borderRadius: 0),
              ),
              Positioned(
                top: 12,
                right: 12,
                child: IconButton.filled(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FileChip extends StatelessWidget {
  const _FileChip({required this.attachment});

  final AirmiusPostAttachment attachment;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 220),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: AirmiusColors.cardSoft,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.attach_file, color: AirmiusColors.blue, size: 18),
          const SizedBox(width: 7),
          Flexible(child: Text(attachment.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 12, fontWeight: FontWeight.w900))),
        ],
      ),
    );
  }
}

class _PostEngagementBar extends StatefulWidget {
  const _PostEngagementBar({required this.post, required this.onPostChanged, required this.onOpenComments});

  final AirmiusPost post;
  final ValueChanged<AirmiusPost> onPostChanged;
  final VoidCallback onOpenComments;

  @override
  State<_PostEngagementBar> createState() => _PostEngagementBarState();
}

class _PostEngagementBarState extends State<_PostEngagementBar> {
  bool _reacting = false;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 2, vertical: 8),
          decoration: const BoxDecoration(
            border: Border(
              top: BorderSide(color: AirmiusColors.border),
              bottom: BorderSide(color: AirmiusColors.border),
            ),
          ),
          child: Row(
            children: [
              Expanded(child: Text('${widget.post.likesCount} ${scope.t('feed.likes')}', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w800))),
              Text('${widget.post.helpfulsCount} ${scope.t('feed.helpful')} - ${widget.post.commentsCount} ${scope.t('feed.comments')}', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w800)),
            ],
          ),
        ),
        Row(
          children: [
            Expanded(
              child: _EngagementButton(
                label: 'Like',
                icon: widget.post.likedByMe ? Icons.favorite : Icons.favorite_border_outlined,
                color: widget.post.likedByMe ? AirmiusColors.red : AirmiusColors.text,
                onTap: _reacting ? null : () => _toggle(like: true),
              ),
            ),
            Expanded(
              child: _EngagementButton(
                label: 'Hilfreich',
                icon: widget.post.helpfulByMe ? Icons.check_circle : Icons.check_circle_outline,
                color: widget.post.helpfulByMe ? AirmiusColors.green : AirmiusColors.text,
                onTap: _reacting ? null : () => _toggle(like: false),
              ),
            ),
            Expanded(
              child: _EngagementButton(
                label: 'Kommentar',
                icon: Icons.mode_comment_outlined,
                color: AirmiusColors.text,
                onTap: widget.onOpenComments,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Future<void> _toggle({required bool like}) async {
    if (_reacting) return;
    final previousPost = widget.post;
    final optimisticPost = like
        ? widget.post.copyWith(
            likedByMe: !widget.post.likedByMe,
            likesCount: widget.post.likesCount + (widget.post.likedByMe ? -1 : 1),
          )
        : widget.post.copyWith(
            helpfulByMe: !widget.post.helpfulByMe,
            helpfulsCount: widget.post.helpfulsCount + (widget.post.helpfulByMe ? -1 : 1),
          );
    setState(() => _reacting = true);
    widget.onPostChanged(optimisticPost);
    try {
      final nextPost = like
          ? await AirmiusServicesScope.of(context).repositories.feed.toggleLike(widget.post.id)
          : await AirmiusServicesScope.of(context).repositories.feed.toggleHelpful(widget.post.id);
      if (!mounted) return;
      widget.onPostChanged(nextPost);
      setState(() => _reacting = false);
    } catch (_) {
      if (!mounted) return;
      widget.onPostChanged(previousPost);
      setState(() => _reacting = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('feed.error'))));
    }
  }
}

class _EngagementButton extends StatelessWidget {
  const _EngagementButton({required this.label, required this.icon, required this.color, required this.onTap});

  final String label;
  final IconData icon;
  final Color color;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(12),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(height: 4),
            Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
          ],
        ),
      ),
    );
  }
}

class _LoadingFeed extends StatelessWidget {
  const _LoadingFeed();

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 20),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: AirmiusColors.blue)),
            const SizedBox(width: 12),
            Text(scope.t('status.loading'), style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

class _ErrorFeed extends StatelessWidget {
  const _ErrorFeed({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.error_outline, color: AirmiusColors.red, size: 34),
          const SizedBox(height: 10),
          Text(scope.t('feed.error'), textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          AirmiusButton(label: scope.t('feed.retry'), icon: Icons.refresh_outlined, onPressed: onRetry, secondary: true),
        ],
      ),
    );
  }
}
