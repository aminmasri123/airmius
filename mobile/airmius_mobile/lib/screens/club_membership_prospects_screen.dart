import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubMembershipProspectsScreen extends StatefulWidget {
  const ClubMembershipProspectsScreen({
    super.key,
    required this.clubId,
    required this.clubName,
    this.teams = const [],
    this.membershipTypes = const [],
  });

  final int clubId;
  final String clubName;
  final List<AirmiusTeam> teams;
  final List<JsonMap> membershipTypes;

  @override
  State<ClubMembershipProspectsScreen> createState() =>
      _ClubMembershipProspectsScreenState();
}

class _ClubMembershipProspectsScreenState
    extends State<ClubMembershipProspectsScreen> {
  static const _statuses = <String>[
    'prospect',
    'trial_scheduled',
    'trial_completed',
    'application',
    'declined',
    'archived',
  ];
  static const _outcomes = <String>[
    'interested',
    'application',
    'converted',
    'no_show',
    'declined',
  ];

  Future<AirmiusPage<AirmiusClubMembershipProspect>>? _future;
  String? _statusFilter;
  bool _mutating = false;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<AirmiusPage<AirmiusClubMembershipProspect>> _load() async {
    final repository = AirmiusServicesScope.of(
      context,
    ).repositories.memberships;
    final first = await repository.clubProspects(
      widget.clubId,
      status: _statusFilter,
    );
    if (first.lastPage <= 1) return first;
    final items = [...first.items];
    for (var page = 2; page <= first.lastPage; page += 1) {
      final next = await repository.clubProspects(
        widget.clubId,
        page: page,
        status: _statusFilter,
      );
      items.addAll(next.items);
    }
    return AirmiusPage(
      items: items,
      currentPage: first.lastPage,
      lastPage: first.lastPage,
    );
  }

  void _reload() => setState(() => _future = _load());

  String _statusLabel(String status) => t('membership.prospect.status.$status');

  String _outcomeLabel(String outcome) =>
      t('membership.prospect.outcome.$outcome');

  String _dateTime(DateTime value) {
    String two(int number) => number.toString().padLeft(2, '0');
    return '${two(value.day)}.${two(value.month)}.${value.year} · '
        '${two(value.hour)}:${two(value.minute)}';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('membership.prospect.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('common.refresh'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _mutating ? null : () => _edit(),
        icon: const Icon(Icons.person_add_alt_1_outlined),
        label: Text(t('membership.prospect.add')),
      ),
      body: PageFrame(
        title: widget.clubName,
        subtitle: t('membership.prospect.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              child: DropdownButtonFormField<String>(
                initialValue: _statusFilter ?? 'all',
                decoration: InputDecoration(
                  labelText: t('membership.prospect.filter'),
                  prefixIcon: const Icon(Icons.filter_alt_outlined),
                ),
                items: [
                  DropdownMenuItem(
                    value: 'all',
                    child: Text(t('membership.prospect.all')),
                  ),
                  for (final status in [..._statuses, 'converted'])
                    DropdownMenuItem(
                      value: status,
                      child: Text(_statusLabel(status)),
                    ),
                ],
                onChanged: (value) {
                  _statusFilter = value == 'all' ? null : value;
                  _reload();
                },
              ),
            ),
            const SizedBox(height: 14),
            FutureBuilder<AirmiusPage<AirmiusClubMembershipProspect>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(
                    child: Padding(
                      padding: EdgeInsets.all(32),
                      child: CircularProgressIndicator(),
                    ),
                  );
                }
                if (snapshot.hasError) {
                  return AirmiusPanel(
                    child: Column(
                      children: [
                        Text(
                          t('membership.prospect.loadFailed'),
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 12),
                        AirmiusButton(
                          label: t('common.retry'),
                          icon: Icons.refresh_outlined,
                          onPressed: _reload,
                        ),
                      ],
                    ),
                  );
                }
                final prospects = snapshot.data?.items ?? const [];
                if (prospects.isEmpty) {
                  return AirmiusPanel(
                    child: Text(
                      t('membership.prospect.empty'),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  );
                }
                return Column(
                  children: [
                    for (final prospect in prospects) ...[
                      _ProspectCard(
                        prospect: prospect,
                        statusLabel: _statusLabel(prospect.status),
                        outcomeLabel: prospect.trialOutcome == null
                            ? null
                            : _outcomeLabel(prospect.trialOutcome!),
                        trialLabel: prospect.trialAt == null
                            ? null
                            : _dateTime(prospect.trialAt!.toLocal()),
                        convertedLabel: t(
                          'membership.prospect.convertedLocked',
                        ),
                        editLabel: t('common.edit'),
                        archiveLabel: t('membership.prospect.archive'),
                        onEdit: prospect.status == 'converted' || _mutating
                            ? null
                            : () => _edit(prospect),
                        onArchive:
                            prospect.status == 'converted' ||
                                prospect.status == 'archived' ||
                                _mutating
                            ? null
                            : () => _archive(prospect),
                      ),
                      const SizedBox(height: 12),
                    ],
                  ],
                );
              },
            ),
            const SizedBox(height: 76),
          ],
        ),
      ),
    );
  }

  Future<void> _edit([AirmiusClubMembershipProspect? prospect]) async {
    final name = TextEditingController(text: prospect?.name ?? '');
    final email = TextEditingController(text: prospect?.email ?? '');
    final phone = TextEditingController(text: prospect?.phone ?? '');
    final source = TextEditingController(text: prospect?.source ?? '');
    final notes = TextEditingController(text: prospect?.notes ?? '');
    var status = prospect?.status ?? 'prospect';
    var outcome = prospect?.trialOutcome;
    var teamId = widget.teams.any((team) => team.id == prospect?.teamId)
        ? prospect!.teamId!
        : 0;
    var membershipTypeId =
        widget.membershipTypes.any(
          (type) => int.tryParse('${type['id']}') == prospect?.membershipTypeId,
        )
        ? prospect!.membershipTypeId!
        : 0;
    var trialAt = prospect?.trialAt?.toLocal();
    final formKey = GlobalKey<FormState>();

    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(
            prospect == null
                ? t('membership.prospect.add')
                : t('membership.prospect.edit'),
          ),
          content: SizedBox(
            width: 520,
            child: SingleChildScrollView(
              child: Form(
                key: formKey,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextFormField(
                      controller: name,
                      decoration: InputDecoration(
                        labelText: t('membership.prospect.name'),
                      ),
                      validator: (value) =>
                          value == null || value.trim().isEmpty
                          ? t('membership.prospect.nameRequired')
                          : null,
                    ),
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: email,
                      keyboardType: TextInputType.emailAddress,
                      decoration: InputDecoration(
                        labelText: t('membership.prospect.email'),
                      ),
                    ),
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: phone,
                      keyboardType: TextInputType.phone,
                      decoration: InputDecoration(
                        labelText: t('membership.prospect.phone'),
                      ),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      initialValue: status,
                      decoration: InputDecoration(
                        labelText: t('membership.prospect.status'),
                      ),
                      items: [
                        for (final value in _statuses)
                          DropdownMenuItem(
                            value: value,
                            child: Text(_statusLabel(value)),
                          ),
                      ],
                      onChanged: (value) {
                        if (value != null) setDialogState(() => status = value);
                      },
                    ),
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: source,
                      decoration: InputDecoration(
                        labelText: t('membership.prospect.source'),
                      ),
                    ),
                    const SizedBox(height: 10),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.event_outlined),
                      title: Text(t('membership.prospect.trialAt')),
                      subtitle: Text(
                        trialAt == null
                            ? t('membership.prospect.notScheduled')
                            : _dateTime(trialAt!),
                      ),
                      trailing: Wrap(
                        children: [
                          if (trialAt != null)
                            IconButton(
                              tooltip: t('membership.prospect.clearTrial'),
                              onPressed: () =>
                                  setDialogState(() => trialAt = null),
                              icon: const Icon(Icons.clear_outlined),
                            ),
                          IconButton(
                            tooltip: t('membership.prospect.chooseTrial'),
                            onPressed: () async {
                              final selected = await _pickTrial(trialAt);
                              if (selected != null) {
                                setDialogState(() => trialAt = selected);
                              }
                            },
                            icon: const Icon(Icons.edit_calendar_outlined),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 10),
                    DropdownButtonFormField<String>(
                      initialValue: outcome ?? 'none',
                      decoration: InputDecoration(
                        labelText: t('membership.prospect.trialOutcome'),
                      ),
                      items: [
                        DropdownMenuItem(
                          value: 'none',
                          child: Text(t('membership.prospect.noOutcome')),
                        ),
                        for (final value in _outcomes)
                          DropdownMenuItem(
                            value: value,
                            child: Text(_outcomeLabel(value)),
                          ),
                      ],
                      onChanged: (value) => setDialogState(
                        () => outcome = value == 'none' ? null : value,
                      ),
                    ),
                    if (widget.teams.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      DropdownButtonFormField<int>(
                        initialValue: teamId,
                        decoration: InputDecoration(
                          labelText: t('membership.prospect.team'),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 0,
                            child: Text(t('membership.prospect.none')),
                          ),
                          for (final team in widget.teams)
                            DropdownMenuItem(
                              value: team.id,
                              child: Text(team.name),
                            ),
                        ],
                        onChanged: (value) =>
                            setDialogState(() => teamId = value ?? 0),
                      ),
                    ],
                    if (widget.membershipTypes.isNotEmpty) ...[
                      const SizedBox(height: 10),
                      DropdownButtonFormField<int>(
                        initialValue: membershipTypeId,
                        decoration: InputDecoration(
                          labelText: t('membership.prospect.membershipType'),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 0,
                            child: Text(t('membership.prospect.none')),
                          ),
                          for (final type in widget.membershipTypes)
                            if (int.tryParse('${type['id']}') case final id?)
                              DropdownMenuItem(
                                value: id,
                                child: Text('${type['name'] ?? id}'),
                              ),
                        ],
                        onChanged: (value) =>
                            setDialogState(() => membershipTypeId = value ?? 0),
                      ),
                    ],
                    const SizedBox(height: 10),
                    TextFormField(
                      controller: notes,
                      maxLines: 4,
                      decoration: InputDecoration(
                        labelText: t('membership.prospect.notes'),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () {
                if (!formKey.currentState!.validate()) return;
                Navigator.pop(dialogContext, <String, dynamic>{
                  'name': name.text.trim(),
                  'email': _nullable(email.text),
                  'phone': _nullable(phone.text),
                  'status': status,
                  'source': _nullable(source.text),
                  'trial_at': trialAt?.toUtc().toIso8601String(),
                  'trial_outcome': outcome,
                  'notes': _nullable(notes.text),
                  'team_id': teamId == 0 ? null : teamId,
                  'club_membership_type_id': membershipTypeId == 0
                      ? null
                      : membershipTypeId,
                });
              },
              child: Text(t('common.save')),
            ),
          ],
        ),
      ),
    );

    name.dispose();
    email.dispose();
    phone.dispose();
    source.dispose();
    notes.dispose();
    if (payload == null || !mounted) return;
    await _save(prospect, payload);
  }

  String? _nullable(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  Future<DateTime?> _pickTrial(DateTime? current) async {
    final initial = current ?? DateTime.now().add(const Duration(days: 1));
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (date == null || !mounted) return null;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null) return null;
    return DateTime(date.year, date.month, date.day, time.hour, time.minute);
  }

  Future<void> _save(
    AirmiusClubMembershipProspect? prospect,
    JsonMap payload,
  ) async {
    setState(() => _mutating = true);
    try {
      final repository = AirmiusServicesScope.of(
        context,
      ).repositories.memberships;
      if (prospect == null) {
        await repository.createClubProspect(widget.clubId, payload);
      } else {
        await repository.updateClubProspect(
          widget.clubId,
          prospect.id,
          payload,
        );
      }
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('membership.prospect.saved'))));
      setState(() {
        _mutating = false;
        _future = _load();
      });
    } catch (error) {
      _showError(error);
    }
  }

  Future<void> _archive(AirmiusClubMembershipProspect prospect) async {
    final confirmed = await confirmDanger(
      context,
      t('membership.prospect.archiveTitle'),
      t(
        'membership.prospect.archiveMessage',
      ).replaceFirst('{name}', prospect.name),
      t('membership.prospect.archive'),
    );
    if (!confirmed || !mounted) return;
    setState(() => _mutating = true);
    try {
      await AirmiusServicesScope.of(context).repositories.memberships
          .archiveClubProspect(widget.clubId, prospect.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('membership.prospect.archived'))),
      );
      setState(() {
        _mutating = false;
        _future = _load();
      });
    } catch (error) {
      _showError(error);
    }
  }

  void _showError(Object error) {
    if (!mounted) return;
    setState(() => _mutating = false);
    final message = error is AirmiusApiException
        ? error.userMessage
        : t('common.errorDetails');
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('${t('membership.prospect.saveFailed')}: $message'),
      ),
    );
  }
}

class _ProspectCard extends StatelessWidget {
  const _ProspectCard({
    required this.prospect,
    required this.statusLabel,
    required this.outcomeLabel,
    required this.trialLabel,
    required this.convertedLabel,
    required this.editLabel,
    required this.archiveLabel,
    required this.onEdit,
    required this.onArchive,
  });

  final AirmiusClubMembershipProspect prospect;
  final String statusLabel;
  final String? outcomeLabel;
  final String? trialLabel;
  final String convertedLabel;
  final String editLabel;
  final String archiveLabel;
  final VoidCallback? onEdit;
  final VoidCallback? onArchive;

  @override
  Widget build(BuildContext context) {
    final details = <String>[
      if (prospect.email != null) prospect.email!,
      if (prospect.phone != null) prospect.phone!,
      if (prospect.teamName != null) prospect.teamName!,
      if (prospect.membershipTypeName != null) prospect.membershipTypeName!,
    ];
    return AirmiusPanel(
      borderColor: prospect.status == 'converted'
          ? AirmiusColors.green.withValues(alpha: 0.55)
          : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  prospect.name,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              Chip(label: Text(statusLabel)),
            ],
          ),
          if (details.isNotEmpty) ...[
            const SizedBox(height: 5),
            Text(
              details.join(' · '),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
          ],
          if (trialLabel != null) ...[
            const SizedBox(height: 8),
            Text(
              '📅 $trialLabel'
              '${outcomeLabel == null ? '' : ' · $outcomeLabel'}',
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          if (prospect.source != null) ...[
            const SizedBox(height: 6),
            Text(
              prospect.source!,
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ],
          if (prospect.notes != null) ...[
            const SizedBox(height: 8),
            Text(
              prospect.notes!,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
          ],
          if (prospect.status == 'converted') ...[
            const SizedBox(height: 10),
            Text(
              convertedLabel,
              style: const TextStyle(
                color: AirmiusColors.green,
                fontWeight: FontWeight.w800,
              ),
            ),
          ] else ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                AirmiusButton(
                  label: editLabel,
                  icon: Icons.edit_outlined,
                  secondary: true,
                  onPressed: onEdit,
                ),
                if (prospect.status != 'archived')
                  AirmiusButton(
                    label: archiveLabel,
                    icon: Icons.archive_outlined,
                    danger: true,
                    onPressed: onArchive,
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
