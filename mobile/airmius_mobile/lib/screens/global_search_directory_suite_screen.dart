import 'dart:async';

import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'clubs_screen.dart';
import 'team_detail_screen.dart';
import 'user_profile_detail_screen.dart';

class GlobalSearchDirectorySuiteScreen extends StatefulWidget {
  const GlobalSearchDirectorySuiteScreen({super.key});

  @override
  State<GlobalSearchDirectorySuiteScreen> createState() =>
      _GlobalSearchDirectorySuiteScreenState();
}

class _GlobalSearchDirectorySuiteScreenState
    extends State<GlobalSearchDirectorySuiteScreen> {
  final TextEditingController _queryController = TextEditingController();
  Timer? _searchDebounce;
  String filter = 'all';
  String query = '';
  Future<AirmiusPage<AirmiusSearchResult>>? _resultsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _resultsFuture ??= _search();
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _queryController.dispose();
    super.dispose();
  }

  Future<AirmiusPage<AirmiusSearchResult>> _search() {
    final trimmed = query.trim();
    if (trimmed.isEmpty) {
      return Future.value(
        const AirmiusPage(items: [], currentPage: 1, lastPage: 1),
      );
    }
    return AirmiusServicesScope.of(
      context,
    ).repositories.search.search(query: trimmed);
  }

  void _runSearch(String value) {
    setState(() {
      query = value;
      _resultsFuture = _search();
    });
  }

  void _scheduleSearch(String value) {
    setState(() => query = value);
    _searchDebounce?.cancel();
    if (value.trim().isEmpty) {
      setState(() => _resultsFuture = _search());
      return;
    }
    _searchDebounce = Timer(const Duration(milliseconds: 320), () {
      if (mounted) _runSearch(value);
    });
  }

  String _t(String key) => AirmiusScope.of(context).t(key);

  String _filterLabel(String key) => switch (key) {
    'person' => _t('search.filter.person'),
    'club' => _t('search.filter.club'),
    'team' => _t('search.filter.team'),
    _ => _t('search.filter.all'),
  };

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final textColor = airmiusTextColor(context);
    final mutedColor = airmiusMutedColor(context);
    final inputColor = airmiusInputColor(context);
    final borderColor = airmiusBorderColor(context);
    final accentColor = airmiusAccentColor(context);
    final errorColor = Theme.of(context).colorScheme.error;
    return PageFrame(
      title: scope.t('search.title'),
      subtitle: scope.t('search.subtitle'),
      actions: const [AirmiusLogoMark(size: 34)],
      showHeader: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SectionLabel(scope.t('search.directory.eyebrow')),
                const SizedBox(height: 8),
                Text(
                  scope.t('search.directory.body'),
                  style: TextStyle(
                    color: textColor,
                    height: 1.45,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _queryController,
                  onChanged: _scheduleSearch,
                  onSubmitted: _runSearch,
                  style: TextStyle(
                    color: textColor,
                    fontWeight: FontWeight.w800,
                  ),
                  decoration: InputDecoration(
                    prefixIcon: Icon(Icons.search, color: mutedColor),
                    hintText: scope.t('search.directory.hint'),
                    hintStyle: TextStyle(color: mutedColor),
                    filled: true,
                    fillColor: inputColor,
                    suffixIcon: IconButton(
                      tooltip: scope.t('search.retry'),
                      icon: Icon(Icons.refresh_outlined, color: mutedColor),
                      onPressed: () => _runSearch(_queryController.text),
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: BorderSide(color: borderColor),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: BorderSide(color: borderColor),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(16),
                      borderSide: BorderSide(color: accentColor, width: 1.5),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SectionLabel(scope.t('search.directory.filter')),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: [
                    ButtonSegment(
                      value: 'all',
                      label: Text(scope.t('search.filter.all')),
                    ),
                    ButtonSegment(
                      value: 'person',
                      label: Text(scope.t('search.people')),
                    ),
                    ButtonSegment(
                      value: 'club',
                      label: Text(scope.t('search.clubs')),
                    ),
                    ButtonSegment(
                      value: 'team',
                      label: Text(scope.t('search.teams')),
                    ),
                  ],
                  selected: {filter},
                  onSelectionChanged: (value) =>
                      setState(() => filter = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          FutureBuilder<AirmiusPage<AirmiusSearchResult>>(
            future: _resultsFuture,
            builder: (context, snapshot) {
              final allResults =
                  snapshot.data?.items ?? const <AirmiusSearchResult>[];
              final filtered = allResults.where(_matchesFilter).toList();
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GridWrap(
                    children: [
                      Metric(
                        value: '${filtered.length}',
                        label: scope.t('search.directory.results'),
                      ),
                      Metric(
                        value: '${_typeCount(allResults)}',
                        label: scope.t('search.directory.types'),
                      ),
                      Metric(
                        value: scope.t('search.directory.live'),
                        label: scope.t('search.directory.api'),
                      ),
                      Metric(
                        value: _filterLabel(filter),
                        label: scope.t('search.directory.filter'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  if (snapshot.connectionState == ConnectionState.waiting)
                    AirmiusPanel(
                      child: Center(
                        child: Padding(
                          padding: EdgeInsets.all(18),
                          child: CircularProgressIndicator(color: accentColor),
                        ),
                      ),
                    )
                  else if (snapshot.hasError)
                    AirmiusPanel(
                      borderColor: errorColor.withValues(alpha: .5),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            scope.t('search.directory.errorTitle'),
                            style: TextStyle(
                              color: textColor,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            scope.t('search.directory.errorBody'),
                            style: TextStyle(color: mutedColor, height: 1.35),
                          ),
                          const SizedBox(height: 12),
                          AirmiusButton(
                            label: scope.t('search.retry'),
                            icon: Icons.refresh_outlined,
                            secondary: true,
                            onPressed: () => _runSearch(_queryController.text),
                          ),
                        ],
                      ),
                    )
                  else if (query.trim().isEmpty)
                    EmptyPanel(scope.t('search.directory.emptyQuery'))
                  else if (filtered.isEmpty)
                    EmptyPanel(scope.t('search.directory.noResults'))
                  else
                    for (final result in filtered) ...[
                      _ResultCard(result: result),
                      const SizedBox(height: 12),
                    ],
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  bool _matchesFilter(AirmiusSearchResult result) {
    if (filter == 'all') return true;
    return _typeKey(result.type) == filter;
  }

  int _typeCount(List<AirmiusSearchResult> results) {
    return results.map((result) => _typeKey(result.type)).toSet().length;
  }
}

class _ResultCard extends StatelessWidget {
  const _ResultCard({required this.result});

  final AirmiusSearchResult result;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final type = _typeKey(result.type);
    final color = _typeColor(context, type);
    final textColor = airmiusTextColor(context);
    final mutedColor = airmiusMutedColor(context);
    return AirmiusPanel(
      onTap: () => _open(context),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Stack(
            alignment: Alignment.bottomRight,
            children: [
              AirmiusAvatar(result.title, imageUrl: _imageUrl()),
              Container(
                width: 22,
                height: 22,
                decoration: BoxDecoration(
                  color: color,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color: airmiusSurfaceColor(context),
                    width: 2,
                  ),
                ),
                child: Icon(
                  _typeIcon(type),
                  color: airmiusOnColor(color),
                  size: 13,
                ),
              ),
            ],
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        result.title,
                        style: TextStyle(
                          color: textColor,
                          fontSize: 17,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    StatusPill(_typeLabel(type, scope), color: color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  result.subtitle.isEmpty
                      ? scope.t('search.directory.apiSource')
                      : result.subtitle,
                  style: TextStyle(
                    color: mutedColor,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: _actionLabel(type, scope),
                  icon: type == 'club'
                      ? Icons.assignment_ind_outlined
                      : Icons.open_in_new_outlined,
                  secondary: type != 'club',
                  onPressed: () => _open(context),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _open(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final type = _typeKey(result.type);
    if (type == 'club' && result.club != null) {
      final club = ClubSummary.fromAirmiusClub(result.club!);
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ClubProfileScreen(
            club: club,
            requested: club.hasPendingMembershipRequest,
            onRequest: (_) {},
            onWithdraw: (_) {},
          ),
        ),
      );
      return;
    }
    if (type == 'person') {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => UserProfileDetailScreen(
            userId: result.id,
            name: result.title,
            body: result.subtitle,
            status: scope.t('search.filter.person'),
            context: scope.t('search.context'),
          ),
        ),
      );
      return;
    }
    if (type == 'team') {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => TeamDetailScreen(
            title: result.team?.name ?? result.title,
            mode: 'Profil',
            teamId: result.team?.id == 0 ? result.id : result.team?.id,
            team: result.team,
          ),
        ),
      );
      return;
    }
  }

  String? _imageUrl() {
    if (result.imageUrl != null) return result.imageUrl;
    if (result.club != null) return result.club!.logoUrl;
    if (result.team != null) return result.team!.logoUrl;
    return null;
  }
}

String _typeKey(String value) {
  final normalized = value.toLowerCase();
  if (normalized.contains('club') || normalized.contains('verein')) {
    return 'club';
  }
  if (normalized.contains('team')) return 'team';
  return 'person';
}

String _typeLabel(String type, AirmiusScope scope) {
  return switch (type) {
    'club' => scope.t('search.filter.club'),
    'team' => scope.t('search.filter.team'),
    _ => scope.t('search.filter.person'),
  };
}

Color _typeColor(BuildContext context, String type) {
  final scheme = Theme.of(context).colorScheme;
  return switch (type) {
    'club' => scheme.primary,
    'team' => scheme.secondary,
    _ => scheme.tertiary,
  };
}

IconData _typeIcon(String type) {
  return switch (type) {
    'club' => Icons.groups_2_outlined,
    'team' => Icons.diversity_3_outlined,
    _ => Icons.person_outline,
  };
}

String _actionLabel(String type, AirmiusScope scope) {
  return switch (type) {
    'club' => scope.t('search.directory.viewClub'),
    'team' => scope.t('search.directory.viewTeam'),
    _ => scope.t('search.directory.viewProfile'),
  };
}
