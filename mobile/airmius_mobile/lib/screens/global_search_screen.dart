import 'dart:async';

import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../navigation/airmius_module_destination.dart';
import '../widgets/airmius_widgets.dart';
import 'clubs_screen.dart';
import 'file_preview_screen.dart';
import 'lesson_detail_screen.dart';
import 'marketplace_screen.dart';
import 'search_operations_screen.dart';
import 'team_detail_screen.dart';
import 'training_event_detail_screen.dart';
import 'user_profile_detail_screen.dart';

class GlobalSearchScreen extends StatefulWidget {
  const GlobalSearchScreen({super.key});

  @override
  State<GlobalSearchScreen> createState() => _GlobalSearchScreenState();
}

class _GlobalSearchScreenState extends State<GlobalSearchScreen> {
  String _query = '';
  String _filter = 'all';
  Future<List<AirmiusSearchResult>>? _resultsFuture;
  Timer? _searchDebounce;
  int _searchSequence = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _resultsFuture ??= Future.value(const <AirmiusSearchResult>[]);
  }

  Future<List<AirmiusSearchResult>> _loadResults(String query) async {
    final trimmed = query.trim();
    if (trimmed.length < 2) return const <AirmiusSearchResult>[];

    final sequence = ++_searchSequence;
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.search.search(query: trimmed);

    return sequence == _searchSequence
        ? page.items
        : const <AirmiusSearchResult>[];
  }

  void _setQuery(String value) {
    _searchDebounce?.cancel();
    setState(() {
      _query = value;
      _resultsFuture = Future.value(const <AirmiusSearchResult>[]);
    });

    if (value.trim().length < 2) return;

    _searchDebounce = Timer(const Duration(milliseconds: 320), () {
      if (!mounted) return;
      final resultsFuture = _loadResults(value);
      setState(() {
        _resultsFuture = resultsFuture;
      });
    });
  }

  void _reload() {
    _searchDebounce?.cancel();
    final resultsFuture = _loadResults(_query);
    setState(() {
      _resultsFuture = resultsFuture;
    });
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _searchSequence++;
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final theme = Theme.of(context);
    final accent = theme.colorScheme.primary;
    final onAccent = theme.colorScheme.onPrimary;

    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: accent,
        foregroundColor: onAccent,
        icon: Icon(Icons.manage_search_outlined),
        label: Text(
          scope.t('search.ops'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        onPressed: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const SearchOperationsScreen()),
        ),
      ),
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('search.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: scope.t('search.title'),
        subtitle: scope.t('search.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            SearchBox(hint: scope.t('search'), onChanged: _setQuery),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final filter in const [
                  'all',
                  'person',
                  'team',
                  'club',
                  'event',
                  'course',
                  'product',
                  'file',
                  'module',
                ])
                  ChoiceChip(
                    selected: _filter == filter,
                    label: Text(scope.t('search.filter.$filter')),
                    onSelected: (_) => setState(() => _filter = filter),
                    selectedColor: accent.withValues(alpha: 0.22),
                    backgroundColor: airmiusSurfaceSoftColor(context),
                    side: BorderSide(
                      color: _filter == filter
                          ? accent
                          : airmiusBorderColor(context),
                    ),
                    labelStyle: TextStyle(
                      color: _filter == filter
                          ? accent
                          : airmiusMutedColor(context),
                      fontWeight: FontWeight.w900,
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            FutureBuilder<List<AirmiusSearchResult>>(
              future: _resultsFuture,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return AirmiusPanel(
                    child: Padding(
                      padding: const EdgeInsets.all(18),
                      child: Center(
                        child: Text(
                          scope.t('search.loading'),
                          style: TextStyle(color: airmiusMutedColor(context)),
                        ),
                      ),
                    ),
                  );
                }
                if (snapshot.hasError) {
                  return AirmiusPanel(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Eyebrow(scope.t('search.apiError')),
                        const SizedBox(height: 8),
                        Text(
                          snapshot.error is AirmiusApiException
                              ? (snapshot.error! as AirmiusApiException)
                                    .userMessage
                              : scope.t('common.errorDetails'),
                          style: TextStyle(color: airmiusMutedColor(context)),
                        ),
                        const SizedBox(height: 12),
                        AirmiusButton(
                          label: scope.t('search.retry'),
                          icon: Icons.refresh_outlined,
                          onPressed: _reload,
                        ),
                      ],
                    ),
                  );
                }

                final results = snapshot.data ?? const <AirmiusSearchResult>[];
                final items = results
                    .where(
                      (item) =>
                          _filter == 'all' || _typeKey(item.type) == _filter,
                    )
                    .toList();
                final clubCount = results
                    .where((item) => _typeKey(item.type) == 'club')
                    .length;
                final personCount = results
                    .where((item) => _typeKey(item.type) == 'person')
                    .length;
                final teamCount = results
                    .where((item) => _typeKey(item.type) == 'team')
                    .length;

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: MetricCard(
                            value: '$clubCount',
                            label: scope.t('search.clubs'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: MetricCard(
                            value: '$personCount',
                            label: scope.t('search.people'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: MetricCard(
                            value: '$teamCount',
                            label: scope.t('search.teams'),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    if (items.isEmpty)
                      AirmiusPanel(
                        child: Padding(
                          padding: const EdgeInsets.all(18),
                          child: Center(
                            child: Text(
                              scope.t('search.empty'),
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                              ),
                            ),
                          ),
                        ),
                      )
                    else
                      for (final item in items) ...[
                        AirmiusPanel(
                          onTap: () => _openResult(item),
                          child: Row(
                            children: [
                              AirmiusAvatar(
                                item.title,
                                imageUrl: _resultImageUrl(item),
                              ),
                              const SizedBox(width: 14),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      item.title,
                                      style: TextStyle(
                                        color: airmiusTextColor(context),
                                        fontSize: 17,
                                        fontWeight: FontWeight.w900,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      item.subtitle,
                                      style: TextStyle(
                                        color: airmiusMutedColor(context),
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    StatusPill(
                                      scope.t(
                                        'search.filter.${_typeKey(item.type)}',
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              Icon(
                                Icons.chevron_right,
                                color: airmiusMutedColor(context),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 12),
                      ],
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openResult(AirmiusSearchResult item) async {
    final club = item.club;
    if (club != null) {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ClubProfileScreen(
            club: ClubSummary.fromAirmiusClub(club),
            requested: false,
            onRequest: (_) {},
            onWithdraw: (_) {},
          ),
        ),
      );
      return;
    }

    final type = _typeKey(item.type);
    if (type == 'module') {
      final destination = AirmiusModuleDestination.resolveKey(
        context,
        item.payload['module_key']?.toString() ?? '',
      );
      if (destination == null) return;

      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => destination),
      );
      return;
    }

    if (type == 'event') {
      try {
        final event = await AirmiusServicesScope.of(
          context,
        ).repositories.events.event(item.id);
        if (!mounted) return;
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => TrainingEventDetailScreen(
              event: event,
              fallbackBody: item.subtitle,
            ),
          ),
        );
      } on AirmiusApiException catch (error) {
        if (!mounted) return;
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
      return;
    }

    if (type == 'course') {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) =>
              LessonDetailScreen(courseId: item.id, initialTitle: item.title),
        ),
      );
      return;
    }

    if (type == 'product') {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => MarketplaceScreen(
            initialQuery: item.title,
            initialProductId: item.id,
            initialProductTitle: item.title,
          ),
        ),
      );
      return;
    }

    if (type == 'file') {
      final fileUrl = item.payload['file_url']?.toString();
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => FilePreviewScreen(
            title: item.title,
            body: item.subtitle,
            status: item.payload['mime_type']?.toString() ?? 'Datei',
            icon: Icons.insert_drive_file_outlined,
            fileId: item.id,
            fileUrl: fileUrl,
          ),
        ),
      );
      return;
    }

    if (type == 'team') {
      final team = item.team;
      final teamId = team?.id == 0 ? item.id : team?.id;
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => TeamDetailScreen(
            title: team?.name ?? item.title,
            mode: 'Profil',
            teamId: teamId,
            team: team,
          ),
        ),
      );
      return;
    }

    final scope = AirmiusScope.of(context);
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => UserProfileDetailScreen(
          userId: item.id,
          name: item.title,
          body: item.subtitle,
          status: scope.t('search.filter.${_typeKey(item.type)}'),
          context: scope.t('search.context'),
          avatarUrl: item.imageUrl,
        ),
      ),
    );
  }

  String? _resultImageUrl(AirmiusSearchResult item) {
    if (item.imageUrl != null) return item.imageUrl;
    if (item.club != null) return item.club!.logoUrl;
    if (item.team != null) return item.team!.logoUrl;
    return null;
  }
}

String _typeKey(String rawType) {
  final type = rawType.toLowerCase();
  if (type.contains('club') || type.contains('verein')) return 'club';
  if (type.contains('team')) return 'team';
  if (type.contains('event') || type.contains('termin')) return 'event';
  if (type.contains('course') || type.contains('kurs')) return 'course';
  if (type.contains('product') || type.contains('produkt')) return 'product';
  if (type.contains('file') || type.contains('datei')) return 'file';
  if (type.contains('module') ||
      type.contains('function') ||
      type.contains('funktion') ||
      type.contains('وظيفة')) {
    return 'module';
  }
  return 'person';
}
