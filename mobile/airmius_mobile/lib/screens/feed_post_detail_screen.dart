import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_post_upload_service.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_inline_video.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/content_report_dialog.dart';

class FeedPostDetailScreen extends StatefulWidget {
  const FeedPostDetailScreen({super.key, required this.post});

  final AirmiusPost post;

  @override
  State<FeedPostDetailScreen> createState() => _FeedPostDetailScreenState();
}

class _FeedPostDetailScreenState extends State<FeedPostDetailScreen> {
  final TextEditingController _commentController = TextEditingController();
  final FocusNode _commentFocusNode = FocusNode();
  late Future<AirmiusPage<AirmiusComment>> _commentsFuture;
  late AirmiusPost _post;
  bool _sending = false;
  bool _reacting = false;
  bool _deleting = false;
  bool _savingEdit = false;
  bool _dirty = false;

  @override
  void initState() {
    super.initState();
    _post = widget.post;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _commentsFuture = _loadComments();
  }

  @override
  void dispose() {
    _commentController.dispose();
    _commentFocusNode.dispose();
    super.dispose();
  }

  Future<AirmiusPage<AirmiusComment>> _loadComments() {
    return AirmiusServicesScope.of(
      context,
    ).repositories.feed.comments(widget.post.id);
  }

  void _reload() {
    setState(() {
      _dirty = true;
      _commentsFuture = _loadComments();
    });
  }

  Future<void> _sendComment() async {
    final content = _commentController.text.trim();
    if (content.isEmpty || _sending) return;

    setState(() => _sending = true);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.feed.createComment(widget.post.id, content);
      if (!mounted) return;
      _commentController.clear();
      setState(() {
        _dirty = true;
        _sending = false;
        _commentsFuture = _loadComments();
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _sending = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('feed.commentsError')),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final meta = [
      _post.clubName,
      _post.teamName,
    ].whereType<String>().where((value) => value.isNotEmpty).join(' - ');

    return PopScope<bool>(
      canPop: false,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        Navigator.pop(context, _dirty);
      },
      child: Scaffold(
        appBar: AppBar(
          backgroundColor:
              Theme.of(context).appBarTheme.backgroundColor ??
              airmiusSurfaceColor(context),
          surfaceTintColor: Colors.transparent,
          title: Text(
            scope.t('posts'),
            style: TextStyle(fontWeight: FontWeight.w900),
          ),
          leading: IconButton(
            tooltip: scope.t('common.back'),
            icon: Icon(Icons.arrow_back),
            onPressed: () => Navigator.pop(context, _dirty),
          ),
          actions: [
            if (_post.canUpdate)
              IconButton(
                tooltip: scope.t('feed.editPost'),
                icon: Icon(Icons.edit_outlined),
                onPressed: _savingEdit ? null : _editPost,
              ),
            if (!_post.canDelete)
              IconButton(
                tooltip: scope.t('feed.postReport'),
                icon: Icon(Icons.flag_outlined),
                onPressed: _reportPost,
              ),
            if (_post.canDelete)
              IconButton(
                tooltip: scope.t('feed.postDelete'),
                icon: Icon(Icons.delete_outline),
                onPressed: _deleting ? null : _deletePost,
              ),
          ],
        ),
        body: PageFrame(
          title: scope.t('posts'),
          subtitle: _post.authorName,
          showHeader: true,
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          AirmiusAvatar(
                            _post.authorName,
                            imageUrl: _post.authorAvatarUrl,
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _post.authorName,
                                  style: TextStyle(
                                    color: airmiusTextColor(context),
                                    fontWeight: FontWeight.w900,
                                    fontSize: 18,
                                  ),
                                ),
                                if (meta.isNotEmpty)
                                  Text(
                                    meta,
                                    style: TextStyle(
                                      color: airmiusMutedColor(context),
                                      fontSize: 12,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                          StatusPill(_post.visibility),
                        ],
                      ),
                      const SizedBox(height: 14),
                      _DetailPostMetaBadges(post: _post),
                      if (_post.content.trim().isNotEmpty) ...[
                        const SizedBox(height: 12),
                        Text(
                          _post.content,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            height: 1.45,
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                      if (_post.imageUrl != null ||
                          _post.attachments.isNotEmpty) ...[
                        const SizedBox(height: 14),
                        _DetailMediaGallery(post: _post),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: MetricCard(
                        value: '${_post.likesCount}',
                        label: scope.t('feed.likes'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value: '${_post.commentsCount}',
                        label: scope.t('feed.comments'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value: '${_post.helpfulsCount}',
                        label: scope.t('feed.helpful'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(
                        label: _post.likedByMe
                            ? '${scope.t('feed.likes')} ✓'
                            : scope.t('feed.likes'),
                        icon: _post.likedByMe
                            ? Icons.favorite
                            : Icons.favorite_border_outlined,
                        secondary: !_post.likedByMe,
                        onPressed: _reacting
                            ? null
                            : () => _toggleReaction(like: true),
                      ),
                      AirmiusButton(
                        label: _post.helpfulByMe
                            ? '${scope.t('feed.helpful')} ✓'
                            : scope.t('feed.helpful'),
                        icon: Icons.volunteer_activism_outlined,
                        secondary: !_post.helpfulByMe,
                        onPressed: _reacting
                            ? null
                            : () => _toggleReaction(like: false),
                      ),
                      AirmiusButton(
                        label: scope.t('feed.comments'),
                        icon: Icons.mode_comment_outlined,
                        secondary: true,
                        onPressed: () => _commentFocusNode.requestFocus(),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                FutureBuilder<AirmiusPage<AirmiusComment>>(
                  future: _commentsFuture,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return AirmiusPanel(
                        child: Text(
                          scope.t('status.loading'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      );
                    }
                    if (snapshot.hasError) {
                      return AirmiusPanel(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text(
                              scope.t('feed.commentsError'),
                              style: TextStyle(
                                color: airmiusTextColor(context),
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 12),
                            AirmiusButton(
                              label: scope.t('status.retry'),
                              icon: Icons.refresh_outlined,
                              onPressed: _reload,
                              secondary: true,
                            ),
                          ],
                        ),
                      );
                    }

                    final comments =
                        snapshot.data?.items ?? const <AirmiusComment>[];
                    return AirmiusPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          SectionLabel(scope.t('feed.comments')),
                          const SizedBox(height: 12),
                          if (comments.isEmpty)
                            Text(
                              scope.t('feed.noComments'),
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontWeight: FontWeight.w700,
                              ),
                            )
                          else
                            for (final comment in comments) ...[
                              _Comment(comment: comment, onChanged: _reload),
                              const SizedBox(height: 10),
                            ],
                          const SizedBox(height: 4),
                          AirmiusTextField(
                            label: scope.t('feed.commentPlaceholder'),
                            icon: Icons.mode_comment_outlined,
                            maxLines: 2,
                            controller: _commentController,
                            focusNode: _commentFocusNode,
                          ),
                          const SizedBox(height: 12),
                          AirmiusButton(
                            label: _sending
                                ? scope.t('status.loading')
                                : scope.t('feed.commentSend'),
                            icon: Icons.send_outlined,
                            onPressed: _sending ? null : _sendComment,
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _toggleReaction({required bool like}) async {
    if (_reacting) return;
    final previousPost = _post;
    final optimisticPost = like
        ? _post.copyWith(
            likedByMe: !_post.likedByMe,
            likesCount: _post.likesCount + (_post.likedByMe ? -1 : 1),
          )
        : _post.copyWith(
            helpfulByMe: !_post.helpfulByMe,
            helpfulsCount: _post.helpfulsCount + (_post.helpfulByMe ? -1 : 1),
          );
    setState(() {
      _post = optimisticPost;
      _dirty = true;
      _reacting = true;
    });
    try {
      final nextPost = like
          ? await AirmiusServicesScope.of(
              context,
            ).repositories.feed.toggleLike(previousPost.id)
          : await AirmiusServicesScope.of(
              context,
            ).repositories.feed.toggleHelpful(previousPost.id);
      if (!mounted) return;
      setState(() {
        _post = nextPost;
        _dirty = true;
        _reacting = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _post = previousPost;
        _reacting = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('feed.error'))),
      );
    }
  }

  Future<void> _deletePost() async {
    final scope = AirmiusScope.of(context);
    final ok = await confirmDanger(
      context,
      scope.t('feed.postDelete'),
      scope.t('feed.postDeleteConfirm'),
    );
    if (!ok || !mounted) return;

    setState(() => _deleting = true);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.feed.deletePost(_post.id);
      if (!mounted) return;
      Navigator.pop(context, true);
    } catch (_) {
      if (!mounted) return;
      setState(() => _deleting = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(scope.t('feed.error'))));
    }
  }

  Future<void> _editPost() async {
    final scope = AirmiusScope.of(context);
    final contentController = TextEditingController(text: _post.content);
    var visibility = _post.visibility;
    var postType = _post.postType;
    var contentOrigin = _post.contentOrigin;
    PlatformFile? imageFile;
    List<PlatformFile> attachments = const [];

    final updated = await showDialog<AirmiusPost>(
      context: context,
      useSafeArea: false,
      builder: (dialogContext) {
        return StatefulBuilder(
          builder: (dialogContext, setDialogState) {
            Future<void> pickImage() async {
              final picked = await FilePicker.platform.pickFiles(
                type: FileType.custom,
                allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'gif'],
                withData: true,
              );
              final file = picked?.files.single;
              if (file == null) return;
              setDialogState(() => imageFile = file);
            }

            Future<void> pickAttachments() async {
              final picked = await FilePicker.platform.pickFiles(
                type: FileType.custom,
                allowMultiple: true,
                allowedExtensions: [
                  'jpg',
                  'jpeg',
                  'png',
                  'webp',
                  'gif',
                  'mp4',
                  'mov',
                  'webm',
                  'ogg',
                  'pdf',
                  'doc',
                  'docx',
                  'xls',
                  'xlsx',
                  'txt',
                  'zip',
                ],
                withData: true,
              );
              if (picked == null) return;
              setDialogState(() => attachments = picked.files);
            }

            Future<void> save() async {
              final content = contentController.text.trim();
              final hasNewMedia = imageFile != null || attachments.isNotEmpty;
              if (_savingEdit || (content.isEmpty && !hasNewMedia)) return;
              if (visibility == 'organization' && _post.clubId == null) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(scope.t('feed.selectClub'))),
                );
                return;
              }
              if (visibility == 'team' && _post.teamId == null) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(scope.t('feed.selectTeam'))),
                );
                return;
              }

              setState(() => _savingEdit = true);
              setDialogState(() {});
              try {
                final services = AirmiusServicesScope.of(context);
                final client = services.clientForSession(
                  services.authState.session,
                );
                final nextPost = await AirmiusPostUploadService(client).update(
                  postId: _post.id,
                  content: content,
                  visibility: visibility,
                  postType: postType,
                  contentOrigin: contentOrigin,
                  clubId: _post.clubId,
                  teamId: _post.teamId,
                  sportId: _post.sportId,
                  sportSkillIds: _post.sportSkillIds,
                  image: imageFile,
                  attachments: attachments,
                );
                if (!mounted) return;
                setState(() => _savingEdit = false);
                if (dialogContext.mounted) {
                  Navigator.pop(dialogContext, nextPost);
                }
              } catch (_) {
                if (!mounted) return;
                setState(() => _savingEdit = false);
                setDialogState(() {});
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text(AirmiusScope.of(context).t('feed.error')),
                  ),
                );
              }
            }

            return Dialog.fullscreen(
              backgroundColor: Colors.black.withValues(alpha: 0.62),
              child: SafeArea(
                child: Align(
                  alignment: MediaQuery.sizeOf(context).width < 700
                      ? Alignment.bottomCenter
                      : Alignment.center,
                  child: ConstrainedBox(
                    constraints: BoxConstraints(
                      maxWidth: 672,
                      maxHeight: MediaQuery.sizeOf(context).height - 24,
                    ),
                    child: Container(
                      margin: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: airmiusSurfaceColor(context),
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(color: airmiusBorderColor(context)),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.34),
                            blurRadius: 30,
                            offset: const Offset(0, 18),
                          ),
                        ],
                      ),
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    scope.t('feed.editPost'),
                                    style: TextStyle(
                                      color: airmiusTextColor(context),
                                      fontSize: 18,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                                IconButton(
                                  tooltip: scope.t('common.close'),
                                  onPressed: _savingEdit
                                      ? null
                                      : () => Navigator.pop(dialogContext),
                                  icon: Icon(
                                    Icons.close,
                                    color: airmiusMutedColor(context),
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 10),
                            AirmiusTextField(
                              label: scope.t('feed.editPlaceholder'),
                              icon: Icons.edit_outlined,
                              maxLines: 4,
                              controller: contentController,
                            ),
                            const SizedBox(height: 12),
                            DropdownButtonFormField<String>(
                              initialValue: visibility,
                              decoration: InputDecoration(
                                labelText: scope.t('feed.audience'),
                              ),
                              dropdownColor: airmiusSurfaceColor(context),
                              items: [
                                DropdownMenuItem(
                                  value: 'public',
                                  child: Text(scope.t('feed.public')),
                                ),
                                DropdownMenuItem(
                                  value: 'organization',
                                  child: Text(scope.t('feed.club')),
                                ),
                                DropdownMenuItem(
                                  value: 'team',
                                  child: Text(scope.t('feed.team')),
                                ),
                              ],
                              onChanged: _savingEdit
                                  ? null
                                  : (value) => setDialogState(
                                      () => visibility = value ?? 'public',
                                    ),
                            ),
                            const SizedBox(height: 10),
                            DropdownButtonFormField<String>(
                              initialValue: postType,
                              decoration: InputDecoration(
                                labelText: scope.t('feed.postType'),
                              ),
                              dropdownColor: airmiusSurfaceColor(context),
                              items: [
                                DropdownMenuItem(
                                  value: 'normal',
                                  child: Text(scope.t('feed.normal')),
                                ),
                                DropdownMenuItem(
                                  value: 'question',
                                  child: Text(scope.t('feed.question')),
                                ),
                                DropdownMenuItem(
                                  value: 'knowledge',
                                  child: Text(scope.t('feed.knowledge')),
                                ),
                                DropdownMenuItem(
                                  value: 'training_drill',
                                  child: Text(scope.t('feed.trainingDrill')),
                                ),
                                DropdownMenuItem(
                                  value: 'tactic',
                                  child: Text(scope.t('feed.tactic')),
                                ),
                                DropdownMenuItem(
                                  value: 'analysis',
                                  child: Text(scope.t('feed.analysis')),
                                ),
                                DropdownMenuItem(
                                  value: 'experience',
                                  child: Text(scope.t('feed.experience')),
                                ),
                                DropdownMenuItem(
                                  value: 'club_update',
                                  child: Text(scope.t('feed.clubUpdate')),
                                ),
                              ],
                              onChanged: _savingEdit
                                  ? null
                                  : (value) => setDialogState(
                                      () => postType = value ?? 'normal',
                                    ),
                            ),
                            const SizedBox(height: 10),
                            DropdownButtonFormField<String>(
                              initialValue: contentOrigin,
                              decoration: InputDecoration(
                                labelText: scope.t('feed.source'),
                              ),
                              dropdownColor: airmiusSurfaceColor(context),
                              items: [
                                DropdownMenuItem(
                                  value: 'self',
                                  child: Text(scope.t('feed.self')),
                                ),
                                DropdownMenuItem(
                                  value: 'ai',
                                  child: Text(scope.t('feed.ai')),
                                ),
                              ],
                              onChanged: _savingEdit
                                  ? null
                                  : (value) => setDialogState(
                                      () => contentOrigin = value ?? 'self',
                                    ),
                            ),
                            const SizedBox(height: 12),
                            if (imageFile != null)
                              Text(
                                '${scope.t('feed.image')}: ${imageFile!.name}',
                                style: TextStyle(
                                  color: airmiusMutedColor(context),
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            if (attachments.isNotEmpty)
                              Text(
                                '${attachments.length} ${scope.t('feed.media')}',
                                style: TextStyle(
                                  color: airmiusMutedColor(context),
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            Wrap(
                              spacing: 8,
                              runSpacing: 8,
                              children: [
                                AirmiusButton(
                                  label: scope.t('feed.image'),
                                  icon: Icons.image_outlined,
                                  onPressed: _savingEdit ? null : pickImage,
                                  secondary: true,
                                ),
                                AirmiusButton(
                                  label: scope.t('feed.media'),
                                  icon: Icons.video_library_outlined,
                                  onPressed: _savingEdit
                                      ? null
                                      : pickAttachments,
                                  secondary: true,
                                ),
                              ],
                            ),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                Expanded(
                                  child: AirmiusButton(
                                    label: scope.t('common.cancel'),
                                    icon: Icons.close_outlined,
                                    onPressed: _savingEdit
                                        ? null
                                        : () => Navigator.pop(dialogContext),
                                    secondary: true,
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: AirmiusButton(
                                    label: _savingEdit
                                        ? scope.t('status.loading')
                                        : scope.t('common.save'),
                                    icon: Icons.save_outlined,
                                    onPressed: _savingEdit ? null : save,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
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
      setState(() {
        _post = updated;
        _dirty = true;
      });
    }
  }

  Future<void> _reportPost() async {
    final scope = AirmiusScope.of(context);
    final report = await showContentReportDialog(
      context,
      title: scope.t('feed.postReport'),
    );
    if (report == null || !mounted) return;
    try {
      await AirmiusServicesScope.of(context).repositories.feed.reportContent(
        type: 'post',
        id: _post.id,
        reason: report.reason,
        details: report.details,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(scope.t('feed.moderationThanks'))));
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('feed.error'))),
      );
    }
  }
}

class _DetailPostMetaBadges extends StatelessWidget {
  const _DetailPostMetaBadges({required this.post});

  final AirmiusPost post;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final badges = <_DetailPostBadgeData>[
      _DetailPostBadgeData(
        _postTypeLabel(post.postType, t),
        AirmiusColors.mutedSoft,
      ),
      _DetailPostBadgeData(
        _contentOriginLabel(post.contentOrigin, t),
        post.contentOrigin == 'ai'
            ? AirmiusColors.blue
            : AirmiusColors.mutedSoft,
      ),
      if (post.moderationStatus != 'approved')
        _DetailPostBadgeData(t('feed.moderationPending'), AirmiusColors.amber),
      if (post.sportName != null)
        _DetailPostBadgeData(post.sportName!, AirmiusColors.green),
      for (final skill in post.sportSkills)
        _DetailPostBadgeData(skill, AirmiusColors.mutedSoft),
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
            child: Text(
              badge.label,
              style: TextStyle(
                color: badge.color,
                fontSize: 11,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
      ],
    );
  }

  static String _postTypeLabel(String type, String Function(String) t) {
    return switch (type) {
      'question' => t('feed.question'),
      'knowledge' => t('feed.knowledge'),
      'training_drill' => t('feed.trainingDrill'),
      'tactic' => t('feed.tactic'),
      'analysis' => t('feed.analysis'),
      'experience' => t('feed.experience'),
      'club_update' => t('feed.clubUpdate'),
      _ => t('feed.normal'),
    };
  }

  static String _contentOriginLabel(String origin, String Function(String) t) {
    return switch (origin) {
      'ai' => t('feed.ai'),
      _ => t('feed.self'),
    };
  }
}

class _DetailPostBadgeData {
  const _DetailPostBadgeData(this.label, this.color);

  final String label;
  final Color color;
}

class _DetailMediaGallery extends StatelessWidget {
  const _DetailMediaGallery({required this.post});

  final AirmiusPost post;

  @override
  Widget build(BuildContext context) {
    final media = <AirmiusPostAttachment>[
      for (final attachment in post.attachments.where(
        (attachment) =>
            attachment.url != post.imageUrl &&
            (attachment.isImage || attachment.isVideo),
      ))
        attachment,
      if (post.imageUrl != null &&
          !post.attachments.any(
            (attachment) =>
                attachment.isImage && attachment.url == post.imageUrl,
          ))
        AirmiusPostAttachment(
          name: 'Beitragsbild',
          url: post.imageUrl!,
          kind: 'image',
        ),
    ];
    final files = post.attachments
        .where((attachment) => !attachment.isImage && !attachment.isVideo)
        .toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final attachment in media) ...[
          _DetailMediaTile(attachment: attachment),
          const SizedBox(height: 10),
        ],
        if (files.isNotEmpty)
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final attachment in files)
                _DetailFileChip(attachment: attachment),
            ],
          ),
      ],
    );
  }
}

class _DetailMediaTile extends StatelessWidget {
  const _DetailMediaTile({required this.attachment});

  final AirmiusPostAttachment attachment;

  @override
  Widget build(BuildContext context) {
    if (attachment.isVideo) {
      return AirmiusInlineVideo(
        url: attachment.url,
        thumbnailUrl: attachment.thumbnailUrl,
        height: 260,
        borderRadius: 18,
        title: attachment.name,
      );
    }
    final imageUrl = attachment.isVideo
        ? attachment.thumbnailUrl
        : attachment.url;
    return ClipRRect(
      borderRadius: BorderRadius.circular(18),
      child: SizedBox(
        height: 260,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (imageUrl != null)
              AirmiusMediaImage(url: imageUrl, borderRadius: 0, height: 260)
            else
              Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [
                      AirmiusColors.blue.withValues(alpha: 0.34),
                      airmiusSurfaceSoftColor(context),
                      AirmiusColors.pink.withValues(alpha: 0.22),
                    ],
                  ),
                ),
              ),
            if (attachment.isVideo)
              Container(color: Colors.black.withValues(alpha: 0.24)),
            if (attachment.isVideo)
              Center(
                child: Icon(
                  Icons.play_circle_fill,
                  color: Colors.white,
                  size: 62,
                ),
              ),
            Positioned(
              left: 12,
              right: 12,
              bottom: 12,
              child: Text(
                attachment.name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  shadows: [Shadow(color: Colors.black, blurRadius: 8)],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DetailFileChip extends StatelessWidget {
  const _DetailFileChip({required this.attachment});

  final AirmiusPostAttachment attachment;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 230),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 9),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.attach_file, color: AirmiusColors.blue, size: 18),
          const SizedBox(width: 7),
          Flexible(
            child: Text(
              attachment.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Comment extends StatelessWidget {
  const _Comment({required this.comment, required this.onChanged});

  final AirmiusComment comment;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AirmiusAvatar(comment.authorName, imageUrl: comment.authorAvatarUrl),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        comment.authorName,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill('${comment.likesCount}'),
                    const SizedBox(width: 4),
                    PopupMenuButton<String>(
                      icon: Icon(
                        Icons.more_horiz,
                        color: airmiusMutedColor(context),
                        size: 20,
                      ),
                      color: airmiusSurfaceColor(context),
                      onSelected: (value) {
                        if (value == 'edit') _edit(context);
                        if (value == 'delete') _delete(context);
                        if (value == 'report') _report(context);
                      },
                      itemBuilder: (context) {
                        final scope = AirmiusScope.of(context);
                        return [
                          if (comment.mine)
                            PopupMenuItem(
                              value: 'edit',
                              child: Text(scope.t('feed.commentEdit')),
                            ),
                          if (comment.canDelete)
                            PopupMenuItem(
                              value: 'delete',
                              child: Text(scope.t('feed.commentDelete')),
                            ),
                          if (!comment.mine)
                            PopupMenuItem(
                              value: 'report',
                              child: Text(scope.t('feed.commentReport')),
                            ),
                        ];
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  comment.content,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
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
                    color: airmiusSurfaceColor(context),
                    borderRadius: BorderRadius.circular(26),
                    border: Border.all(color: airmiusBorderColor(context)),
                    boxShadow: const [
                      BoxShadow(
                        color: Colors.black45,
                        blurRadius: 28,
                        offset: Offset(0, 18),
                      ),
                    ],
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              scope.t('feed.commentEdit'),
                              style: TextStyle(
                                color: airmiusTextColor(context),
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                          IconButton(
                            tooltip: scope.t('common.close'),
                            onPressed: () => Navigator.pop(dialogContext),
                            icon: Icon(
                              Icons.close,
                              color: airmiusMutedColor(context),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      TextField(
                        controller: controller,
                        maxLines: 5,
                        autofocus: true,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w700,
                        ),
                        decoration: InputDecoration(
                          filled: true,
                          fillColor: airmiusInputColor(context),
                          hintText: scope.t('feed.commentPlaceholder'),
                          hintStyle: TextStyle(
                            color: airmiusMutedColor(context),
                          ),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(16),
                            borderSide: BorderSide(
                              color: airmiusBorderColor(context),
                            ),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(16),
                            borderSide: BorderSide(
                              color: airmiusBorderColor(context),
                            ),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(16),
                            borderSide: BorderSide(color: AirmiusColors.blue),
                          ),
                        ),
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: AirmiusButton(
                              label: scope.t('common.cancel'),
                              icon: Icons.close_outlined,
                              secondary: true,
                              onPressed: () => Navigator.pop(dialogContext),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: AirmiusButton(
                              label: scope.t('status.ready'),
                              icon: Icons.check_outlined,
                              onPressed: () => Navigator.pop(
                                dialogContext,
                                controller.text.trim(),
                              ),
                            ),
                          ),
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
    await AirmiusServicesScope.of(
      context,
    ).repositories.feed.updateComment(comment.id, next);
    onChanged();
  }

  Future<void> _delete(BuildContext context) async {
    final ok = await confirmDanger(
      context,
      AirmiusScope.of(context).t('feed.commentDelete'),
      AirmiusScope.of(context).t('feed.commentDelete'),
    );
    if (!ok || !context.mounted) return;
    await AirmiusServicesScope.of(
      context,
    ).repositories.feed.deleteComment(comment.id);
    onChanged();
  }

  Future<void> _report(BuildContext context) async {
    final scope = AirmiusScope.of(context);
    final report = await showContentReportDialog(
      context,
      title: scope.t('feed.commentReport'),
    );
    if (report == null || !context.mounted) return;
    try {
      await AirmiusServicesScope.of(context).repositories.feed.reportContent(
        type: 'comment',
        id: comment.id,
        reason: report.reason,
        details: report.details,
      );
      if (!context.mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(scope.t('feed.moderationThanks'))));
      onChanged();
    } catch (_) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('feed.error'))),
      );
    }
  }
}
