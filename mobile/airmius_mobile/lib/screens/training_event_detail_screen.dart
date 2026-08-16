import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'chat_detail_screen.dart';
import 'file_manager_screen.dart';
import 'sport_map_center_screen.dart';
import 'training_plans_logs_screen.dart';

class TrainingEventDetailScreen extends StatefulWidget {
  const TrainingEventDetailScreen({
    super.key,
    required this.event,
    required this.fallbackBody,
  });

  final AirmiusEvent event;
  final String fallbackBody;

  @override
  State<TrainingEventDetailScreen> createState() =>
      _TrainingEventDetailScreenState();
}

class _TrainingEventDetailScreenState extends State<TrainingEventDetailScreen> {
  late AirmiusEvent _event;
  String? _selectedStatus;
  bool _saving = false;
  bool _managing = false;
  bool _loaded = false;
  bool _commentsLoading = false;
  bool _commentSending = false;
  List<AirmiusEventComment> _comments = const [];
  bool _attendanceLoading = false;
  List<AirmiusEventAttendanceMember> _attendanceMembers = const [];
  final Map<int, String?> _attendance = {};
  bool _penaltiesLoading = false;
  JsonMap? _penaltyData;
  String? _penaltyError;
  bool _decisionsLoading = false;
  String? _decisionsError;
  List<AirmiusEventDecision> _decisions = const [];
  int? _votingDecisionId;
  int? _closingDecisionId;
  int? _selectedPenaltyUserId;
  int? _selectedPenaltyRuleId;
  final TextEditingController _penaltyAmountController =
      TextEditingController();
  final TextEditingController _penaltyMinutesController =
      TextEditingController();
  final TextEditingController _penaltyNoteController = TextEditingController();
  final TextEditingController _commentController = TextEditingController();

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
    _commentController.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    try {
      final next = await AirmiusServicesScope.of(
        context,
      ).repositories.events.event(_event.id);
      if (!mounted) return;
      setState(() {
        _event = next;
        _selectedStatus = next.myParticipationStatus;
      });
      await _loadComments();
      if (next.canManageAttendance) {
        await _loadAttendance();
      }
      if (next.usesPenaltyCatalog && next.teamId != null) {
        await _loadPenalties();
      }
      await _loadDecisions();
    } catch (_) {
      // The list item already contains enough event data for the user to continue reading.
    }
  }

  Future<void> _loadDecisions() async {
    if (_decisionsLoading) return;
    setState(() {
      _decisionsLoading = true;
      _decisionsError = null;
    });
    try {
      final decisions = await AirmiusServicesScope.of(
        context,
      ).repositories.events.decisions(_event.id);
      if (!mounted) return;
      setState(() {
        _decisions = decisions;
        _decisionsLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _decisionsLoading = false;
        _decisionsError = AirmiusScope.of(
          context,
        ).t('events.decisionLoadError');
      });
    }
  }

  Future<void> _voteDecision(
    AirmiusEventDecision decision,
    int optionId,
  ) async {
    if (!decision.isOpen || _votingDecisionId != null) return;
    setState(() => _votingDecisionId = decision.id);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.events.castDecisionVote(_event.id, decision.id, optionId);
      await _loadDecisions();
      if (!mounted) return;
      _showMessage(AirmiusScope.of(context).t('events.voteSaved'));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      _showMessage(error.userMessage);
    } catch (_) {
      if (!mounted) return;
      _showMessage(AirmiusScope.of(context).t('events.voteError'));
    } finally {
      if (mounted) setState(() => _votingDecisionId = null);
    }
  }

  Future<void> _createDecision() async {
    if (!_event.canUpdate || _managing) return;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => const _CreateDecisionDialog(),
    );
    if (payload == null || !mounted) return;
    setState(() => _managing = true);
    try {
      final decision = await AirmiusServicesScope.of(
        context,
      ).repositories.events.createDecision(_event.id, payload);
      if (!mounted) return;
      setState(() {
        _decisions = [decision, ..._decisions];
        _managing = false;
      });
      _showMessage(AirmiusScope.of(context).t('events.decisionCreated'));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(error.userMessage);
    } catch (_) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(AirmiusScope.of(context).t('events.decisionCreateError'));
    }
  }

  Future<void> _closeDecision(AirmiusEventDecision decision) async {
    if (!_event.canUpdate || !decision.isOpen || _closingDecisionId != null) {
      return;
    }
    final scope = AirmiusScope.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(scope.t('events.closeDecisionTitle')),
        content: Text(scope.t('events.closeDecisionBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(scope.t('events.keep')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(scope.t('events.closeDecision')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _closingDecisionId = decision.id);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.events.closeDecision(_event.id, decision.id);
      await _loadDecisions();
      if (!mounted) return;
      _showMessage(AirmiusScope.of(context).t('events.decisionClosed'));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      _showMessage(error.userMessage);
    } catch (_) {
      if (!mounted) return;
      _showMessage(AirmiusScope.of(context).t('events.decisionCloseError'));
    } finally {
      if (mounted) setState(() => _closingDecisionId = null);
    }
  }

  Future<void> _loadAttendance() async {
    if (_attendanceLoading) return;
    setState(() => _attendanceLoading = true);
    try {
      final members = await AirmiusServicesScope.of(
        context,
      ).repositories.events.attendance(_event.id);
      if (!mounted) return;
      setState(() {
        _attendanceMembers = members;
        _attendance
          ..clear()
          ..addEntries(
            members.map((member) => MapEntry(member.id, member.status)),
          );
        _attendanceLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _attendanceLoading = false);
    }
  }

  Future<void> _loadComments() async {
    if (_commentsLoading) return;
    setState(() => _commentsLoading = true);
    try {
      final page = await AirmiusServicesScope.of(
        context,
      ).repositories.events.comments(_event.id);
      if (!mounted) return;
      setState(() {
        _comments = page.items;
        _commentsLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _commentsLoading = false);
    }
  }

  Future<void> _sendComment() async {
    final content = _commentController.text.trim();
    if (content.isEmpty || _commentSending) return;
    setState(() => _commentSending = true);
    try {
      final comment = await AirmiusServicesScope.of(
        context,
      ).repositories.events.createComment(_event.id, content);
      if (!mounted) return;
      _commentController.clear();
      setState(() {
        _comments = [comment, ..._comments];
        _event = _event.copyWith(commentsCount: _event.commentsCount + 1);
        _commentSending = false;
      });
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _commentSending = false);
      _showMessage(error.userMessage);
    } catch (_) {
      if (!mounted) return;
      setState(() => _commentSending = false);
      _showMessage(AirmiusScope.of(context).t('events.commentError'));
    }
  }

  Future<void> _openChat() async {
    final conversationId = _event.conversationId;
    if (conversationId == null) return;
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ChatDetailScreen(
          conversationId: conversationId,
          title: _event.title,
          kind: 'event',
        ),
      ),
    );
  }

  Future<void> _openFiles() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => FileManagerScreen(
          initialScope: 'event',
          initialEventId: _event.id,
        ),
      ),
    );
    if (mounted) await _refresh();
  }

  Future<void> _openTrainingLog() async {
    final scope = AirmiusScope.of(context);
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => TrainingPlansLogsScreen(
          initialTab: 1,
          createLogOnOpen: true,
          initialLogTitle: _event.title,
          initialLogNotes: scope
              .t('events.trainingLogContext')
              .replaceAll('{title}', _event.title),
          initialSportRouteId: _event.sportRoute?.id,
          initialSportRouteTitle: _event.sportRoute?.title,
        ),
      ),
    );
  }

  Future<void> _editEvent() async {
    if (!_event.canUpdate || _managing) return;
    var sportRoutes = <AirmiusSportRouteReference>[
      if (_event.sportRoute != null) _event.sportRoute!,
    ];

    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      final response = await client.trainingRouteOptions();
      final data = response['data'];
      final routes = data is JsonMap ? data['routes'] : null;
      if (routes is List) {
        sportRoutes = routes
            .whereType<JsonMap>()
            .map(AirmiusSportRouteReference.fromJson)
            .toList();
      }
    } catch (_) {
      // Editing remains available with the currently linked route.
    }
    if (!mounted) return;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _EditEventDialog(
        event: _event,
        sportRoutes: sportRoutes,
      ),
    );
    if (payload == null || !mounted) return;
    setState(() => _managing = true);
    try {
      final next = await AirmiusServicesScope.of(
        context,
      ).repositories.events.update(_event.id, payload);
      if (!mounted) return;
      setState(() {
        _event = next;
        _managing = false;
      });
      _showMessage(AirmiusScope.of(context).t('events.updated'));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(error.userMessage);
    } catch (_) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(AirmiusScope.of(context).t('events.updateError'));
    }
  }

  void _openSportRoute() {
    final route = _event.sportRoute;
    if (route == null) return;

    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SportMapCenterScreen(initialRouteId: route.id),
      ),
    );
  }

  Future<void> _cancelEvent() async {
    if (!_event.canCancel || _event.status == 'cancelled' || _managing) return;
    final reasonController = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(AirmiusScope.of(context).t('events.cancelTitle')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(AirmiusScope.of(context).t('events.cancelBody')),
            const SizedBox(height: 14),
            TextField(
              controller: reasonController,
              maxLength: 1000,
              maxLines: 3,
              decoration: InputDecoration(
                labelText: AirmiusScope.of(context).t('events.cancelReason'),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(AirmiusScope.of(context).t('events.keep')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(AirmiusScope.of(context).t('events.cancelConfirm')),
          ),
        ],
      ),
    );
    final reason = reasonController.text.trim();
    reasonController.dispose();
    if (confirmed != true || !mounted) return;

    setState(() => _managing = true);
    try {
      final next = await AirmiusServicesScope.of(
        context,
      ).repositories.events.cancel(_event.id, reason: reason);
      if (!mounted) return;
      setState(() {
        _event = next;
        _managing = false;
      });
      _showMessage(AirmiusScope.of(context).t('events.cancelled'));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(error.userMessage);
    } catch (_) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(AirmiusScope.of(context).t('events.cancelError'));
    }
  }

  Future<void> _deleteEvent() async {
    if (!_event.canDelete || _managing) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(AirmiusScope.of(context).t('events.deleteTitle')),
        content: Text(AirmiusScope.of(context).t('events.deleteBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(AirmiusScope.of(context).t('events.keep')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AirmiusColors.red),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(AirmiusScope.of(context).t('events.deleteConfirm')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _managing = true);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.events.delete(_event.id);
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(error.userMessage);
    } catch (_) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(AirmiusScope.of(context).t('events.deleteError'));
    }
  }

  Future<void> _saveAttendance() async {
    if (!_event.canManageAttendance ||
        _attendanceMembers.isEmpty ||
        _managing) {
      return;
    }
    final rows = [
      for (final member in _attendanceMembers)
        if (_attendance[member.id] != null)
          {'user_id': member.id, 'status': _attendance[member.id]},
    ];
    if (rows.isEmpty) return;
    setState(() => _managing = true);
    try {
      final next = await AirmiusServicesScope.of(
        context,
      ).repositories.events.recordAttendance(_event.id, rows);
      if (!mounted) return;
      setState(() {
        _event = next;
        _managing = false;
      });
      await _loadAttendance();
      if (!mounted) return;
      _showMessage(AirmiusScope.of(context).t('events.attendanceSaved'));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(error.userMessage);
    } catch (_) {
      if (!mounted) return;
      setState(() => _managing = false);
      _showMessage(AirmiusScope.of(context).t('events.attendanceError'));
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
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
      final json = await container
          .clientForSession(container.authState.session)
          .teamPenalties(teamId, eventId: _event.id);
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
        _penaltyError = AirmiusScope.of(context).t('common.errorDetails');
        _penaltiesLoading = false;
      });
    }
  }

  Future<void> _assignPenalty() async {
    final teamId = _event.teamId;
    final userId = _selectedPenaltyUserId;
    if (teamId == null || userId == null || _penaltiesLoading) return;

    final amountText = _penaltyAmountController.text.trim().replaceAll(
      ',',
      '.',
    );
    final minutesText = _penaltyMinutesController.text.trim();
    final payload = <String, dynamic>{
      'event_id': _event.id,
      'user_id': userId,
      if (_selectedPenaltyRuleId != null)
        'penalty_rule_id': _selectedPenaltyRuleId,
      if (amountText.isNotEmpty) 'amount': double.tryParse(amountText) ?? 0,
      if (minutesText.isNotEmpty) 'minutes': int.tryParse(minutesText) ?? 0,
      if (_penaltyNoteController.text.trim().isNotEmpty)
        'note': _penaltyNoteController.text.trim(),
    };

    setState(() {
      _penaltiesLoading = true;
      _penaltyError = null;
    });
    try {
      final container = AirmiusServicesScope.of(context);
      await container
          .clientForSession(container.authState.session)
          .createTeamPenaltyFee(teamId, payload);
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
        _penaltyError = AirmiusScope.of(context).t('common.errorDetails');
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
        _penaltyError = AirmiusScope.of(context).t('common.errorDetails');
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
      final next = await AirmiusServicesScope.of(
        context,
      ).repositories.events.respond(_event.id, status);
      if (!mounted) return;
      setState(() {
        _event = next;
        _selectedStatus = next.myParticipationStatus;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('events.saved'))),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _event = previousEvent;
        _selectedStatus = previousStatus;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('events.saveError'))),
      );
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
      final index = participants.indexWhere(
        (participant) => participant.id == authUser.id,
      );
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
      final next = await AirmiusServicesScope.of(
        context,
      ).repositories.events.leave(_event.id);
      if (!mounted) return;
      setState(() {
        _event = next;
        _selectedStatus = null;
        _saving = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('events.withdrawn'))),
      );
    } catch (_) {
      if (!mounted) return;
      setState(() => _saving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('events.saveError'))),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final location = _event.location ?? scope.t('events.locationMissing');
    final body = _event.notes?.isNotEmpty == true
        ? _event.notes!
        : widget.fallbackBody;

    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('events.detailTitle'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          if (_event.canUpdate || _event.canCancel || _event.canDelete)
            PopupMenuButton<String>(
              tooltip: scope.t('events.manage'),
              enabled: !_managing,
              onSelected: (value) {
                if (value == 'edit') _editEvent();
                if (value == 'cancel') _cancelEvent();
                if (value == 'delete') _deleteEvent();
              },
              itemBuilder: (_) => [
                if (_event.canUpdate && _event.status != 'cancelled')
                  PopupMenuItem(
                    value: 'edit',
                    child: ListTile(
                      leading: Icon(Icons.edit_outlined),
                      title: Text(scope.t('events.edit')),
                    ),
                  ),
                if (_event.canCancel && _event.status != 'cancelled')
                  PopupMenuItem(
                    value: 'cancel',
                    child: ListTile(
                      leading: Icon(
                        Icons.event_busy_outlined,
                        color: AirmiusColors.amber,
                      ),
                      title: Text(scope.t('events.cancel')),
                    ),
                  ),
                if (_event.canDelete)
                  PopupMenuItem(
                    value: 'delete',
                    child: ListTile(
                      leading: Icon(
                        Icons.delete_outline,
                        color: AirmiusColors.red,
                      ),
                      title: Text(scope.t('events.delete')),
                    ),
                  ),
              ],
            ),
        ],
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
                      IconBadge(
                        icon: Icons.event_available_outlined,
                        color: airmiusAccentColor(context),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          body,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            height: 1.4,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      StatusPill(
                        _event.status,
                        color: _event.status == 'cancelled'
                            ? AirmiusColors.red
                            : AirmiusColors.green,
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(
                        _formatDateTime(_event.startsAt),
                        color: airmiusAccentColor(context),
                      ),
                      StatusPill(_event.type, color: AirmiusColors.amber),
                      StatusPill(_event.visibility, color: AirmiusColors.green),
                    ],
                  ),
                ],
              ),
            ),
            if (_event.status == 'cancelled') ...[
              const SizedBox(height: 14),
              AirmiusPanel(
                borderColor: AirmiusColors.red.withValues(alpha: 0.55),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.event_busy_outlined, color: AirmiusColors.red),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            scope.t('events.cancelledTitle'),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          if (_event.cancellationReason?.isNotEmpty ==
                              true) ...[
                            const SizedBox(height: 6),
                            Text(
                              _event.cancellationReason!,
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                height: 1.35,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
            if (_managing) ...[
              const SizedBox(height: 10),
              LinearProgressIndicator(
                color: airmiusAccentColor(context),
                backgroundColor: airmiusSurfaceSoftColor(context),
              ),
            ],
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: MetricCard(
                    value: _time(_event.startsAt),
                    label: scope.t('events.start'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: '${_event.yesCount}',
                    label: scope.t('events.yes'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: '${_event.maybeCount}',
                    label: scope.t('events.maybe'),
                  ),
                ),
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
                    Text(
                      scope.t('events.notJoinable'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                        fontWeight: FontWeight.w700,
                      ),
                    )
                  else
                    LayoutBuilder(
                      builder: (context, constraints) {
                        final itemWidth = (constraints.maxWidth - 8) / 2;
                        return Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            _AttendanceChip(
                              width: itemWidth,
                              value: 'yes',
                              selected: _selectedStatus == 'yes',
                              label: scope.t('events.yes'),
                              icon: Icons.check_circle_outline_rounded,
                              onTap: () => _respond('yes'),
                            ),
                            _AttendanceChip(
                              width: itemWidth,
                              value: 'late',
                              selected: _selectedStatus == 'late',
                              label: scope.t('events.late'),
                              icon: Icons.schedule_rounded,
                              onTap: () => _respond('late'),
                            ),
                            _AttendanceChip(
                              width: itemWidth,
                              value: 'maybe',
                              selected: _selectedStatus == 'maybe',
                              label: scope.t('events.maybe'),
                              icon: Icons.help_outline_rounded,
                              onTap: () => _respond('maybe'),
                            ),
                            _AttendanceChip(
                              width: itemWidth,
                              value: 'no',
                              selected: _selectedStatus == 'no',
                              label: scope.t('events.no'),
                              icon: Icons.cancel_outlined,
                              onTap: () => _respond('no'),
                            ),
                          ],
                        );
                      },
                    ),
                  if (_saving) ...[
                    const SizedBox(height: 12),
                    LinearProgressIndicator(
                      color: airmiusAccentColor(context),
                      backgroundColor: airmiusSurfaceSoftColor(context),
                    ),
                  ],
                  if (_event.myParticipationStatus != null) ...[
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: scope.t('events.withdraw'),
                      icon: Icons.undo_outlined,
                      secondary: true,
                      onPressed: _leave,
                    ),
                  ],
                ],
              ),
            ),
            if (_event.canManageAttendance) ...[
              const SizedBox(height: 14),
              _AttendanceManager(
                members: _attendanceMembers,
                statuses: _attendance,
                loading: _attendanceLoading,
                saving: _managing,
                onChanged: (userId, status) =>
                    setState(() => _attendance[userId] = status),
                onSave: _saveAttendance,
              ),
            ],
            const SizedBox(height: 14),
            _EventDecisionsPanel(
              decisions: _decisions,
              loading: _decisionsLoading,
              error: _decisionsError,
              canManage: _event.canUpdate,
              votingDecisionId: _votingDecisionId,
              closingDecisionId: _closingDecisionId,
              onRefresh: _loadDecisions,
              onCreate: _createDecision,
              onVote: _voteDecision,
              onClose: _closeDecision,
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('events.organization')),
                  const SizedBox(height: 10),
                  _EventRow(
                    icon: Icons.location_on_outlined,
                    title: scope.t('events.location'),
                    body: location,
                  ),
                  if (_event.sportRoute != null) ...[
                    const SizedBox(height: 10),
                    _EventRow(
                      icon: Icons.route_outlined,
                      title: scope.t('events.route'),
                      body: [
                        _event.sportRoute!.title,
                        if (_event.sportRoute!.startName != null ||
                            _event.sportRoute!.endName != null)
                          '${_event.sportRoute!.startName ?? '–'} → ${_event.sportRoute!.endName ?? '–'}',
                      ].join('\n'),
                    ),
                    const SizedBox(height: 10),
                    AirmiusButton(
                      label: scope.t('events.routeOpen'),
                      icon: Icons.map_outlined,
                      onPressed: _openSportRoute,
                      secondary: true,
                    ),
                  ],
                  if (_event.clubName != null) ...[
                    const SizedBox(height: 10),
                    _EventRow(
                      icon: Icons.groups_2_outlined,
                      title: scope.t('clubs.title'),
                      body: _event.clubName!,
                    ),
                  ],
                  if (_event.teamName != null) ...[
                    const SizedBox(height: 10),
                    _EventRow(
                      icon: Icons.diversity_3_outlined,
                      title: scope.t('teams.title'),
                      body: _event.teamName!,
                    ),
                  ],
                  const SizedBox(height: 10),
                  _EventRow(
                    icon: Icons.chat_bubble_outline,
                    title: scope.t('events.comments'),
                    body: '${_event.commentsCount}',
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label:
                        '${scope.t('events.files')} (${_event.filesCount})',
                    icon: Icons.folder_outlined,
                    onPressed: _openFiles,
                    secondary: true,
                  ),
                  if (_event.type == 'training' &&
                      _event.status != 'cancelled') ...[
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: scope.t('events.documentTraining'),
                      icon: Icons.fact_check_outlined,
                      onPressed: _openTrainingLog,
                    ),
                  ],
                  if (_event.conversationId != null) ...[
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: scope.t('events.openChat'),
                      icon: Icons.forum_outlined,
                      onPressed: _openChat,
                      secondary: true,
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: airmiusAccentColor(context).withValues(alpha: 0.45),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  StatusPill(
                    '${_event.participantsCount} ${scope.t('events.participants')}',
                    color: airmiusAccentColor(context),
                  ),
                  StatusPill(
                    '${_event.noCount} ${scope.t('events.no')}',
                    color: AirmiusColors.red,
                  ),
                  StatusPill(
                    _event.myParticipationStatus == null
                        ? scope.t('events.noResponse')
                        : scope.t(
                            'events.status.${_event.myParticipationStatus}',
                          ),
                    color: AirmiusColors.green,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _EventCommentsPanel(
              comments: _comments,
              controller: _commentController,
              loading: _commentsLoading,
              sending: _commentSending,
              onRefresh: _loadComments,
              onSend: _sendComment,
            ),
            if (_event.usesPenaltyCatalog && _event.teamId != null) ...[
              const SizedBox(height: 14),
              _EventPenaltyPanel(
                loading: _penaltiesLoading,
                error: _penaltyError,
                data: _penaltyData,
                participants: _event.participants
                    .where(
                      (item) => item.status == 'yes' || item.status == 'late',
                    )
                    .toList(),
                selectedUserId: _selectedPenaltyUserId,
                selectedRuleId: _selectedPenaltyRuleId,
                amountController: _penaltyAmountController,
                minutesController: _penaltyMinutesController,
                noteController: _penaltyNoteController,
                onUserChanged: (value) =>
                    setState(() => _selectedPenaltyUserId = value),
                onRuleChanged: (value) =>
                    setState(() => _selectedPenaltyRuleId = value),
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

class _EventDecisionsPanel extends StatelessWidget {
  const _EventDecisionsPanel({
    required this.decisions,
    required this.loading,
    required this.error,
    required this.canManage,
    required this.votingDecisionId,
    required this.closingDecisionId,
    required this.onRefresh,
    required this.onCreate,
    required this.onVote,
    required this.onClose,
  });

  final List<AirmiusEventDecision> decisions;
  final bool loading;
  final String? error;
  final bool canManage;
  final int? votingDecisionId;
  final int? closingDecisionId;
  final Future<void> Function() onRefresh;
  final Future<void> Function() onCreate;
  final Future<void> Function(AirmiusEventDecision decision, int optionId)
  onVote;
  final Future<void> Function(AirmiusEventDecision decision) onClose;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      borderColor: airmiusAccentColor(context).withValues(alpha: 0.45),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow(scope.t('events.decisions')),
                    const SizedBox(height: 5),
                    Text(
                      scope.t('events.decisionsHint'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
              if (canManage)
                IconButton(
                  tooltip: scope.t('events.createDecision'),
                  onPressed: loading ? null : onCreate,
                  icon: const Icon(Icons.add_circle_outline),
                ),
            ],
          ),
          if (loading) ...[
            const SizedBox(height: 12),
            LinearProgressIndicator(
              color: airmiusAccentColor(context),
              backgroundColor: airmiusSurfaceSoftColor(context),
            ),
          ],
          if (error != null) ...[
            const SizedBox(height: 12),
            Text(
              error!,
              style: TextStyle(
                color: AirmiusColors.red,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 8),
            AirmiusButton(
              label: scope.t('events.retryDecisions'),
              icon: Icons.refresh,
              secondary: true,
              onPressed: onRefresh,
            ),
          ],
          if (!loading && error == null && decisions.isEmpty) ...[
            const SizedBox(height: 12),
            Text(
              scope.t('events.noDecisions'),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            ),
            if (canManage) ...[
              const SizedBox(height: 10),
              AirmiusButton(
                label: scope.t('events.createDecision'),
                icon: Icons.add_outlined,
                onPressed: onCreate,
              ),
            ],
          ],
          if (decisions.isNotEmpty) ...[
            const SizedBox(height: 12),
            for (final decision in decisions) ...[
              _EventDecisionCard(
                decision: decision,
                voting: votingDecisionId == decision.id,
                canManage: canManage,
                closing: closingDecisionId == decision.id,
                onVote: (optionId) => onVote(decision, optionId),
                onClose: () => onClose(decision),
              ),
              if (decision != decisions.last) const SizedBox(height: 10),
            ],
          ],
        ],
      ),
    );
  }
}

class _EventDecisionCard extends StatelessWidget {
  const _EventDecisionCard({
    required this.decision,
    required this.voting,
    required this.canManage,
    required this.closing,
    required this.onVote,
    required this.onClose,
  });

  final AirmiusEventDecision decision;
  final bool voting;
  final bool canManage;
  final bool closing;
  final ValueChanged<int> onVote;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final total = decision.options.fold<int>(
      0,
      (sum, option) => sum + option.votes,
    );
    final open = decision.isOpen;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  decision.question,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
              ),
              StatusPill(
                open ? scope.t('events.open') : scope.t('events.closed'),
                color: open ? AirmiusColors.green : AirmiusColors.amber,
              ),
              if (canManage && open)
                IconButton(
                  tooltip: scope.t('events.closeDecision'),
                  onPressed: closing ? null : onClose,
                  icon: const Icon(Icons.lock_outline),
                ),
            ],
          ),
          if (decision.description?.isNotEmpty == true) ...[
            const SizedBox(height: 6),
            Text(
              decision.description!,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
          ],
          const SizedBox(height: 8),
          for (final option in decision.options) ...[
            RadioListTile<int>(
              value: option.id,
              // Keep the legacy API for the minimum Flutter version supported by Airmius.
              // ignore: deprecated_member_use
              groupValue: decision.myOptionId,
              // ignore: deprecated_member_use
              onChanged: open && !voting
                  ? (value) {
                      if (value != null) onVote(value);
                    }
                  : null,
              dense: true,
              contentPadding: EdgeInsets.zero,
              title: Text(option.label),
              subtitle: total == 0
                  ? null
                  : Text(
                      '${option.votes} ${scope.t('events.votes')} · ${(option.votes * 100 / total).round()}%',
                    ),
            ),
          ],
          if (voting) ...[
            const SizedBox(height: 6),
            LinearProgressIndicator(
              color: airmiusAccentColor(context),
              backgroundColor: airmiusSurfaceColor(context),
            ),
          ],
          if (closing) ...[
            const SizedBox(height: 6),
            LinearProgressIndicator(
              color: airmiusAccentColor(context),
              backgroundColor: airmiusSurfaceColor(context),
            ),
          ],
          if (!open && decision.closesAt != null) ...[
            const SizedBox(height: 4),
            Text(
              '${scope.t('events.closedAt')}: ${_eventDecisionDate(decision.closesAt!)}',
              style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
            ),
          ],
        ],
      ),
    );
  }
}

class _CreateDecisionDialog extends StatefulWidget {
  const _CreateDecisionDialog();

  @override
  State<_CreateDecisionDialog> createState() => _CreateDecisionDialogState();
}

class _CreateDecisionDialogState extends State<_CreateDecisionDialog> {
  final _formKey = GlobalKey<FormState>();
  final _questionController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _optionControllers = [TextEditingController(), TextEditingController()];

  @override
  void dispose() {
    _questionController.dispose();
    _descriptionController.dispose();
    for (final controller in _optionControllers) {
      controller.dispose();
    }
    super.dispose();
  }

  void _addOption() {
    if (_optionControllers.length >= 6) return;
    setState(() => _optionControllers.add(TextEditingController()));
  }

  void _removeOption(int index) {
    if (_optionControllers.length <= 2) return;
    final controller = _optionControllers.removeAt(index);
    controller.dispose();
    setState(() {});
  }

  void _submit() {
    final scope = AirmiusScope.of(context);
    if (!_formKey.currentState!.validate()) return;
    final options = _optionControllers
        .map((controller) => controller.text.trim())
        .where((value) => value.isNotEmpty)
        .toList();
    if (options.length < 2) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(scope.t('events.twoOptionsRequired'))),
      );
      return;
    }
    Navigator.of(context).pop<JsonMap>({
      'question': _questionController.text.trim(),
      'description': _descriptionController.text.trim().isEmpty
          ? null
          : _descriptionController.text.trim(),
      'options': options,
    });
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AlertDialog(
      title: Text(scope.t('events.createDecisionTitle')),
      content: SizedBox(
        width: 520,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _questionController,
                  maxLength: 255,
                  autofocus: true,
                  decoration: InputDecoration(
                    labelText: scope.t('events.question'),
                    hintText: scope.t('events.questionHint'),
                  ),
                  validator: (value) => value == null || value.trim().isEmpty
                      ? scope.t('events.questionRequired')
                      : null,
                ),
                TextFormField(
                  controller: _descriptionController,
                  maxLength: 1000,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: scope.t('events.description'),
                  ),
                ),
                const SizedBox(height: 8),
                for (var index = 0; index < _optionControllers.length; index++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: TextFormField(
                      controller: _optionControllers[index],
                      maxLength: 255,
                      decoration: InputDecoration(
                        labelText: '${scope.t('events.option')} ${index + 1}',
                        hintText: scope.t('events.optionHint'),
                        suffixIcon: _optionControllers.length > 2
                            ? IconButton(
                                tooltip: scope.t('events.removeOption'),
                                onPressed: () => _removeOption(index),
                                icon: const Icon(Icons.close),
                              )
                            : null,
                      ),
                      validator: (value) =>
                          value == null || value.trim().isEmpty
                          ? scope.t('events.optionRequired')
                          : null,
                    ),
                  ),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: TextButton.icon(
                    onPressed: _optionControllers.length >= 6
                        ? null
                        : _addOption,
                    icon: const Icon(Icons.add),
                    label: Text(scope.t('events.addOption')),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(scope.t('events.keep')),
        ),
        FilledButton.icon(
          onPressed: _submit,
          icon: const Icon(Icons.how_to_vote_outlined),
          label: Text(scope.t('events.createDecision')),
        ),
      ],
    );
  }
}

String _eventDecisionDate(DateTime value) {
  final local = value.toLocal();
  return '${local.day.toString().padLeft(2, '0')}.${local.month.toString().padLeft(2, '0')}.${local.year} ${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
}

class _EditEventDialog extends StatefulWidget {
  const _EditEventDialog({
    required this.event,
    required this.sportRoutes,
  });

  final AirmiusEvent event;
  final List<AirmiusSportRouteReference> sportRoutes;

  @override
  State<_EditEventDialog> createState() => _EditEventDialogState();
}

class _EditEventDialogState extends State<_EditEventDialog> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _titleController;
  late final TextEditingController _locationController;
  late final TextEditingController _limitController;
  late final TextEditingController _notesController;
  late DateTime _start;
  DateTime? _end;
  int? _sportRouteId;

  @override
  void initState() {
    super.initState();
    _titleController = TextEditingController(text: widget.event.title);
    _locationController = TextEditingController(
      text: widget.event.locationName ?? widget.event.location,
    );
    _limitController = TextEditingController(
      text: widget.event.maxParticipants?.toString() ?? '',
    );
    _notesController = TextEditingController(text: widget.event.notes);
    _start = widget.event.startsAt.toLocal();
    _end = widget.event.endsAt?.toLocal();
    _sportRouteId = widget.event.sportRoute?.id;
  }

  @override
  void dispose() {
    _titleController.dispose();
    _locationController.dispose();
    _limitController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  Future<void> _pickDateTime({required bool start}) async {
    final initial = start
        ? _start
        : (_end ?? _start.add(const Duration(hours: 1)));
    final date = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime.now().subtract(const Duration(days: 365)),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(initial),
    );
    if (time == null || !mounted) return;
    final value = DateTime(
      date.year,
      date.month,
      date.day,
      time.hour,
      time.minute,
    );
    setState(() {
      if (start) {
        _start = value;
        if (_end != null && _end!.isBefore(_start)) {
          _end = _start.add(const Duration(hours: 1));
        }
      } else {
        _end = value;
      }
    });
  }

  void _submit() {
    if (!_formKey.currentState!.validate()) return;
    if (_end != null && _end!.isBefore(_start)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('events.endError'))),
      );
      return;
    }
    final limitText = _limitController.text.trim();
    Navigator.of(context).pop(<String, dynamic>{
      'title': _titleController.text.trim(),
      'start_time': _start.toUtc().toIso8601String(),
      'end_time': _end?.toUtc().toIso8601String(),
      'sport_route_id': _sportRouteId,
      'location_name': _locationController.text.trim().isEmpty
          ? null
          : _locationController.text.trim(),
      'max_participants': limitText.isEmpty ? null : int.parse(limitText),
      'notes': _notesController.text.trim().isEmpty
          ? null
          : _notesController.text.trim(),
    });
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AlertDialog(
      title: Text(scope.t('events.editTitle')),
      content: SizedBox(
        width: 520,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _titleController,
                  maxLength: 255,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(
                    labelText: scope.t('events.fieldTitle'),
                    prefixIcon: Icon(Icons.title),
                  ),
                  validator: (value) => value == null || value.trim().isEmpty
                      ? scope.t('events.titleRequired')
                      : null,
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: () => _pickDateTime(start: true),
                  icon: Icon(Icons.schedule),
                  label: Text(
                    '${scope.t('events.start')}: ${_eventEditDate(_start)}',
                  ),
                ),
                const SizedBox(height: 10),
                OutlinedButton.icon(
                  onPressed: () => _pickDateTime(start: false),
                  icon: Icon(Icons.update),
                  label: Text(
                    _end == null
                        ? scope.t('events.endOptional')
                        : '${scope.t('events.end')}: ${_eventEditDate(_end!)}',
                  ),
                ),
                if (_end != null)
                  Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: TextButton.icon(
                      onPressed: () => setState(() => _end = null),
                      icon: Icon(Icons.close, size: 18),
                      label: Text(scope.t('events.removeEnd')),
                    ),
                  ),
                DropdownButtonFormField<int?>(
                  initialValue: _sportRouteId,
                  decoration: InputDecoration(
                    labelText: scope.t('events.route'),
                    prefixIcon: const Icon(Icons.route_outlined),
                  ),
                  items: [
                    DropdownMenuItem<int?>(
                      value: null,
                      child: Text(scope.t('events.routeNone')),
                    ),
                    ...widget.sportRoutes.map(
                      (route) => DropdownMenuItem<int?>(
                        value: route.id,
                        child: Text(route.title, overflow: TextOverflow.ellipsis),
                      ),
                    ),
                  ],
                  onChanged: (value) => setState(() => _sportRouteId = value),
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _locationController,
                  maxLength: 255,
                  textInputAction: TextInputAction.next,
                  decoration: InputDecoration(
                    labelText: scope.t('events.location'),
                    prefixIcon: Icon(Icons.location_on_outlined),
                  ),
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _limitController,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: scope.t('events.maxParticipants'),
                    prefixIcon: Icon(Icons.groups_outlined),
                  ),
                  validator: (value) {
                    final text = value?.trim() ?? '';
                    if (text.isEmpty) return null;
                    final number = int.tryParse(text);
                    return number == null || number < 1 || number > 100000
                        ? scope.t('events.maxParticipantsError')
                        : null;
                  },
                ),
                const SizedBox(height: 10),
                TextFormField(
                  controller: _notesController,
                  maxLines: 4,
                  decoration: InputDecoration(
                    labelText: scope.t('events.notes'),
                    alignLabelWithHint: true,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(scope.t('events.keep')),
        ),
        FilledButton.icon(
          onPressed: _submit,
          icon: Icon(Icons.save_outlined),
          label: Text(scope.t('events.saveChanges')),
        ),
      ],
    );
  }
}

class _AttendanceManager extends StatelessWidget {
  const _AttendanceManager({
    required this.members,
    required this.statuses,
    required this.loading,
    required this.saving,
    required this.onChanged,
    required this.onSave,
  });

  final List<AirmiusEventAttendanceMember> members;
  final Map<int, String?> statuses;
  final bool loading;
  final bool saving;
  final void Function(int userId, String? status) onChanged;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      borderColor: AirmiusColors.green.withValues(alpha: 0.45),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(Icons.fact_check_outlined, color: AirmiusColors.green),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  scope.t('events.manageAttendance'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            scope.t('events.manageAttendanceBody'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
          const SizedBox(height: 14),
          if (loading)
            LinearProgressIndicator(
              color: AirmiusColors.green,
              backgroundColor: airmiusSurfaceSoftColor(context),
            )
          else if (members.isEmpty)
            Text(
              scope.t('events.noParticipants'),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            )
          else ...[
            for (final member in members) ...[
              Row(
                children: [
                  CircleAvatar(
                    radius: 20,
                    backgroundColor: airmiusAccentColor(
                      context,
                    ).withValues(alpha: 0.16),
                    child: Text(
                      member.name.isEmpty
                          ? '?'
                          : member.name.characters.first.toUpperCase(),
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      member.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    flex: 2,
                    child: DropdownButton<String>(
                      isExpanded: true,
                      value: statuses[member.id] ?? '',
                      dropdownColor: airmiusSurfaceColor(context),
                      items: [
                        DropdownMenuItem(
                          value: '',
                          child: Text(
                            scope.t('events.noResponse'),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        for (final status in const [
                          'yes',
                          'late',
                          'maybe',
                          'no',
                        ])
                          DropdownMenuItem(
                            value: status,
                            child: Text(
                              scope.t('events.status.$status'),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                      ],
                      onChanged: saving
                          ? null
                          : (value) {
                              if (value != null) {
                                onChanged(
                                  member.id,
                                  value.isEmpty ? null : value,
                                );
                              }
                            },
                    ),
                  ),
                ],
              ),
              Divider(height: 18, color: airmiusBorderColor(context)),
            ],
            AirmiusButton(
              label: scope.t('events.saveAttendance'),
              icon: Icons.save_outlined,
              onPressed: saving ? null : onSave,
            ),
          ],
        ],
      ),
    );
  }
}

class _EventCommentsPanel extends StatelessWidget {
  const _EventCommentsPanel({
    required this.comments,
    required this.controller,
    required this.loading,
    required this.sending,
    required this.onRefresh,
    required this.onSend,
  });

  final List<AirmiusEventComment> comments;
  final TextEditingController controller;
  final bool loading;
  final bool sending;
  final VoidCallback onRefresh;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(
                Icons.chat_bubble_outline,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  scope.t('events.comments'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              IconButton(
                tooltip: scope.t('events.refreshComments'),
                onPressed: loading ? null : onRefresh,
                icon: Icon(Icons.refresh),
              ),
            ],
          ),
          const SizedBox(height: 10),
          TextField(
            controller: controller,
            minLines: 2,
            maxLines: 5,
            maxLength: 1500,
            textCapitalization: TextCapitalization.sentences,
            decoration: InputDecoration(
              labelText: scope.t('events.commentHint'),
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 10),
          AirmiusButton(
            label: sending
                ? scope.t('events.commentSending')
                : scope.t('events.sendComment'),
            icon: Icons.send_outlined,
            onPressed: sending ? null : onSend,
          ),
          if (loading) ...[
            const SizedBox(height: 12),
            LinearProgressIndicator(
              color: airmiusAccentColor(context),
              backgroundColor: airmiusSurfaceSoftColor(context),
            ),
          ],
          const SizedBox(height: 14),
          if (!loading && comments.isEmpty)
            Text(
              scope.t('events.noComments'),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            )
          else
            for (final comment in comments)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CircleAvatar(
                      radius: 19,
                      backgroundColor: airmiusAccentColor(
                        context,
                      ).withValues(alpha: 0.16),
                      child: Text(
                        comment.userName.isEmpty
                            ? '?'
                            : comment.userName.characters.first.toUpperCase(),
                        style: TextStyle(
                          color: airmiusAccentColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: airmiusSurfaceSoftColor(context),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(
                            color: airmiusBorderColor(context),
                          ),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    comment.userName,
                                    style: TextStyle(
                                      color: airmiusTextColor(context),
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                                Text(
                                  _eventCommentDate(comment.createdAt),
                                  style: TextStyle(
                                    color: airmiusMutedColor(context),
                                    fontSize: 11,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 5),
                            Text(
                              comment.content,
                              style: TextStyle(
                                color: airmiusTextColor(context),
                                height: 1.35,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
        ],
      ),
    );
  }
}

String _eventEditDate(DateTime value) {
  final local = value.toLocal();
  return '${local.day.toString().padLeft(2, '0')}.${local.month.toString().padLeft(2, '0')}.${local.year} '
      '${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
}

String _eventCommentDate(DateTime value) {
  final local = value.toLocal();
  return '${local.day.toString().padLeft(2, '0')}.${local.month.toString().padLeft(2, '0')} '
      '${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
}

class _AttendanceChip extends StatelessWidget {
  const _AttendanceChip({
    required this.width,
    required this.value,
    required this.selected,
    required this.label,
    required this.icon,
    required this.onTap,
  });

  final double width;
  final String value;
  final bool selected;
  final String label;
  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = airmiusParticipationColor(context, value);
    final foreground = selected ? airmiusOnColor(color) : color;

    return SizedBox(
      width: width,
      child: ChoiceChip(
        key: ValueKey('event-rsvp-$value'),
        selected: selected,
        showCheckmark: false,
        label: SizedBox(
          width: double.infinity,
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
          ),
        ),
        onSelected: (_) => onTap(),
        selectedColor: color,
        backgroundColor: color.withValues(alpha: 0.1),
        side: BorderSide(
          color: selected ? color : color.withValues(alpha: 0.72),
          width: selected ? 1.6 : 1,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 11),
        labelStyle: TextStyle(color: foreground, fontWeight: FontWeight.w900),
        avatar: Icon(
          selected ? Icons.check_rounded : icon,
          color: foreground,
          size: 18,
        ),
      ),
    );
  }
}

class _EventRow extends StatelessWidget {
  const _EventRow({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: airmiusAccentColor(context)),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                body,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
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
    final scope = AirmiusScope.of(context);
    final rules = _jsonList(
      data?['rules'],
    ).where((rule) => _jsonBool(rule['is_active'])).toList();
    final fees = _jsonList(data?['fees']);
    final summary = data?['summary'] is JsonMap
        ? data!['summary'] as JsonMap
        : const <String, dynamic>{};
    final canManage = _jsonBool(data?['can_manage']);

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  scope.t('events.penalties.teamFund'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              IconButton(
                onPressed: loading ? null : onRefresh,
                icon: Icon(Icons.refresh, color: airmiusAccentColor(context)),
              ),
            ],
          ),
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusPill(
                '${scope.t('events.penalties.open')} ${_money(context, summary['open_amount'])}',
                color: AirmiusColors.amber,
              ),
              StatusPill(
                '${scope.t('events.penalties.paid')} ${_money(context, summary['paid_amount'])}',
                color: AirmiusColors.green,
              ),
            ],
          ),
          if (loading) ...[
            const SizedBox(height: 12),
            LinearProgressIndicator(
              color: airmiusAccentColor(context),
              backgroundColor: airmiusSurfaceSoftColor(context),
            ),
          ],
          if (error != null) ...[
            const SizedBox(height: 12),
            Text(
              error!,
              style: TextStyle(
                color: AirmiusColors.red,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
          if (canManage) ...[
            const SizedBox(height: 14),
            DropdownButtonFormField<int>(
              initialValue: selectedUserId,
              decoration: InputDecoration(
                labelText: scope.t('events.penalties.player'),
              ),
              dropdownColor: airmiusSurfaceColor(context),
              items: [
                for (final participant in participants)
                  DropdownMenuItem(
                    value: participant.id,
                    child: Text(
                      '${participant.name} (${participant.status == 'late' ? scope.t('events.status.late') : scope.t('events.status.yes')})',
                    ),
                  ),
              ],
              onChanged: loading ? null : onUserChanged,
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<int>(
              initialValue: selectedRuleId,
              decoration: InputDecoration(
                labelText: scope.t('events.penalties.catalogItem'),
              ),
              dropdownColor: airmiusSurfaceColor(context),
              items: [
                for (final rule in rules)
                  DropdownMenuItem(
                    value: _jsonInt(rule['id']),
                    child: Text(
                      '${rule['title'] ?? scope.t('events.penalties.penalty')} - ${_money(context, rule['amount'])}',
                    ),
                  ),
              ],
              onChanged: loading ? null : onRuleChanged,
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: amountController,
                    keyboardType: const TextInputType.numberWithOptions(
                      decimal: true,
                    ),
                    decoration: InputDecoration(
                      labelText: scope.t('events.penalties.amountOptional'),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextField(
                    controller: minutesController,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: scope.t('events.penalties.minutesOptional'),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            TextField(
              controller: noteController,
              maxLines: 2,
              decoration: InputDecoration(
                labelText: scope.t('events.penalties.noteOptional'),
              ),
            ),
            const SizedBox(height: 12),
            AirmiusButton(
              label: scope.t('events.penalties.assign'),
              icon: Icons.add_card_outlined,
              onPressed: selectedUserId == null || loading ? null : onAssign,
            ),
          ],
          const SizedBox(height: 14),
          if (fees.isEmpty)
            Text(
              scope.t('events.penalties.empty'),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            )
          else
            ...fees.map(
              (fee) => _PenaltyFeeTile(
                fee: fee,
                canManage: canManage,
                loading: loading,
                onPaid: onPaid,
                onCancel: onCancel,
              ),
            ),
        ],
      ),
    );
  }
}

class _PenaltyFeeTile extends StatelessWidget {
  const _PenaltyFeeTile({
    required this.fee,
    required this.canManage,
    required this.loading,
    required this.onPaid,
    required this.onCancel,
  });

  final JsonMap fee;
  final bool canManage;
  final bool loading;
  final ValueChanged<int> onPaid;
  final ValueChanged<int> onCancel;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final member = fee['member'] is JsonMap
        ? fee['member'] as JsonMap
        : const <String, dynamic>{};
    final rule = fee['rule'] is JsonMap
        ? fee['rule'] as JsonMap
        : const <String, dynamic>{};
    final status = '${fee['status'] ?? 'open'}';
    final feeId = _jsonInt(fee['id']);
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        border: Border.all(color: airmiusBorderColor(context)),
        borderRadius: BorderRadius.circular(12),
        color: airmiusSurfaceSoftColor(context),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  '${member['name'] ?? scope.t('events.penalties.player')}',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(
                status == 'paid'
                    ? scope.t('events.penalties.paid')
                    : status == 'cancelled'
                    ? scope.t('events.penalties.cancelled')
                    : scope.t('events.penalties.open'),
                color: status == 'paid'
                    ? AirmiusColors.green
                    : status == 'cancelled'
                    ? AirmiusColors.red
                    : AirmiusColors.amber,
              ),
            ],
          ),
          const SizedBox(height: 6),
          Text(
            '${rule['title'] ?? fee['note'] ?? scope.t('events.penalties.penalty')} - ${_money(context, fee['amount'])}',
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w800,
            ),
          ),
          if (canManage && status == 'open') ...[
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: AirmiusButton(
                    label: scope.t('events.penalties.markPaid'),
                    icon: Icons.check_circle_outline,
                    onPressed: loading ? null : () => onPaid(feeId),
                    secondary: true,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: AirmiusButton(
                    label: scope.t('events.penalties.cancel'),
                    icon: Icons.cancel_outlined,
                    onPressed: loading ? null : () => onCancel(feeId),
                    secondary: true,
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

List<JsonMap> _jsonList(Object? value) =>
    value is List ? value.whereType<JsonMap>().toList() : const [];

bool _jsonBool(Object? value) =>
    value == true || value == 1 || value == '1' || value == 'true';

int _jsonInt(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? 0}') ?? 0;
}

String _money(BuildContext context, Object? value) {
  final number = value is num
      ? value.toDouble()
      : double.tryParse('${value ?? 0}') ?? 0;
  return NumberFormat.currency(
    locale: AirmiusScope.of(context).language.locale.toLanguageTag(),
    symbol: '€',
  ).format(number);
}
