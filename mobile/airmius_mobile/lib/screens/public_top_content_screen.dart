import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'blog_media_center_screen.dart';
import 'sponsors_center_screen.dart';

/// Public discovery page backed by published blog and sponsor records.
class PublicTopContentScreen extends StatefulWidget {
  const PublicTopContentScreen({super.key});

  @override
  State<PublicTopContentScreen> createState() => _PublicTopContentScreenState();
}

class _PublicTopContentScreenState extends State<PublicTopContentScreen> {
  Future<_PublicTopBundle>? _future;
  String _filter = 'all';

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_PublicTopBundle> _load() async {
    final responses = await Future.wait([
      _client.publicBlog(),
      _client.publicSponsors(),
    ]);
    final items = <_PublicTopItem>[];
    for (final post in _jsonMaps(responses[0]['data'])) {
      final title = _text(post['title']);
      if (title.isEmpty) continue;
      items.add(
        _PublicTopItem(
          title: title,
          body: _text(post['excerpt']).isEmpty
              ? _text(post['summary'])
              : _text(post['excerpt']),
          type: 'blog',
          icon: Icons.article_outlined,
        ),
      );
    }
    for (final sponsor in _jsonMaps(responses[1]['data'])) {
      final title = _text(sponsor['name']).isEmpty
          ? _text(sponsor['display_name'])
          : _text(sponsor['name']);
      if (title.isEmpty) continue;
      items.add(
        _PublicTopItem(
          title: title,
          body: _text(sponsor['description']),
          type: 'sponsor',
          icon: Icons.handshake_outlined,
        ),
      );
    }
    return _PublicTopBundle(items: items);
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('publicTop.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('publicTop.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<_PublicTopBundle>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _ErrorState(onRetry: _reload);
          }
          final all = snapshot.data?.items ?? const <_PublicTopItem>[];
          final visible = _filter == 'all'
              ? all
              : all.where((item) => item.type == _filter).toList();
          return PageFrame(
            title: t('publicTop.title'),
            subtitle: t('publicTop.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('publicTop.title')),
                      const SizedBox(height: 8),
                      Text(
                        t('publicTop.hero'),
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _FilterChip(
                      label: t('publicTop.all'),
                      selected: _filter == 'all',
                      onTap: () => setState(() => _filter = 'all'),
                    ),
                    _FilterChip(
                      label: t('publicTop.blog'),
                      selected: _filter == 'blog',
                      onTap: () => setState(() => _filter = 'blog'),
                    ),
                    _FilterChip(
                      label: t('publicTop.sponsors'),
                      selected: _filter == 'sponsor',
                      onTap: () => setState(() => _filter = 'sponsor'),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                if (visible.isEmpty)
                  AirmiusPanel(
                    child: Text(
                      t('publicTop.empty'),
                      textAlign: TextAlign.center,
                    ),
                  )
                else
                  ...visible.map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _ContentCard(item: item),
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

class _ContentCard extends StatelessWidget {
  const _ContentCard({required this.item});

  final _PublicTopItem item;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final isBlog = item.type == 'blog';
    return AirmiusPanel(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => isBlog
              ? const BlogMediaCenterScreen()
              : const SponsorsCenterScreen(),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            item.icon,
            color: Theme.of(context).colorScheme.primary,
            size: 30,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                if (item.body.isNotEmpty) ...[
                  const SizedBox(height: 5),
                  Text(item.body),
                ],
                const SizedBox(height: 8),
                StatusPill(
                  isBlog ? t('publicTop.blog') : t('publicTop.sponsors'),
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right),
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
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
      selected: selected,
      label: Text(label),
      onSelected: (_) => onTap(),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: AirmiusButton(
        label: AirmiusScope.of(context).t('publicTop.reload'),
        icon: Icons.refresh_outlined,
        onPressed: onRetry,
      ),
    );
  }
}

class _PublicTopBundle {
  const _PublicTopBundle({required this.items});

  final List<_PublicTopItem> items;
}

class _PublicTopItem {
  const _PublicTopItem({
    required this.title,
    required this.body,
    required this.type,
    required this.icon,
  });

  final String title;
  final String body;
  final String type;
  final IconData icon;
}

List<JsonMap> _jsonMaps(dynamic value) {
  if (value is! List) return const [];
  return value
      .whereType<Map>()
      .map((item) => Map<String, dynamic>.from(item))
      .toList();
}

String _text(dynamic value) => value?.toString().trim() ?? '';
