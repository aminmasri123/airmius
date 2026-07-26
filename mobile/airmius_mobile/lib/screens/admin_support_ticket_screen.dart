import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

/// Permission-scoped support operations for support staff and platform admins.
///
/// The screen deliberately exposes only ticket workflow data. User secrets,
/// authentication tokens and internal diagnostics stay on the server.
class AdminSupportTicketScreen extends StatefulWidget {
  const AdminSupportTicketScreen({super.key});

  @override
  State<AdminSupportTicketScreen> createState() =>
      _AdminSupportTicketScreenState();
}

class _AdminSupportTicketScreenState extends State<AdminSupportTicketScreen> {
  Future<AirmiusJson>? _future;
  String _filter = 'open';
  bool _busy = false;

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

  Future<AirmiusJson> _load() {
    return _client.adminSupportTickets(
      overdue: _filter == 'overdue',
      status: _filter == 'open' || _filter == 'overdue' ? null : _filter,
    );
  }

  void _reload() => setState(() => _future = _load());

  Future<void> _updateTicket(
    AirmiusSupportTicket ticket, {
    Map<String, dynamic>? values,
  }) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await _client.adminUpdateSupportTicket(ticket.id, values ?? {});
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('support.admin.saved'))));
      _reload();
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openEditor(AirmiusSupportTicket ticket) async {
    var status = ticket.status;
    var priority = ticket.priority;
    var escalated = ticket.escalatedAt != null;
    final note = TextEditingController(text: ticket.adminNote ?? '');
    final values = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('support.admin.editTitle')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                DropdownButtonFormField<String>(
                  initialValue: status,
                  decoration: InputDecoration(
                    labelText: t('support.admin.status'),
                  ),
                  items:
                      [
                            'open',
                            'in_progress',
                            'waiting_user',
                            'resolved',
                            'closed',
                          ]
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(_statusLabel(value)),
                            ),
                          )
                          .toList(),
                  onChanged: (value) => setDialogState(() {
                    if (value != null) status = value;
                  }),
                ),
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: priority,
                  decoration: InputDecoration(
                    labelText: t('support.admin.priority'),
                  ),
                  items: ['low', 'normal', 'high', 'urgent']
                      .map(
                        (value) => DropdownMenuItem(
                          value: value,
                          child: Text(_priorityLabel(value)),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setDialogState(() {
                    if (value != null) priority = value;
                  }),
                ),
                const SizedBox(height: 12),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(t('support.admin.escalated')),
                  value: escalated,
                  onChanged: (value) => setDialogState(() => escalated = value),
                ),
                TextField(
                  controller: note,
                  minLines: 2,
                  maxLines: 5,
                  decoration: InputDecoration(
                    labelText: t('support.admin.note'),
                    hintText: t('support.admin.noteHint'),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, {
                'status': status,
                'priority': priority,
                'escalated': escalated,
                'admin_note': note.text.trim(),
              }),
              child: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
    note.dispose();
    if (values != null && mounted) await _updateTicket(ticket, values: values);
  }

  String _statusLabel(String value) {
    return switch (value) {
      'in_progress' => t('support.ticketStatus.inProgress'),
      'waiting_user' => t('support.ticketStatus.waiting'),
      'resolved' => t('support.ticketStatus.resolved'),
      'closed' => t('support.ticketStatus.closed'),
      _ => t('support.ticketStatus.open'),
    };
  }

  String _priorityLabel(String value) => t('support.priority.$value');

  String _date(DateTime? value) {
    if (value == null) return '–';
    final local = value.toLocal();
    return '${local.day.toString().padLeft(2, '0')}.${local.month.toString().padLeft(2, '0')}.${local.year} ${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('support.admin.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<AirmiusJson>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: FilledButton.icon(
                onPressed: _reload,
                icon: const Icon(Icons.refresh_outlined),
                label: Text(t('common.retry')),
              ),
            );
          }
          final data = snapshot.data?['data'] is JsonMap
              ? snapshot.data!['data'] as JsonMap
              : <String, dynamic>{};
          final summary = data['summary'] is JsonMap
              ? data['summary'] as JsonMap
              : <String, dynamic>{};
          final rawTickets = data['tickets'];
          final tickets = rawTickets is List
              ? rawTickets
                    .whereType<JsonMap>()
                    .map(AirmiusSupportTicket.fromJson)
                    .toList()
              : const <AirmiusSupportTicket>[];
          return PageFrame(
            title: t('support.admin.title'),
            subtitle: t('support.admin.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('support.admin.eyebrow')),
                      const SizedBox(height: 7),
                      Text(
                        t('support.admin.headline'),
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 7),
                      Text(t('support.admin.body')),
                      const SizedBox(height: 14),
                      Wrap(
                        spacing: 10,
                        runSpacing: 10,
                        children: [
                          Metric(
                            value: '${summary['open'] ?? 0}',
                            label: t('support.admin.metricOpen'),
                          ),
                          Metric(
                            value: '${summary['urgent'] ?? 0}',
                            label: t('support.admin.metricUrgent'),
                          ),
                          Metric(
                            value: '${summary['overdue'] ?? 0}',
                            label: t('support.admin.metricOverdue'),
                          ),
                          Metric(
                            value: '${summary['escalated'] ?? 0}',
                            label: t('support.admin.metricEscalated'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _filterChip('open', t('support.admin.filterOpen')),
                      _filterChip('overdue', t('support.admin.filterOverdue')),
                      _filterChip(
                        'in_progress',
                        t('support.ticketStatus.inProgress'),
                      ),
                      _filterChip(
                        'waiting_user',
                        t('support.ticketStatus.waiting'),
                      ),
                      _filterChip(
                        'resolved',
                        t('support.ticketStatus.resolved'),
                      ),
                    ],
                  ),
                ),
                if (_busy) ...[
                  const SizedBox(height: 10),
                  const LinearProgressIndicator(minHeight: 3),
                ],
                const SizedBox(height: 14),
                if (tickets.isEmpty)
                  AirmiusPanel(child: Text(t('support.admin.empty')))
                else
                  ...tickets.map(_ticketCard),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _filterChip(String value, String label) {
    return Padding(
      padding: const EdgeInsetsDirectional.only(end: 8),
      child: ChoiceChip(
        selected: _filter == value,
        label: Text(label),
        onSelected: (_) {
          if (_filter == value) return;
          setState(() {
            _filter = value;
            _future = _load();
          });
        },
      ),
    );
  }

  Widget _ticketCard(AirmiusSupportTicket ticket) {
    final theme = Theme.of(context);
    final requester =
        ticket.requesterName ?? t('support.admin.unknownRequester');
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: AirmiusPanel(
        borderColor: ticket.isOverdue
            ? theme.colorScheme.error
            : ticket.priority == 'urgent'
            ? theme.colorScheme.secondary
            : null,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Expanded(
                  child: Text(
                    ticket.subject,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                IconButton(
                  tooltip: t('support.admin.edit'),
                  onPressed: _busy ? null : () => _openEditor(ticket),
                  icon: const Icon(Icons.edit_outlined),
                ),
              ],
            ),
            const SizedBox(height: 5),
            Text('$requester · ${ticket.category}'),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                Chip(label: Text(_statusLabel(ticket.status))),
                Chip(label: Text(_priorityLabel(ticket.priority))),
                if (ticket.isOverdue)
                  Chip(
                    avatar: const Icon(Icons.warning_amber_outlined, size: 18),
                    label: Text(t('support.admin.overdue')),
                  ),
                if (ticket.escalatedAt != null)
                  Chip(
                    avatar: const Icon(Icons.trending_up_outlined, size: 18),
                    label: Text(t('support.admin.escalated')),
                  ),
              ],
            ),
            const SizedBox(height: 7),
            Text(
              t(
                'support.admin.slaDue',
              ).replaceFirst('{date}', _date(ticket.dueAt)),
              style: theme.textTheme.bodySmall,
            ),
            if (ticket.assigneeName != null) ...[
              const SizedBox(height: 3),
              Text(
                t(
                  'support.admin.assignedTo',
                ).replaceFirst('{name}', ticket.assigneeName!),
                style: theme.textTheme.bodySmall,
              ),
            ],
            if (ticket.adminNote?.isNotEmpty == true) ...[
              const SizedBox(height: 8),
              Text(ticket.adminNote!),
            ],
            const SizedBox(height: 8),
            Text(ticket.message, maxLines: 4, overflow: TextOverflow.ellipsis),
          ],
        ),
      ),
    );
  }
}
