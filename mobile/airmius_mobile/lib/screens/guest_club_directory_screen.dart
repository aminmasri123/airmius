import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'public_interest_screen.dart';

/// Guest-safe catalogue for verified, publicly listed clubs.
///
/// The catalogue intentionally contains only public profile and membership
/// discovery data. Members, finance, applications and documents stay behind
/// the authenticated club APIs.
class GuestClubDirectoryScreen extends StatefulWidget {
  const GuestClubDirectoryScreen({super.key});

  @override
  State<GuestClubDirectoryScreen> createState() =>
      _GuestClubDirectoryScreenState();
}

class _GuestClubDirectoryScreenState extends State<GuestClubDirectoryScreen> {
  Future<List<JsonMap>>? _future;
  String _query = '';
  String _sport = '';
  String _location = '';

  String t(String key) => AirmiusScope.of(context).t(key);

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<List<JsonMap>> _load() async {
    final response = await _client.publicClubs(
      query: _query,
      sport: _sport,
      location: _location,
    );
    final data = response['data'];
    if (data is! List) return const <JsonMap>[];
    return data
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  void _reload() => setState(() => _future = _load());

  void _applyFilters() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('guestClubs.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('guestClubs.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<List<JsonMap>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return PageFrame(
              title: t('guestClubs.title'),
              subtitle: t('guestClubs.subtitle'),
              child: AirmiusPanel(
                child: Column(
                  children: [
                    Text(
                      t('guestClubs.loadError'),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: t('guestClubs.retry'),
                      icon: Icons.refresh_outlined,
                      onPressed: _reload,
                    ),
                  ],
                ),
              ),
            );
          }

          final clubs = snapshot.data ?? const <JsonMap>[];
          final sports = clubs
              .map((club) => _text(club['sport_type']))
              .where((value) => value.isNotEmpty)
              .toSet()
              .toList();
          return PageFrame(
            title: t('guestClubs.title'),
            subtitle: t('guestClubs.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('guestClubs.eyebrow')),
                      const SizedBox(height: 8),
                      Text(
                        t('guestClubs.intro'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                SearchBox(
                  hint: t('guestClubs.search'),
                  onChanged: (value) => _query = value,
                  onSubmitted: (_) => _applyFilters(),
                ),
                const SizedBox(height: 10),
                TextField(
                  decoration: InputDecoration(
                    labelText: t('guestClubs.location'),
                    prefixIcon: const Icon(Icons.location_on_outlined),
                  ),
                  onChanged: (value) => _location = value,
                  onSubmitted: (_) => _applyFilters(),
                ),
                if (sports.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        _SportChip(
                          label: t('guestClubs.allSports'),
                          selected: _sport.isEmpty,
                          onTap: () {
                            _sport = '';
                            _applyFilters();
                          },
                        ),
                        ...sports.map(
                          (sport) => _SportChip(
                            label: sport,
                            selected: _sport == sport,
                            onTap: () {
                              _sport = sport;
                              _applyFilters();
                            },
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                if (clubs.isEmpty)
                  AirmiusPanel(
                    child: Text(
                      t('guestClubs.empty'),
                      textAlign: TextAlign.center,
                    ),
                  )
                else
                  ...clubs.map(
                    (club) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _ClubCard(
                        club: club,
                        onInterest: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => PublicInterestScreen(
                              topic: _text(club['name'], t('guestClubs.club')),
                              kind: 'club_interest',
                              icon: Icons.groups_outlined,
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

  String _text(Object? value, [String fallback = '']) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? fallback : text;
  }
}

class _ClubCard extends StatelessWidget {
  const _ClubCard({required this.club, required this.onInterest});

  final JsonMap club;
  final VoidCallback onInterest;

  String _text(Object? value, [String fallback = '']) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? fallback : text;
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final name = _text(club['name'], t('guestClubs.club'));
    final sport = _text(club['sport_type']);
    final city = [
      club['city'],
      club['state'],
      club['country'],
    ].map(_text).where((value) => value.isNotEmpty).join(', ');
    final teams = club['teams_count'] is num
        ? '${(club['teams_count'] as num).round()}'
        : '0';
    final types = club['membership_types'] is List
        ? (club['membership_types'] as List).whereType<Map>().toList()
        : const <Map>[];
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AirmiusAvatar(name, imageUrl: _text(club['logo_url'])),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    if (sport.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        sport,
                        style: TextStyle(color: theme.colorScheme.primary),
                      ),
                    ],
                    if (city.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        city,
                        style: TextStyle(
                          color: theme.colorScheme.onSurfaceVariant,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (club['is_official'] == true)
                StatusPill(
                  t('guestClubs.official'),
                  color: theme.colorScheme.secondary,
                ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusPill('$teams ${t('guestClubs.teams')}'),
              StatusPill(
                types.isEmpty
                    ? t('guestClubs.noMembership')
                    : '${types.length} ${t('guestClubs.membership')}',
              ),
            ],
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('guestClubs.interested'),
            icon: Icons.send_outlined,
            secondary: true,
            onPressed: onInterest,
          ),
        ],
      ),
    );
  }
}

class _SportChip extends StatelessWidget {
  const _SportChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 8),
      child: FilterChip(
        label: Text(label),
        selected: selected,
        showCheckmark: false,
        onSelected: (_) => onTap(),
      ),
    );
  }
}
