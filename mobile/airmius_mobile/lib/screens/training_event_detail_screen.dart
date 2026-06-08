import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TrainingEventDetailScreen extends StatefulWidget {
  const TrainingEventDetailScreen({
    super.key,
    required this.event,
    required this.fallbackBody,
  });

  final AirmiusEvent event;
  final String fallbackBody;

  @override
  State<TrainingEventDetailScreen> createState() => _TrainingEventDetailScreenState();
}

class _TrainingEventDetailScreenState extends State<TrainingEventDetailScreen> {
  late AirmiusEvent _event;
  String? _selectedStatus;
  bool _saving = false;
  bool _loaded = false;

  @override
  void initState() {
    super.initState();
    _event = widget.event;
    _selectedStatus = widget.event.myParticipationStatus;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_loaded) return;
    _loaded = true;
    _refresh();
  }

  Future<void> _refresh() async {
    try {
      final next = await AirmiusServicesScope.of(context).repositories.events.event(_event.id);
      if (!mounted) return;
      setState(() {
        _event = next;
        _selectedStatus = next.myParticipationStatus;
      });
    } catch (_) {
      // The list item already contains enough event data for the user to continue reading.
    }
  }

  Future<void> _respond(String status) async {
    if (_saving || !_event.canJoin) return;
    setState(() {
      _saving = true;
      _selectedStatus = status;
    });
    try {
      final next = await AirmiusServicesScope.of(context).repositories.events.respond(_event.id, status);
      if (!mounted) return;
      setState(() {
        _event = next;
        _selectedStatus = next.myParticipationStatus;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('events.saved'))));
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('events.saveError'))));
    }
  }

  Future<void> _leave() async {
    if (_saving || _event.myParticipationStatus == null) return;
    setState(() => _saving = true);
    try {
      final next = await AirmiusServicesScope.of(context).repositories.events.leave(_event.id);
      if (!mounted) return;
      setState(() {
        _event = next;
        _selectedStatus = null;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('events.withdrawn'))));
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('events.saveError'))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final location = _event.location ?? scope.t('events.locationMissing');
    final body = _event.notes?.isNotEmpty == true ? _event.notes! : widget.fallbackBody;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(scope.t('events.detailTitle'), style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: _event.title,
        subtitle: scope.t('events.detailSubtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const IconBadge(icon: Icons.event_available_outlined, color: AirmiusColors.blue),
                      const SizedBox(width: 12),
                      Expanded(child: Text(body, style: const TextStyle(color: AirmiusColors.text, height: 1.4, fontWeight: FontWeight.w800))),
                      StatusPill(_event.status, color: _event.status == 'cancelled' ? AirmiusColors.red : AirmiusColors.green),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(_formatDateTime(_event.startsAt), color: AirmiusColors.blue),
                      StatusPill(_event.type, color: AirmiusColors.amber),
                      StatusPill(_event.visibility, color: AirmiusColors.green),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(child: MetricCard(value: _time(_event.startsAt), label: scope.t('events.start'))),
                const SizedBox(width: 10),
                Expanded(child: MetricCard(value: '${_event.yesCount}', label: scope.t('events.yes'))),
                const SizedBox(width: 10),
                Expanded(child: MetricCard(value: '${_event.maybeCount}', label: scope.t('events.maybe'))),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('events.attendance')),
                  const SizedBox(height: 10),
                  if (!_event.canJoin)
                    Text(scope.t('events.notJoinable'), style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700))
                  else
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _AttendanceChip(value: 'yes', selected: _selectedStatus == 'yes', label: scope.t('events.yes'), color: AirmiusColors.green, onTap: () => _respond('yes')),
                        _AttendanceChip(value: 'maybe', selected: _selectedStatus == 'maybe', label: scope.t('events.maybe'), color: AirmiusColors.amber, onTap: () => _respond('maybe')),
                        _AttendanceChip(value: 'no', selected: _selectedStatus == 'no', label: scope.t('events.no'), color: AirmiusColors.red, onTap: () => _respond('no')),
                      ],
                    ),
                  if (_saving) ...[
                    const SizedBox(height: 12),
                    const LinearProgressIndicator(color: AirmiusColors.blue, backgroundColor: AirmiusColors.cardSoft),
                  ],
                  if (_event.myParticipationStatus != null) ...[
                    const SizedBox(height: 12),
                    AirmiusButton(label: scope.t('events.withdraw'), icon: Icons.undo_outlined, secondary: true, onPressed: _leave),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('events.organization')),
                  const SizedBox(height: 10),
                  _EventRow(icon: Icons.location_on_outlined, title: scope.t('events.location'), body: location),
                  if (_event.clubName != null) ...[
                    const SizedBox(height: 10),
                    _EventRow(icon: Icons.groups_2_outlined, title: scope.t('clubs.title'), body: _event.clubName!),
                  ],
                  if (_event.teamName != null) ...[
                    const SizedBox(height: 10),
                    _EventRow(icon: Icons.diversity_3_outlined, title: scope.t('teams.title'), body: _event.teamName!),
                  ],
                  const SizedBox(height: 10),
                  _EventRow(icon: Icons.chat_bubble_outline, title: scope.t('events.comments'), body: '${_event.commentsCount}'),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.blue.withValues(alpha: 0.45),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  StatusPill('${_event.participantsCount} ${scope.t('events.participants')}', color: AirmiusColors.blue),
                  StatusPill('${_event.noCount} ${scope.t('events.no')}', color: AirmiusColors.red),
                  StatusPill(_event.myParticipationStatus == null ? scope.t('events.noResponse') : scope.t('events.status.${_event.myParticipationStatus}'), color: AirmiusColors.green),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _time(DateTime value) {
    final local = value.toLocal();
    return '${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
  }

  String _formatDateTime(DateTime value) {
    final local = value.toLocal();
    return '${local.day.toString().padLeft(2, '0')}.${local.month.toString().padLeft(2, '0')}.${local.year} ${_time(local)}';
  }
}

class _AttendanceChip extends StatelessWidget {
  const _AttendanceChip({
    required this.value,
    required this.selected,
    required this.label,
    required this.color,
    required this.onTap,
  });

  final String value;
  final bool selected;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      selected: selected,
      label: Text(label),
      onSelected: (_) => onTap(),
      selectedColor: color.withValues(alpha: 0.22),
      backgroundColor: AirmiusColors.cardSoft,
      side: BorderSide(color: selected ? color : AirmiusColors.border),
      labelStyle: TextStyle(color: selected ? color : AirmiusColors.muted, fontWeight: FontWeight.w900),
      avatar: selected ? Icon(Icons.check_circle, color: color, size: 18) : null,
    );
  }
}

class _EventRow extends StatelessWidget {
  const _EventRow({required this.icon, required this.title, required this.body});

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: AirmiusColors.blue),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
              const SizedBox(height: 4),
              Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            ],
          ),
        ),
      ],
    );
  }
}
