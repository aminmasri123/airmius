import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TrainingProgressScreen extends StatefulWidget {
  const TrainingProgressScreen({super.key, this.initialUserId});

  final int? initialUserId;

  @override
  State<TrainingProgressScreen> createState() => _TrainingProgressScreenState();
}

class _TrainingProgressScreenState extends State<TrainingProgressScreen> {
  Future<Map<String, dynamic>>? _future;
  int _days = 28;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final response = await _client.trainingAnalytics(
      userId: widget.initialUserId,
      days: _days,
    );
    return _map(response['data']);
  }

  void _reload() => setState(() => _future = _load());

  void _changePeriod(int days) {
    if (_days == days) return;
    setState(() {
      _days = days;
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(title: Text(t('trainingProgress.title'))),
      body: SafeArea(
        child: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snapshot.hasError) {
              final message = snapshot.error is AirmiusApiException
                  ? (snapshot.error! as AirmiusApiException).userMessage
                  : t('trainingProgress.error');
              return Center(
                child: AirmiusPanel(
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    const Icon(Icons.cloud_off_outlined, size: 42),
                    const SizedBox(height: 10),
                    Text(message, textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    AirmiusButton(label: t('trainingProgress.retry'), icon: Icons.refresh, onPressed: _reload),
                  ]),
                ),
              );
            }

            return _content(snapshot.data ?? const {}, t);
          },
        ),
      ),
    );
  }

  Widget _content(Map<String, dynamic> data, String Function(String) t) {
    final summary = _map(data['summary']);
    final weeks = _maps(data['weeks']);
    final alerts = _maps(data['alerts']);
    final recent = _maps(data['recent']);
    final maxLoad = weeks.fold<double>(0, (max, week) => _asDouble(week['load_score']) > max ? _asDouble(week['load_score']) : max);
    final colorScheme = Theme.of(context).colorScheme;

    return RefreshIndicator(
      onRefresh: () async => _reload(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
        children: [
          AirmiusPanel(
            gradient: true,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Text(t('trainingProgress.title'), style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900)),
              const SizedBox(height: 6),
              Text(t('trainingProgress.subtitle'), style: Theme.of(context).textTheme.bodyMedium?.copyWith(height: 1.4)),
              const SizedBox(height: 14),
              SegmentedButton<int>(
                segments: [
                  ButtonSegment(value: 7, label: Text(t('trainingProgress.days7'))),
                  ButtonSegment(value: 28, label: Text(t('trainingProgress.days28'))),
                  ButtonSegment(value: 90, label: Text(t('trainingProgress.days90'))),
                ],
                selected: {_days},
                onSelectionChanged: (value) => _changePeriod(value.first),
              ),
            ]),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _MetricCard(label: t('trainingProgress.sessions'), value: '${_asInt(summary['sessions'])}', icon: Icons.fitness_center_outlined),
              _MetricCard(label: t('trainingProgress.duration'), value: '${_asInt(summary['duration_minutes'])} ${t('trainingProgress.minutes')}', icon: Icons.timer_outlined),
              _MetricCard(label: t('trainingProgress.distance'), value: _distance(_asInt(summary['distance_meters']), t), icon: Icons.route_outlined),
              _MetricCard(label: t('trainingProgress.load'), value: '${_asInt(summary['load_score'])}', icon: Icons.speed_outlined),
              _MetricCard(label: t('trainingProgress.averageRpe'), value: _number(summary['average_rpe'], '–'), icon: Icons.bolt_outlined),
              _MetricCard(label: t('trainingProgress.averagePain'), value: _number(summary['average_pain'], '–'), icon: Icons.healing_outlined),
            ],
          ),
          const SizedBox(height: 14),
          _section(
            title: t('trainingProgress.weeklyLoad'),
            icon: Icons.show_chart_outlined,
            child: weeks.isEmpty
                ? Text(t('trainingProgress.noWeeks'))
                : Column(children: weeks.map((week) {
                    final load = _asDouble(week['load_score']);
                    final ratio = maxLoad <= 0 ? 0.0 : (load / maxLoad).clamp(0.0, 1.0);
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        Row(children: [
                          Expanded(child: Text(_text(week['week']), style: Theme.of(context).textTheme.labelLarge)),
                          Text('${_asInt(week['sessions'])} ${t('trainingProgress.sessionsShort')} · ${_asInt(week['load_score'])}'),
                        ]),
                        const SizedBox(height: 6),
                        LinearProgressIndicator(value: ratio, minHeight: 8, borderRadius: BorderRadius.circular(8), color: colorScheme.primary),
                      ]),
                    );
                  }).toList()),
          ),
          const SizedBox(height: 14),
          _section(
            title: t('trainingProgress.alerts'),
            icon: Icons.warning_amber_outlined,
            child: alerts.isEmpty
                ? Text(t('trainingProgress.noAlerts'))
                : Column(children: alerts.map((alert) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.warning_amber_outlined, color: AirmiusColors.amber),
                    title: Text(_text(alert['title'], t('trainingProgress.untitled'))),
                    subtitle: Text('${t('trainingProgress.rpe')}: ${_number(alert['rpe'], '–')} · ${t('trainingProgress.pain')}: ${_number(alert['pain'], '–')}'),
                  )).toList()),
          ),
          const SizedBox(height: 14),
          _section(
            title: t('trainingProgress.recent'),
            icon: Icons.history_outlined,
            child: recent.isEmpty
                ? Text(t('trainingProgress.noRecent'))
                : Column(children: recent.map((log) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.directions_run_outlined),
                    title: Text(_text(log['title'], t('trainingProgress.untitled'))),
                    subtitle: Text('${_text(log['sport_type'], t('trainingProgress.training'))} · ${_asInt(log['duration_minutes'])} ${t('trainingProgress.minutes')}'),
                    trailing: log['rpe'] == null ? null : Text('RPE ${_number(log['rpe'], '–')}'),
                  )).toList()),
          ),
        ],
      ),
    );
  }

  Widget _section({required String title, required IconData icon, required Widget child}) => AirmiusPanel(
    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Row(children: [Icon(icon, size: 22), const SizedBox(width: 8), Expanded(child: Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)))]),
      const SizedBox(height: 12),
      child,
    ]),
  );

  String _distance(int meters, String Function(String) t) => meters < 1000 ? '$meters m' : '${(meters / 1000).toStringAsFixed(1)} ${t('trainingProgress.kilometers')}';

  String _number(Object? value, String fallback) => value == null ? fallback : (value is num ? value.toString() : _text(value, fallback));
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.label, required this.value, required this.icon});
  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 160,
    child: AirmiusPanel(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 22, color: Theme.of(context).colorScheme.primary),
        const SizedBox(height: 8),
        Text(value, style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900)),
        const SizedBox(height: 2),
        Text(label, maxLines: 2, overflow: TextOverflow.ellipsis, style: Theme.of(context).textTheme.bodySmall),
      ]),
    ),
  );
}

Map<String, dynamic> _map(Object? value) => value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<Map<String, dynamic>> _maps(Object? value) => value is List
    ? value.whereType<Map>().map((item) => Map<String, dynamic>.from(item)).toList()
    : const [];

String _text(Object? value, [String fallback = '']) => value?.toString() ?? fallback;

int _asInt(Object? value) => value is num ? value.toInt() : int.tryParse(_text(value)) ?? 0;

double _asDouble(Object? value) => value is num ? value.toDouble() : double.tryParse(_text(value)) ?? 0;
