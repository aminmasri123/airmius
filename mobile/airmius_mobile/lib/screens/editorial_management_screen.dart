import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:file_picker/file_picker.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../core/editorial_image_upload.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/editorial_rich_editor.dart';
import '../widgets/editorial_labels.dart';

class EditorialManagementScreen extends StatefulWidget {
  const EditorialManagementScreen({super.key});

  @override
  State<EditorialManagementScreen> createState() =>
      _EditorialManagementScreenState();
}

class _EditorialManagementScreenState extends State<EditorialManagementScreen> {
  Future<JsonMap>? _future;
  String _status = 'all';
  String _contentLocale = 'all';
  final _search = TextEditingController();
  int _page = 1;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.editorialPosts(
      status: _status,
      contentLocale: _contentLocale,
      page: _page,
      query: _search.text,
    );
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _reload() {
    setState(() {
      _future = _client.editorialPosts(
        status: _status,
        contentLocale: _contentLocale,
        page: _page,
        query: _search.text,
      );
    });
  }

  Future<void> _run(
    Future<AirmiusJson> Function() action,
    String success,
  ) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      _toast(success);
      _reload();
    } catch (error) {
      if (mounted) {
        _toast(
          '${t('editorial.actionError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _editPost(
    List<JsonMap> categories,
    JsonMap can, {
    JsonMap? post,
    String? translationLocale,
  }) async {
    final creating = post == null || translationLocale != null;
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _EditorialPostSheet(
        categories: categories,
        canPublish: can['publish'] == true,
        post: post,
        translationLocale: translationLocale,
        client: _client,
        onSave: (payload) => creating
            ? _client.createEditorialPost(payload)
            : _client.updateEditorialPost(_editorInt(post['id']), payload),
      ),
    );
    if (saved != true || !mounted) return;
    _toast(t(creating ? 'editorial.created' : 'editorial.updated'));
    _reload();
  }

  Future<void> _deletePost(JsonMap post) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('editorial.deleteTitle')),
        content: Text(t('editorial.deleteQuestion')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
              foregroundColor: Theme.of(context).colorScheme.onError,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(
      () => _client.deleteEditorialPost(_editorInt(post['id'])),
      t('editorial.deleted'),
    );
  }

  Future<void> _manageCategories(List<JsonMap> categories) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _CategoryManager(
        categories: categories,
        client: _client,
        onChanged: _reload,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('editorial.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('editorial.reload'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<JsonMap>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _EditorialError(onRetry: _reload);
          }
          final posts = _editorList(snapshot.data?['data']);
          final categories = _editorList(snapshot.data?['categories']);
          final can = _editorMap(snapshot.data?['can']);
          final meta = _editorMap(snapshot.data?['meta']);

          return PageFrame(
            title: t('editorial.title'),
            subtitle: t('editorial.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('editorial.workflow')),
                      const SizedBox(height: 8),
                      Text(
                        t('editorial.hero'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('editorial.qualityHint'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 14),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          if (can['create'] == true)
                            AirmiusButton(
                              label: t('editorial.newPost'),
                              icon: Icons.add_outlined,
                              onPressed: _busy
                                  ? null
                                  : () => _editPost(categories, can),
                            ),
                          if (can['manage_categories'] == true)
                            AirmiusButton(
                              label: t('editorial.categories'),
                              icon: Icons.category_outlined,
                              secondary: true,
                              onPressed: _busy
                                  ? null
                                  : () => _manageCategories(categories),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _search,
                  maxLength: 120,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) {
                    _page = 1;
                    _reload();
                  },
                  decoration: InputDecoration(
                    labelText: t('adminNative.search'),
                    suffixIcon: IconButton(
                      tooltip: t('adminNative.search'),
                      icon: const Icon(Icons.search),
                      onPressed: () {
                        _page = 1;
                        _reload();
                      },
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                DropdownButtonFormField<String>(
                  initialValue: _contentLocale,
                  decoration: InputDecoration(
                    labelText: editorialLabel(context, 'language'),
                  ),
                  items: ['all', 'de', 'en', 'fr', 'ar']
                      .map(
                        (locale) => DropdownMenuItem(
                          value: locale,
                          child: Text(
                            locale == 'all'
                                ? t('editorial.status.all')
                                : locale.toUpperCase(),
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) {
                    _contentLocale = value ?? 'all';
                    _page = 1;
                    _reload();
                  },
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children:
                        ['all', 'draft', 'review', 'published', 'archived'].map(
                          (status) {
                            final selected = _status == status;
                            return ChoiceChip(
                              selected: selected,
                              label: Text(t('editorial.status.$status')),
                              onSelected: (_) {
                                _status = status;
                                _page = 1;
                                _reload();
                              },
                              selectedColor: airmiusAccentColor(
                                context,
                              ).withValues(alpha: 0.22),
                              backgroundColor: airmiusSurfaceSoftColor(context),
                              side: BorderSide(
                                color: selected
                                    ? airmiusAccentColor(context)
                                    : airmiusBorderColor(context),
                              ),
                            );
                          },
                        ).toList(),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    IconButton(
                      tooltip: t('adminNative.previous'),
                      onPressed: _page > 1
                          ? () {
                              _page--;
                              _reload();
                            }
                          : null,
                      icon: const Icon(Icons.chevron_left),
                    ),
                    Text(
                      '${_editorInt(meta['current_page'])} / ${_editorInt(meta['last_page'])}',
                    ),
                    IconButton(
                      tooltip: t('adminNative.next'),
                      onPressed: _page < _editorInt(meta['last_page'])
                          ? () {
                              _page++;
                              _reload();
                            }
                          : null,
                      icon: const Icon(Icons.chevron_right),
                    ),
                  ],
                ),
                if (posts.isEmpty)
                  AirmiusPanel(
                    child: Text(
                      t('editorial.empty'),
                      textAlign: TextAlign.center,
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  )
                else
                  ...posts.map(
                    (post) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _EditorialPostCard(
                        post: post,
                        onTranslate: can['create'] == true
                            ? (locale) => _editPost(
                                categories,
                                can,
                                post: post,
                                translationLocale: locale,
                              )
                            : null,
                        canEdit: can['update'] == true,
                        canDelete: can['delete'] == true,
                        onEdit: () => _editPost(categories, can, post: post),
                        onDelete: () => _deletePost(post),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _EditorialPostCard extends StatelessWidget {
  const _EditorialPostCard({
    required this.post,
    required this.canEdit,
    required this.canDelete,
    required this.onEdit,
    required this.onDelete,
    this.onTranslate,
  });

  final JsonMap post;
  final bool canEdit;
  final bool canDelete;
  final VoidCallback onEdit;
  final VoidCallback onDelete;
  final ValueChanged<String>? onTranslate;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final status = _editorText(post['status'], fallback: 'draft');
    final score = _editorInt(post['seo_score']);
    final color = score >= 85
        ? AirmiusColors.green
        : score >= 60
        ? AirmiusColors.amber
        : AirmiusColors.red;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.article_outlined,
                color: airmiusAccentColor(context),
                size: 28,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _editorText(
                        post['title'],
                        fallback: t('editorial.untitled'),
                      ),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                        fontSize: 17,
                      ),
                    ),
                    if (_editorText(post['excerpt']).isNotEmpty) ...[
                      const SizedBox(height: 5),
                      Text(
                        _editorText(post['excerpt']),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.35,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              StatusPill(
                t('editorial.status.$status'),
                color: status == 'published'
                    ? AirmiusColors.green
                    : status == 'review'
                    ? AirmiusColors.amber
                    : airmiusAccentColor(context),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusPill(_editorText(post['content_locale']).toUpperCase()),
              if (onTranslate != null)
                PopupMenuButton<String>(
                  tooltip: editorialLabel(context, 'translation'),
                  icon: const Icon(Icons.translate),
                  onSelected: onTranslate,
                  itemBuilder: (_) => ['de', 'en', 'fr', 'ar']
                      .where(
                        (locale) =>
                            locale != post['content_locale'] &&
                            !_editorList(
                              post['translations'],
                            ).any((variant) => variant['locale'] == locale),
                      )
                      .map(
                        (locale) => PopupMenuItem(
                          value: locale,
                          child: Text(locale.toUpperCase()),
                        ),
                      )
                      .toList(),
                ),
              StatusPill(
                t('editorial.score').replaceFirst('{score}', '$score'),
                color: color,
              ),
              StatusPill(
                t('editorial.revisions').replaceFirst(
                  '{count}',
                  '${_editorInt(post['revisions_count'])}',
                ),
              ),
            ],
          ),
          if (canEdit || canDelete) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                if (canEdit)
                  AirmiusButton(
                    label: t('edit'),
                    icon: Icons.edit_outlined,
                    secondary: true,
                    onPressed: onEdit,
                  ),
                if (canDelete)
                  AirmiusButton(
                    label: t('delete'),
                    icon: Icons.delete_outline,
                    danger: true,
                    onPressed: onDelete,
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _EditorialPostSheet extends StatefulWidget {
  const _EditorialPostSheet({
    required this.categories,
    required this.canPublish,
    this.post,
    this.translationLocale,
    required this.client,
    required this.onSave,
  });

  final List<JsonMap> categories;
  final bool canPublish;
  final JsonMap? post;
  final String? translationLocale;
  final AirmiusApiClient client;
  final Future<AirmiusJson> Function(JsonMap) onSave;

  @override
  State<_EditorialPostSheet> createState() => _EditorialPostSheetState();
}

class _EditorialPostSheetState extends State<_EditorialPostSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _title;
  late final TextEditingController _excerpt;
  late final EditorialRichDocument _content;
  late final TextEditingController _cover;
  late final TextEditingController _tags;
  late final TextEditingController _metaTitle;
  late final TextEditingController _metaDescription;
  late String _status;
  int? _categoryId;
  late String _locale;
  bool _saving = false;
  bool _uploading = false;
  String? _error;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    final post = widget.post ?? const <String, dynamic>{};
    _title = TextEditingController(text: _editorText(post['title']));
    _excerpt = TextEditingController(text: _editorText(post['excerpt']));
    _content = EditorialRichDocument(
      _editorText(
        post['content_html'],
        fallback: _legacyHtml(_editorText(post['content_text'])),
      ),
    );
    _cover = TextEditingController(text: _editorText(post['cover_image']));
    _tags = TextEditingController(
      text: post['tags'] is List ? (post['tags'] as List).join(', ') : '',
    );
    _metaTitle = TextEditingController(text: _editorText(post['meta_title']));
    _metaDescription = TextEditingController(
      text: _editorText(post['meta_description']),
    );
    _status = widget.translationLocale != null
        ? 'draft'
        : _editorText(post['status'], fallback: 'draft');
    _locale =
        widget.translationLocale ??
        _editorText(post['content_locale'], fallback: widget.client.locale);
    _categoryId = _editorNullableInt(post['blog_category_id']);
  }

  @override
  void dispose() {
    for (final controller in [
      _title,
      _excerpt,
      _cover,
      _tags,
      _metaTitle,
      _metaDescription,
    ]) {
      controller.dispose();
    }
    _content.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_saving || _uploading) return;
    if (!_formKey.currentState!.validate()) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSave({
        'title': _title.text.trim(),
        'slug': widget.post == null || widget.translationLocale != null
            ? null
            : _editorText(widget.post?['slug']),
        'content_locale': _locale,
        if (widget.translationLocale != null)
          'translation_of_id': widget.post!['id'],
        'excerpt': _emptyNull(_excerpt.text),
        'content': _content.toHtml(),
        'content_format': 'html',
        'cover_image': _emptyNull(_cover.text),
        'blog_category_id': _categoryId,
        'tags': _tags.text
            .split(',')
            .map((tag) => tag.trim())
            .where((tag) => tag.isNotEmpty)
            .toSet()
            .toList(),
        'meta_title': _emptyNull(_metaTitle.text),
        'meta_description': _emptyNull(_metaDescription.text),
        'status': _status,
        'published_at': widget.translationLocale == null
            ? (widget.post?['published_at'])
            : null,
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : t('editorial.actionError'),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _upload(String kind) async {
    if (_uploading || _saving) return;
    setState(() {
      _uploading = true;
      _error = null;
    });
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
        withData: true,
      );
      if (result == null || !mounted) return;
      final file = result.files.single;
      if (file.bytes == null) {
        throw StateError(editorialLabel(context, 'uploadError'));
      }
      final response = await uploadEditorialImage(
        api: widget.client,
        bytes: file.bytes!,
        filename: file.name,
        kind: kind,
      );
      if (!mounted) return;
      final data = _editorMap(response['data']);
      setState(() {
        if (kind == 'cover') {
          _cover.text = _editorText(data['url']);
        } else {
          _content.insertHtmlBlock(_editorText(data['content_html']));
        }
      });
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : editorialLabel(context, 'uploadError'),
        );
      }
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  Future<void> _preview() async {
    if (_saving || _uploading) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final response = await widget.client.previewEditorialPost(
        _content.toHtml(),
      );
      if (!mounted) return;
      await Navigator.push(
        context,
        MaterialPageRoute<void>(
          builder: (_) => Scaffold(
            appBar: AppBar(title: Text(editorialLabel(context, 'preview'))),
            body: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                Text(
                  _title.text,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                if (_cover.text.startsWith('https://') ||
                    _cover.text.startsWith('http://'))
                  Image.network(
                    _cover.text,
                    errorBuilder: (_, _, _) =>
                        const Icon(Icons.broken_image_outlined),
                  ),
                if (_excerpt.text.isNotEmpty) Text(_excerpt.text),
                EditorialHtmlPreview(
                  htmlContent: _editorText(
                    _editorMap(response['data'])['content_html'],
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : t('editorial.actionError'),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final statuses = [
      'draft',
      'review',
      if (widget.canPublish) 'published',
      'archived',
    ];
    return PopScope(
      canPop: !_saving && !_uploading,
      child: DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.95,
        minChildSize: 0.7,
        maxChildSize: 0.98,
        builder: (context, controller) => Form(
          key: _formKey,
          child: SingleChildScrollView(
            controller: controller,
            padding: EdgeInsets.fromLTRB(
              20,
              12,
              20,
              24 + MediaQuery.viewInsetsOf(context).bottom,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        t(
                          widget.post == null
                              ? 'editorial.createTitle'
                              : 'editorial.editTitle',
                        ),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: t('close'),
                      onPressed: _saving || _uploading
                          ? null
                          : () => Navigator.pop(context),
                      icon: const Icon(Icons.close),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _EditorField(
                  controller: _title,
                  label: t('editorial.postTitle'),
                  required: true,
                  maxLength: 255,
                ),
                const SizedBox(height: 12),
                _EditorField(
                  controller: _excerpt,
                  label: t('editorial.excerpt'),
                  maxLines: 3,
                  maxLength: 500,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: _locale,
                  decoration: InputDecoration(
                    labelText: editorialLabel(context, 'language'),
                  ),
                  items: ['de', 'en', 'fr', 'ar']
                      .map(
                        (locale) => DropdownMenuItem(
                          value: locale,
                          child: Text(locale.toUpperCase()),
                        ),
                      )
                      .toList(),
                  onChanged: _saving
                      ? null
                      : (value) => setState(() => _locale = value!),
                ),
                const SizedBox(height: 12),
                EditorialRichEditor(
                  document: _content,
                  enabled: !_saving && !_uploading,
                  onInsertImage: () => _upload('inline'),
                ),
                const SizedBox(height: 12),
                LayoutBuilder(
                  builder: (context, constraints) => DropdownMenu<int>(
                    width: constraints.maxWidth,
                    initialSelection: _categoryId,
                    enableFilter: true,
                    label: Text(t('editorial.category')),
                    dropdownMenuEntries: widget.categories
                        .where((category) => category['is_active'] == true)
                        .map(
                          (category) => DropdownMenuEntry(
                            value: _editorInt(category['id']),
                            label: _editorText(category['name']),
                          ),
                        )
                        .toList(),
                    onSelected: (value) => setState(() => _categoryId = value),
                    trailingIcon: IconButton(
                      tooltip: t('editorial.noCategory'),
                      icon: const Icon(Icons.clear),
                      onPressed: () => setState(() => _categoryId = null),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                _EditorField(controller: _tags, label: t('editorial.tags')),
                const SizedBox(height: 12),
                _EditorField(
                  controller: _cover,
                  label: t('editorial.coverUrl'),
                  keyboardType: TextInputType.url,
                ),
                Align(
                  alignment: Alignment.centerLeft,
                  child: IconButton(
                    tooltip: editorialLabel(context, 'cover'),
                    icon: const Icon(Icons.add_photo_alternate_outlined),
                    onPressed: _saving || _uploading
                        ? null
                        : () => _upload('cover'),
                  ),
                ),
                if (_uploading) const LinearProgressIndicator(),
                const SizedBox(height: 18),
                Eyebrow(t('editorial.seo')),
                const SizedBox(height: 10),
                _EditorField(
                  controller: _metaTitle,
                  label: t('editorial.metaTitle'),
                  maxLength: 255,
                ),
                const SizedBox(height: 12),
                _EditorField(
                  controller: _metaDescription,
                  label: t('editorial.metaDescription'),
                  maxLines: 3,
                  maxLength: 500,
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: statuses.contains(_status) ? _status : 'draft',
                  decoration: InputDecoration(
                    labelText: t('editorial.statusLabel'),
                  ),
                  items: statuses
                      .map(
                        (status) => DropdownMenuItem(
                          value: status,
                          child: Text(t('editorial.status.$status')),
                        ),
                      )
                      .toList(),
                  onChanged: (value) =>
                      setState(() => _status = value ?? 'draft'),
                ),
                const SizedBox(height: 10),
                Text(
                  t('editorial.publishRequirements'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 18),
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                TextButton.icon(
                  onPressed: _saving || _uploading ? null : _preview,
                  icon: const Icon(Icons.visibility_outlined),
                  label: Text(editorialLabel(context, 'preview')),
                ),
                AirmiusButton(
                  label: t('save'),
                  icon: Icons.save_outlined,
                  onPressed: _saving || _uploading ? null : _submit,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CategoryManager extends StatefulWidget {
  const _CategoryManager({
    required this.categories,
    required this.client,
    required this.onChanged,
  });

  final List<JsonMap> categories;
  final AirmiusApiClient client;
  final VoidCallback onChanged;

  @override
  State<_CategoryManager> createState() => _CategoryManagerState();
}

class _CategoryManagerState extends State<_CategoryManager> {
  late List<JsonMap> _categories;
  bool _busy = false;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    _categories = List<JsonMap>.from(widget.categories);
  }

  Future<void> _edit({JsonMap? category}) async {
    final name = TextEditingController(text: _editorText(category?['name']));
    final description = TextEditingController(
      text: _editorText(category?['description']),
    );
    final result = await showDialog<JsonMap>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          t(
            category == null
                ? 'editorial.categoryCreate'
                : 'editorial.categoryEdit',
          ),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: name,
              decoration: InputDecoration(
                labelText: t('editorial.categoryName'),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: description,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: t('editorial.categoryDescription'),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: () {
              if (name.text.trim().isEmpty) return;
              Navigator.pop(context, {
                'name': name.text.trim(),
                'slug': category == null ? null : _editorText(category['slug']),
                'description': _emptyNull(description.text),
                'sort_order': _editorInt(category?['sort_order']),
                'is_active': category?['is_active'] != false,
              });
            },
            child: Text(t('save')),
          ),
        ],
      ),
    );
    name.dispose();
    description.dispose();
    if (result == null || !mounted) return;
    setState(() => _busy = true);
    try {
      final response = category == null
          ? await widget.client.createEditorialCategory(result)
          : await widget.client.updateEditorialCategory(
              _editorInt(category['id']),
              result,
            );
      final saved = _editorMap(response['data']);
      setState(() {
        if (category == null) {
          _categories.add(saved);
        } else {
          final index = _categories.indexWhere(
            (item) => _editorInt(item['id']) == _editorInt(category['id']),
          );
          if (index >= 0) _categories[index] = saved;
        }
      });
      widget.onChanged();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${t('editorial.actionError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _delete(JsonMap category) async {
    setState(() => _busy = true);
    try {
      await widget.client.deleteEditorialCategory(_editorInt(category['id']));
      setState(
        () => _categories.removeWhere(
          (item) => _editorInt(item['id']) == _editorInt(category['id']),
        ),
      );
      widget.onChanged();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${t('editorial.categoryDeleteError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.85,
      minChildSize: 0.55,
      maxChildSize: 0.95,
      builder: (context, controller) => ListView(
        controller: controller,
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 30),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  t('editorial.categories'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close),
              ),
            ],
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('editorial.categoryCreate'),
            icon: Icons.add_outlined,
            onPressed: _busy ? null : () => _edit(),
          ),
          const SizedBox(height: 14),
          ..._categories.map(
            (category) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: AirmiusPanel(
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        _editorText(category['name']),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: t('edit'),
                      onPressed: _busy ? null : () => _edit(category: category),
                      icon: const Icon(Icons.edit_outlined),
                    ),
                    IconButton(
                      tooltip: t('delete'),
                      onPressed: _busy ? null : () => _delete(category),
                      icon: const Icon(
                        Icons.delete_outline,
                        color: AirmiusColors.red,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _EditorField extends StatelessWidget {
  const _EditorField({
    required this.controller,
    required this.label,
    this.required = false,
    this.maxLines = 1,
    this.maxLength,
    this.keyboardType,
  });

  final TextEditingController controller;
  final String label;
  final bool required;
  final int maxLines;
  final int? maxLength;
  final TextInputType? keyboardType;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      maxLines: maxLines,
      maxLength: maxLength,
      keyboardType: keyboardType,
      decoration: InputDecoration(labelText: label),
      validator: (value) {
        if (required && (value == null || value.trim().isEmpty)) {
          return AirmiusScope.of(context).t('required');
        }
        return null;
      },
    );
  }
}

class _EditorialError extends StatelessWidget {
  const _EditorialError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(t('editorial.loadError')),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('editorial.retry'),
            icon: Icons.refresh_outlined,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

JsonMap _editorMap(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<JsonMap> _editorList(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <JsonMap>[];

String _editorText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _editorInt(Object? value) =>
    value is int ? value : int.tryParse('${value ?? ''}') ?? 0;

int? _editorNullableInt(Object? value) {
  final parsed = _editorInt(value);
  return parsed == 0 ? null : parsed;
}

String? _emptyNull(String value) {
  final trimmed = value.trim();
  return trimmed.isEmpty ? null : trimmed;
}

String _legacyHtml(String text) =>
    '<p>${const HtmlEscape().convert(text).replaceAll('\n', '<br>')}</p>';
