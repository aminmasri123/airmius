import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart' hide JsonMap;
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
  bool _penaltiesLoading = false;
  JsonMap? _penaltyData;
  String? _penaltyError;
  int? _selectedPenaltyUserId;
  int? _selectedPenaltyRuleId;
  final TextEditingController _penaltyAmountController = TextEditingController();
  final TextEditingController _penaltyMinutesController = TextEditingController();
  final TextEditingController _penaltyNoteController = TextEditingController();

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

  @override
  void dispose() {
    _penaltyAmountController.dispose();
    _penaltyMinutesController.dispose();
    _penaltyNoteController.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    try {
      final next = await AirmiusServicesScope.of(context).repositories.events.event(_event.id);
      if (!mounted) return;
      setState(() {
        _event = next;
        _selectedStatus = next.myParticipationStatus;
      });
      if (next.usesPenaltyCatalog && next.teamId != null) {
        await _loadPenalties();
      }
    } catch (_) {
      // The list item already contains enough event data for the user to continue reading.
    }
  }

  Future<void> _loadPenalties() async {
    final teamId = _event.teamId;
    if (teamId == null) return;
    setState(() {
      _penaltiesLoading = true;
      _penaltyError = null;
    });
    try {
      final container = AirmiusServicesScope.of(context);
      final json = await container.clientForSession(container.authState.session).teamPenalties(teamId, eventId: _event.id);
      final data = json['data'];
      if (!mounted) return;
      setState(() {
        _penaltyData = data is JsonMap ? data : json;
        _penaltiesLoading = false;
      });
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _penaltyError = error.userMessage;
        _penaltiesLoading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _penaltyError = '$error';
        _penaltiesLoading = false;
      });
    }
  }

  Future<void> _assignPenalty() async {
    final teamId = _event.teamId;
    final userId = _selectedPenaltyUserId;
    if (teamId == null || userId == null || _penaltiesLoading) return;

    final amountText = _penaltyAmountController.text.trim().replaceAll(',', '.');
    final minutesText = _penaltyMinutesController.text.trim();
    final payload = <String, dynamic>{
      'event_id': _event.id,
      'user_id': userId,
      if (_selectedPenaltyRuleId != null) 'penalty_rule_id': _selectedPenaltyRuleId,
      if (amountText.isNotEmpty) 'amount': double.tryParse(amountText) ?? 0,
      if (minutesText.isNotEmpty) 'minutes': int.tryParse(minutesText) ?? 0,
      if (_penaltyNoteController.text.trim().isNotEmpty) 'note': _penaltyNoteController.text.trim(),
    };

    setState(() {
      _penaltiesLoading = true;
      _penaltyError = null;
    });
    try {
      final container = AirmiusServicesScope.of(context);
      await container.clientForSession(container.authState.session).createTeamPenaltyFee(teamId, payload);
      _penaltyAmountController.clear();
      _penaltyMinutesController.clear();
      _penaltyNoteController.clear();
      if (mounted) {
        setState(() {
          _selectedPenaltyUserId = null;
          _selectedPenaltyRuleId = null;
        });
      }
      await _loadPenalties();
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _penaltyError = error.userMessage;
        _penaltiesLoading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _penaltyError = '$error';
        _penaltiesLoading = false;
      });
    }
  }

  Future<void> _updatePenaltyStatus(int feeId, {required bool paid}) async {
    final teamId = _event.teamId;
    if (teamId == null || _penaltiesLoading) return;
    setState(() {
      _penaltiesLoading = true;
      _penaltyError = null;
    });
    try {
      final container = AirmiusServicesScope.of(context);
      final client = container.clientForSession(container.authState.session);
      if (paid) {
        await client.markTeamPenaltyFeePaid(teamId, feeId);
      } else {
        await client.cancelTeamPenaltyFee(teamId, feeId);
      }
      await _loadPenalties();
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _penaltyError = error.userMessage;
        _penaltiesLoading = false;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _penaltyError = '$error';
        _penaltiesLoading = false;
      });
    }
  }

  Future<void> _respond(String status) async {
    if (_saving || !_event.canJoin) return;
    final previousEvent = _event;
    final previousStatus = _selectedStatus;
    setState(() {
      _saving = true;
      _selectedStatus = status;
      _event = _optimisticEvent(status);
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
      setState(() {
        _event = previousEvent;
        _selectedStatus = previousStatus;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(AirmiusScope.of(context).t('events.saveError'))));
    }
  }

  AirmiusEvent _optimisticEvent(String status) {
    final previous = _event.myParticipationStatus;
    var yes = _event.yesCount;
    var maybe = _event.maybeCount;
    var no = _event.noCount;
    var participantsCount = _event.participantsCount;

    if (previous == 'yes') yes = _positiveCount(yes - 1);
    if (previous == 'maybe') maybe = _positiveCount(maybe - 1);
    if (previous == 'no') no = _positiveCount(no - 1);
    if (status == 'yes') yes += 1;
    if (status == 'maybe') maybe += 1;
    if (status == 'no') no += 1;
    if (previous == null) participantsCount += 1;

    final authUser = AirmiusServicesScope.of(context).authState.user;
    final participants = [..._event.participants];
    if (authUser != null) {
      final index = participants.indexWhere((participant) => participant.id == authUser.id);
      final updated = AirmiusEventParticipant(
        id: authUser.id,
        name: authUser.name,
        status: status,
        email: authUser.email,
        avatarUrl: authUser.avatarUrl,
      );
      if (index >= 0) {
        participants[index] = updated;
      } else {
        participants.add(updated);
      }
    }

    return _event.copyWith(
      yesCount: yes,
      maybeCount: maybe,
      noCount: no,
      participantsCount: participantsCount,
      myParticipationStatus: status,
      participants: participants,
    );
  }

  int _positiveCount(int value) => value < 0 ? 0 : value;

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
                        _AttendanceChip(value: 'late', selected: _selectedStatus == 'late', label: 'Verspaetet', color: AirmiusColors.blue, onTap: () => _respond('late')),
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
            if (_event.usesPenaltyCatalog && _event.teamId != null) ...[
              const SizedBox(height: 14),
              _EventPenaltyPanel(
                loading: _penaltiesLoading,
                error: _penaltyError,
                data: _penaltyData,
                participants: _event.participants.where((item) => item.status == 'yes' || item.status == 'late').toList(),
                selectedUserId: _selectedPenaltyUserId,
                selectedRuleId: _selectedPenaltyRuleId,
                amountController: _penaltyAmountController,
                minutesController: _penaltyMinutesController,
                noteController: _penaltyNoteController,
                onUserChanged: (value) => setState(() => _selectedPenaltyUserId = value),
                onRuleChanged: (value) => setState(() => _selectedPenaltyRuleId = value),
                onAssign: _assignPenalty,
                onRefresh: _loadPenalties,
                onPaid: (feeId) => _updatePenaltyStatus(feeId, paid: true),
                onCancel: (feeId) => _updatePenaltyStatus(feeId, paid: false),
              ),
            ],
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

class _EventPenaltyPanel extends StatelessWidget {
  const _EventPenaltyPanel({
    required this.loading,
    required this.error,
    required this.data,
    required this.participants,
    required this.selectedUserId,
    required this.selectedRuleId,
    required this.amountController,
    required this.minutesController,
    required this.noteController,
    required this.onUserChanged,
    required this.onRuleChanged,
    required this.onAssign,
    required this.onRefresh,
    required this.onPaid,
    required this.onCancel,
  });

  final bool loading;
  final String? error;
  final JsonMap? data;
  final List<AirmiusEventParticipant> participants;
  final int? selectedUserId;
  final int? selectedRuleId;
  final TextEditingController amountController;
  final TextEditingController minutesController;
  final TextEditingController noteController;
  final ValueChanged<int?> onUserChanged;
  final ValueChanged<int?> onRuleChanged;
  final VoidCallback onAssign;
  final VoidCallback onRefresh;
  final ValueChanged<int> onPaid;
  final ValueChanged<int> onCancel;

  @override
  Widget build(BuildContext context) {
    final rules = _jsonList(data?['rules']).where((rule) => _jsonBool(rule['is_active'])).toList();
    final fees = _jsonList(data?['fees']);
    final summary = data?['summary'] is JsonMap ? data!['summary'] as JsonMap : const <String, dynamic>{};
    final canManage = _jsonBool(data?['can_manage']);

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const Expanded(child: Text('Mannschaftskasse', style: TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900))),
              IconButton(onPressed: loading ? null : onRefresh, icon: const Icon(Icons.refresh, color: AirmiusColors.blue)),
            ],
          ),
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusPill('Offen ${_money(summary['open_amount'])}', color: AirmiusColors.amber),
              StatusPill('Bezahlt ${_money(summary['paid_amount'])}', color: AirmiusColors.green),
            ],
          ),
          if (loading) ...[
            const SizedBox(height: 12),
            const LinearProgressIndicator(color: AirmiusColors.blue, backgroundColor: AirmiusColors.cardSoft),
          ],
          if (error != null) ...[
            const SizedBox(height: 12),
            Text(error!, style: const TextStyle(color: AirmiusColors.red, fontWeight: FontWeight.w800)),
          ],
          if (canManage) ...[
            const SizedBox(height: 14),
            DropdownButtonFormField<int>(
              value: selectedUserId,
              decoration: const InputDecoration(labelText: 'Spieler'),
              dropdownColor: AirmiusColors.card,
              items: [
                for (final participant in participants)
                  DropdownMenuItem(value: participant.id, child: Text('${participant.name} (${participant.status == 'late' ? 'verspaetet' : 'dabei'})')),
              ],
              onChanged: loading ? null : onUserChanged,
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<int>(
              value: selectedRuleId,
              decoration: const InputDecoration(labelText: 'Strafe aus Katalog'),
              dropdownColor: AirmiusColors.card,
              items: [
                for (final rule in rules)
                  DropdownMenuItem(value: _jsonInt(rule['id']), child: Text('${rule['title'] ?? 'Strafe'} - ${_money(rule['amount'])}')),
              ],
              onChanged: loading ? null : onRuleChanged,
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: amountController,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    decoration: const InputDecoration(labelText: 'Betrag optional'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextField(
                    controller: minutesController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(labelText: 'Minuten optional'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            TextField(
              controller: noteController,
              maxLines: 2,
              decoration: const InputDecoration(labelText: 'Notiz optional'),
            ),
            const SizedBox(height: 12),
            AirmiusButton(label: 'Strafe zuweisen', icon: Icons.add_card_outlined, onPressed: selectedUserId == null || loading ? null : onAssign),
          ],
          const SizedBox(height: 14),
          if (fees.isEmpty)
            const Text('Noch keine Event-Strafen erfasst.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700))
          else
            ...fees.map((fee) => _PenaltyFeeTile(fee: fee, canManage: canManage, loading: loading, onPaid: onPaid, onCancel: onCancel)),
        ],
      ),
    );
  }
}

class _PenaltyFeeTile extends StatelessWidget {
  const _PenaltyFeeTile({required this.fee, required this.canManage, required this.loading, required this.onPaid, required this.onCancel});

  final JsonMap fee;
  final bool canManage;
  final bool loading;
  final ValueChanged<int> onPaid;
  final ValueChanged<int> onCancel;

  @override
  Widget build(BuildContext context) {
    final member = fee['member'] is JsonMap ? fee['member'] as JsonMap : const <String, dynamic>{};
    final rule = fee['rule'] is JsonMap ? fee['rule'] as JsonMap : const <String, dynamic>{};
    final status = '${fee['status'] ?? 'open'}';
    final feeId = _jsonInt(fee['id']);
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(border: Border.all(color: AirmiusColors.border), borderRadius: BorderRadius.circular(12), color: AirmiusColors.cardSoft),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(child: Text('${member['name'] ?? 'Spieler'}', style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
              StatusPill(status == 'paid' ? 'Bezahlt' : status == 'cancelled' ? 'Storniert' : 'Offen', color: status == 'paid' ? AirmiusColors.green : status == 'cancelled' ? AirmiusColors.red : AirmiusColors.amber),
            ],
          ),
          const SizedBox(height: 6),
          Text('${rule['title'] ?? fee['note'] ?? 'Strafe'} - ${_money(fee['amount'])}', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
          if (canManage && status == 'open') ...[
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(child: AirmiusButton(label: 'Bezahlt', icon: Icons.check_circle_outline, onPressed: loading ? null : () => onPaid(feeId), secondary: true)),
                const SizedBox(width: 8),
                Expanded(child: AirmiusButton(label: 'Stornieren', icon: Icons.cancel_outlined, onPressed: loading ? null : () => onCancel(feeId), secondary: true)),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

List<JsonMap> _jsonList(Object? value) => value is List ? value.whereType<JsonMap>().toList() : const [];

bool _jsonBool(Object? value) => value == true || value == 1 || value == '1' || value == 'true';

int _jsonInt(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? 0}') ?? 0;
}

String _money(Object? value) {
  final number = value is num ? value.toDouble() : double.tryParse('${value ?? 0}') ?? 0;
  return '${number.toStringAsFixed(2).replaceAll('.', ',')} EUR';
}
