import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';

class ClubYearPeriodsScreen extends StatefulWidget {
  const ClubYearPeriodsScreen({super.key, required this.club});

  final ClubSummary club;

  @override
  State<ClubYearPeriodsScreen> createState() => _ClubYearPeriodsScreenState();
}

class _ClubYearPeriodsScreenState extends State<ClubYearPeriodsScreen> {
  Map<String, dynamic> _data = const {};
  bool _loading = true;
  bool _busy = false;
  String? _error;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  List<Map<String, dynamic>> get _periods {
    final value = _data['periods'];
    return value is List
        ? value.whereType<Map<String, dynamic>>().toList()
        : const [];
  }

  bool get _canManage => _data['can_manage'] == true;
  bool get _canEdit => _data['can_edit'] == true ||
      (!_data.containsKey('can_edit') && _canManage);
  bool get _canDelete => _data['can_delete'] == true ||
      (!_data.containsKey('can_delete') && _canManage);
  bool get _canViewReports => _data['can_view_reports'] == true;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await _client.clubYearPeriods(widget.club.id);
      final data = response['data'];
      if (mounted) {
        setState(() => _data = data is Map<String, dynamic> ? data : const {});
      }
    } catch (error) {
      if (mounted) setState(() => _error = _safeError(error));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _safeError(Object error) => error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('clubYearPeriods.error');

  Future<void> _edit([Map<String, dynamic>? period]) async {
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _YearPeriodDialog(period: period),
    );
    if (payload == null || !mounted) return;
    await _write(
      () => _client.saveClubYearPeriod(
        widget.club.id,
        payload,
        periodId: _asInt(period?['id']),
      ),
    );
  }

  Future<void> _delete(Map<String, dynamic> period) async {
    final t = AirmiusScope.of(context).t;
    final confirmed =
        await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(t('clubYearPeriods.deleteTitle')),
            content: Text(t('clubYearPeriods.deleteMessage')),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(t('common.cancel')),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: Text(t('common.delete')),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !mounted) return;
    await _write(
      () => _client.deleteClubYearPeriod(widget.club.id, _asInt(period['id'])!),
    );
  }

  Future<void> _write(Future<AirmiusJson> Function() action) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = _safeError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _showReport(String type, Object periodId) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await _client.clubYearPeriodReport(
        widget.club.id,
        type: type,
        periodId: periodId,
      );
      if (!mounted) return;
      final data = response['data'];
      if (data is Map<String, dynamic>) {
        await showDialog<void>(
          context: context,
          builder: (_) => _YearPeriodReportDialog(report: data),
        );
      }
    } catch (error) {
      if (mounted) setState(() => _error = _safeError(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('clubYearPeriods.title')),
        actions: [
          if (_canEdit)
            IconButton(
              tooltip: t('clubYearPeriods.add'),
              onPressed: _busy ? null : () => _edit(),
              icon: const Icon(Icons.add_circle_outline),
            ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              t('clubYearPeriods.hint'),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
            if (_busy)
              const Padding(
                padding: EdgeInsets.only(top: 12),
                child: LinearProgressIndicator(),
              ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Semantics(
                liveRegion: true,
                child: MaterialBanner(
                  content: Text(_error!),
                  actions: [
                    TextButton(
                      onPressed: _load,
                      child: Text(t('common.retry')),
                    ),
                  ],
                ),
              ),
            ],
            if (_loading) ...[
              const SizedBox(height: 32),
              const Center(child: CircularProgressIndicator()),
            ] else ...[
              const SizedBox(height: 16),
              for (final type in const [
                'business',
                'contribution',
                'sport',
              ]) ...[_typeCard(type), const SizedBox(height: 12)],
            ],
          ],
        ),
      ),
    );
  }

  Widget _typeCard(String type) {
    final t = AirmiusScope.of(context).t;
    final periods = _periods.where((period) => period['type'] == type).toList();
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.date_range_outlined),
                const SizedBox(width: 8),
                Text(
                  t('clubYearPeriods.type.$type'),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ],
            ),
            if (periods.isEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(t('clubYearPeriods.empty')),
              )
            else
              for (final period in periods)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text('${period['name'] ?? ''}'),
                  subtitle: Text(
                    '${period['starts_on']} – ${period['ends_on']} · '
                    '${t('clubYearPeriods.status.${period['status']}')}',
                  ),
                  trailing: _canEdit || _canDelete
                      ? Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            if (_canViewReports)
                              IconButton(
                                tooltip: t('clubYearPeriods.report'),
                                onPressed: _busy
                                    ? null
                                    : () => _showReport(type, period['id']!),
                                icon: const Icon(Icons.assessment_outlined),
                              ),
                            PopupMenuButton<String>(
                              onSelected: (action) => action == 'edit'
                                  ? _edit(period)
                                  : _delete(period),
                              itemBuilder: (_) => [
                                if (_canEdit) PopupMenuItem(
                                  value: 'edit',
                                  child: Text(t('common.edit')),
                                ),
                                if (_canDelete) PopupMenuItem(
                                  value: 'delete',
                                  child: Text(t('common.delete')),
                                ),
                              ],
                            ),
                          ],
                        )
                      : _canViewReports
                      ? IconButton(
                          tooltip: t('clubYearPeriods.report'),
                          onPressed: _busy
                              ? null
                              : () => _showReport(type, period['id']!),
                          icon: const Icon(Icons.assessment_outlined),
                        )
                      : null,
                ),
            if (_canViewReports)
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton.icon(
                  onPressed: _busy
                      ? null
                      : () => _showReport(type, 'unassigned'),
                  icon: const Icon(Icons.history_outlined),
                  label: Text(t('clubYearPeriods.unassignedReport')),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _YearPeriodReportDialog extends StatelessWidget {
  const _YearPeriodReportDialog({required this.report});

  final Map<String, dynamic> report;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final period = report['period'];
    final summary = _map(report['summary']);
    final unassigned = _map(report['unassigned']);
    final invoices = _map(summary['invoices']);
    final bank = _map(summary['bank_transactions']);
    final entries = _map(summary['finance_entries']);
    final events = _map(summary['events']);
    final historicInvoices = _map(unassigned['invoices']);
    final historicBank = _map(unassigned['bank_transactions']);
    final historicEntries = _map(unassigned['finance_entries']);
    final historicEvents = _map(unassigned['events']);

    return AlertDialog(
      title: Text(
        period is Map<String, dynamic>
            ? '${period['name'] ?? ''}'
            : t('clubYearPeriods.unassigned'),
      ),
      content: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            if (period is! Map<String, dynamic>)
              Text(t('clubYearPeriods.unassignedHint')),
            if (invoices.isNotEmpty) ...[
              _row(t('clubYearPeriods.invoiceCount'), invoices['total_count']),
              _row(
                t('clubYearPeriods.openAmount'),
                _money(invoices['open_amount']),
              ),
              _row(
                t('clubYearPeriods.paidAmount'),
                _money(invoices['paid_amount']),
              ),
              _row(
                t('clubYearPeriods.overdueCount'),
                invoices['overdue_count'],
              ),
            ],
            if (bank.isNotEmpty) ...[
              _row(t('clubYearPeriods.bankCount'), bank['total_count']),
              _row(t('clubYearPeriods.credits'), _money(bank['credit_amount'])),
              _row(t('clubYearPeriods.debits'), _money(bank['debit_amount'])),
              _row(t('clubYearPeriods.net'), _money(bank['net_amount'])),
            ],
            if (entries.isNotEmpty) ...[
              _row(t('clubYearPeriods.entryCount'), entries['total_count']),
              _row(
                t('clubYearPeriods.income'),
                _money(entries['income_amount']),
              ),
              _row(
                t('clubYearPeriods.expenses'),
                _money(entries['expense_amount']),
              ),
              _row(t('clubYearPeriods.net'), _money(entries['net_amount'])),
            ],
            if (events.isNotEmpty) ...[
              _row(t('clubYearPeriods.eventCount'), events['total_count']),
              _row(
                t('clubYearPeriods.scheduledCount'),
                events['scheduled_count'],
              ),
              _row(
                t('clubYearPeriods.cancelledCount'),
                events['cancelled_count'],
              ),
            ],
            if (report['selection'] == 'period') ...[
              const Divider(height: 28),
              Text(
                t('clubYearPeriods.unassignedSeparate'),
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              if (historicInvoices.isNotEmpty)
                _row(
                  t('clubYearPeriods.invoiceCount'),
                  historicInvoices['total_count'],
                ),
              if (historicBank.isNotEmpty)
                _row(
                  t('clubYearPeriods.bankCount'),
                  historicBank['total_count'],
                ),
              if (historicEntries.isNotEmpty)
                _row(
                  t('clubYearPeriods.entryCount'),
                  historicEntries['total_count'],
                ),
              if (historicEvents.isNotEmpty)
                _row(
                  t('clubYearPeriods.eventCount'),
                  historicEvents['total_count'],
                ),
            ],
          ],
        ),
      ),
      actions: [
        FilledButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.close')),
        ),
      ],
    );
  }

  static Map<String, dynamic> _map(Object? value) =>
      value is Map<String, dynamic> ? value : const {};

  static Widget _row(String label, Object? value) => Padding(
    padding: const EdgeInsets.only(top: 8),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [Text(label), const SizedBox(width: 20), Text('${value ?? 0}')],
    ),
  );

  static String _money(Object? value) =>
      '${(num.tryParse('$value') ?? 0).toStringAsFixed(2)} €';
}

class _YearPeriodDialog extends StatefulWidget {
  const _YearPeriodDialog({required this.period});

  final Map<String, dynamic>? period;

  @override
  State<_YearPeriodDialog> createState() => _YearPeriodDialogState();
}

class _YearPeriodDialogState extends State<_YearPeriodDialog> {
  late String _type;
  late final TextEditingController _name;
  late final TextEditingController _start;
  late final TextEditingController _end;
  String? _error;

  @override
  void initState() {
    super.initState();
    final period = widget.period ?? const {};
    _type = '${period['type'] ?? 'business'}';
    _name = TextEditingController(text: '${period['name'] ?? ''}');
    _start = TextEditingController(
      text: formatAirmiusDate(parseAirmiusDate(period['starts_on']?.toString())),
    );
    _end = TextEditingController(
      text: formatAirmiusDate(parseAirmiusDate(period['ends_on']?.toString())),
    );
  }

  @override
  void dispose() {
    _name.dispose();
    _start.dispose();
    _end.dispose();
    super.dispose();
  }

  void _submit() {
    final t = AirmiusScope.of(context).t;
    final start = parseAirmiusDate(_start.text);
    final end = parseAirmiusDate(_end.text);
    if (_name.text.trim().isEmpty || start == null || end == null) {
      setState(() => _error = t('clubYearPeriods.required'));
      return;
    }
    if (end.isBefore(start)) {
      setState(() => _error = t('clubYearPeriods.invalidRange'));
      return;
    }
    Navigator.pop(context, {
      'type': _type,
      'name': _name.text.trim(),
      'starts_on': formatAirmiusApiDate(start),
      'ends_on': formatAirmiusApiDate(end),
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(
        widget.period == null
            ? t('clubYearPeriods.add')
            : t('clubYearPeriods.edit'),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _type,
              decoration: InputDecoration(labelText: t('clubYearPeriods.type')),
              items: const ['business', 'contribution', 'sport']
                  .map(
                    (type) => DropdownMenuItem(
                      value: type,
                      child: Text(t('clubYearPeriods.type.$type')),
                    ),
                  )
                  .toList(),
              onChanged: (value) => setState(() => _type = value ?? _type),
            ),
            TextField(
              controller: _name,
              decoration: InputDecoration(labelText: t('clubYearPeriods.name')),
            ),
            TextField(
              controller: _start,
              keyboardType: TextInputType.datetime,
              inputFormatters: const [AirmiusDateInputFormatter()],
              decoration: InputDecoration(
                labelText: t('clubYearPeriods.startsOn'),
              ),
            ),
            TextField(
              controller: _end,
              keyboardType: TextInputType.datetime,
              inputFormatters: const [AirmiusDateInputFormatter()],
              decoration: InputDecoration(
                labelText: t('clubYearPeriods.endsOn'),
              ),
            ),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.cancel')),
        ),
        FilledButton(onPressed: _submit, child: Text(t('common.save'))),
      ],
    );
  }
}

int? _asInt(dynamic value) => value is int ? value : int.tryParse('$value');
