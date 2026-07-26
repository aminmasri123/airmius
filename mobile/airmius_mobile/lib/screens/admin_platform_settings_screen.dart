import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class AdminPlatformSettingsScreen extends StatefulWidget {
  const AdminPlatformSettingsScreen({super.key});

  @override
  State<AdminPlatformSettingsScreen> createState() =>
      _AdminPlatformSettingsScreenState();
}

class _AdminPlatformSettingsScreenState
    extends State<AdminPlatformSettingsScreen> {
  Future<AirmiusJson>? _future;
  String _section = 'system';
  String _month = DateFormat('yyyy-MM').format(DateTime.now());
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.adminSystemDashboard(month: _month);
  }

  void _reload() {
    setState(() {
      _future = _client.adminSystemDashboard(month: _month);
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
          t('systemAdmin.title'),
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
            final error = snapshot.error;
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      error is AirmiusApiException
                          ? error.userMessage
                          : t('systemAdmin.loadFailed'),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 14),
                    FilledButton.icon(
                      onPressed: _reload,
                      icon: const Icon(Icons.refresh_outlined),
                      label: Text(t('common.retry')),
                    ),
                  ],
                ),
              ),
            );
          }
          final data = _systemMap(snapshot.data?['data']);
          return PageFrame(
            title: t('systemAdmin.title'),
            subtitle: t('systemAdmin.subtitle'),
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
    final settings = _systemMap(data['settings']);
    final maintenance = _systemMap(settings['maintenance']);
    final costs = _systemMap(data['provider_costs']);
    final totals = _systemMap(costs['totals']);
    final tokenStatus = _systemMap(settings['ai_token_status']);
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('systemAdmin.eyebrow')),
          const SizedBox(height: 7),
          Text(
            t('systemAdmin.headline'),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 7),
          Text(t('systemAdmin.body')),
          const SizedBox(height: 15),
          Wrap(
            spacing: 9,
            runSpacing: 9,
            children: [
              _SystemMetric(
                value: maintenance['enabled'] == true
                    ? t('systemAdmin.on')
                    : t('systemAdmin.off'),
                label: t('systemAdmin.maintenance'),
              ),
              _SystemMetric(
                value: '${_systemList(tokenStatus['providers']).length}',
                label: t('systemAdmin.aiProviders'),
              ),
              _SystemMetric(
                value: '${_systemInt(totals['events'])}',
                label: t('systemAdmin.providerEvents'),
              ),
              _SystemMetric(
                value: _systemMoney(totals['estimated_cost_eur']),
                label: t('systemAdmin.estimatedCosts'),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _tabs() {
    final tabs = {
      'system': t('systemAdmin.system'),
      'billing': t('systemAdmin.billing'),
      'templates': t('systemAdmin.templates'),
      'providers': t('systemAdmin.providers'),
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
    'billing' => _billing(data),
    'templates' => _templates(data),
    'providers' => _providers(data),
    _ => _system(data),
  };

  Widget _system(AirmiusJson data) {
    final settings = _systemMap(data['settings']);
    final maintenance = _systemMap(settings['maintenance']);
    final tokens = _systemList(
      _systemMap(settings['ai_token_status'])['providers'],
    );
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          title: t('systemAdmin.maintenance'),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: StatusPill(
                  maintenance['enabled'] == true
                      ? t('systemAdmin.enabled')
                      : t('systemAdmin.disabled'),
                ),
              ),
              const SizedBox(height: 10),
              Text(
                '${maintenance['title'] ?? '—'}',
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 6),
              Text('${maintenance['message'] ?? '—'}'),
              const SizedBox(height: 14),
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: FilledButton.icon(
                  onPressed: _busy
                      ? null
                      : () => _editMaintenance(data, maintenance),
                  icon: const Icon(Icons.construction_outlined),
                  label: Text(t('systemAdmin.editMaintenance')),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Text(
          t('systemAdmin.aiProviders'),
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 10),
        if (tokens.isEmpty)
          EmptyPanel(t('systemAdmin.noProviders'))
        else
          ...tokens.map((raw) {
            final token = _systemMap(raw);
            final severity = '${token['severity'] ?? 'neutral'}';
            return Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: AirmiusPanel(
                title: '${token['label'] ?? token['key'] ?? '—'}',
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: StatusPill(
                        '${token['status_label'] ?? '—'}',
                        color: _severityColor(context, severity),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '${token['model'] ?? t('systemAdmin.noModel')}\n'
                      '${token['message'] ?? ''}',
                    ),
                    const SizedBox(height: 6),
                    Text(
                      token['has_api_key'] == true
                          ? t('systemAdmin.keyConfigured')
                          : t('systemAdmin.keyMissing'),
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ),
            );
          }),
      ],
    );
  }

  Widget _billing(AirmiusJson data) {
    final billing = _systemMap(_systemMap(data['settings'])['billing']);
    return AirmiusPanel(
      title: t('systemAdmin.billingData'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _value(t('systemAdmin.company'), billing['company_name']),
          _value(t('systemAdmin.legalName'), billing['legal_name']),
          _value(
            t('systemAdmin.address'),
            '${billing['company_street'] ?? ''}, '
            '${billing['company_postal_code'] ?? ''} '
            '${billing['company_city'] ?? ''}',
          ),
          _value(t('systemAdmin.email'), billing['company_email']),
          _value(t('systemAdmin.vatId'), billing['vat_id']),
          _value(
            t('systemAdmin.iban'),
            _maskedIban('${billing['iban'] ?? ''}'),
          ),
          _value(
            t('systemAdmin.paymentTerms'),
            '${billing['payment_terms_days'] ?? 14}',
          ),
          const SizedBox(height: 12),
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: FilledButton.icon(
              onPressed: _busy ? null : () => _editBilling(data, billing),
              icon: const Icon(Icons.receipt_long_outlined),
              label: Text(t('systemAdmin.editBilling')),
            ),
          ),
        ],
      ),
    );
  }

  Widget _templates(AirmiusJson data) {
    final templates = _systemList(
      _systemMap(data['settings'])['email_templates'],
    );
    if (templates.isEmpty) return EmptyPanel(t('systemAdmin.noTemplates'));
    return Column(
      children: templates.map((raw) {
        final item = _systemMap(raw);
        final template = _systemMap(item['template']);
        return Padding(
          padding: const EdgeInsets.only(bottom: 12),
          child: AirmiusPanel(
            title: '${item['label'] ?? item['key'] ?? '—'}',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text('${item['description'] ?? ''}'),
                const SizedBox(height: 8),
                Text(
                  '${template['subject'] ?? '—'}',
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 12),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: OutlinedButton.icon(
                    onPressed: _busy ? null : () => _editTemplate(data, item),
                    icon: const Icon(Icons.edit_note_outlined),
                    label: Text(t('common.edit')),
                  ),
                ),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }

  Widget _providers(AirmiusJson data) {
    final costs = _systemMap(data['provider_costs']);
    final period = _systemMap(costs['period']);
    final cards = _systemList(costs['cards']);
    final recommendations = _systemList(costs['recommendations']);
    final comparisons = _systemList(costs['comparisons']);
    final usage = _systemList(costs['usageRows']);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          title: t('systemAdmin.period'),
          child: Row(
            children: [
              Expanded(
                child: TextFormField(
                  initialValue: '${period['month'] ?? _month}',
                  decoration: InputDecoration(
                    labelText: t('systemAdmin.monthFormat'),
                  ),
                  onChanged: (value) => _month = value.trim(),
                ),
              ),
              const SizedBox(width: 10),
              FilledButton(
                onPressed: _busy ? null : _reload,
                child: Text(t('systemAdmin.load')),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Wrap(
          spacing: 9,
          runSpacing: 9,
          children: cards.map((raw) {
            final card = _systemMap(raw);
            return _SystemMetric(
              value: '${card['value'] ?? 0}',
              label: '${card['label'] ?? '—'}',
            );
          }).toList(),
        ),
        const SizedBox(height: 12),
        ...recommendations.map((raw) {
          final item = _systemMap(raw);
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: AirmiusPanel(
              title: '${item['title'] ?? '—'}',
              child: Text('${item['body'] ?? ''}'),
            ),
          );
        }),
        ...comparisons.map((raw) {
          final item = _systemMap(raw);
          final cheapest = _systemMap(item['cheapest_plan']);
          return Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: AirmiusPanel(
              title: '${item['label'] ?? '—'}',
              child: Text(
                '${t('systemAdmin.usage')}: ${item['units'] ?? 0} ${item['unit_label'] ?? ''}\n'
                '${t('systemAdmin.cheapest')}: ${cheapest['label'] ?? '—'} · '
                '${_systemMoney(cheapest['estimated_cost_eur'])}',
              ),
            ),
          );
        }),
        if (usage.isEmpty)
          EmptyPanel(t('systemAdmin.noUsage'))
        else
          ...usage.take(30).map((raw) {
            final row = _systemMap(raw);
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: AirmiusPanel(
                title: '${row['provider'] ?? '—'} · ${row['service'] ?? '—'}',
                child: Text(
                  '${row['area'] ?? '—'} · ${row['operation'] ?? '—'}\n'
                  '${t('systemAdmin.requests')}: ${row['requests'] ?? 0} · '
                  '${t('systemAdmin.users')}: ${row['users'] ?? 0}',
                ),
              ),
            );
          }),
      ],
    );
  }

  Widget _value(String label, dynamic value) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Text('$label: ${value == null || '$value'.isEmpty ? '—' : value}'),
  );

  Future<void> _editMaintenance(
    AirmiusJson data,
    AirmiusJson maintenance,
  ) async {
    final title = TextEditingController(text: '${maintenance['title'] ?? ''}');
    final message = TextEditingController(
      text: '${maintenance['message'] ?? ''}',
    );
    var enabled = maintenance['enabled'] == true;
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
                  t('systemAdmin.editMaintenance'),
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  value: enabled,
                  title: Text(t('systemAdmin.enabled')),
                  onChanged: (value) => setSheetState(() => enabled = value),
                ),
                TextField(
                  controller: title,
                  decoration: InputDecoration(
                    labelText: t('systemAdmin.titleLabel'),
                  ),
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: message,
                  maxLines: 4,
                  decoration: InputDecoration(
                    labelText: t('systemAdmin.message'),
                  ),
                ),
                const SizedBox(height: 14),
                FilledButton(
                  onPressed: () => Navigator.pop(sheetContext, {
                    'enabled': enabled,
                    'title': title.text.trim(),
                    'message': message.text.trim(),
                  }),
                  child: Text(t('common.save')),
                ),
              ],
            ),
          ),
        ),
      ),
    );
    title.dispose();
    message.dispose();
    if (result == null) return;
    await _saveSettings(data, maintenanceOverride: result);
  }

  Future<void> _editBilling(AirmiusJson data, AirmiusJson billing) async {
    final fields = <String, TextEditingController>{
      for (final key in _billingKeys)
        key: TextEditingController(text: '${billing[key] ?? ''}'),
    };
    final result = await showModalBottomSheet<AirmiusJson>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => Padding(
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
                t('systemAdmin.editBilling'),
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 14),
              for (final key in _billingKeys) ...[
                TextField(
                  controller: fields[key],
                  decoration: InputDecoration(
                    labelText: t('systemAdmin.field.$key'),
                  ),
                  keyboardType: key == 'payment_terms_days'
                      ? TextInputType.number
                      : TextInputType.text,
                ),
                const SizedBox(height: 10),
              ],
              FilledButton(
                onPressed: () => Navigator.pop(sheetContext, {
                  for (final key in _billingKeys)
                    key: key == 'payment_terms_days'
                        ? int.tryParse(fields[key]!.text.trim()) ?? 14
                        : fields[key]!.text.trim(),
                }),
                child: Text(t('common.save')),
              ),
            ],
          ),
        ),
      ),
    );
    for (final controller in fields.values) {
      controller.dispose();
    }
    if (result == null) return;
    await _saveSettings(data, billingOverride: result);
  }

  Future<void> _editTemplate(AirmiusJson data, AirmiusJson item) async {
    final template = _systemMap(item['template']);
    final subject = TextEditingController(text: '${template['subject'] ?? ''}');
    final greeting = TextEditingController(
      text: '${template['greeting'] ?? ''}',
    );
    final body = TextEditingController(text: '${template['body'] ?? ''}');
    final action = TextEditingController(
      text: '${template['action_label'] ?? ''}',
    );
    final result = await showModalBottomSheet<AirmiusJson>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (sheetContext) => Padding(
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
                '${item['label'] ?? t('systemAdmin.template')}',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: subject,
                decoration: InputDecoration(
                  labelText: t('systemAdmin.subject'),
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: greeting,
                decoration: InputDecoration(
                  labelText: t('systemAdmin.greeting'),
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: body,
                maxLines: 8,
                decoration: InputDecoration(
                  labelText: t('systemAdmin.message'),
                ),
              ),
              const SizedBox(height: 10),
              TextField(
                controller: action,
                decoration: InputDecoration(
                  labelText: t('systemAdmin.actionLabel'),
                ),
              ),
              const SizedBox(height: 14),
              FilledButton(
                onPressed: () => Navigator.pop(sheetContext, {
                  'subject': subject.text.trim(),
                  'greeting': greeting.text.trim(),
                  'body': body.text.trim(),
                  'action_label': action.text.trim(),
                }),
                child: Text(t('common.save')),
              ),
            ],
          ),
        ),
      ),
    );
    for (final controller in [subject, greeting, body, action]) {
      controller.dispose();
    }
    if (result == null) return;
    await _saveSettings(
      data,
      templateKey: '${item['key']}',
      templateOverride: result,
    );
  }

  Future<void> _saveSettings(
    AirmiusJson data, {
    AirmiusJson? maintenanceOverride,
    AirmiusJson? billingOverride,
    String? templateKey,
    AirmiusJson? templateOverride,
  }) async {
    final settings = _systemMap(data['settings']);
    final maintenance = {
      ..._systemMap(settings['maintenance']),
      ...?maintenanceOverride,
    };
    final billing = {..._systemMap(settings['billing']), ...?billingOverride};
    final templates = <String, dynamic>{};
    for (final raw in _systemList(settings['email_templates'])) {
      final item = _systemMap(raw);
      final key = '${item['key']}';
      templates[key] = key == templateKey
          ? templateOverride
          : _systemMap(item['template']);
    }
    final payload = <String, dynamic>{
      'maintenance_enabled': maintenance['enabled'] == true,
      'maintenance_title': maintenance['title'],
      'maintenance_message': maintenance['message'],
      for (final key in _billingKeys)
        'billing_$key': key == 'payment_terms_days'
            ? _systemInt(billing[key], fallback: 14)
            : billing[key],
      'email_templates': templates,
    };
    await _run(
      () => _client.adminUpdateSystemSettings(payload),
      t('systemAdmin.saved'),
    );
  }

  Color _severityColor(BuildContext context, String severity) =>
      switch (severity) {
        'danger' => Theme.of(context).colorScheme.error,
        'warning' => Colors.orange,
        'success' => Colors.green,
        _ => Theme.of(context).colorScheme.outline,
      };

  String _systemMoney(dynamic value) => NumberFormat.simpleCurrency(
    locale: Localizations.localeOf(context).toLanguageTag(),
    name: 'EUR',
  ).format(value is num ? value : double.tryParse('$value') ?? 0);

  String _maskedIban(String value) {
    final normalized = value.replaceAll(' ', '');
    if (normalized.length <= 8) return normalized.isEmpty ? '—' : normalized;
    return '${normalized.substring(0, 4)} •••• ${normalized.substring(normalized.length - 4)}';
  }
}

class _SystemMetric extends StatelessWidget {
  const _SystemMetric({required this.value, required this.label});

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

const _billingKeys = [
  'brand_name',
  'company_name',
  'legal_name',
  'company_street',
  'company_postal_code',
  'company_city',
  'company_country',
  'company_email',
  'company_website',
  'tax_number',
  'vat_id',
  'court',
  'registration_number',
  'managing_director',
  'small_business_notice',
  'invoice_note',
  'bank_account_holder',
  'bank_name',
  'iban',
  'bic',
  'payment_terms_days',
];

AirmiusJson _systemMap(dynamic value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

List<dynamic> _systemList(dynamic value) => value is List ? value : const [];

int _systemInt(dynamic value, {int fallback = 0}) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? fallback;
