import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'clubs_screen.dart';
import 'search_operations_screen.dart';
import 'team_detail_screen.dart';
import 'user_profile_detail_screen.dart';

class GlobalSearchScreen extends StatefulWidget {
  const GlobalSearchScreen({super.key});

  @override
  State<GlobalSearchScreen> createState() => _GlobalSearchScreenState();
}

class _GlobalSearchScreenState extends State<GlobalSearchScreen> {
  String _query = '';
  String _filter = 'Alle';
  Future<List<AirmiusSearchResult>>? _resultsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _resultsFuture ??= _loadResults('');
  }

  Future<List<AirmiusSearchResult>> _loadResults(String query) async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.search.search(query: query.trim());
    return page.items;
  }

  void _setQuery(String value) {
    setState(() {
      _query = value;
      _resultsFuture = _loadResults(value);
    });
  }

  void _reload() {
    setState(() => _resultsFuture = _loadResults(_query));
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);

    return Scaffold(
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF1D5FA8),
        foregroundColor: Colors.white,
        icon: const Icon(Icons.manage_search_outlined),
        label: const Text('Search Ops', style: TextStyle(fontWeight: FontWeight.w900)),
        onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SearchOperationsScreen())),
      ),
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(scope.t('search.title'), style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: scope.t('search.title'),
        subtitle: scope.t('search.subtitle'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          SearchBox(hint: scope.t('search'), onChanged: _setQuery),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            for (final filter in const ['Alle', 'Person', 'Team', 'Verein'])
              ChoiceChip(
                selected: _filter == filter,
                label: Text(filter),
                onSelected: (_) => setState(() => _filter = filter),
                selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                backgroundColor: AirmiusColors.cardSoft,
                side: BorderSide(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.border),
                labelStyle: TextStyle(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
              ),
          ]),
          const SizedBox(height: 14),
          FutureBuilder<List<AirmiusSearchResult>>(
            future: _resultsFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return AirmiusPanel(child: Padding(padding: const EdgeInsets.all(18), child: Center(child: Text(scope.t('search.loading'), style: const TextStyle(color: AirmiusColors.muted)))));
              }
              if (snapshot.hasError) {
                return AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  const Eyebrow('API Fehler'),
                  const SizedBox(height: 8),
                  Text('${snapshot.error}', style: const TextStyle(color: AirmiusColors.muted)),
                  const SizedBox(height: 12),
                  AirmiusButton(label: scope.t('search.retry'), icon: Icons.refresh_outlined, onPressed: _reload),
                ]));
              }

              final results = snapshot.data ?? const <AirmiusSearchResult>[];
              final items = results.where((item) => _filter == 'Alle' || _typeLabel(item.type) == _filter).toList();
              final clubCount = results.where((item) => _typeLabel(item.type) == 'Verein').length;
              final personCount = results.where((item) => _typeLabel(item.type) == 'Person').length;
              final teamCount = results.where((item) => _typeLabel(item.type) == 'Team').length;

              return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                Row(children: [
                  Expanded(child: MetricCard(value: '$clubCount', label: scope.t('search.clubs'))),
                  const SizedBox(width: 10),
                  Expanded(child: MetricCard(value: '$personCount', label: scope.t('search.people'))),
                  const SizedBox(width: 10),
                  Expanded(child: MetricCard(value: '$teamCount', label: scope.t('search.teams'))),
                ]),
                const SizedBox(height: 14),
                if (items.isEmpty)
                  AirmiusPanel(child: Padding(padding: const EdgeInsets.all(18), child: Center(child: Text(scope.t('search.empty'), style: const TextStyle(color: AirmiusColors.muted)))))
                else
                  for (final item in items) ...[
                    AirmiusPanel(
                      onTap: () => _openResult(item),
                      child: Row(children: [
                        AirmiusAvatar(item.title, imageUrl: _resultImageUrl(item)),
                        const SizedBox(width: 14),
                        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900)),
                          const SizedBox(height: 4),
                          Text(item.subtitle, style: const TextStyle(color: AirmiusColors.muted)),
                          const SizedBox(height: 8),
                          StatusPill(_typeLabel(item.type)),
                        ])),
                        const Icon(Icons.chevron_right, color: AirmiusColors.muted),
                      ]),
                    ),
                    const SizedBox(height: 12),
                  ],
              ]);
            },
          ),
        ]),
      ),
    );
  }

  void _openResult(AirmiusSearchResult item) {
    final club = item.club;
    if (club != null) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => ClubProfileScreen(club: ClubSummary.fromAirmiusClub(club), requested: false, onRequest: (_) {}, onWithdraw: (_) {})));
      return;
    }

    if (_typeLabel(item.type) == 'Team') {
      final team = item.team;
      final teamId = team?.id == 0 ? item.id : team?.id;
      Navigator.push(context, MaterialPageRoute(builder: (_) => TeamDetailScreen(title: team?.name ?? item.title, mode: 'Profil', teamId: teamId, team: team)));
      return;
    }

    Navigator.push(context, MaterialPageRoute(builder: (_) => UserProfileDetailScreen(name: item.title, body: item.subtitle, status: _typeLabel(item.type), context: 'Globale Suche')));
  }

  String? _resultImageUrl(AirmiusSearchResult item) {
    if (item.imageUrl != null) return item.imageUrl;
    if (item.club != null) return item.club!.logoUrl;
    if (item.team != null) return item.team!.logoUrl;
    return null;
  }
}

String _typeLabel(String rawType) {
  final type = rawType.toLowerCase();
  if (type.contains('club') || type.contains('verein')) return 'Verein';
  if (type.contains('team')) return 'Team';
  return 'Person';
}
