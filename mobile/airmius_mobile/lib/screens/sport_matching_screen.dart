import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SportMatchingScreen extends StatefulWidget {
  const SportMatchingScreen({super.key});

  @override
  State<SportMatchingScreen> createState() => _SportMatchingScreenState();
}

class _SportMatchingScreenState extends State<SportMatchingScreen> {
  final _cityController = TextEditingController();
  Future<JsonMap>? _future;
  String _mode = 'partner';
  int? _sportId;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String _c(String de, String en, String fr, String ar) =>
      switch (AirmiusScope.of(context).language) {
        AirmiusLanguage.de => de,
        AirmiusLanguage.en => en,
        AirmiusLanguage.fr => fr,
        AirmiusLanguage.ar => ar,
      };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  @override
  void dispose() {
    _cityController.dispose();
    super.dispose();
  }

  Future<JsonMap> _load() => _client.sportMatchings(
    mode: _mode,
    city: _cityController.text,
    sportId: _sportId,
  );

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _c(
            'Sport-Matching',
            'Sport matching',
            'Matching sportif',
            'مطابقة رياضية',
          ),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(onPressed: _reload, icon: const Icon(Icons.refresh)),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _busy ? null : () => _openCreate(),
        icon: const Icon(Icons.add),
        label: Text(
          _c('Suche erstellen', 'Create search', 'Créer', 'إنشاء بحث'),
        ),
      ),
      body: FutureBuilder<JsonMap>(
        future: _future,
        builder: (context, snapshot) {
          final data = snapshot.data;
          final meta = data?['meta'] is JsonMap
              ? data!['meta'] as JsonMap
              : const <String, dynamic>{};
          final sports = _maps(meta['sports']);
          return PageFrame(
            title: _c(
              'Gemeinsam Sport machen',
              'Play sports together',
              'Faire du sport ensemble',
              'مارس الرياضة مع الآخرين',
            ),
            subtitle: _c(
              'Finde Personen oder ein gegnerisches Team – unabhängig von der Sportart.',
              'Find people or an opposing team, for any sport.',
              'Trouvez des partenaires ou une équipe adverse, pour tous les sports.',
              'ابحث عن شركاء أو فريق منافس لأي رياضة.',
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _modeSelector(),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _cityController,
                        textInputAction: TextInputAction.search,
                        onSubmitted: (_) => _reload(),
                        decoration: InputDecoration(
                          prefixIcon: const Icon(Icons.location_on_outlined),
                          hintText: _c(
                            'Ort, z. B. Kenitra',
                            'City, e.g. Kenitra',
                            'Ville, ex. Kénitra',
                            'المدينة، مثال القنيطرة',
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton.filled(
                      onPressed: _reload,
                      icon: const Icon(Icons.search),
                    ),
                  ],
                ),
                if (sports.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int?>(
                    initialValue: _sportId,
                    decoration: InputDecoration(
                      labelText: _c('Sportart', 'Sport', 'Sport', 'الرياضة'),
                    ),
                    items: [
                      DropdownMenuItem<int?>(
                        value: null,
                        child: Text(
                          _c(
                            'Alle Sportarten',
                            'All sports',
                            'Tous les sports',
                            'كل الرياضات',
                          ),
                        ),
                      ),
                      ...sports.map(
                        (sport) => DropdownMenuItem<int?>(
                          value: _int(sport['id']),
                          child: Text('${sport['name'] ?? ''}'),
                        ),
                      ),
                    ],
                    onChanged: (value) {
                      _sportId = value;
                      _reload();
                    },
                  ),
                ],
                const SizedBox(height: 16),
                if (snapshot.connectionState == ConnectionState.waiting)
                  const Center(child: CircularProgressIndicator())
                else if (snapshot.hasError)
                  _error(snapshot.error)
                else
                  _matchingList(data ?? const {}, meta),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _modeSelector() => SegmentedButton<String>(
    segments: [
      ButtonSegment(
        value: 'partner',
        icon: const Icon(Icons.directions_run),
        label: Text(_c('Sportpartner', 'Partners', 'Partenaires', 'شركاء')),
      ),
      ButtonSegment(
        value: 'team',
        icon: const Icon(Icons.groups_2_outlined),
        label: Text(_c('Teamgegner', 'Teams', 'Équipes', 'فرق')),
      ),
    ],
    selected: {_mode},
    onSelectionChanged: (values) {
      setState(() {
        _mode = values.first;
        _future = _load();
      });
    },
  );

  Widget _matchingList(JsonMap response, JsonMap meta) {
    final matchings = _maps(response['data']);
    final teams = _maps(meta['teams']);
    if (matchings.isEmpty) {
      return AirmiusPanel(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Text(
            _c(
              'Noch keine passenden Suchen. Erstelle die erste.',
              'No matching searches yet. Create the first one.',
              'Aucune recherche. Créez la première.',
              'لا توجد طلبات مطابقة بعد. أنشئ الأول.',
            ),
            textAlign: TextAlign.center,
          ),
        ),
      );
    }
    return Column(
      children: matchings
          .map(
            (matching) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _card(matching, teams),
            ),
          )
          .toList(),
    );
  }

  Widget _card(JsonMap matching, List<JsonMap> teams) {
    final sport = matching['sport'] is JsonMap
        ? matching['sport'] as JsonMap
        : const <String, dynamic>{};
    final team = matching['team'] is JsonMap
        ? matching['team'] as JsonMap
        : null;
    final mine = matching['mine'] == true;
    final applications = _maps(matching['applications']);
    final startsAt = DateTime.tryParse('${matching['starts_at'] ?? ''}');
    return AirmiusPanel(
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            Chip(label: Text('${sport['name'] ?? ''}')),
            Chip(
              label: Text(
                matching['mode'] == 'team'
                    ? '${matching['team_size']} vs. ${matching['team_size']}'
                    : '${matching['participants_needed']} ${_c('gesucht', 'wanted', 'recherchés', 'مطلوب')}',
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        Text(
          '${matching['title'] ?? ''}',
          style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w900),
        ),
        if ('${matching['description'] ?? ''}'.trim().isNotEmpty) ...[
          const SizedBox(height: 6),
          Text('${matching['description']}'),
        ],
        const SizedBox(height: 10),
        Text('📍 ${matching['city']}, ${matching['country_code']}'),
        Text(
          '🗓 ${startsAt == null ? '' : MaterialLocalizations.of(context).formatMediumDate(startsAt.toLocal())}',
        ),
        Text('🎯 ${matching['skill_level']} · ${matching['radius_km']} km'),
        if (team != null) Text('👥 ${team['name']}'),
        if (mine && applications.isNotEmpty) ...[
          const Divider(height: 24),
          ...applications.map(
            (application) => _applicationRow(matching, application),
          ),
        ] else if (!mine && matching['my_application'] == null) ...[
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: _busy ? null : () => _apply(matching, teams),
            icon: const Icon(Icons.waving_hand_outlined),
            label: Text(
              _c(
                'Interesse senden',
                'Send interest',
                'Participer',
                'إرسال اهتمام',
              ),
            ),
          ),
        ] else if (!mine) ...[
          const SizedBox(height: 10),
          Text(
            '${_c('Anfrage', 'Request', 'Demande', 'الطلب')}: ${matching['my_application']}',
            style: TextStyle(
              color: airmiusAccentColor(context),
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ],
    );
  }

  Widget _applicationRow(JsonMap matching, JsonMap application) {
    final user = application['user'] is JsonMap
        ? application['user'] as JsonMap
        : const <String, dynamic>{};
    final team = application['team'] is JsonMap
        ? application['team'] as JsonMap
        : null;
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text('${team?['name'] ?? user['name'] ?? ''}'),
      subtitle: Text('${application['message'] ?? ''}'),
      trailing: application['status'] == 'pending'
          ? Wrap(
              children: [
                IconButton(
                  onPressed: () => _decide(matching, application, 'accepted'),
                  icon: const Icon(Icons.check, color: Colors.green),
                ),
                IconButton(
                  onPressed: () => _decide(matching, application, 'declined'),
                  icon: const Icon(Icons.close, color: Colors.red),
                ),
              ],
            )
          : Text('${application['status']}'),
    );
  }

  Widget _error(Object? error) => AirmiusPanel(
    children: [
      Text('${error ?? ''}'),
      const SizedBox(height: 8),
      OutlinedButton(onPressed: _reload, child: const Text('Retry')),
    ],
  );

  Future<void> _apply(JsonMap matching, List<JsonMap> teams) async {
    int? teamId;
    if (matching['mode'] == 'team') {
      teamId = await showModalBottomSheet<int>(
        context: context,
        builder: (context) => SafeArea(
          child: ListView(
            shrinkWrap: true,
            children: teams
                .map(
                  (team) => ListTile(
                    title: Text('${team['name']}'),
                    onTap: () => Navigator.pop(context, _int(team['id'])),
                  ),
                )
                .toList(),
          ),
        ),
      );
      if (teamId == null) return;
    }
    await _run(
      () => _client.applyForSportMatching(_int(matching['id']), teamId: teamId),
    );
  }

  Future<void> _decide(JsonMap matching, JsonMap application, String status) =>
      _run(
        () => _client.decideSportMatchingApplication(
          _int(matching['id']),
          _int(application['id']),
          status,
        ),
      );

  Future<void> _run(Future<JsonMap> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (mounted) _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openCreate() async {
    final response = await _future;
    if (!mounted || response == null) return;
    final meta = response['meta'] is JsonMap
        ? response['meta'] as JsonMap
        : const <String, dynamic>{};
    final payload = await showModalBottomSheet<JsonMap>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => _CreateMatchingSheet(
        mode: _mode,
        sports: _maps(meta['sports']),
        teams: _maps(meta['teams']),
      ),
    );
    if (payload != null) {
      await _run(() => _client.createSportMatching(payload));
    }
  }

  static List<JsonMap> _maps(Object? value) =>
      value is List ? value.whereType<JsonMap>().toList() : const [];

  static int _int(Object? value) => int.tryParse('$value') ?? 0;
}

class _CreateMatchingSheet extends StatefulWidget {
  const _CreateMatchingSheet({
    required this.mode,
    required this.sports,
    required this.teams,
  });

  final String mode;
  final List<JsonMap> sports;
  final List<JsonMap> teams;

  @override
  State<_CreateMatchingSheet> createState() => _CreateMatchingSheetState();
}

class _CreateMatchingSheetState extends State<_CreateMatchingSheet> {
  final _title = TextEditingController();
  final _city = TextEditingController();
  final _country = TextEditingController(text: 'DE');
  final _description = TextEditingController();
  final _count = TextEditingController(text: '1');
  final _teamSize = TextEditingController();
  int? _sportId;
  int? _teamId;
  DateTime _startsAt = DateTime.now().add(const Duration(days: 1));

  @override
  void dispose() {
    for (final controller in [
      _title,
      _city,
      _country,
      _description,
      _count,
      _teamSize,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(
      16,
      12,
      16,
      16 + MediaQuery.viewInsetsOf(context).bottom,
    ),
    child: ListView(
      children: [
        Text(
          widget.mode == 'team' ? 'Teamgegner finden' : 'Sportpartner finden',
          style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 16),
        DropdownButtonFormField<int>(
          decoration: const InputDecoration(labelText: 'Sportart'),
          items: widget.sports
              .map(
                (sport) => DropdownMenuItem(
                  value: int.tryParse('${sport['id']}') ?? 0,
                  child: Text('${sport['name']}'),
                ),
              )
              .toList(),
          onChanged: (value) => _sportId = value,
        ),
        if (widget.mode == 'team') ...[
          const SizedBox(height: 10),
          DropdownButtonFormField<int>(
            decoration: const InputDecoration(labelText: 'Dein Team'),
            items: widget.teams
                .map(
                  (team) => DropdownMenuItem(
                    value: int.tryParse('${team['id']}') ?? 0,
                    child: Text('${team['name']}'),
                  ),
                )
                .toList(),
            onChanged: (value) => _teamId = value,
          ),
        ],
        const SizedBox(height: 10),
        TextField(
          controller: _title,
          decoration: const InputDecoration(labelText: 'Titel'),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _city,
          decoration: const InputDecoration(labelText: 'Ort'),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _country,
          textCapitalization: TextCapitalization.characters,
          maxLength: 2,
          decoration: const InputDecoration(labelText: 'Land (ISO)'),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: widget.mode == 'team' ? _teamSize : _count,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            labelText: widget.mode == 'team'
                ? 'Personen pro Team'
                : 'Gesuchte Personen',
          ),
        ),
        const SizedBox(height: 10),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Termin'),
          subtitle: Text(
            MaterialLocalizations.of(context).formatFullDate(_startsAt),
          ),
          trailing: const Icon(Icons.calendar_month),
          onTap: () async {
            final date = await showDatePicker(
              context: context,
              initialDate: _startsAt,
              firstDate: DateTime.now(),
              lastDate: DateTime.now().add(const Duration(days: 730)),
            );
            if (date != null) {
              setState(
                () => _startsAt = DateTime(date.year, date.month, date.day, 10),
              );
            }
          },
        ),
        TextField(
          controller: _description,
          maxLines: 3,
          decoration: const InputDecoration(labelText: 'Beschreibung'),
        ),
        const SizedBox(height: 18),
        FilledButton(onPressed: _submit, child: const Text('Veröffentlichen')),
      ],
    ),
  );

  void _submit() {
    if (_sportId == null ||
        _title.text.trim().isEmpty ||
        _city.text.trim().isEmpty ||
        (widget.mode == 'team' &&
            (_teamId == null || _teamSize.text.isEmpty))) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Bitte alle Pflichtfelder ausfüllen.')),
      );
      return;
    }
    Navigator.pop(context, <String, dynamic>{
      'mode': widget.mode,
      'sport_id': _sportId,
      'team_id': widget.mode == 'team' ? _teamId : null,
      'title': _title.text.trim(),
      'description': _description.text.trim(),
      'city': _city.text.trim(),
      'country_code': _country.text.trim().toUpperCase(),
      'radius_km': 25,
      'starts_at': _startsAt.toUtc().toIso8601String(),
      'participants_needed': widget.mode == 'team'
          ? 1
          : int.tryParse(_count.text) ?? 1,
      'team_size': widget.mode == 'team' ? int.tryParse(_teamSize.text) : null,
      'skill_level': 'all',
    });
  }
}
