import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/country_field.dart';
import 'recruiting_pipeline_screen.dart';

class ClubJobsScreen extends StatefulWidget {
  const ClubJobsScreen({
    super.key,
    required this.clubId,
    required this.clubName,
  });

  final int clubId;
  final String clubName;

  @override
  State<ClubJobsScreen> createState() => _ClubJobsScreenState();
}

class _ClubJobsScreenState extends State<ClubJobsScreen> {
  Future<List<Map<String, dynamic>>>? _future;

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

  Future<List<Map<String, dynamic>>> _load() async {
    final response = await _client.clubJobs(widget.clubId);
    final data = response['data'];
    if (data is! List) return [];
    return data
        .whereType<Map>()
        .map((job) => Map<String, dynamic>.from(job))
        .toList();
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _openJobForm([Map<String, dynamic>? job]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ClubJobFormScreen(
          clubId: widget.clubId,
          clubName: widget.clubName,
          job: job,
        ),
      ),
    );

    if (saved == true && mounted) {
      await _reload();
    }
  }

  Future<void> _deleteJob(Map<String, dynamic> job) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Stelle löschen'),
        content: Text('"${job['title'] ?? ''}" wirklich löschen?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Löschen'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    await _client.deleteClubJob(widget.clubId, _integer(job['id']));
    if (mounted) await _reload();
  }

  void _openPipeline() {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const RecruitingPipelineScreen()));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Jobs & Bewerbungen'),
        actions: [
          IconButton(
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openJobForm(),
        icon: const Icon(Icons.add),
        label: const Text('Stelle erstellen'),
      ),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return EmptyPanel('Die Stellen konnten nicht geladen werden.');
          }
          final jobs = snapshot.data ?? const [];
          return PageFrame(
            title: 'Jobs & Bewerbungen',
            subtitle: widget.clubName,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        t('recruitingPipeline.title'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 8),
                      Text(t('recruitingPipeline.subtitle')),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: _openPipeline,
                        icon: const Icon(Icons.work_history_outlined),
                        label: Text(t('recruitingPipeline.title')),
                      ),
                    ],
                  ),
                ),
                if (jobs.isEmpty)
                  const Padding(
                    padding: EdgeInsets.only(top: 10),
                    child: EmptyPanel('Noch keine Stellen angelegt.'),
                  )
                else ...[
                  const SizedBox(height: 10),
                  for (final job in jobs)
                    _JobCard(
                      job: job,
                      onEdit: () => _openJobForm(job),
                      onDelete: () => _deleteJob(job),
                    ),
                ],
                const SizedBox(height: 72),
              ],
            ),
          );
        },
      ),
    );
  }
}

class ClubJobFormScreen extends StatefulWidget {
  const ClubJobFormScreen({
    super.key,
    required this.clubId,
    required this.clubName,
    this.job,
  });

  final int clubId;
  final String clubName;
  final Map<String, dynamic>? job;

  @override
  State<ClubJobFormScreen> createState() => _ClubJobFormScreenState();
}

class _ClubJobFormScreenState extends State<ClubJobFormScreen> {
  late final TextEditingController _title;
  late final TextEditingController _city;
  late final TextEditingController _postalCode;
  late final TextEditingController _address;
  late final TextEditingController _country;
  late final TextEditingController _contactEmail;
  late final TextEditingController _applicationUrl;
  late final TextEditingController _description;
  DateTime? _startsAt;
  DateTime? _endsAt;
  late String _type;
  late bool _isPublished;
  bool _saving = false;
  String? _error;

  bool get _isEditing => widget.job != null;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    final job = widget.job;
    _title = TextEditingController(text: '${job?['title'] ?? ''}');
    final locationParts = _splitLocation(job?['location']);
    _city = TextEditingController(text: locationParts.city);
    _postalCode = TextEditingController(text: locationParts.postalCode);
    _address = TextEditingController(text: locationParts.address);
    _country = TextEditingController(
      text: locationParts.country.isEmpty ? 'DE' : locationParts.country,
    );
    _contactEmail = TextEditingController(
      text: '${job?['contact_email'] ?? ''}',
    );
    _applicationUrl = TextEditingController(
      text: '${job?['application_url'] ?? ''}',
    );
    _description = TextEditingController(text: '${job?['description'] ?? ''}');
    _startsAt = _parseDate(job?['starts_at']);
    _endsAt = _parseDate(job?['ends_at']);
    _type = '${job?['type'] ?? 'volunteer'}';
    _isPublished = job?['is_published'] == true;
  }

  @override
  void dispose() {
    _title.dispose();
    _city.dispose();
    _postalCode.dispose();
    _address.dispose();
    _country.dispose();
    _contactEmail.dispose();
    _applicationUrl.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (_title.text.trim().isEmpty || _description.text.trim().isEmpty) {
      setState(() => _error = 'Bitte gib Titel und Beschreibung ein.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });

    final body = {
      'title': _title.text.trim(),
      'type': _type,
      'location': _composeLocation(
        city: _city.text,
        postalCode: _postalCode.text,
        address: _address.text,
        country: _country.text,
      ),
      'contact_email': _contactEmail.text.trim().isEmpty
          ? null
          : _contactEmail.text.trim(),
      'application_url': _applicationUrl.text.trim().isEmpty
          ? null
          : _applicationUrl.text.trim(),
      'description': _description.text.trim(),
      'starts_at': _apiDate(_startsAt),
      'ends_at': _apiDate(_endsAt),
      'is_published': _isPublished,
    };

    try {
      if (_isEditing) {
        await _client.updateClubJob(
          widget.clubId,
          _integer(widget.job?['id']),
          body,
        );
      } else {
        await _client.createClubJob(widget.clubId, body);
      }
      if (mounted) Navigator.pop(context, true);
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _saving = false;
        _error = 'Die Stelle konnte nicht gespeichert werden.';
      });
    }
  }

  Future<void> _pickDate({required bool endDate}) async {
    final current = endDate ? _endsAt : _startsAt;
    final selected = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime.now(),
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (endDate) {
        _endsAt = selected;
      } else {
        _startsAt = selected;
        if (_endsAt != null && _endsAt!.isBefore(selected)) {
          _endsAt = selected;
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final title = _isEditing ? 'Stelle bearbeiten' : 'Stelle erstellen';
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(16, 8, 16, 16),
        child: FilledButton.icon(
          onPressed: _saving ? null : _save,
          icon: _saving
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.save_outlined),
          label: Text(_saving ? 'Speichern ...' : t('common.save')),
        ),
      ),
      body: PageFrame(
        title: title,
        subtitle: widget.clubName,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextField(
                    controller: _title,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(labelText: 'Titel'),
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<String>(
                    initialValue: _type,
                    decoration: const InputDecoration(labelText: 'Typ'),
                    items: [
                      DropdownMenuItem(
                        value: 'volunteer',
                        child: Text(t('recruitingMobile.volunteer')),
                      ),
                      DropdownMenuItem(
                        value: 'professional',
                        child: Text(t('recruitingMobile.professional')),
                      ),
                    ],
                    onChanged: _saving
                        ? null
                        : (value) =>
                              setState(() => _type = value ?? 'volunteer'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _city,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(labelText: 'Ort'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _postalCode,
                    keyboardType: TextInputType.number,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Postleitzahl',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _address,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(labelText: 'Adresse'),
                  ),
                  const SizedBox(height: 12),
                  CountryField(
                    controller: _country,
                    label: 'Land',
                    required: true,
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _contactEmail,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Kontakt-E-Mail',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _applicationUrl,
                    keyboardType: TextInputType.url,
                    textInputAction: TextInputAction.next,
                    decoration: const InputDecoration(
                      labelText: 'Direktbewerbungs-Link',
                      hintText: 'https://...',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _description,
                    minLines: 5,
                    maxLines: 9,
                    decoration: const InputDecoration(
                      labelText: 'Beschreibung',
                      alignLabelWithHint: true,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _DateTile(
                    label: 'Startdatum',
                    value: _startsAt,
                    onTap: _saving ? null : () => _pickDate(endDate: false),
                    onClear: _saving || _startsAt == null
                        ? null
                        : () => setState(() => _startsAt = null),
                  ),
                  const SizedBox(height: 12),
                  _DateTile(
                    label: 'Bewerbungsfrist',
                    value: _endsAt,
                    onTap: _saving ? null : () => _pickDate(endDate: true),
                    onClear: _saving || _endsAt == null
                        ? null
                        : () => setState(() => _endsAt = null),
                  ),
                  const SizedBox(height: 12),
                  SwitchListTile(
                    value: _isPublished,
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Direkt veröffentlichen'),
                    subtitle: const Text(
                      'Wenn deaktiviert, bleibt die Stelle als Entwurf gespeichert.',
                    ),
                    onChanged: _saving
                        ? null
                        : (value) => setState(() => _isPublished = value),
                  ),
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 92),
          ],
        ),
      ),
    );
  }
}

class _JobCard extends StatelessWidget {
  const _JobCard({
    required this.job,
    required this.onEdit,
    required this.onDelete,
  });

  final Map<String, dynamic> job;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final published = job['is_published'] == true;
    final deadline = _parseDate(job['ends_at']);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  '${job['title'] ?? ''}',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(published ? 'Veröffentlicht' : 'Entwurf'),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            [
              '${job['type'] ?? ''}' == 'professional'
                  ? 'Beruflich'
                  : 'Ehrenamt',
              if ('${job['location'] ?? ''}'.trim().isNotEmpty)
                '${job['location']}',
              if (deadline != null) 'Frist ${_displayDate(deadline)}',
              '${job['interests_count'] ?? 0} Bewerbungen',
            ].join(' · '),
          ),
          const SizedBox(height: 10),
          Text(
            '${job['description'] ?? ''}',
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: onDelete,
                  icon: const Icon(Icons.delete_outline),
                  label: const Text('Löschen'),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: FilledButton.icon(
                  onPressed: onEdit,
                  icon: const Icon(Icons.edit_outlined),
                  label: const Text('Bearbeiten'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DateTile extends StatelessWidget {
  const _DateTile({
    required this.label,
    required this.value,
    required this.onTap,
    required this.onClear,
  });

  final String label;
  final DateTime? value;
  final VoidCallback? onTap;
  final VoidCallback? onClear;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(
      onPressed: onTap,
      style: OutlinedButton.styleFrom(
        alignment: Alignment.centerLeft,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      ),
      child: Row(
        children: [
          const Icon(Icons.event_outlined),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 2),
                Text(
                  value == null ? 'Kein Datum gesetzt' : _displayDate(value!),
                ),
              ],
            ),
          ),
          if (onClear != null)
            IconButton(
              onPressed: onClear,
              icon: const Icon(Icons.close),
              tooltip: 'Datum entfernen',
            ),
        ],
      ),
    );
  }
}

int _integer(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('$value') ?? 0;
}

String? _composeLocation({
  required String city,
  required String postalCode,
  required String address,
  required String country,
}) {
  final cityLine = [
    postalCode.trim(),
    city.trim(),
  ].where((part) => part.isNotEmpty).join(' ');
  final value = [
    address.trim(),
    cityLine,
    country.trim(),
  ].where((part) => part.isNotEmpty).join(', ');
  return value.isEmpty ? null : value;
}

_JobLocationParts _splitLocation(Object? value) {
  final raw = '$value'.trim();
  if (raw.isEmpty || raw == 'null') return const _JobLocationParts();
  final parts = raw
      .split(',')
      .map((part) => part.trim())
      .where((part) => part.isNotEmpty)
      .toList();
  final address = parts.length > 1 ? parts.first : '';
  final country = _normalizeCountry(parts.length > 2 ? parts.last : '');
  final citySource = parts.length > 1 ? parts[1] : parts.first;
  final match = RegExp(r'^(\d{3,10})\s+(.+)$').firstMatch(citySource);
  return _JobLocationParts(
    address: address,
    postalCode: match?.group(1) ?? '',
    city: match?.group(2) ?? citySource,
    country: country,
  );
}

DateTime? _parseDate(Object? value) {
  final text = '$value'.trim();
  if (text.isEmpty || text == 'null') return null;
  return DateTime.tryParse(text);
}

String? _apiDate(DateTime? value) {
  if (value == null) return null;
  return DateFormat('yyyy-MM-dd').format(value);
}

String _displayDate(DateTime value) => DateFormat.yMMMd().format(value);

String _normalizeCountry(String value) {
  final country = value.trim();
  if (country.isEmpty) return '';
  final lower = country.toLowerCase();
  return switch (lower) {
    'deutschland' || 'germany' || 'allemagne' => 'DE',
    _ => country.length == 2 ? country.toUpperCase() : country,
  };
}

class _JobLocationParts {
  const _JobLocationParts({
    this.city = '',
    this.postalCode = '',
    this.address = '',
    this.country = '',
  });

  final String city;
  final String postalCode;
  final String address;
  final String country;
}
