import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class AdminMailCenterScreen extends StatefulWidget {
  const AdminMailCenterScreen({super.key});

  @override
  State<AdminMailCenterScreen> createState() => _AdminMailCenterScreenState();
}

class _AdminMailCenterScreenState extends State<AdminMailCenterScreen> {
  Future<AirmiusJson>? _future;
  String _section = 'deliveries';
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.adminMailDashboard();
  }

  void _reload() {
    setState(() {
      _future = _client.adminMailDashboard();
    });
  }

  Future<void> _run(
    Future<AirmiusJson> Function() action,
    String success,
  ) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(success)));
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('mailAdmin.title'),
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
          final data = _mailMap(snapshot.data?['data']);
          return PageFrame(
            title: t('mailAdmin.title'),
            subtitle: t('mailAdmin.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _hero(data),
                const SizedBox(height: 14),
                _tabs(),
                if (_busy) ...[
                  const SizedBox(height: 10),
                  const LinearProgressIndicator(minHeight: 3),
                ],
                const SizedBox(height: 14),
                _content(data),
              ],
            ),
          );
        },
      ),
    );
  }

  Widget _hero(AirmiusJson data) {
    final summary = _mailMap(data['summary']);
    final queue = _mailMap(data['queue']);
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('mailAdmin.eyebrow')),
          const SizedBox(height: 7),
          Text(
            t('mailAdmin.headline'),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 7),
          Text(t('mailAdmin.body')),
          const SizedBox(height: 15),
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              _MailMetric(
                value: '${_mailInt(summary['last_24h'])}',
                label: t('mailAdmin.last24h'),
              ),
              _MailMetric(
                value: '${_mailInt(summary['failed'])}',
                label: t('mailAdmin.failed'),
              ),
              _MailMetric(
                value: '${_mailInt(queue['pending_jobs'])}',
                label: t('mailAdmin.pendingJobs'),
              ),
              _MailMetric(
                value: '${_mailInt(queue['failed_jobs'])}',
                label: t('mailAdmin.failedJobs'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _tabs() {
    final tabs = {
      'deliveries': t('mailAdmin.deliveries'),
      'senders': t('mailAdmin.senders'),
      'rules': t('mailAdmin.rules'),
      'queue': t('mailAdmin.queue'),
    };
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: tabs.entries
            .map(
              (entry) => Padding(
                padding: const EdgeInsetsDirectional.only(end: 8),
                child: ChoiceChip(
                  selected: _section == entry.key,
                  label: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 7),
                    child: Text(entry.value),
                  ),
                  onSelected: (_) => setState(() => _section = entry.key),
                ),
              ),
            )
            .toList(),
      ),
    );
  }

  Widget _content(AirmiusJson data) => switch (_section) {
    'senders' => _senders(data),
    'rules' => _rules(data),
    'queue' => _queue(data),
    _ => _deliveries(data),
  };

  Widget _deliveries(AirmiusJson data) {
    final values = _mailList(data['deliveries']);
    final categories = _mailList(
      data['categories'],
    ).map((value) => '$value').toList();
    if (values.isEmpty) return EmptyPanel(t('mailAdmin.noDeliveries'));
    return Column(
      children: values.map((raw) {
        final item = _mailMap(raw);
        final recipient = _mailMap(item['recipient']);
        final status = '${item['status'] ?? 'unknown'}';
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${item['mail_type'] ?? '—'}',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: StatusPill(t('mailAdmin.status.$status')),
                ),
                const SizedBox(height: 10),
                Text(
                  '${recipient['name'] ?? '—'} · ${recipient['email'] ?? '—'}\n'
                  '${item['used_category'] ?? item['primary_category'] ?? '—'} · '
                  '${item['created_at'] ?? '—'}',
                ),
                if ('${item['error_message'] ?? ''}'.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    '${item['error_message']}',
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (item['resendable'] == true)
                      OutlinedButton.icon(
                        onPressed: _busy
                            ? null
                            : () => _resend(item, categories),
                        icon: const Icon(Icons.replay_outlined),
                        label: Text(t('mailAdmin.resend')),
                      ),
                    if (status != 'resolved')
                      OutlinedButton.icon(
                        onPressed: _busy ? null : () => _resolve(item),
                        icon: const Icon(Icons.task_alt_outlined),
                        label: Text(t('mailAdmin.resolve')),
                      ),
                  ],
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _senders(AirmiusJson data) {
    final canManageSecrets =
        _mailMap(data['abilities'])['manage_secrets'] == true;
    final values = _mailList(data['senders']);
    if (values.isEmpty) return EmptyPanel(t('mailAdmin.noSenders'));
    return Column(
      children: values.map((raw) {
        final sender = _mailMap(raw);
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: AirmiusPanel(
            title: '${sender['category'] ?? '—'}',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${sender['name'] ?? '—'} · ${sender['address'] ?? '—'}\n'
                  '${sender['mailer'] ?? '—'} · ${sender['host'] ?? '—'}:${sender['port'] ?? '—'}',
                ),
                const SizedBox(height: 10),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: StatusPill(
                    sender['ready'] == true
                        ? t('mailAdmin.ready')
                        : t('mailAdmin.notReady'),
                  ),
                ),
                if (canManageSecrets) ...[
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      OutlinedButton.icon(
                        onPressed: _busy ? null : () => _editSender(sender),
                        icon: const Icon(Icons.edit_outlined),
                        label: Text(t('common.edit')),
                      ),
                      OutlinedButton.icon(
                        onPressed: _busy
                            ? null
                            : () => _run(
                                () => _client.adminTestMailSender(
                                  '${sender['category']}',
                                ),
                                t('mailAdmin.testSent'),
                              ),
                        icon: const Icon(Icons.mark_email_read_outlined),
                        label: Text(t('mailAdmin.sendTest')),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _rules(AirmiusJson data) {
    final preferences = _mailMap(data['preferences']);
    return AirmiusPanel(
      title: t('mailAdmin.invoiceRouting'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            '${t('mailAdmin.primary')}: ${preferences['invoice_primary_category'] ?? '—'}\n'
            '${t('mailAdmin.fallback')}: ${preferences['invoice_fallback_category'] ?? '—'}\n'
            '${t('mailAdmin.disabled')}: ${_mailList(preferences['disabled_categories']).join(', ')}',
          ),
          const SizedBox(height: 14),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: FilledButton.icon(
              onPressed: _busy ? null : () => _editRules(data),
              icon: const Icon(Icons.alt_route_outlined),
              label: Text(t('mailAdmin.editRules')),
            ),
          ),
        ],
      ),
    );
  }

  Widget _queue(AirmiusJson data) {
    final queue = _mailMap(data['queue']);
    final failures = _mailList(queue['recent_failed_jobs']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          title: t('mailAdmin.queue'),
          child: Text(
            '${t('mailAdmin.pendingJobs')}: ${_mailInt(queue['pending_jobs'])}\n'
            '${t('mailAdmin.failedJobs')}: ${_mailInt(queue['failed_jobs'])}',
          ),
        ),
        const SizedBox(height: 12),
        if (failures.isEmpty)
          EmptyPanel(t('mailAdmin.noQueueErrors'))
        else
          ...failures.map((raw) {
            final item = _mailMap(raw);
            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: AirmiusPanel(
                title: '${item['queue'] ?? t('mailAdmin.queue')}',
                child: Text('${item['error'] ?? '—'}'),
              ),
            );
          }),
      ],
    );
  }

  Future<void> _resolve(AirmiusJson item) async {
    final yes = await _confirm(
      t('mailAdmin.resolve'),
      t('mailAdmin.resolveBody'),
    );
    if (!yes) return;
    await _run(
      () => _client.adminResolveMailDelivery(_mailInt(item['id'])),
      t('mailAdmin.resolved'),
    );
  }

  Future<void> _resend(AirmiusJson item, List<String> categories) async {
    if (categories.isEmpty) return;
    var category = '${item['primary_category'] ?? categories.first}';
    if (!categories.contains(category)) category = categories.first;
    final selected = await showDialog<String>(
      context: context,
      builder: (dialogContext) => SimpleDialog(
        title: Text(t('mailAdmin.selectSender')),
        children: categories
            .map(
              (value) => SimpleDialogOption(
                onPressed: () => Navigator.pop(dialogContext, value),
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  child: Text(value),
                ),
              ),
            )
            .toList(),
      ),
    );
    if (selected == null) return;
    await _run(
      () => _client.adminResendMailDelivery(_mailInt(item['id']), selected),
      t('mailAdmin.resent'),
    );
  }

  Future<void> _editRules(AirmiusJson data) async {
    final categories = _mailList(
      data['categories'],
    ).map((value) => '$value').toList();
    if (categories.isEmpty) return;
    final current = _mailMap(data['preferences']);
    var primary = '${current['invoice_primary_category'] ?? categories.first}';
    var fallback =
        '${current['invoice_fallback_category'] ?? categories.first}';
    final result = await showDialog<List<String>>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('mailAdmin.editRules')),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DropdownButtonFormField<String>(
                initialValue: primary,
                decoration: InputDecoration(labelText: t('mailAdmin.primary')),
                items: _options(categories),
                onChanged: (value) =>
                    setDialogState(() => primary = value ?? primary),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: fallback,
                decoration: InputDecoration(labelText: t('mailAdmin.fallback')),
                items: _options(categories),
                onChanged: (value) =>
                    setDialogState(() => fallback = value ?? fallback),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () =>
                  Navigator.pop(dialogContext, [primary, fallback]),
              child: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );
    if (result == null) return;
    await _run(
      () => _client.adminUpdateMailPreferences({
        'invoice_primary_category': result[0],
        'invoice_fallback_category': result[1],
        'disabled_categories': current['disabled_categories'] ?? [],
      }),
      t('mailAdmin.rulesSaved'),
    );
  }

  Future<void> _editSender(AirmiusJson sender) async {
    final address = TextEditingController(text: '${sender['address'] ?? ''}');
    final name = TextEditingController(text: '${sender['name'] ?? ''}');
    final host = TextEditingController(text: '${sender['host'] ?? ''}');
    final port = TextEditingController(text: '${sender['port'] ?? ''}');
    final username = TextEditingController(text: '${sender['username'] ?? ''}');
    final password = TextEditingController();
    var active = sender['active'] == true;
    final result = await showModalBottomSheet<AirmiusJson>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setSheetState) => Padding(
          padding: EdgeInsets.fromLTRB(
            18,
            18,
            18,
            18 + MediaQuery.viewInsetsOf(sheetContext).bottom,
          ),
          child: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  t('mailAdmin.editSender'),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 14),
                for (final pair in [
                  (address, t('mailAdmin.address')),
                  (name, t('mailAdmin.name')),
                  (host, t('mailAdmin.host')),
                  (port, t('mailAdmin.port')),
                  (username, t('mailAdmin.username')),
                  (password, t('mailAdmin.newPassword')),
                ]) ...[
                  TextField(
                    controller: pair.$1,
                    obscureText: pair.$1 == password,
                    decoration: InputDecoration(labelText: pair.$2),
                  ),
                  const SizedBox(height: 10),
                ],
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  value: active,
                  title: Text(t('mailAdmin.active')),
                  onChanged: (value) => setSheetState(() => active = value),
                ),
                FilledButton(
                  onPressed: () => Navigator.pop(sheetContext, {
                    'from_address': address.text.trim(),
                    'from_name': name.text.trim(),
                    'host': host.text.trim(),
                    'port': int.tryParse(port.text.trim()),
                    'username': username.text.trim(),
                    'new_password': password.text.trim(),
                    'scheme': sender['scheme'] ?? '',
                    'active': active,
                  }),
                  child: Text(t('common.save')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    for (final controller in [address, name, host, port, username, password]) {
      controller.dispose();
    }
    if (result == null) return;
    await _run(
      () => _client.adminUpdateMailSender('${sender['category']}', result),
      t('mailAdmin.senderSaved'),
    );
  }

  Future<bool> _confirm(String title, String body) async =>
      await showDialog<bool>(
        context: context,
        builder: (dialogContext) => AlertDialog(
          title: Text(title),
          content: Text(body),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(t('common.confirm')),
            ),
          ],
        ),
      ) ??
      false;

  List<DropdownMenuItem<String>> _options(List<String> values) => values
      .map((value) => DropdownMenuItem(value: value, child: Text(value)))
      .toList();
}

class _MailMetric extends StatelessWidget {
  const _MailMetric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(minWidth: 130),
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.surface.withValues(alpha: .72),
          borderRadius: BorderRadius.circular(16),
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              Text(label),
            ],
          ),
        ),
      ),
    );
  }
}

AirmiusJson _mailMap(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<dynamic> _mailList(dynamic value) => value is List ? value : const [];

int _mailInt(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
