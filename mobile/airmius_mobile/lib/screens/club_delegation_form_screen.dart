import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../widgets/airmius_widgets.dart';

class ClubDelegationFormScreen extends StatefulWidget {
  const ClubDelegationFormScreen({
    super.key,
    required this.clubName,
    required this.memberName,
    required this.memberId,
    required this.permissions,
    required this.departments,
    required this.teams,
    required this.onSubmit,
  });

  final String clubName, memberName;
  final int memberId;
  // The caller supplies only permissions the current actor may delegate.
  final List<JsonMap> permissions, departments, teams;
  final Future<void> Function(JsonMap) onSubmit;

  @override
  State<ClubDelegationFormScreen> createState() =>
      _ClubDelegationFormScreenState();
}

class _ClubDelegationFormScreenState extends State<ClubDelegationFormScreen> {
  final _selected = <String>{};
  String _scopeType = 'club';
  int? _scopeId;
  late DateTime _startsAt, _endsAt;
  bool _saving = false;
  String? _error;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    _startsAt = DateTime.now();
    _endsAt = _startsAt.add(const Duration(days: 7));
  }

  Future<void> _pickDate(bool start) async {
    final now = DateTime.now();
    final limit = start
        ? now.add(const Duration(days: 90))
        : _startsAt.add(const Duration(days: 90));
    final value = await showDatePicker(
      context: context,
      initialDate: start ? _startsAt : _endsAt,
      firstDate: start ? now : _startsAt.add(const Duration(days: 1)),
      lastDate: limit,
    );
    if (value == null || !mounted) return;
    setState(() {
      if (start) {
        _startsAt = DateUtils.isSameDay(value, now)
            ? now
            : DateTime(value.year, value.month, value.day, 12);
        if (!_endsAt.isAfter(_startsAt)) {
          _endsAt = _startsAt.add(const Duration(days: 1));
        }
      } else {
        final end = DateTime(value.year, value.month, value.day, 23, 59);
        _endsAt = end.isAfter(limit) ? limit : end;
      }
    });
  }

  Future<void> _save() async {
    if (_saving ||
        _selected.isEmpty ||
        (_scopeType != 'club' && _scopeId == null)) {
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSubmit({
        'grantee_user_id': widget.memberId,
        'permissions': _selected.toList(),
        'scope_type': _scopeType,
        'scope_id': _scopeType == 'club' ? null : _scopeId,
        'starts_at': _startsAt.toIso8601String(),
        'ends_at': _endsAt.toIso8601String(),
      });
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : t('membership.permissionsFailed'),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final targets = _scopeType == 'department'
        ? widget.departments
        : widget.teams;
    return PopScope(
      canPop: !_saving,
      child: Scaffold(
        appBar: AppBar(title: Text(t('membership.access.delegationCreate'))),
        bottomNavigationBar: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_error != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Semantics(
                      liveRegion: true,
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            Icons.info_outline,
                            color: Theme.of(context).colorScheme.error,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              _error!,
                              style: TextStyle(
                                color: Theme.of(context).colorScheme.error,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                AirmiusButton(
                  label: t('membership.access.grant'),
                  icon: Icons.check,
                  onPressed:
                      _saving ||
                          _selected.isEmpty ||
                          (_scopeType != 'club' && _scopeId == null)
                      ? null
                      : _save,
                ),
              ],
            ),
          ),
        ),
        body: SafeArea(
          bottom: false,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                widget.memberName,
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 4),
              Text(widget.clubName),
              const SizedBox(height: 20),
              AirmiusPanel(
                child: Column(
                  children: [
                    DropdownButtonFormField<String>(
                      initialValue: _scopeType,
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: t('membership.access.scope'),
                      ),
                      items:
                          [
                                ('club', 'membership.access.scopeClub'),
                                (
                                  'department',
                                  'membership.access.scopeDepartment',
                                ),
                                ('team', 'membership.access.scopeTeam'),
                              ]
                              .map(
                                (item) => DropdownMenuItem(
                                  value: item.$1,
                                  child: Text(t(item.$2)),
                                ),
                              )
                              .toList(),
                      onChanged: _saving
                          ? null
                          : (value) => setState(() {
                              _scopeType = value ?? 'club';
                              _scopeId = null;
                            }),
                    ),
                    if (_scopeType != 'club') ...[
                      const SizedBox(height: 16),
                      DropdownButtonFormField<int>(
                        key: ValueKey(_scopeType),
                        initialValue: _scopeId,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: t('membership.access.scopeTarget'),
                        ),
                        items: targets
                            .map(
                              (target) => DropdownMenuItem(
                                value: (target['id'] as num).toInt(),
                                child: Text('${target['name']}'),
                              ),
                            )
                            .toList(),
                        onChanged: _saving
                            ? null
                            : (value) => setState(() => _scopeId = value),
                      ),
                    ],
                    const SizedBox(height: 16),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.today_outlined),
                      title: Text(t('membership.access.startsAt')),
                      subtitle: Text(
                        MaterialLocalizations.of(
                          context,
                        ).formatMediumDate(_startsAt),
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: _saving ? null : () => _pickDate(true),
                    ),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.event_outlined),
                      title: Text(t('membership.access.endsAt')),
                      subtitle: Text(
                        MaterialLocalizations.of(
                          context,
                        ).formatMediumDate(_endsAt),
                      ),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: _saving ? null : () => _pickDate(false),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Text(
                t('membership.access.permissions'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              AirmiusPanel(
                child: Column(
                  children: [
                    for (final permission in widget.permissions)
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(
                          '${permission['label'] ?? permission['key']}',
                        ),
                        value: _selected.contains('${permission['key']}'),
                        onChanged: _saving
                            ? null
                            : (value) => setState(() {
                                final key = '${permission['key']}';
                                value == true
                                    ? _selected.add(key)
                                    : _selected.remove(key);
                              }),
                      ),
                  ],
                ),
              ),
              if (_saving)
                const Padding(
                  padding: EdgeInsets.only(top: 16),
                  child: LinearProgressIndicator(),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
