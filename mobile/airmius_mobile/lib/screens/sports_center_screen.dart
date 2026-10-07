import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SportsCenterScreen extends StatefulWidget {
  const SportsCenterScreen({super.key});

  @override
  State<SportsCenterScreen> createState() => _SportsCenterScreenState();
}

class _SportsCenterScreenState extends State<SportsCenterScreen> {
  Future<_SportBundle>? _future;
  String _section = 'profiles';
  String _catalogQuery = '';
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_SportBundle> _load() async {
    final responses = await Future.wait([
      _client.sportProfiles(),
      _client.sportCv(),
    ]);
    return _SportBundle(
      profiles: _sportMaps(responses[0]['data']),
      cv: _sportMap(responses[1]['data']),
    );
  }

  void _reload() {
    setState(() {
      _future = _load();
    });
  }

  Future<void> _editProfile(JsonMap profile) async {
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _SportProfileDialog(profile: profile),
    );
    if (payload == null || !mounted) return;
    final sport = _sportMap(profile['sport']);
    setState(() => _busy = true);
    try {
      await _client.updateSportProfile(_sportInt(sport['id']), payload);
      if (!mounted) return;
      _toast(
        AirmiusScope.of(context).t(
          _sportBool(profile['has_profile']) ? 'sports.saved' : 'sports.added',
        ),
      );
      _reload();
    } catch (error) {
      if (mounted) {
        _toast(
          '${AirmiusScope.of(context).t('sports.error')} ${error is AirmiusApiException ? error.userMessage : AirmiusScope.of(context).t('common.errorDetails')}',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _deleteProfile(JsonMap profile) async {
    final t = AirmiusScope.of(context).t;
    final sport = _sportMap(profile['sport']);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('sports.deleteTitle')),
        content: Text(
          t(
            'sports.deleteQuestion',
          ).replaceFirst('{sport}', _sportText(sport['name'])),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('sports.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _busy = true);
    try {
      await _client.deleteSportProfile(_sportInt(sport['id']));
      if (!mounted) return;
      _toast(t('sports.deleted'));
      _reload();
    } catch (error) {
      if (mounted) {
        _toast(
          '${t('sports.error')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _editSkill(JsonMap skill) async {
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _SportSkillDialog(skill: skill),
    );
    if (payload == null || !mounted) return;
    setState(() => _busy = true);
    try {
      await _client.updateSportSkill(_sportInt(skill['id']), payload);
      if (!mounted) return;
      _toast(AirmiusScope.of(context).t('sports.skillSaved'));
      _reload();
    } catch (error) {
      if (mounted) {
        _toast(
          '${AirmiusScope.of(context).t('sports.error')} ${error is AirmiusApiException ? error.userMessage : AirmiusScope.of(context).t('common.errorDetails')}',
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

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('sports.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('sports.reload'),
            onPressed: _busy ? null : _reload,
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('sports.title'),
        subtitle: t('sports.subtitle'),
        child: FutureBuilder<_SportBundle>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _SportLoading();
            }
            if (snapshot.hasError) {
              return _SportError(error: snapshot.error, onRetry: _reload);
            }
            return _content(snapshot.data ?? const _SportBundle());
          },
        ),
      ),
    );
  }

  Widget _content(_SportBundle bundle) {
    final t = AirmiusScope.of(context).t;
    final active = bundle.profiles
        .where((profile) => _sportBool(profile['has_profile']))
        .toList();
    final available = bundle.profiles
        .where((profile) => !_sportBool(profile['has_profile']))
        .toList();
    final quality = _sportMap(bundle.cv['profile_quality']);
    final skills = _sportMaps(
      bundle.cv['top_skills'] ?? bundle.cv['verified_skills'],
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('sports.mySportCv')),
              const SizedBox(height: 8),
              Text(
                _sportText(
                  bundle.cv['headline'],
                  fallback: t('sports.overviewHint'),
                ),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 21,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                t('sports.privacyHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: MetricCard(
                      value: '${active.length}',
                      label: t('sports.profiles'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '${skills.length}',
                      label: t('sports.skills'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '${_sportInt(quality['score'])}%',
                      label: t('sports.readiness'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        SegmentedButton<String>(
          segments: [
            ButtonSegment(
              value: 'profiles',
              icon: Icon(Icons.sports_outlined),
              label: Text(t('sports.profiles')),
            ),
            ButtonSegment(
              value: 'catalog',
              icon: Icon(Icons.add_circle_outline),
              label: Text(t('sports.addSport')),
            ),
            ButtonSegment(
              value: 'skills',
              icon: Icon(Icons.military_tech_outlined),
              label: Text(t('sports.skills')),
            ),
          ],
          selected: {_section},
          showSelectedIcon: false,
          onSelectionChanged: (selection) =>
              setState(() => _section = selection.first),
        ),
        const SizedBox(height: 14),
        if (_section == 'profiles')
          _profilesSection(active)
        else if (_section == 'catalog')
          _catalogSection(available)
        else
          _skillsSection(skills),
      ],
    );
  }

  Widget _profilesSection(List<JsonMap> profiles) {
    final t = AirmiusScope.of(context).t;
    if (profiles.isEmpty) {
      return _SportEmpty(
        icon: Icons.sports_outlined,
        title: t('sports.emptyProfiles'),
        hint: t('sports.emptyProfilesHint'),
        action: AirmiusButton(
          label: t('sports.addFirst'),
          icon: Icons.add_circle_outline,
          onPressed: () => setState(() => _section = 'catalog'),
        ),
      );
    }
    return Column(
      children: [
        for (var index = 0; index < profiles.length; index++) ...[
          _SportProfileCard(
            profile: profiles[index],
            busy: _busy,
            onEdit: () => _editProfile(profiles[index]),
            onDelete: () => _deleteProfile(profiles[index]),
          ),
          if (index < profiles.length - 1) const SizedBox(height: 12),
        ],
      ],
    );
  }

  Widget _catalogSection(List<JsonMap> profiles) {
    final t = AirmiusScope.of(context).t;
    if (profiles.isEmpty) {
      return _SportEmpty(
        icon: Icons.check_circle_outline,
        title: t('sports.allAdded'),
        hint: t('sports.allAddedHint'),
      );
    }
    final query = _catalogQuery.trim().toLowerCase();
    final filteredProfiles = query.isEmpty
        ? profiles
        : profiles.where((profile) {
            final sport = _sportMap(profile['sport']);
            return [
              _sportText(sport['name']),
              _sportText(sport['category']),
              _sportText(sport['slug']),
            ].any((value) => value.toLowerCase().contains(query));
          }).toList();
    return Column(
      children: [
        TextField(
          onChanged: (value) => setState(() => _catalogQuery = value),
          textInputAction: TextInputAction.search,
          decoration: InputDecoration(
            prefixIcon: const Icon(Icons.search),
            suffixIcon: _catalogQuery.isEmpty
                ? null
                : IconButton(
                    tooltip: t('sports.clearSearch'),
                    onPressed: () => setState(() => _catalogQuery = ''),
                    icon: const Icon(Icons.close),
                  ),
            labelText: t('sports.searchSport'),
            hintText: t('sports.searchSportHint'),
          ),
        ),
        const SizedBox(height: 12),
        if (filteredProfiles.isEmpty)
          _SportEmpty(
            icon: Icons.search_off_outlined,
            title: t('sports.noSearchResults'),
            hint: t('sports.noSearchResultsHint'),
          ),
        for (var index = 0; index < filteredProfiles.length; index++) ...[
          AirmiusPanel(
            child: Row(
              children: [
                Icon(
                  Icons.sports_outlined,
                  color: airmiusAccentColor(context),
                  size: 28,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _sportText(
                          _sportMap(filteredProfiles[index]['sport'])['name'],
                        ),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        _sportText(
                          _sportMap(
                            filteredProfiles[index]['sport'],
                          )['category'],
                          fallback: t('sports.sport'),
                        ),
                        style: TextStyle(color: airmiusMutedColor(context)),
                      ),
                    ],
                  ),
                ),
                IconButton.filledTonal(
                  tooltip: t('sports.add'),
                  onPressed: _busy
                      ? null
                      : () => _editProfile(filteredProfiles[index]),
                  icon: Icon(Icons.add),
                ),
              ],
            ),
          ),
          if (index < filteredProfiles.length - 1) const SizedBox(height: 10),
        ],
      ],
    );
  }

  Widget _skillsSection(List<JsonMap> skills) {
    final t = AirmiusScope.of(context).t;
    if (skills.isEmpty) {
      return _SportEmpty(
        icon: Icons.military_tech_outlined,
        title: t('sports.emptySkills'),
        hint: t('sports.emptySkillsHint'),
      );
    }
    return Column(
      children: [
        for (var index = 0; index < skills.length; index++) ...[
          _SkillCard(
            skill: skills[index],
            busy: _busy,
            onEdit: () => _editSkill(skills[index]),
          ),
          if (index < skills.length - 1) const SizedBox(height: 10),
        ],
      ],
    );
  }
}

class _SportProfileCard extends StatelessWidget {
  const _SportProfileCard({
    required this.profile,
    required this.busy,
    required this.onEdit,
    required this.onDelete,
  });

  final JsonMap profile;
  final bool busy;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final sport = _sportMap(profile['sport']);
    final readiness = _sportMap(profile['readiness']);
    final score = _sportInt(readiness['score']);
    final missing = _sportMaps(readiness['missing']);
    return AirmiusPanel(
      borderColor:
          (score >= 100
                  ? Theme.of(context).colorScheme.secondary
                  : airmiusAccentColor(context))
              .withValues(alpha: .45),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: airmiusAccentColor(context).withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(
                  Icons.sports_outlined,
                  color: airmiusAccentColor(context),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _sportText(sport['name'], fallback: t('sports.sport')),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Wrap(
                      spacing: 7,
                      runSpacing: 7,
                      children: [
                        StatusPill(
                          t('sports.status.${_sportText(profile['status'])}'),
                          color: Theme.of(context).colorScheme.secondary,
                        ),
                        StatusPill(
                          t(
                            'sports.level.${_sportText(profile['experience_level'])}',
                          ),
                        ),
                        StatusPill(
                          t(
                            'sports.visibility.${_sportText(profile['visibility'])}',
                          ),
                          color: Theme.of(context).colorScheme.tertiary,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              PopupMenuButton<String>(
                enabled: !busy,
                tooltip: t('sports.actions'),
                onSelected: (action) =>
                    action == 'edit' ? onEdit() : onDelete(),
                itemBuilder: (context) => [
                  PopupMenuItem(
                    value: 'edit',
                    child: ListTile(
                      leading: Icon(Icons.edit_outlined),
                      title: Text(t('sports.edit')),
                      contentPadding: EdgeInsets.zero,
                    ),
                  ),
                  PopupMenuItem(
                    value: 'delete',
                    child: ListTile(
                      leading: Icon(
                        Icons.delete_outline,
                        color: Theme.of(context).colorScheme.error,
                      ),
                      title: Text(t('sports.delete')),
                      contentPadding: EdgeInsets.zero,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(99),
                  child: LinearProgressIndicator(
                    value: score.clamp(0, 100) / 100,
                    minHeight: 10,
                    backgroundColor: airmiusSurfaceSoftColor(context),
                    valueColor: AlwaysStoppedAnimation<Color>(
                      score >= 100
                          ? Theme.of(context).colorScheme.secondary
                          : airmiusAccentColor(context),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Text(
                '$score%',
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
          if (missing.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              t(
                'sports.missingFields',
              ).replaceFirst('{count}', '${missing.length}'),
              style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
            ),
          ],
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('sports.editProfile'),
            icon: Icons.edit_outlined,
            secondary: true,
            onPressed: busy ? null : onEdit,
          ),
        ],
      ),
    );
  }
}

class _SkillCard extends StatelessWidget {
  const _SkillCard({
    required this.skill,
    required this.busy,
    required this.onEdit,
  });

  final JsonMap skill;
  final bool busy;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final sport = _sportMap(skill['sport']);
    final verification = _sportMap(skill['verification']);
    return AirmiusPanel(
      child: Row(
        children: [
          Icon(
            Icons.military_tech_outlined,
            color: Theme.of(context).colorScheme.tertiary,
            size: 30,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _sportText(skill['name'], fallback: t('sports.skill')),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    _sportText(sport['name']),
                    t(
                      'sports.skillLevel.${_sportText(skill['self_level'], fallback: 'developing')}',
                    ),
                  ].where((value) => value.isNotEmpty).join(' · '),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(
                      t(
                        'sports.verification.${_sportText(verification['status'], fallback: 'self_reported')}',
                      ),
                      color: _sportText(verification['status']) == 'verified'
                          ? Theme.of(context).colorScheme.secondary
                          : airmiusAccentColor(context),
                    ),
                    if (_sportInt(skill['endorsements_count']) > 0)
                      StatusPill(
                        t('sports.endorsements').replaceFirst(
                          '{count}',
                          '${_sportInt(skill['endorsements_count'])}',
                        ),
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                  ],
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: t('sports.editSkill'),
            onPressed: busy ? null : onEdit,
            icon: Icon(Icons.edit_outlined),
          ),
        ],
      ),
    );
  }
}

class _SportProfileDialog extends StatefulWidget {
  const _SportProfileDialog({required this.profile});

  final JsonMap profile;

  @override
  State<_SportProfileDialog> createState() => _SportProfileDialogState();
}

class _SportProfileDialogState extends State<_SportProfileDialog> {
  late String _status;
  late String _level;
  late String _visibility;
  late final Map<String, TextEditingController> _controllers;
  late final Map<String, String> _metricVisibility;
  late final Set<String> _unknown;

  List<JsonMap> get _fields => _sportMaps(widget.profile['fields']);

  @override
  void initState() {
    super.initState();
    _status = _sportText(widget.profile['status'], fallback: 'active');
    _level = _sportText(
      widget.profile['experience_level'],
      fallback: 'beginner',
    );
    _visibility = _sportText(widget.profile['visibility'], fallback: 'private');
    final metrics = _sportMap(widget.profile['metrics']);
    final visibility = _sportMap(widget.profile['metric_visibility']);
    _unknown = (metrics['_unknown_fields'] is List)
        ? (metrics['_unknown_fields'] as List).map((value) => '$value').toSet()
        : <String>{};
    _controllers = {
      for (final field in _fields)
        _sportText(field['key']): TextEditingController(
          text: _sportText(field['type']) == 'date'
              ? formatAirmiusDate(
                  parseAirmiusDate(metrics[_sportText(field['key'])]?.toString()),
                )
              : _sportText(metrics[_sportText(field['key'])]),
        ),
    };
    _metricVisibility = {
      for (final field in _fields)
        _sportText(field['key']): _sportText(
          visibility[_sportText(field['key'])],
          fallback: _sportText(
            field['default_visibility'],
            fallback: 'private',
          ),
        ),
    };
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final sport = _sportMap(widget.profile['sport']);
    return AlertDialog(
      title: Text(
        t('sports.profileFor').replaceFirst(
          '{sport}',
          _sportText(sport['name'], fallback: t('sports.sport')),
        ),
      ),
      content: SizedBox(
        width: 620,
        child: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                t('sports.profileHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _status,
                decoration: InputDecoration(labelText: t('sports.status')),
                items: _statusValues
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(t('sports.status.$value')),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _status = value!),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _level,
                decoration: InputDecoration(
                  labelText: t('sports.experienceLevel'),
                ),
                items: _levelValues
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(t('sports.level.$value')),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _level = value!),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _visibility,
                decoration: InputDecoration(labelText: t('sports.visibility')),
                items: _visibilityValues
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(t('sports.visibility.$value')),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _visibility = value!),
              ),
              const SizedBox(height: 18),
              Text(
                t('sports.performanceData'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                t('sports.performanceHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 12),
              for (final field in _fields) _field(context, field),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton.icon(
          onPressed: () {
            Navigator.pop(context, {
              'status': _status,
              'experience_level': _level,
              'visibility': _visibility,
              'metrics': {
                for (final field in _fields)
                  _sportText(field['key']): _sportText(field['type']) == 'date'
                      ? formatAirmiusApiDate(
                          parseAirmiusDate(
                            _controllers[_sportText(field['key'])]?.text,
                          ),
                        )
                      : _controllers[_sportText(field['key'])]?.text.trim(),
              },
              'metric_visibility': _metricVisibility,
              'unknown_metrics': {
                for (final field in _fields)
                  _sportText(field['key']): _unknown.contains(
                    _sportText(field['key']),
                  ),
              },
            });
          },
          icon: Icon(Icons.save_outlined),
          label: Text(t('save')),
        ),
      ],
    );
  }

  Widget _field(BuildContext context, JsonMap field) {
    final t = AirmiusScope.of(context).t;
    final key = _sportText(field['key']);
    final required = _sportBool(field['required']);
    final unknown = _unknown.contains(key);
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: AirmiusPanel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextFormField(
              controller: _controllers[key],
              enabled: !unknown,
              minLines: _sportText(field['type']) == 'textarea' ? 2 : 1,
              maxLines: _sportText(field['type']) == 'textarea' ? 4 : 1,
              keyboardType: _sportText(field['type']) == 'number'
                  ? const TextInputType.numberWithOptions(decimal: true)
                  : _sportText(field['type']) == 'date'
                  ? TextInputType.datetime
                  : TextInputType.text,
              inputFormatters: _sportText(field['type']) == 'date'
                  ? const [AirmiusDateInputFormatter()]
                  : null,
              decoration: InputDecoration(
                labelText:
                    '${_sportText(field['label'], fallback: key)}${required ? ' *' : ''}',
                helperText: _sportText(field['help']),
                suffixText: _sportText(field['unit']),
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 10,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                SizedBox(
                  width: 185,
                  child: DropdownButtonFormField<String>(
                    initialValue: _metricVisibility[key],
                    decoration: InputDecoration(
                      labelText: t('sports.metricVisibility'),
                      isDense: true,
                    ),
                    items: _visibilityValues
                        .map(
                          (value) => DropdownMenuItem(
                            value: value,
                            child: Text(t('sports.visibility.$value')),
                          ),
                        )
                        .toList(),
                    onChanged: (value) =>
                        setState(() => _metricVisibility[key] = value!),
                  ),
                ),
                FilterChip(
                  selected: unknown,
                  label: Text(t('sports.unknown')),
                  onSelected: (selected) {
                    setState(() {
                      if (selected) {
                        _unknown.add(key);
                        _controllers[key]?.clear();
                      } else {
                        _unknown.remove(key);
                      }
                    });
                  },
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _SportSkillDialog extends StatefulWidget {
  const _SportSkillDialog({required this.skill});

  final JsonMap skill;

  @override
  State<_SportSkillDialog> createState() => _SportSkillDialogState();
}

class _SportSkillDialogState extends State<_SportSkillDialog> {
  late String _level;
  bool _visible = true;
  late final TextEditingController _notes;

  @override
  void initState() {
    super.initState();
    _level = _sportText(widget.skill['self_level'], fallback: 'developing');
    _visible = widget.skill.containsKey('is_visible')
        ? _sportBool(widget.skill['is_visible'])
        : true;
    _notes = TextEditingController(text: _sportText(widget.skill['notes']));
  }

  @override
  void dispose() {
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        _sportText(widget.skill['name'], fallback: t('sports.skill')),
      ),
      content: SizedBox(
        width: 460,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _level,
              decoration: InputDecoration(labelText: t('sports.skillLevel')),
              items: _skillLevelValues
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('sports.skillLevel.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _level = value!),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _notes,
              minLines: 2,
              maxLines: 4,
              maxLength: 500,
              decoration: InputDecoration(labelText: t('sports.notes')),
            ),
            Material(
              color: Colors.transparent,
              child: SwitchListTile(
                value: _visible,
                contentPadding: EdgeInsets.zero,
                title: Text(t('sports.skillVisible')),
                subtitle: Text(t('sports.skillVisibleHint')),
                onChanged: (value) => setState(() => _visible = value),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(context, {
            'self_level': _level,
            'is_visible': _visible,
            'notes': _notes.text.trim(),
          }),
          child: Text(t('save')),
        ),
      ],
    );
  }
}

class _SportEmpty extends StatelessWidget {
  const _SportEmpty({
    required this.icon,
    required this.title,
    required this.hint,
    this.action,
  });

  final IconData icon;
  final String title;
  final String hint;
  final Widget? action;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    child: Column(
      children: [
        Icon(icon, color: airmiusAccentColor(context), size: 42),
        const SizedBox(height: 12),
        Text(
          title,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          hint,
          textAlign: TextAlign.center,
          style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
        ),
        if (action != null) ...[const SizedBox(height: 14), action!],
      ],
    ),
  );
}

class _SportLoading extends StatelessWidget {
  const _SportLoading();

  @override
  Widget build(BuildContext context) => const AirmiusPanel(
    child: Padding(
      padding: EdgeInsets.all(30),
      child: Center(child: CircularProgressIndicator()),
    ),
  );
}

class _SportError extends StatelessWidget {
  const _SportError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final message = error is AirmiusApiException
        ? (error as AirmiusApiException).userMessage
        : t('common.errorDetails');
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.error.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('sports.loadError'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(message, style: TextStyle(color: airmiusMutedColor(context))),
          const SizedBox(height: 14),
          AirmiusButton(
            label: t('sports.retry'),
            icon: Icons.refresh_outlined,
            secondary: true,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

class _SportBundle {
  const _SportBundle({this.profiles = const [], this.cv = const {}});

  final List<JsonMap> profiles;
  final JsonMap cv;
}

const _statusValues = ['active', 'wants_to_learn', 'coach', 'interested'];
const _levelValues = [
  'beginner',
  'intermediate',
  'advanced',
  'expert',
  'elite',
];
const _visibilityValues = ['private', 'trainer', 'public'];
const _skillLevelValues = [
  'learning',
  'developing',
  'solid',
  'strong',
  'expert',
];

JsonMap _sportMap(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<JsonMap> _sportMaps(Object? value) => value is List
    ? value.map(_sportMap).where((item) => item.isNotEmpty).toList()
    : const [];

String _sportText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

int _sportInt(Object? value) =>
    value is num ? value.round() : int.tryParse('$value') ?? 0;

bool _sportBool(Object? value) =>
    value == true || value == 1 || '$value'.toLowerCase() == 'true';
