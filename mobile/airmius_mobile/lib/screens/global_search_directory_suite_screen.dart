import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
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
  State<GlobalSearchDirectorySuiteScreen> createState() => _GlobalSearchDirectorySuiteScreenState();
}

class _GlobalSearchDirectorySuiteScreenState extends State<GlobalSearchDirectorySuiteScreen> {
  final TextEditingController _queryController = TextEditingController(text: 'ZBB');
  String filter = 'Alle';
  String query = 'ZBB';
  Future<AirmiusPage<AirmiusSearchResult>>? _resultsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _resultsFuture ??= _search();
  }

  @override
  void dispose() {
    _queryController.dispose();
    super.dispose();
  }

  Future<AirmiusPage<AirmiusSearchResult>> _search() {
    final trimmed = query.trim();
    if (trimmed.isEmpty) {
      return Future.value(const AirmiusPage(items: [], currentPage: 1, lastPage: 1));
    }
    return AirmiusServicesScope.of(context).repositories.search.search(query: trimmed);
  }

  void _runSearch(String value) {
    setState(() {
      query = value;
      _resultsFuture = _search();
    });
  }

  @override
  Widget build(BuildContext context) {
    return PageFrame(
      title: 'Globale Suche',
      subtitle: 'Personen, Vereine und Teams finden',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('DISCOVERY'),
                const SizedBox(height: 8),
                const Text(
                  'Suche wie in der Web-App nach Personen, Vereinen, Teams und öffentlichen Profilen. Treffer fuehren direkt in die passende mobile Detailansicht.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _queryController,
                  onChanged: _runSearch,
                  style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.search, color: AirmiusColors.muted),
                    hintText: 'Suche nach Personen, Teams, Vereinen',
                    hintStyle: const TextStyle(color: AirmiusColors.muted),
                    filled: true,
                    fillColor: AirmiusColors.surface2,
                    suffixIcon: IconButton(
                      icon: const Icon(Icons.refresh_outlined, color: AirmiusColors.muted),
                      onPressed: () => _runSearch(_queryController.text),
                    ),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AirmiusColors.border)),
                    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AirmiusColors.border)),
                    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(16), borderSide: const BorderSide(color: AirmiusColors.blue)),
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
                const SectionLabel('FILTER'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Alle', label: Text('Alle')),
                    ButtonSegment(value: 'Person', label: Text('Personen')),
                    ButtonSegment(value: 'Verein', label: Text('Vereine')),
                    ButtonSegment(value: 'Team', label: Text('Teams')),
                  ],
                  selected: {filter},
                  onSelectionChanged: (value) => setState(() => filter = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          FutureBuilder<AirmiusPage<AirmiusSearchResult>>(
            future: _resultsFuture,
            builder: (context, snapshot) {
              final allResults = snapshot.data?.items ?? const <AirmiusSearchResult>[];
              final filtered = allResults.where(_matchesFilter).toList();
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  GridWrap(
                    children: [
                      Metric(value: '${filtered.length}', label: 'Treffer'),
                      Metric(value: '${_typeCount(allResults)}', label: 'Typen'),
                      const Metric(value: 'Live', label: 'API'),
                      Metric(value: filter, label: 'Filter'),
                    ],
                  ),
                  const SizedBox(height: 14),
                  if (snapshot.connectionState == ConnectionState.waiting)
                    const AirmiusPanel(child: Center(child: Padding(padding: EdgeInsets.all(18), child: CircularProgressIndicator(color: AirmiusColors.blue))))
                  else if (snapshot.hasError)
                    AirmiusPanel(
                      borderColor: AirmiusColors.red.withValues(alpha: .5),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const Text('Suche konnte nicht geladen werden.', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                          const SizedBox(height: 8),
                          Text('${snapshot.error}', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 12),
                          AirmiusButton(label: 'Erneut suchen', icon: Icons.refresh_outlined, secondary: true, onPressed: () => _runSearch(_queryController.text)),
                        ],
                      ),
                    )
                  else if (query.trim().isEmpty)
                    const EmptyPanel('Gib einen Suchbegriff ein, um Personen, Vereine und Teams zu finden.')
                  else if (filtered.isEmpty)
                    const EmptyPanel('Keine Treffer für diese Suche.')
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
    if (filter == 'Alle') return true;
    return _typeLabel(result.type) == filter;
  }

  int _typeCount(List<AirmiusSearchResult> results) {
    return results.map((result) => _typeLabel(result.type)).toSet().length;
  }
}

class _ResultCard extends StatelessWidget {
  const _ResultCard({required this.result});

  final AirmiusSearchResult result;

  @override
  Widget build(BuildContext context) {
    final type = _typeLabel(result.type);
    final color = _typeColor(type);
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
                decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(8), border: Border.all(color: AirmiusColors.card, width: 2)),
                child: Icon(_typeIcon(type), color: Colors.white, size: 13),
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
                    Expanded(child: Text(result.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(type, color: color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(result.subtitle.isEmpty ? 'Direkter Treffer aus der Laravel-Suche' : result.subtitle, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: _actionLabel(type),
                  icon: type == 'Verein' ? Icons.assignment_ind_outlined : Icons.open_in_new_outlined,
                  secondary: type != 'Verein',
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
    final type = _typeLabel(result.type);
    if (type == 'Verein' && result.club != null) {
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
    if (type == 'Person') {
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => UserProfileDetailScreen(
            name: result.title,
            body: result.subtitle,
            status: type,
            context: 'Globale Suche',
          ),
        ),
      );
      return;
    }
    if (type == 'Team') {
      Navigator.push(context, MaterialPageRoute(builder: (_) => TeamDetailScreen(title: result.team?.name ?? result.title, mode: 'Profil', teamId: result.team?.id == 0 ? result.id : result.team?.id, team: result.team)));
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

String _typeLabel(String value) {
  final normalized = value.toLowerCase();
  if (normalized.contains('club') || normalized.contains('verein')) return 'Verein';
  if (normalized.contains('team')) return 'Team';
  return 'Person';
}

Color _typeColor(String type) {
  return switch (type) {
    'Verein' => AirmiusColors.blue,
    'Team' => AirmiusColors.green,
    _ => AirmiusColors.amber,
  };
}

IconData _typeIcon(String type) {
  return switch (type) {
    'Verein' => Icons.groups_2_outlined,
    'Team' => Icons.diversity_3_outlined,
    _ => Icons.person_outline,
  };
}

String _actionLabel(String type) {
  return switch (type) {
    'Verein' => 'Verein ansehen',
    'Team' => 'Team ansehen',
    _ => 'Profil ansehen',
  };
}
