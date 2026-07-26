import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SponsorManagementScreen extends StatefulWidget {
  const SponsorManagementScreen({super.key});

  @override
  State<SponsorManagementScreen> createState() =>
      _SponsorManagementScreenState();
}

class _SponsorManagementScreenState extends State<SponsorManagementScreen> {
  Future<JsonMap>? _future;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.sponsorManagement();
  }

  void _reload() {
    setState(() {
      _future = _client.sponsorManagement();
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
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${t('sponsorAdmin.actionError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _edit(List<JsonMap> clubs, {JsonMap? sponsor}) async {
    final payload = await showModalBottomSheet<JsonMap>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (_) => _SponsorEditorSheet(clubs: clubs, sponsor: sponsor),
    );
    if (payload == null || !mounted) return;
    await _run(
      () => sponsor == null
          ? _client.createManagedSponsor(payload)
          : _client.updateManagedSponsor(_adminInt(sponsor['id']), payload),
      t(sponsor == null ? 'sponsorAdmin.created' : 'sponsorAdmin.updated'),
    );
  }

  Future<void> _delete(JsonMap sponsor) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('sponsorAdmin.deleteTitle')),
        content: Text(t('sponsorAdmin.deleteQuestion')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
              foregroundColor: Theme.of(context).colorScheme.onError,
            ),
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(
      () => _client.deleteManagedSponsor(_adminInt(sponsor['id'])),
      t('sponsorAdmin.deleted'),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('sponsorAdmin.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('sponsorAdmin.reload'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<JsonMap>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: AirmiusButton(
                label: t('sponsorAdmin.retry'),
                icon: Icons.refresh_outlined,
                onPressed: _reload,
              ),
            );
          }
          final sponsors = _adminList(snapshot.data?['data']);
          final clubs = _adminList(snapshot.data?['clubs']);
          final stats = _adminMap(snapshot.data?['stats']);
          return PageFrame(
            title: t('sponsorAdmin.title'),
            subtitle: t('sponsorAdmin.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('sponsorAdmin.secure')),
                      const SizedBox(height: 8),
                      Text(
                        t('sponsorAdmin.hero'),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t('sponsorAdmin.accessHint'),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 14),
                      AirmiusButton(
                        label: t('sponsorAdmin.create'),
                        icon: Icons.add_business_outlined,
                        onPressed: _busy ? null : () => _edit(clubs),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: MetricCard(
                        value: '${_adminInt(stats['total'])}',
                        label: t('sponsors.all'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value: '${_adminInt(stats['platform'])}',
                        label: t('sponsors.platform'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: MetricCard(
                        value: '${_adminInt(stats['club'])}',
                        label: t('sponsors.clubs'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                if (sponsors.isEmpty)
                  AirmiusPanel(
                    child: Text(
                      t('sponsorAdmin.empty'),
                      textAlign: TextAlign.center,
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  )
                else
                  ...sponsors.map(
                    (sponsor) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: AirmiusPanel(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              children: [
                                const Icon(
                                  Icons.handshake_outlined,
                                  color: AirmiusColors.green,
                                  size: 28,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Text(
                                    _adminText(sponsor['name']),
                                    style: TextStyle(
                                      color: airmiusTextColor(context),
                                      fontSize: 17,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                                StatusPill(
                                  t(
                                    'sponsors.scope.${_adminText(sponsor['scope'], fallback: 'platform')}',
                                  ),
                                ),
                              ],
                            ),
                            if (_adminText(sponsor['email']).isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Text(
                                _adminText(sponsor['email']),
                                style: TextStyle(
                                  color: airmiusMutedColor(context),
                                ),
                              ),
                            ],
                            const SizedBox(height: 12),
                            Wrap(
                              spacing: 10,
                              runSpacing: 10,
                              children: [
                                AirmiusButton(
                                  label: t('edit'),
                                  icon: Icons.edit_outlined,
                                  secondary: true,
                                  onPressed: _busy
                                      ? null
                                      : () => _edit(clubs, sponsor: sponsor),
                                ),
                                AirmiusButton(
                                  label: t('delete'),
                                  icon: Icons.delete_outline,
                                  danger: true,
                                  onPressed: _busy
                                      ? null
                                      : () => _delete(sponsor),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _SponsorEditorSheet extends StatefulWidget {
  const _SponsorEditorSheet({required this.clubs, this.sponsor});

  final List<JsonMap> clubs;
  final JsonMap? sponsor;

  @override
  State<_SponsorEditorSheet> createState() => _SponsorEditorSheetState();
}

class _SponsorEditorSheetState extends State<_SponsorEditorSheet> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _contact;
  late final TextEditingController _email;
  late final TextEditingController _website;
  late final TextEditingController _logoLight;
  late final TextEditingController _logoDark;
  late final TextEditingController _amount;
  late String _scope;
  int? _clubId;
  DateTime? _starts;
  DateTime? _ends;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    final sponsor = widget.sponsor ?? const <String, dynamic>{};
    _name = TextEditingController(text: _adminText(sponsor['name']));
    _contact = TextEditingController(text: _adminText(sponsor['contact_name']));
    _email = TextEditingController(text: _adminText(sponsor['email']));
    _website = TextEditingController(text: _adminText(sponsor['website']));
    _logoLight = TextEditingController(text: _adminText(sponsor['logo_light']));
    _logoDark = TextEditingController(text: _adminText(sponsor['logo_dark']));
    _amount = TextEditingController(text: _adminText(sponsor['amount']));
    _scope = _adminText(sponsor['scope'], fallback: 'club');
    _clubId = _adminNullableInt(sponsor['club_id']);
    _starts = DateTime.tryParse(_adminText(sponsor['starts_at']));
    _ends = DateTime.tryParse(_adminText(sponsor['ends_at']));
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _contact,
      _email,
      _website,
      _logoLight,
      _logoDark,
      _amount,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _pickDate(bool start) async {
    final current = start ? _starts : _ends;
    final selected = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (selected == null || !mounted) return;
    setState(() {
      if (start) {
        _starts = selected;
        if (_ends != null && _ends!.isBefore(selected)) _ends = selected;
      } else {
        _ends = selected;
      }
    });
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    if (_scope == 'club' && _clubId == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('sponsorAdmin.clubRequired'))));
      return;
    }
    Navigator.pop(context, {
      'scope': _scope,
      'club_id': _scope == 'club' ? _clubId : null,
      'name': _name.text.trim(),
      'contact_name': _adminNull(_contact.text),
      'email': _adminNull(_email.text),
      'website': _adminNull(_website.text),
      'logo_light': _adminNull(_logoLight.text),
      'logo_dark': _adminNull(_logoDark.text),
      'amount': double.tryParse(_amount.text.replaceAll(',', '.')),
      'starts_at': _dateString(_starts),
      'ends_at': _dateString(_ends),
    });
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.95,
      minChildSize: 0.7,
      maxChildSize: 0.98,
      builder: (context, controller) => Form(
        key: _formKey,
        child: SingleChildScrollView(
          controller: controller,
          padding: EdgeInsets.fromLTRB(
            20,
            12,
            20,
            24 + MediaQuery.viewInsetsOf(context).bottom,
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      t(
                        widget.sponsor == null
                            ? 'sponsorAdmin.createTitle'
                            : 'sponsorAdmin.editTitle',
                      ),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: t('close'),
                    onPressed: () => Navigator.pop(context),
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _scope,
                decoration: InputDecoration(labelText: t('sponsorAdmin.scope')),
                items: ['club', 'platform', 'outfit_subscription']
                    .map(
                      (scope) => DropdownMenuItem(
                        value: scope,
                        child: Text(t('sponsors.scope.$scope')),
                      ),
                    )
                    .toList(),
                onChanged: (value) => setState(() => _scope = value ?? 'club'),
              ),
              if (_scope == 'club') ...[
                const SizedBox(height: 12),
                DropdownButtonFormField<int>(
                  initialValue: _clubId,
                  decoration: InputDecoration(
                    labelText: t('sponsorAdmin.club'),
                  ),
                  items: widget.clubs
                      .map(
                        (club) => DropdownMenuItem(
                          value: _adminInt(club['id']),
                          child: Text(_adminText(club['name'])),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setState(() => _clubId = value),
                ),
              ],
              const SizedBox(height: 12),
              _SponsorField(
                controller: _name,
                label: t('sponsorAdmin.name'),
                required: true,
              ),
              const SizedBox(height: 12),
              _SponsorField(
                controller: _contact,
                label: t('sponsorAdmin.contact'),
              ),
              const SizedBox(height: 12),
              _SponsorField(
                controller: _email,
                label: t('sponsorAdmin.email'),
                keyboardType: TextInputType.emailAddress,
              ),
              const SizedBox(height: 12),
              _SponsorField(
                controller: _website,
                label: t('sponsorAdmin.website'),
                keyboardType: TextInputType.url,
              ),
              const SizedBox(height: 12),
              _SponsorField(
                controller: _amount,
                label: t('sponsorAdmin.amount'),
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
              ),
              const SizedBox(height: 12),
              _SponsorField(
                controller: _logoLight,
                label: t('sponsorAdmin.logoLight'),
                keyboardType: TextInputType.url,
              ),
              const SizedBox(height: 12),
              _SponsorField(
                controller: _logoDark,
                label: t('sponsorAdmin.logoDark'),
                keyboardType: TextInputType.url,
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _pickDate(true),
                      icon: const Icon(Icons.date_range_outlined),
                      label: Text(
                        _starts == null
                            ? t('sponsorAdmin.start')
                            : _dateString(_starts)!,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _pickDate(false),
                      icon: const Icon(Icons.event_busy_outlined),
                      label: Text(
                        _ends == null
                            ? t('sponsorAdmin.end')
                            : _dateString(_ends)!,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              AirmiusButton(
                label: t('save'),
                icon: Icons.save_outlined,
                onPressed: _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SponsorField extends StatelessWidget {
  const _SponsorField({
    required this.controller,
    required this.label,
    this.required = false,
    this.keyboardType,
  });

  final TextEditingController controller;
  final String label;
  final bool required;
  final TextInputType? keyboardType;

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      decoration: InputDecoration(labelText: label),
      validator: (value) {
        if (required && (value == null || value.trim().isEmpty)) {
          return AirmiusScope.of(context).t('required');
        }
        return null;
      },
    );
  }
}

JsonMap _adminMap(Object? value) =>
    value is Map<String, dynamic> ? value : <String, dynamic>{};

List<JsonMap> _adminList(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <JsonMap>[];

String _adminText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _adminInt(Object? value) =>
    value is int ? value : int.tryParse('${value ?? ''}') ?? 0;

int? _adminNullableInt(Object? value) {
  final parsed = _adminInt(value);
  return parsed == 0 ? null : parsed;
}

String? _adminNull(String value) {
  final text = value.trim();
  return text.isEmpty ? null : text;
}

String? _dateString(DateTime? date) {
  if (date == null) return null;
  return '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
}
