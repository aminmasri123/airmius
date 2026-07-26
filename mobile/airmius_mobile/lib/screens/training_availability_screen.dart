import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TrainingAvailabilityScreen extends StatefulWidget {
  const TrainingAvailabilityScreen({super.key, this.userId});

  final int? userId;

  @override
  State<TrainingAvailabilityScreen> createState() =>
      _TrainingAvailabilityScreenState();
}

class _TrainingAvailabilityScreenState
    extends State<TrainingAvailabilityScreen> {
  Future<Map<String, dynamic>>? _future;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  bool get _readOnly => widget.userId != null;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final response = await _client.trainingAvailability(userId: widget.userId);
    return _map(response['data']);
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(t('trainingAvailability.title')),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return _errorState(snapshot.error);
          }
          final data = snapshot.data ?? const <String, dynamic>{};
          return RefreshIndicator(
            onRefresh: () async {
              _reload();
              await _future;
            },
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 36),
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('trainingAvailability.title').toUpperCase()),
                      const SizedBox(height: 7),
                      Text(
                        t('trainingAvailability.subtitle'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                          height: 1.16,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('trainingAvailability.readOnly'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                _currentPanel(data['current'], data['can_edit'] == true),
                const SizedBox(height: 14),
                _historyPanel(data['history']),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _currentPanel(Object? value, bool canEdit) {
    final current = _map(value);
    final theme = Theme.of(context);
    return AirmiusPanel(
      title: t('trainingAvailability.current'),
      borderColor: current.isEmpty
          ? null
          : _statusColor(context, _text(current['status'])),
      child: current.isEmpty
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  t('trainingAvailability.empty'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                if (canEdit) ...[
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: t('trainingAvailability.add'),
                    icon: Icons.add_circle_outline,
                    onPressed: _busy ? null : () => _editStatus(),
                  ),
                ],
              ],
            )
          : Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CircleAvatar(
                      backgroundColor: _statusColor(
                        context,
                        _text(current['status']),
                      ).withValues(alpha: .16),
                      child: Icon(
                        _statusIcon(_text(current['status'])),
                        color: _statusColor(context, _text(current['status'])),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        t(
                          'trainingAvailability.status.${_text(current['status'])}',
                        ),
                        style: theme.textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    if (_bool(current['is_active']))
                      StatusPill(
                        t('common.active'),
                        color: _statusColor(context, _text(current['status'])),
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                _dateRow(current),
                const SizedBox(height: 8),
                Text(
                  t(
                    'trainingAvailability.visibility.${_text(current['visibility'], fallback: 'private')}',
                  ),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (_text(current['note']).isNotEmpty && !_readOnly) ...[
                  const SizedBox(height: 10),
                  Text(
                    _text(current['note']),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      height: 1.35,
                    ),
                  ),
                ] else if (_readOnly) ...[
                  const SizedBox(height: 10),
                  Text(
                    t('trainingAvailability.sharedNote'),
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                ],
                if (canEdit) ...[
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(
                        label: t('trainingAvailability.update'),
                        icon: Icons.edit_outlined,
                        secondary: true,
                        onPressed: _busy
                            ? null
                            : () => _editStatus(existing: current),
                      ),
                      AirmiusButton(
                        label: t('trainingAvailability.clear'),
                        icon: Icons.done_all_outlined,
                        onPressed: _busy
                            ? null
                            : () => _clearStatus(_int(current['id'])),
                      ),
                    ],
                  ),
                ],
              ],
            ),
    );
  }

  Widget _historyPanel(Object? value) {
    final history = _maps(value);
    return AirmiusPanel(
      title: t('trainingAvailability.history'),
      child: history.isEmpty
          ? Text(
              t('trainingAvailability.empty'),
              style: TextStyle(color: airmiusMutedColor(context)),
            )
          : Column(
              children: history
                  .map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _HistoryRow(status: item),
                    ),
                  )
                  .toList(),
            ),
    );
  }

  Widget _dateRow(Map<String, dynamic> status) {
    final starts = _formatDate(_text(status['starts_on']));
    final ends = _text(status['ends_on']).isEmpty
        ? t('trainingAvailability.noEnd')
        : _formatDate(_text(status['ends_on']));
    return Row(
      children: [
        Icon(
          Icons.date_range_outlined,
          size: 19,
          color: airmiusMutedColor(context),
        ),
        const SizedBox(width: 8),
        Expanded(child: Text('$starts – $ends')),
      ],
    );
  }

  Widget _errorState(Object? error) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: AirmiusPanel(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.cloud_off_outlined,
              color: Theme.of(context).colorScheme.error,
              size: 32,
            ),
            const SizedBox(height: 10),
            Text(t('trainingAvailability.error'), textAlign: TextAlign.center),
            const SizedBox(height: 12),
            AirmiusButton(
              label: t('trainingProgress.retry'),
              icon: Icons.refresh_outlined,
              onPressed: _reload,
            ),
          ],
        ),
      ),
    ),
  );

  Future<void> _editStatus({Map<String, dynamic>? existing}) async {
    final payload = await showModalBottomSheet<AirmiusJson>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _TrainingAvailabilityEditor(existing: existing),
    );
    if (!mounted || payload == null) return;
    setState(() => _busy = true);
    try {
      if (existing == null) {
        await _client.createTrainingAvailability(payload);
      } else {
        await _client.updateTrainingAvailability(
          _int(existing['id'])!,
          payload,
        );
      }
      if (!mounted) return;
      _toast(t('trainingAvailability.saved'));
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } catch (_) {
      if (mounted) _toast(t('trainingAvailability.invalid'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _clearStatus(int? id) async {
    if (id == null) return;
    setState(() => _busy = true);
    try {
      await _client.clearTrainingAvailability(id);
      if (!mounted) return;
      _toast(t('trainingAvailability.cleared'));
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _toast(String value) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(value)));
  }

  String _formatDate(String value) {
    if (value.isEmpty) return '–';
    final date = DateTime.tryParse(value);
    if (date == null) return value;
    return DateFormat.yMMMd(
      Localizations.localeOf(context).languageCode,
    ).format(date);
  }

  static Color _statusColor(BuildContext context, String status) {
    final scheme = Theme.of(context).colorScheme;
    return switch (status) {
      'available' => scheme.tertiary,
      'limited' => scheme.secondary,
      'unavailable' => scheme.error,
      'injured' => Colors.orange.shade800,
      'ill' => Colors.deepPurple.shade600,
      _ => scheme.primary,
    };
  }

  static IconData _statusIcon(String status) => switch (status) {
    'available' => Icons.check_circle_outline,
    'limited' => Icons.speed_outlined,
    'unavailable' => Icons.event_busy_outlined,
    'injured' => Icons.healing_outlined,
    'ill' => Icons.sick_outlined,
    _ => Icons.info_outline,
  };

  static Map<String, dynamic> _map(Object? value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

  static List<Map<String, dynamic>> _maps(Object? value) =>
      value is List ? value.whereType<Map>().map(_map).toList() : const [];

  static String _text(Object? value, {String fallback = ''}) {
    final text = '$value'.trim();
    return value == null || text == 'null' ? fallback : text;
  }

  static int? _int(Object? value) =>
      value is num ? value.toInt() : int.tryParse('$value');

  static bool _bool(Object? value) =>
      value == true || value == 1 || value == '1';
}

class _HistoryRow extends StatelessWidget {
  const _HistoryRow({required this.status});

  final Map<String, dynamic> status;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final name = scope.t(
      'trainingAvailability.status.${_text(status['status'])}',
    );
    final starts = _format(_text(status['starts_on']), context);
    final ends = _text(status['ends_on']).isEmpty
        ? scope.t('trainingAvailability.noEnd')
        : _format(_text(status['ends_on']), context);
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(
        TrainingAvailabilityScreenStateHelper.icon(_text(status['status'])),
        color: TrainingAvailabilityScreenStateHelper.color(
          context,
          _text(status['status']),
        ),
      ),
      title: Text(name, style: const TextStyle(fontWeight: FontWeight.w800)),
      subtitle: Text('$starts – $ends'),
      trailing: _bool(status['is_active'])
          ? StatusPill(
              scope.t('common.active'),
              color: Theme.of(context).colorScheme.tertiary,
            )
          : null,
    );
  }

  static String _format(String value, BuildContext context) {
    final date = DateTime.tryParse(value);
    return date == null
        ? value
        : DateFormat.yMMMd(
            Localizations.localeOf(context).languageCode,
          ).format(date);
  }

  static String _text(Object? value) => value == null ? '' : '$value'.trim();

  static bool _bool(Object? value) =>
      value == true || value == 1 || value == '1';
}

/// Kept separate so the history row can stay a small, const-friendly widget.
abstract final class TrainingAvailabilityScreenStateHelper {
  static IconData icon(String status) => switch (status) {
    'available' => Icons.check_circle_outline,
    'limited' => Icons.speed_outlined,
    'unavailable' => Icons.event_busy_outlined,
    'injured' => Icons.healing_outlined,
    'ill' => Icons.sick_outlined,
    _ => Icons.info_outline,
  };

  static Color color(BuildContext context, String status) {
    final scheme = Theme.of(context).colorScheme;
    return switch (status) {
      'available' => scheme.tertiary,
      'limited' => scheme.secondary,
      'unavailable' => scheme.error,
      'injured' => Colors.orange.shade800,
      'ill' => Colors.deepPurple.shade600,
      _ => scheme.primary,
    };
  }
}

class _TrainingAvailabilityEditor extends StatefulWidget {
  const _TrainingAvailabilityEditor({this.existing});

  final Map<String, dynamic>? existing;

  @override
  State<_TrainingAvailabilityEditor> createState() =>
      _TrainingAvailabilityEditorState();
}

class _TrainingAvailabilityEditorState
    extends State<_TrainingAvailabilityEditor> {
  late String _status;
  late String _visibility;
  late DateTime _starts;
  DateTime? _ends;
  late final TextEditingController _note;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    final existing = widget.existing ?? const <String, dynamic>{};
    _status = _text(existing['status'], fallback: 'available');
    _visibility = _text(existing['visibility'], fallback: 'private');
    _starts = DateTime.tryParse(_text(existing['starts_on'])) ?? DateTime.now();
    _ends = DateTime.tryParse(_text(existing['ends_on']));
    _note = TextEditingController(text: _text(existing['note']));
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final statuses = ['available', 'limited', 'unavailable', 'injured', 'ill'];
    final visibilities = ['private', 'trainer', 'team'];
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        10,
        16,
        16 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: Theme.of(context).dividerColor,
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Text(
              t(
                widget.existing == null
                    ? 'trainingAvailability.add'
                    : 'trainingAvailability.update',
              ),
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: _status,
              decoration: InputDecoration(
                labelText: t('trainingAvailability.status'),
              ),
              items: statuses
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('trainingAvailability.status.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _status = value ?? _status),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _visibility,
              decoration: InputDecoration(
                labelText: t('trainingAvailability.visibility'),
              ),
              items: visibilities
                  .map(
                    (value) => DropdownMenuItem(
                      value: value,
                      child: Text(t('trainingAvailability.visibility.$value')),
                    ),
                  )
                  .toList(),
              onChanged: (value) =>
                  setState(() => _visibility = value ?? _visibility),
            ),
            const SizedBox(height: 10),
            _DateButton(
              label: t('trainingAvailability.starts'),
              value: _format(_starts),
              onPressed: () async {
                final value = await showDatePicker(
                  context: context,
                  initialDate: _starts,
                  firstDate: DateTime.now().subtract(const Duration(days: 365)),
                  lastDate: DateTime.now().add(const Duration(days: 730)),
                );
                if (value != null) setState(() => _starts = value);
              },
            ),
            const SizedBox(height: 8),
            _DateButton(
              label: t('trainingAvailability.ends'),
              value: _ends == null
                  ? t('trainingAvailability.noEnd')
                  : _format(_ends!),
              onPressed: () async {
                final value = await showDatePicker(
                  context: context,
                  initialDate: _ends ?? _starts,
                  firstDate: _starts,
                  lastDate: DateTime.now().add(const Duration(days: 730)),
                );
                if (value != null) setState(() => _ends = value);
              },
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _note,
              minLines: 2,
              maxLines: 4,
              maxLength: 1000,
              decoration: InputDecoration(
                labelText: t('trainingAvailability.note'),
                hintText: t('trainingAvailability.noteHint'),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              t('trainingAvailability.privateNote'),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: AirmiusButton(
                    label: t('trainingAvailability.cancel'),
                    icon: Icons.close_outlined,
                    secondary: true,
                    onPressed: () => Navigator.pop(context),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: AirmiusButton(
                    label: t('trainingAvailability.save'),
                    icon: Icons.check_outlined,
                    onPressed: () => Navigator.pop(context, _payload()),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  AirmiusJson _payload() => {
    'status': _status,
    'visibility': _visibility,
    'starts_on': _dateOnly(_starts),
    if (_ends != null) 'ends_on': _dateOnly(_ends!),
    if (_note.text.trim().isNotEmpty) 'note': _note.text.trim(),
  };

  String _format(DateTime value) => DateFormat.yMMMd(
    Localizations.localeOf(context).languageCode,
  ).format(value);

  static String _dateOnly(DateTime value) =>
      '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

  static String _text(Object? value, {String fallback = ''}) {
    if (value == null) return fallback;
    final text = '$value'.trim();
    return text.isEmpty || text == 'null' ? fallback : text;
  }
}

class _DateButton extends StatelessWidget {
  const _DateButton({
    required this.label,
    required this.value,
    required this.onPressed,
  });

  final String label;
  final String value;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => OutlinedButton.icon(
    onPressed: onPressed,
    icon: const Icon(Icons.calendar_today_outlined),
    label: Align(
      alignment: AlignmentDirectional.centerStart,
      child: Text('$label: $value'),
    ),
    style: OutlinedButton.styleFrom(
      minimumSize: const Size.fromHeight(52),
      alignment: AlignmentDirectional.centerStart,
      padding: const EdgeInsets.symmetric(horizontal: 14),
    ),
  );
}
