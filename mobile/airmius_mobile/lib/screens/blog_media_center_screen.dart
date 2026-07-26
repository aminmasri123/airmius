import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'editorial_management_screen.dart';

class BlogMediaCenterScreen extends StatefulWidget {
  const BlogMediaCenterScreen({super.key});

  @override
  State<BlogMediaCenterScreen> createState() => _BlogMediaCenterScreenState();
}

class _BlogMediaCenterScreenState extends State<BlogMediaCenterScreen> {
  final _search = TextEditingController();
  Future<JsonMap>? _future;
  String _category = '';

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  bool get _canManage {
    final user = AirmiusServicesScope.of(context).authState.user;
    return user?.can('blog.view') == true ||
        user?.can('blog.create') == true ||
        user?.hasAnyRole(const [
              'redaktor',
              'media_manager',
              'super_admin',
              'admin',
            ]) ==
            true;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.publicBlog();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  void _load() => setState(
    () =>
        _future = _client.publicBlog(query: _search.text, category: _category),
  );

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('content.blogTitle'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          if (_canManage)
            IconButton(
              tooltip: t('editorial.open'),
              onPressed: () => Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (_) => const EditorialManagementScreen(),
                ),
              ).then((_) => _load()),
              icon: const Icon(Icons.edit_note_outlined),
            ),
          IconButton(
            tooltip: t('content.reload'),
            onPressed: _load,
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
            return _ContentError(onRetry: _load);
          }
          final posts = _contentList(snapshot.data?['data']);
          final categories = _contentList(snapshot.data?['categories']);
          final meta = _contentMap(snapshot.data?['meta']);

          return PageFrame(
            title: t('content.blogTitle'),
            subtitle: t('content.blogSubtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('content.knowledge')),
                      const SizedBox(height: 8),
                      Text(
                        t('content.blogHero'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('content.publishedOnly'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => _load(),
                  decoration: InputDecoration(
                    hintText: t('content.search'),
                    prefixIcon: const Icon(Icons.search_outlined),
                    suffixIcon: IconButton(
                      tooltip: t('content.search'),
                      onPressed: _load,
                      icon: const Icon(Icons.arrow_forward_outlined),
                    ),
                  ),
                ),
                if (categories.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        _CategoryChip(
                          label: t('content.all'),
                          selected: _category.isEmpty,
                          onTap: () {
                            _category = '';
                            _load();
                          },
                        ),
                        ...categories.map(
                          (category) => Padding(
                            padding: const EdgeInsetsDirectional.only(start: 8),
                            child: _CategoryChip(
                              label: _contentText(category['name']),
                              selected:
                                  _category == _contentText(category['slug']),
                              onTap: () {
                                _category = _contentText(category['slug']);
                                _load();
                              },
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                Text(
                  t('content.articleCount').replaceFirst(
                    '{count}',
                    '${_contentInt(meta['total'], fallback: posts.length)}',
                  ),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 10),
                if (posts.isEmpty)
                  AirmiusPanel(
                    child: Text(
                      t('content.emptyBlog'),
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                  )
                else
                  ...posts.map(
                    (post) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _PostCard(
                        post: post,
                        onTap: () => Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => _PublicBlogPostScreen(
                              slug: _contentText(post['slug']),
                            ),
                          ),
                        ),
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

class _PostCard extends StatelessWidget {
  const _PostCard({required this.post, required this.onTap});

  final JsonMap post;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final imageUrl = _contentText(post['cover_image_url']);
    final category = _contentText(_contentMap(post['category'])['name']);
    return AirmiusPanel(
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (imageUrl.isNotEmpty)
            AirmiusMediaImage(
              url: imageUrl,
              height: 170,
              borderRadius: 14,
              semanticLabel: _contentText(
                post['title'],
                fallback: t('content.article'),
              ),
            ),
          if (imageUrl.isNotEmpty) const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (category.isNotEmpty) StatusPill(category),
              StatusPill(
                t('content.readingMinutes').replaceFirst(
                  '{count}',
                  '${_contentInt(post['reading_time_minutes'], fallback: 1)}',
                ),
                color: Theme.of(context).colorScheme.secondary,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            _contentText(post['title'], fallback: t('content.article')),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
              height: 1.2,
            ),
          ),
          if (_contentText(post['excerpt']).isNotEmpty) ...[
            const SizedBox(height: 7),
            Text(
              _contentText(post['excerpt']),
              maxLines: 3,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
          ],
          const SizedBox(height: 10),
          Row(
            children: [
              Icon(
                Icons.person_outline,
                size: 18,
                color: airmiusMutedColor(context),
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  _contentText(
                    _contentMap(post['author'])['name'],
                    fallback: 'Airmius',
                  ),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
              ),
              Text(
                t('content.read'),
                style: TextStyle(
                  color: airmiusAccentColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
              Icon(Icons.chevron_right, color: airmiusAccentColor(context)),
            ],
          ),
        ],
      ),
    );
  }
}

class _PublicBlogPostScreen extends StatefulWidget {
  const _PublicBlogPostScreen({required this.slug});

  final String slug;

  @override
  State<_PublicBlogPostScreen> createState() => _PublicBlogPostScreenState();
}

class _PublicBlogPostScreenState extends State<_PublicBlogPostScreen> {
  Future<JsonMap>? _future;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.publicBlogPost(widget.slug);
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(t('content.article')),
      ),
      body: FutureBuilder<JsonMap>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ContentError(
              onRetry: () =>
                  setState(() => _future = _client.publicBlogPost(widget.slug)),
            );
          }
          final post = _contentMap(snapshot.data?['data']);
          final imageUrl = _contentText(post['cover_image_url']);
          final category = _contentText(_contentMap(post['category'])['name']);
          return PageFrame(
            title: _contentText(post['title'], fallback: t('content.article')),
            subtitle: _contentText(post['excerpt']),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (imageUrl.isNotEmpty)
                  AirmiusMediaImage(
                    url: imageUrl,
                    height: 230,
                    borderRadius: 18,
                    semanticLabel: _contentText(
                      post['title'],
                      fallback: t('content.article'),
                    ),
                  ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (category.isNotEmpty) StatusPill(category),
                    StatusPill(
                      t('content.readingMinutes').replaceFirst(
                        '{count}',
                        '${_contentInt(post['reading_time_minutes'], fallback: 1)}',
                      ),
                      color: Theme.of(context).colorScheme.secondary,
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: SelectableText(
                    _contentText(
                      post['content_text'],
                      fallback: t('content.noContent'),
                    ),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 16,
                      height: 1.65,
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

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      selectedColor: airmiusAccentColor(context).withValues(alpha: 0.22),
      backgroundColor: airmiusSurfaceSoftColor(context),
      side: BorderSide(
        color: selected
            ? airmiusAccentColor(context)
            : airmiusBorderColor(context),
      ),
      labelStyle: TextStyle(
        color: selected
            ? airmiusAccentColor(context)
            : airmiusMutedColor(context),
        fontWeight: FontWeight.w900,
      ),
    );
  }
}

class _ContentError extends StatelessWidget {
  const _ContentError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.article_outlined,
              color: Theme.of(context).colorScheme.error,
              size: 44,
            ),
            const SizedBox(height: 12),
            Text(t('content.loadError')),
            const SizedBox(height: 12),
            AirmiusButton(
              label: t('content.retry'),
              icon: Icons.refresh_outlined,
              onPressed: onRetry,
            ),
          ],
        ),
      ),
    );
  }
}

JsonMap _contentMap(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<JsonMap> _contentList(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <JsonMap>[];

String _contentText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _contentInt(Object? value, {int fallback = 0}) =>
    value is int ? value : int.tryParse('${value ?? ''}') ?? fallback;
