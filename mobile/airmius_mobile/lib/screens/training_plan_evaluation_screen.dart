import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/airmius_api_client.dart';
import '../core/training_evaluation_copy.dart';
import '../core/airmius_l10n.dart';
import '../widgets/airmius_widgets.dart';

typedef EvaluationLoader =
    Future<Map<String, dynamic>> Function(Map<String, String> filters);
typedef EvaluationReply = Future<void> Function(int logId, String body);

class TrainingPlanEvaluationScreen extends StatefulWidget {
  const TrainingPlanEvaluationScreen({
    super.key,
    required this.load,
    required this.reply,
  });
  final EvaluationLoader load;
  final EvaluationReply reply;

  @override
  State<TrainingPlanEvaluationScreen> createState() =>
      _TrainingPlanEvaluationScreenState();
}

class _TrainingPlanEvaluationScreenState
    extends State<TrainingPlanEvaluationScreen> {
  Map<String, dynamic>? _data;
  final _search = TextEditingController();
  final _expanded = <int>{};
  String? _plan;
  String _week = '';
  bool _openOnly = false;
  bool _loading = true;
  bool _error = false;
  int _request = 0;
  String t(String key) => evaluationText(context, key);

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _request++;
    _search.dispose();
    super.dispose();
  }

  Future<void> _load([int page = 1]) async {
    final request = ++_request;
    setState(() {
      _loading = true;
      _error = false;
      _data = null;
    });
    try {
      final result = await widget.load({
        'plan_id': ?_plan,
        if (_week.isNotEmpty) 'week': _week,
        'search': _search.text.trim(),
        'open_only': _openOnly ? '1' : '0',
        'page': '$page',
      });
      if (!mounted || request != _request) return;
      setState(() {
        _data = result;
        _plan = result['plan_id']?.toString();
        _loading = false;
      });
    } catch (_) {
      if (!mounted || request != _request) return;
      setState(() {
        _loading = false;
        _error = true;
      });
    }
  }

  String _date(dynamic value, {bool week = false}) {
    final date = DateTime.tryParse('$value');
    if (date == null) return t('unscheduled');
    return DateFormat(
      week ? 'dd.MM.yyyy' : 'dd.MM.yyyy HH:mm',
    ).format(week ? date : date.toLocal());
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    final plans = _maps(data?['plans']);
    final rows = _maps(data?['rows']);
    final pagination = _map(data?['pagination']);
    final page = _int(pagination['page'], 1);
    return Scaffold(
      appBar: AppBar(
        title: Text(t('title')),
        actions: [
          IconButton(
            tooltip: t('refresh'),
            onPressed: _loading ? null : () => _load(page),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(t('intro'), style: Theme.of(context).textTheme.bodyMedium),
              const SizedBox(height: 20),
              if (_loading) ...[
                const LinearProgressIndicator(),
                const SizedBox(height: 12),
                Text(t('loading')),
              ],
              if (_error) ...[
                Text(t('error'), key: const Key('evaluation-error')),
                const SizedBox(height: 12),
                AirmiusButton(
                  label: t('refresh'),
                  icon: Icons.refresh,
                  onPressed: () => _load(),
                ),
              ],
              if (data != null && plans.isEmpty) Text(t('empty')),
              if (data != null && plans.isNotEmpty) ...[
                DropdownButtonFormField<String>(
                  key: ValueKey('plan-$_plan'),
                  initialValue: _plan,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: t('plan')),
                  items: plans
                      .map(
                        (p) => DropdownMenuItem(
                          value: '${p['id']}',
                          child: Text(
                            '${p['title']}',
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) {
                    _plan = value;
                    _week = '';
                    _expanded.clear();
                    _load();
                  },
                ),
                const SizedBox(height: 20),
                DropdownButtonFormField<String>(
                  key: ValueKey('week-$_week'),
                  initialValue: _week,
                  isExpanded: true,
                  decoration: InputDecoration(labelText: t('week')),
                  items: [
                    DropdownMenuItem(value: '', child: Text(t('allWeeks'))),
                    for (final w in data['weeks'] as List? ?? [])
                      DropdownMenuItem(
                        value: '$w',
                        child: Text(_date(w, week: true)),
                      ),
                  ],
                  onChanged: (value) {
                    _week = value ?? '';
                    _load();
                  },
                ),
                const SizedBox(height: 20),
                TextField(
                  controller: _search,
                  maxLength: 100,
                  textInputAction: TextInputAction.search,
                  decoration: InputDecoration(
                    labelText: t('search'),
                    counterText: '',
                    suffixIcon: IconButton(
                      tooltip: t('search'),
                      icon: const Icon(Icons.search),
                      onPressed: () => _load(),
                    ),
                  ),
                  onSubmitted: (_) => _load(),
                ),
                const SizedBox(height: 12),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  value: _openOnly,
                  title: Text(t('openOnly')),
                  onChanged: (value) {
                    _openOnly = value ?? false;
                    _load();
                  },
                ),
                const SizedBox(height: 12),
                LayoutBuilder(
                  builder: (context, constraints) => Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      for (final key in [
                        'athletes',
                        'reported',
                        'missing',
                        'feedback_open',
                        'pain',
                      ])
                        SizedBox(
                          width: constraints.maxWidth < 340
                              ? constraints.maxWidth
                              : (constraints.maxWidth - 10) / 2,
                          child: AirmiusPanel(
                            padding: const EdgeInsets.all(14),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '${_map(data['summary'])[key] ?? 0}',
                                  style: Theme.of(
                                    context,
                                  ).textTheme.headlineSmall,
                                ),
                                Text(t(key)),
                              ],
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                Text(t('missingHint')),
                const SizedBox(height: 8),
                Text(t('openHint')),
                const SizedBox(height: 20),
                if (rows.isEmpty) Text(t('noResults')),
                for (final athlete in rows)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: AirmiusPanel(
                      padding: EdgeInsets.zero,
                      child: ExpansionTile(
                        key: ValueKey('athlete-$_plan-${athlete['id']}'),
                        initiallyExpanded: _expanded.contains(
                          _int(athlete['id']),
                        ),
                        onExpansionChanged: (expanded) => setState(() {
                          if (expanded) {
                            _expanded.add(_int(athlete['id']));
                          } else {
                            _expanded.remove(_int(athlete['id']));
                          }
                        }),
                        title: Text(
                          '${athlete['name']}',
                          style: const TextStyle(fontWeight: FontWeight.bold),
                        ),
                        subtitle: Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                (athlete['teams'] as List? ?? []).isEmpty
                                    ? t('direct')
                                    : (athlete['teams'] as List).join(' · '),
                              ),
                              const SizedBox(height: 8),
                              Text(
                                '${athlete['reported']}/${_maps(athlete['sessions']).length} ${t('reported')}',
                              ),
                              if (_int(athlete['feedback_open']) > 0)
                                Text(
                                  '${athlete['feedback_open']} ${t('feedback_open')}',
                                ),
                              if (athlete['pain'] == true)
                                Text(
                                  t('pain'),
                                  style: TextStyle(
                                    color: Theme.of(context).colorScheme.error,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        children: [
                          if (_expanded.contains(_int(athlete['id'])))
                            Padding(
                              padding: const EdgeInsets.all(14),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.stretch,
                                children: [
                                  for (final session in _maps(
                                    athlete['sessions'],
                                  )) ...[
                                    const Divider(height: 28),
                                    Text(
                                      '${session['title']}',
                                      style: Theme.of(
                                        context,
                                      ).textTheme.titleMedium,
                                    ),
                                    Text(_date(session['scheduled_at'])),
                                    const SizedBox(height: 12),
                                    if (session['missing'] == true) ...[
                                      Text(
                                        t(
                                          session['overdue'] == true
                                              ? 'overdue'
                                              : 'noReport',
                                        ),
                                      ),
                                      for (final exercise in _maps(
                                        session['planned'],
                                      ))
                                        _exercise(exercise),
                                    ],
                                    for (final report in _maps(
                                      session['reports'],
                                    ))
                                      _report(report, page),
                                  ],
                                ],
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                Text(
                  '${t('page')} $page/${pagination['last_page']} · ${pagination['total']} ${t('total')}',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 10),
                Wrap(
                  alignment: WrapAlignment.spaceBetween,
                  spacing: 12,
                  runSpacing: 12,
                  children: [
                    OutlinedButton(
                      onPressed: page > 1 ? () => _load(page - 1) : null,
                      child: Text(t('previous')),
                    ),
                    OutlinedButton(
                      onPressed: page < _int(pagination['last_page'], 1)
                          ? () => _load(page + 1)
                          : null,
                      child: Text(t('next')),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _report(Map<String, dynamic> report, int page) => Padding(
    padding: const EdgeInsets.only(top: 16, bottom: 16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          '${_date(report['performed_at'])} · ${t('${report['status']}')}',
          style: const TextStyle(fontWeight: FontWeight.bold),
        ),
        Text(t(report['feedback_open'] == true ? 'feedback_open' : 'reviewed')),
        if (_int(report['pain']) > 0)
          Text(
            '${t('pain')}: ${report['pain']}/10',
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        if (report['rpe'] != null) Text('${t('rpe')}: ${report['rpe']}/10'),
        if (report['duration_minutes'] != null ||
            report['distance_meters'] != null)
          Text(
            '${t('sessionTotal')}: ${_values({'duration_minutes': report['duration_minutes'], if (report['distance_meters'] is num) 'distance_km': (report['distance_meters'] as num) / 1000})}',
          ),
        if ('${report['notes'] ?? ''}'.isNotEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 10),
            child: Text('${report['notes']}'),
          ),
        Text(
          t(report['snapshot'] == true ? 'snapshot' : 'legacy'),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        for (final exercise in _maps(report['exercises'])) _exercise(exercise),
        const SizedBox(height: 12),
        Text(t('feedback'), style: Theme.of(context).textTheme.titleMedium),
        if ('${report['trainer_feedback'] ?? ''}'.isNotEmpty)
          Text('${t('trainer')}: ${report['trainer_feedback']}'),
        for (final feedback in _maps(report['feedback']))
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${feedback['author'] ?? ''} · ${t('${feedback['role']}')} · ${_date(feedback['created_at'])}',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                Text('${feedback['body']}'),
              ],
            ),
          ),
        const SizedBox(height: 12),
        _ReplyForm(
          key: ValueKey('reply-${report['id']}'),
          send: (body) async {
            try {
              await widget.reply(_int(report['id']), body);
            } on AirmiusApiException catch (error) {
              if (mounted && [401, 403, 404].contains(error.statusCode)) {
                _request++;
                setState(() {
                  _data = null;
                  _error = true;
                  _loading = false;
                });
              }
              rethrow;
            }
            if (!mounted) return;
            ScaffoldMessenger.of(
              context,
            ).showSnackBar(SnackBar(content: Text(t('sent'))));
            await _load(page);
          },
        ),
      ],
    ),
  );

  Widget _exercise(Map<String, dynamic> exercise) => Padding(
    padding: const EdgeInsets.only(top: 14),
    child: AirmiusPanel(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            '${exercise['title']}',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          if (_maps(exercise['planned']).isEmpty) Text(t('extra')),
          if ('${exercise['notes'] ?? ''}'.isNotEmpty)
            Text('${exercise['notes']}'),
          if (exercise['unstructured'] == true)
            Text(
              t('unstructured'),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          const SizedBox(height: 12),
          LayoutBuilder(
            builder: (context, constraints) {
              final planned = _sets(
                t('planned'),
                _maps(exercise['planned']),
                actual: false,
              );
              final actual = _sets(
                t('actual'),
                _maps(exercise['actual']),
                actual: true,
              );
              return constraints.maxWidth < 500
                  ? Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [planned, const SizedBox(height: 14), actual],
                    )
                  : Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(child: planned),
                        const SizedBox(width: 16),
                        Expanded(child: actual),
                      ],
                    );
            },
          ),
        ],
      ),
    ),
  );

  Widget _sets(
    String title,
    List<Map<String, dynamic>> sets, {
    required bool actual,
  }) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Text(
        title,
        style: TextStyle(
          fontWeight: FontWeight.bold,
          color: Theme.of(context).colorScheme.primary,
        ),
      ),
      if (sets.isEmpty) Text(actual ? t('notRecorded') : '—'),
      for (var i = 0; i < sets.length; i++)
        Padding(
          padding: const EdgeInsets.only(top: 6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${t('set')} ${sets[i]['set_index'] ?? i + 1}: ${actual && sets[i]['completed'] == false ? t('notCompleted') : _values(sets[i])}',
              ),
              if (sets[i]['substituted_for'] != null)
                Text('${t('substitution')}: ${sets[i]['title']}'),
              if ('${sets[i]['skip_reason'] ?? sets[i]['notes'] ?? ''}'
                  .isNotEmpty)
                Text('${sets[i]['skip_reason'] ?? sets[i]['notes']}'),
            ],
          ),
        ),
    ],
  );

  String _values(Map<String, dynamic> set) {
    final number = NumberFormat.decimalPattern(
      AirmiusScope.of(context).language.name,
    )..maximumFractionDigits = 2;
    String n(dynamic v) => number.format(num.tryParse('$v') ?? 0);
    final values = <String>[
      if (_int(set['sets']) > 1) '${n(set['sets'])} ×',
      if (set['reps'] != null) '${n(set['reps'])} ${t('reps')}',
      for (final entry in {
        'weight_kg': 'kg',
        'duration_minutes': 'min',
        'distance_km': 'km',
        'rounds': t('rounds'),
      }.entries)
        if (set[entry.key] != null) '${n(set[entry.key])} ${entry.value}',
      if (set['rest_seconds'] != null)
        '${t('rest')} ${n(set['rest_seconds'])} s',
    ];
    return values.isEmpty ? t('notRecorded') : values.join(' · ');
  }
}

class _ReplyForm extends StatefulWidget {
  const _ReplyForm({super.key, required this.send});
  final Future<void> Function(String body) send;
  @override
  State<_ReplyForm> createState() => _ReplyFormState();
}

class _ReplyFormState extends State<_ReplyForm> {
  final _controller = TextEditingController();
  bool _busy = false;
  String? _error;
  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_busy) return;
    if (_controller.text.trim().length < 2) {
      setState(() => _error = 'replyRequired');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.send(_controller.text.trim());
    } catch (_) {
      if (mounted) setState(() => _error = 'sendError');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      TextField(
        controller: _controller,
        minLines: 2,
        maxLines: 5,
        maxLength: 3000,
        enabled: !_busy,
        decoration: InputDecoration(
          labelText: evaluationText(context, 'reply'),
          alignLabelWithHint: true,
        ),
      ),
      if (_error != null)
        Text(
          evaluationText(context, _error!),
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
      const SizedBox(height: 10),
      AirmiusButton(
        label: evaluationText(context, 'send'),
        icon: Icons.send_outlined,
        onPressed: _busy ? null : _send,
      ),
    ],
  );
}

Map<String, dynamic> _map(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : {};
List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.whereType<Map>().map((v) => Map<String, dynamic>.from(v)).toList()
    : [];
int _int(dynamic value, [int fallback = 0]) =>
    num.tryParse('$value')?.toInt() ?? fallback;
