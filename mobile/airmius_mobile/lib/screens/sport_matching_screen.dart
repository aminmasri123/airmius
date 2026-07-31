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
  final _sportController = TextEditingController();
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
    _sportController.dispose();
    super.dispose();
  }

  Future<JsonMap> _load() => _client.sportMatchings(
    mode: _mode,
    city: _cityController.text,
    sportId: _sportId,
  );

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

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
                  _MatchingSportAutocomplete(
                    controller: _sportController,
                    sports: sports,
                    hintText: _c(
                      'Wunschsport suchen, z. B. Laufen',
                      'Search for a sport, e.g. running',
                      'Rechercher un sport, ex. course',
                      'ابحث عن الرياضة المطلوبة، مثل الجري',
                    ),
                    allSportsLabel: _c(
                      'Alle Sportarten',
                      'All sports',
                      'Tous les sports',
                      'كل الرياضات',
                    ),
                    onTextChanged: () => _sportId = null,
                    onSelected: (sport) {
                      _sportId = sport == null ? null : _int(sport['id']);
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
        if ('${matching['location_name'] ?? ''}'.trim().isNotEmpty)
          Text('🏟️ ${matching['location_name']}'),
        if ('${matching['address'] ?? ''}'.trim().isNotEmpty)
          Text('Adresse: ${matching['address']}'),
        Text(
          '🗓 ${startsAt == null ? '' : _formatMatchingDateTime(context, startsAt.toLocal())}',
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

  Widget _error(Object? error) {
    final message = error is AirmiusApiException
        ? error.statusCode >= 500
              ? _c(
                  'Sport-Matching konnte gerade nicht geladen werden. Bitte versuche es erneut.',
                  'Sport matching could not be loaded right now. Please try again.',
                  'Le matching sportif ne peut pas être chargé actuellement. Réessayez.',
                  'تعذر تحميل المطابقة الرياضية حاليًا. يرجى المحاولة مرة أخرى.',
                )
              : error.userMessage
        : _c(
            'Bitte prüfe deine Verbindung und versuche es erneut.',
            'Check your connection and try again.',
            'Vérifiez votre connexion puis réessayez.',
            'تحقق من اتصالك ثم حاول مرة أخرى.',
          );
    return AirmiusPanel(
      children: [
        Icon(
          Icons.cloud_off_outlined,
          color: Theme.of(context).colorScheme.error,
          size: 34,
        ),
        const SizedBox(height: 10),
        Text(
          message,
          style: const TextStyle(fontWeight: FontWeight.w800, height: 1.35),
        ),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: _reload,
          icon: const Icon(Icons.refresh),
          label: Text(
            _c('Erneut versuchen', 'Try again', 'Réessayer', 'إعادة المحاولة'),
          ),
        ),
      ],
    );
  }

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
        final message = error is AirmiusApiException
            ? error.userMessage
            : _c(
                'Die Aktion konnte nicht abgeschlossen werden.',
                'The action could not be completed.',
                'L’action n’a pas pu être terminée.',
                'تعذر إكمال الإجراء.',
              );
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(message)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openCreate() async {
    JsonMap? response;
    try {
      response = await _future;
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              _c(
                'Sportarten und Teams konnten nicht geladen werden. Bitte versuche es erneut.',
                'Sports and teams could not be loaded. Please try again.',
                'Les sports et les équipes n’ont pas pu être chargés. Réessayez.',
                'تعذر تحميل الرياضات والفرق. يرجى المحاولة مرة أخرى.',
              ),
            ),
          ),
        );
      }
      return;
    }
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
        defaultCountryCode: AirmiusServicesScope.of(
          context,
        ).authState.user?.country,
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
    this.defaultCountryCode,
  });

  final String mode;
  final List<JsonMap> sports;
  final List<JsonMap> teams;
  final String? defaultCountryCode;

  @override
  State<_CreateMatchingSheet> createState() => _CreateMatchingSheetState();
}

class _CreateMatchingSheetState extends State<_CreateMatchingSheet> {
  final _title = TextEditingController();
  final _location = TextEditingController();
  final _postalCode = TextEditingController();
  final _locationName = TextEditingController();
  final _address = TextEditingController();
  final _sport = TextEditingController();
  final _description = TextEditingController();
  final _count = TextEditingController(text: '1');
  final _teamSize = TextEditingController();
  int? _sportId;
  int? _teamId;
  late String _countryCode;
  DateTime _startsAt = DateTime.now().add(const Duration(days: 1));

  @override
  void initState() {
    super.initState();
    final requested = widget.defaultCountryCode?.trim().toUpperCase();
    _countryCode =
        _matchingCountries.any((country) => country.code == requested)
        ? requested!
        : 'DE';
  }

  @override
  void dispose() {
    for (final controller in [
      _title,
      _location,
      _postalCode,
      _locationName,
      _address,
      _sport,
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
        _MatchingSportAutocomplete(
          controller: _sport,
          sports: widget.sports,
          hintText: _copy(
            'Wunschsport suchen',
            'Search for a sport',
            'Rechercher un sport',
            'ابحث عن الرياضة المطلوبة',
          ),
          onTextChanged: () => setState(() => _sportId = null),
          onSelected: (sport) => setState(
            () => _sportId = sport == null ? null : _int(sport['id']),
          ),
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
        TextField(
          controller: _title,
          decoration: const InputDecoration(labelText: 'Titel (optional)'),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _location,
          textCapitalization: TextCapitalization.words,
          decoration: InputDecoration(
            labelText: _copy(
              'Stadt / Ort',
              'City',
              'Ville',
              'المدينة',
            ),
            hintText: _copy(
              'z. B. Kenitra',
              'e.g. Kenitra',
              'ex. Kénitra',
              'مثال: القنيطرة',
            ),
            prefixIcon: const Icon(Icons.location_on_outlined),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _postalCode,
          keyboardType: TextInputType.streetAddress,
          decoration: const InputDecoration(
            labelText: 'PLZ (optional)',
            hintText: 'z. B. 14000',
            prefixIcon: Icon(Icons.local_post_office_outlined),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _locationName,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(
            labelText: 'Sportstätte / Treffpunkt (optional)',
            hintText: 'z. B. Stadtpark oder Court 2',
            prefixIcon: Icon(Icons.place_outlined),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _address,
          textCapitalization: TextCapitalization.words,
          decoration: const InputDecoration(
            labelText: 'Adresse (optional)',
            hintText: 'Straße und Hausnummer',
            prefixIcon: Icon(Icons.signpost_outlined),
          ),
        ),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          initialValue: _countryCode,
          isExpanded: true,
          dropdownColor: airmiusSurfaceColor(context),
          decoration: InputDecoration(
            labelText: _copy('Land', 'Country', 'Pays', 'الدولة'),
            prefixIcon: const Icon(Icons.public_outlined),
          ),
          items: [
            for (final country in _matchingCountries)
              DropdownMenuItem(
                value: country.code,
                child: Text(country.label(AirmiusScope.of(context).language)),
              ),
          ],
          onChanged: (value) {
            if (value != null) setState(() => _countryCode = value);
          },
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
          title: const Text('Datum und Uhrzeit'),
          subtitle: Text(
            _formatMatchingDateTime(context, _startsAt),
          ),
          trailing: const Icon(Icons.schedule),
          onTap: () async {
            final date = await showDatePicker(
              context: context,
              initialDate: _startsAt,
              firstDate: DateTime.now(),
              lastDate: DateTime.now().add(const Duration(days: 730)),
            );
            if (date != null) {
              if (!context.mounted) return;
              final time = await showTimePicker(
                context: context,
                initialTime: TimeOfDay.fromDateTime(_startsAt),
              );
              if (time == null) return;
              setState(
                () => _startsAt = DateTime(
                  date.year,
                  date.month,
                  date.day,
                  time.hour,
                  time.minute,
                ),
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
        _location.text.trim().isEmpty ||
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
      if (_title.text.trim().isNotEmpty) 'title': _title.text.trim(),
      'description': _description.text.trim(),
      'city': _location.text.trim(),
      'postal_code': _postalCode.text.trim(),
      'location_name': _locationName.text.trim(),
      'address': _address.text.trim(),
      'country_code': _countryCode,
      'radius_km': 25,
      'starts_at': _startsAt.toUtc().toIso8601String(),
      'participants_needed': widget.mode == 'team'
          ? 1
          : int.tryParse(_count.text) ?? 1,
      'team_size': widget.mode == 'team' ? int.tryParse(_teamSize.text) : null,
      'skill_level': 'all',
    });
  }

  String _copy(String de, String en, String fr, String ar) =>
      switch (AirmiusScope.of(context).language) {
        AirmiusLanguage.de => de,
        AirmiusLanguage.en => en,
        AirmiusLanguage.fr => fr,
        AirmiusLanguage.ar => ar,
      };

  static int _int(Object? value) => int.tryParse('$value') ?? 0;
}

String _formatMatchingDateTime(BuildContext context, DateTime value) {
  final localizations = MaterialLocalizations.of(context);
  return '${localizations.formatMediumDate(value)} · ${localizations.formatTimeOfDay(TimeOfDay.fromDateTime(value), alwaysUse24HourFormat: true)} Uhr';
}

class _MatchingSportAutocomplete extends StatelessWidget {
  const _MatchingSportAutocomplete({
    required this.controller,
    required this.sports,
    required this.hintText,
    required this.onSelected,
    this.allSportsLabel,
    this.onTextChanged,
  });

  final TextEditingController controller;
  final List<JsonMap> sports;
  final String hintText;
  final String? allSportsLabel;
  final ValueChanged<JsonMap?> onSelected;
  final VoidCallback? onTextChanged;

  @override
  Widget build(BuildContext context) {
    return Autocomplete<JsonMap>(
      displayStringForOption: (sport) => '${sport['name'] ?? ''}',
      optionsBuilder: (value) {
        final query = value.text.trim().toLowerCase();
        if (query.isEmpty) return sports.take(12);
        return sports
            .where((sport) {
              final name = '${sport['name'] ?? ''}'.toLowerCase();
              final slug = '${sport['slug'] ?? ''}'.toLowerCase();
              return name.contains(query) || slug.contains(query);
            })
            .take(12);
      },
      onSelected: (sport) {
        controller.text = '${sport['name'] ?? ''}';
        onSelected(sport);
      },
      fieldViewBuilder: (context, textController, focusNode, onFieldSubmitted) {
        if (textController.text.isEmpty && controller.text.isNotEmpty) {
          textController.text = controller.text;
        }
        return TextField(
          controller: textController,
          focusNode: focusNode,
          textInputAction: TextInputAction.search,
          decoration: InputDecoration(
            labelText: hintText,
            prefixIcon: const Icon(Icons.sports_outlined),
            suffixIcon: textController.text.isEmpty
                ? const Icon(Icons.search)
                : IconButton(
                    tooltip: allSportsLabel,
                    onPressed: () {
                      textController.clear();
                      controller.clear();
                      onSelected(null);
                    },
                    icon: const Icon(Icons.clear),
                  ),
          ),
          onChanged: (value) {
            controller.text = value;
            onTextChanged?.call();
          },
        );
      },
      optionsViewBuilder: (context, onOptionSelected, options) {
        final items = options.toList(growable: false);
        final width = (MediaQuery.sizeOf(context).width - 32)
            .clamp(220.0, 560.0)
            .toDouble();
        return Align(
          alignment: AlignmentDirectional.topStart,
          child: Material(
            color: Colors.transparent,
            child: Container(
              width: width,
              margin: const EdgeInsets.only(top: 6),
              constraints: const BoxConstraints(maxHeight: 300),
              decoration: BoxDecoration(
                color: airmiusSurfaceColor(context),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: airmiusBorderColor(context)),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.24),
                    blurRadius: 18,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: items.isEmpty
                  ? const SizedBox.shrink()
                  : ListView.separated(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      shrinkWrap: true,
                      itemCount: items.length,
                      separatorBuilder: (_, _) => Divider(
                        height: 1,
                        color: airmiusBorderColor(context),
                      ),
                      itemBuilder: (context, index) {
                        final sport = items[index];
                        return Material(
                          color: Colors.transparent,
                          child: ListTile(
                            leading: const Icon(Icons.sports_outlined),
                            title: Text(
                              '${sport['name'] ?? ''}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            onTap: () => onOptionSelected(sport),
                          ),
                        );
                      },
                    ),
            ),
          ),
        );
      },
    );
  }
}

const _matchingCountries = <_MatchingCountry>[
  _MatchingCountry('DE', 'Deutschland', 'Germany', 'Allemagne', 'ألمانيا'),
  _MatchingCountry('MA', 'Marokko', 'Morocco', 'Maroc', 'المغرب'),
  _MatchingCountry('AT', 'Österreich', 'Austria', 'Autriche', 'النمسا'),
  _MatchingCountry('CH', 'Schweiz', 'Switzerland', 'Suisse', 'سويسرا'),
  _MatchingCountry('FR', 'Frankreich', 'France', 'France', 'فرنسا'),
  _MatchingCountry('BE', 'Belgien', 'Belgium', 'Belgique', 'بلجيكا'),
  _MatchingCountry('NL', 'Niederlande', 'Netherlands', 'Pays-Bas', 'هولندا'),
  _MatchingCountry('LU', 'Luxemburg', 'Luxembourg', 'Luxembourg', 'لوكسمبورغ'),
  _MatchingCountry('ES', 'Spanien', 'Spain', 'Espagne', 'إسبانيا'),
  _MatchingCountry('PT', 'Portugal', 'Portugal', 'Portugal', 'البرتغال'),
  _MatchingCountry('IT', 'Italien', 'Italy', 'Italie', 'إيطاليا'),
  _MatchingCountry(
    'GB',
    'Großbritannien',
    'United Kingdom',
    'Royaume-Uni',
    'المملكة المتحدة',
  ),
  _MatchingCountry('IE', 'Irland', 'Ireland', 'Irlande', 'أيرلندا'),
  _MatchingCountry('DK', 'Dänemark', 'Denmark', 'Danemark', 'الدنمارك'),
  _MatchingCountry('SE', 'Schweden', 'Sweden', 'Suède', 'السويد'),
  _MatchingCountry('NO', 'Norwegen', 'Norway', 'Norvège', 'النرويج'),
  _MatchingCountry('PL', 'Polen', 'Poland', 'Pologne', 'بولندا'),
  _MatchingCountry('CZ', 'Tschechien', 'Czechia', 'Tchéquie', 'التشيك'),
  _MatchingCountry('TR', 'Türkei', 'Türkiye', 'Turquie', 'تركيا'),
  _MatchingCountry(
    'US',
    'USA',
    'United States',
    'États-Unis',
    'الولايات المتحدة',
  ),
  _MatchingCountry('CA', 'Kanada', 'Canada', 'Canada', 'كندا'),
];

class _MatchingCountry {
  const _MatchingCountry(this.code, this.de, this.en, this.fr, this.ar);

  final String code;
  final String de;
  final String en;
  final String fr;
  final String ar;

  String label(AirmiusLanguage language) => switch (language) {
    AirmiusLanguage.de => de,
    AirmiusLanguage.en => en,
    AirmiusLanguage.fr => fr,
    AirmiusLanguage.ar => ar,
  };
}
